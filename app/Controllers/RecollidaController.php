<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Evento;
use App\Models\Inscrito;
use App\Models\RecollidaLog;
use App\Models\Tarifa;
use App\Models\Usuario;

/**
 * Recollida de dorsal i material (el dia, o dies abans, de la cursa).
 *
 * Dashboard per esdeveniment: quants han recollit / pendents, talles pendents
 * d'entregar, llistat de confirmats amb cerca i botó de marcar recollida.
 * També permet assignar/editar el dorsal al mostrador i escanejar el QR del
 * comprovant del corredor (mateix token que el check-in) per trobar-lo a l'instant.
 */
final class RecollidaController
{
    private const PER_PAGE = 50;

    public function index(Request $req): void
    {
        $user    = Auth::user();
        $eventos = self::listEventosForUser($user);

        // ── QR escanejat: ?token=... → localitza l'inscrit i fixa el seu event ──
        $scanned    = null;
        $scanError  = null;
        $scanToken  = self::normalizeToken((string) ($req->query('token') ?? ''));
        $eventoId   = $req->query('evento_id') ? (int) $req->query('evento_id') : null;

        if ($scanToken !== '') {
            $ins = Inscrito::findByQrToken($scanToken);
            if ($ins === null) {
                $scanError = 'QR no vàlid o no trobat.';
            } elseif (!Evento::userCanEdit($user->id, $user->rol, (int) $ins['evento_id'])) {
                $scanError = 'No tens permís per gestionar aquest esdeveniment.';
            } elseif ($ins['estado'] !== 'confirmado') {
                $scanError = 'Aquesta inscripció no està confirmada (no pot recollir el dorsal).';
                $eventoId  = (int) $ins['evento_id'];
            } else {
                $eventoId = (int) $ins['evento_id'];
                $scanned  = [
                    'inscrito'   => $ins,
                    'tarifa'     => Tarifa::findById((int) $ins['tarifa_id']),
                    'recollitPor'=> !empty($ins['dorsal_recollit_por'])
                        ? Usuario::findById((int) $ins['dorsal_recollit_por']) : null,
                    'calaix'     => self::calaixPerInscrit($ins),
                ];
            }
        }

        // Organitzador sense event triat → primer dels seus
        if (empty($eventoId) && $user->rol !== 'superadmin' && count($eventos) > 0) {
            $eventoId = (int) $eventos[0]['id'];
        }
        if ($eventoId && !Evento::userCanEdit($user->id, $user->rol, $eventoId)) {
            Response::forbidden();
        }

        $filters = [
            'evento_id' => $eventoId,
            'estado'    => 'confirmado',                 // només confirmats poden recollir
            'recollida' => $req->query('recollida') ?: null, // 'pendent' | 'fet' | null (tots)
            'search'    => $req->query('search') ?: null,
        ];
        $filtersClean = array_filter($filters, fn($v) => $v !== null && $v !== '');

        $page       = max(1, (int) ($req->query('page') ?? 1));
        $stats      = ['total' => 0, 'recollits' => 0, 'pendents' => 0];
        $tallas     = [];
        $inscritos  = [];
        $total      = 0;
        $totalPages = 1;

        if ($eventoId) {
            $stats  = Inscrito::recollidaStats($eventoId);
            $tallas = Inscrito::tallasPendents($eventoId);
            $total  = Inscrito::countForAdmin($filtersClean);
            $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
            if ($page > $totalPages) $page = $totalPages;
            $inscritos = Inscrito::listForAdmin($filtersClean, $page, self::PER_PAGE);
        }

        $from = $total > 0 ? ($page - 1) * self::PER_PAGE + 1 : 0;
        $to   = min($total, $page * self::PER_PAGE);

        View::render('admin/recollida/index', [
            'user'       => $user,
            'eventos'    => $eventos,
            'inscritos'  => $inscritos,
            'filters'    => $filters,
            'stats'      => $stats,
            'tallas'     => $tallas,
            'scanned'    => $scanned,
            'scanError'  => $scanError,
            'pucEditarDorsal' => self::pucEditarDorsal($user),
            'eventoSel'  => $eventoId ? Evento::findById((int) $eventoId) : null,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'from'       => $from,
            'to'         => $to,
            'flash'      => Session::pullAllFlashes(),
            'wide'       => true,
        ], layout: 'admin');
    }

    /**
     * Pantalla amb el lector de QR (càmera) → redirigeix a index amb ?token.
     */
    public function scanner(Request $req): void
    {
        View::render('admin/recollida/scanner', [
            'user' => Auth::user(),
        ], layout: 'admin');
    }

    /**
     * Historial d'accions de recollida (auditoria) — només per a gestors
     * (superadmin/organizador), no per als usuaris de rol 'recollida'.
     */
    public function historial(Request $req): void
    {
        $user = Auth::user();
        if ($user->rol === 'recollida') Response::forbidden();

        $eventos  = self::listEventosForUser($user);
        $eventoId = $req->query('evento_id') ? (int) $req->query('evento_id')
            : (count($eventos) > 0 ? (int) $eventos[0]['id'] : null);
        if ($eventoId && !Evento::userCanEdit($user->id, $user->rol, $eventoId)) {
            Response::forbidden();
        }

        View::render('admin/recollida/historial', [
            'user'     => $user,
            'eventos'  => $eventos,
            'eventoId' => $eventoId,
            'logs'     => $eventoId ? RecollidaLog::listByEvento($eventoId) : [],
            'flash'    => Session::pullAllFlashes(),
            'wide'     => true,
        ], layout: 'admin');
    }

    /**
     * Marca la recollida. Accepta un `dorsal` opcional (assignar + recollir alhora).
     */
    public function marcar(Request $req, array $params): void
    {
        [$user, $inscrito] = $this->guard($req, $params);

        if ($inscrito['estado'] !== 'confirmado') {
            Session::flash('error', 'Només els inscrits confirmats poden recollir el dorsal.');
            $this->back($req, (int) $inscrito['evento_id']);
        }

        // Dorsal opcional (ve del flux d'escaneig QR). Només organitzador/superadmin
        // poden assignar-lo; per al rol 'recollida' s'ignora encara que vingui al POST.
        $dorsalRaw = self::pucEditarDorsal($user) ? trim((string) $req->post('dorsal')) : '';
        if ($dorsalRaw !== '') {
            if (!$this->assignDorsal($inscrito, $dorsalRaw)) {
                $this->back($req, (int) $inscrito['evento_id']); // assignDorsal ja ha posat el flash d'error
            }
        }

        $ok = Inscrito::marcarRecollida((int) $inscrito['id'], $user->id);
        if ($ok) {
            $dorsalLog = $dorsalRaw !== '' && ctype_digit($dorsalRaw) ? (int) $dorsalRaw : (!empty($inscrito['numero_dorsal']) ? (int) $inscrito['numero_dorsal'] : null);
            RecollidaLog::registrar((int) $inscrito['evento_id'], $inscrito, $user, 'recollit', $dorsalLog);
        }
        Session::flash($ok ? 'success' : 'error', $ok
            ? 'Dorsal recollit · ' . $inscrito['nombre'] . ' ' . ($inscrito['apellido'] ?? '')
            : 'Aquest dorsal ja constava com a recollit.');

        $this->back($req, (int) $inscrito['evento_id']);
    }

    /**
     * Desfà la recollida.
     */
    public function desfer(Request $req, array $params): void
    {
        [$user, $inscrito] = $this->guard($req, $params);
        Inscrito::desferRecollida((int) $inscrito['id']);
        RecollidaLog::registrar((int) $inscrito['evento_id'], $inscrito, $user, 'desfet', !empty($inscrito['numero_dorsal']) ? (int) $inscrito['numero_dorsal'] : null);
        Session::flash('success', 'Recollida desfeta · ' . $inscrito['nombre']);
        $this->back($req, (int) $inscrito['evento_id']);
    }

    /**
     * Assigna / edita / esborra el número de dorsal.
     */
    public function dorsal(Request $req, array $params): void
    {
        [$user, $inscrito] = $this->guard($req, $params);

        // Assignar/editar dorsal: només organitzador/superadmin
        if (!self::pucEditarDorsal($user)) {
            Response::forbidden();
        }

        $dorsalRaw = trim((string) $req->post('dorsal'));
        if ($dorsalRaw === '') {
            Inscrito::setDorsal((int) $inscrito['id'], null);
            RecollidaLog::registrar((int) $inscrito['evento_id'], $inscrito, $user, 'dorsal_esborrat', !empty($inscrito['numero_dorsal']) ? (int) $inscrito['numero_dorsal'] : null);
            Session::flash('success', 'Dorsal esborrat · ' . $inscrito['nombre']);
        } elseif ($this->assignDorsal($inscrito, $dorsalRaw)) {
            RecollidaLog::registrar((int) $inscrito['evento_id'], $inscrito, $user, 'dorsal', (int) $dorsalRaw);
            Session::flash('success', 'Dorsal ' . (int) $dorsalRaw . ' assignat · ' . $inscrito['nombre']);
        }

        $this->back($req, (int) $inscrito['evento_id']);
    }

    /**
     * Normalitza el token escanejat. La càmera ja envia el token net; la pistola
     * QR pot disparar la URL sencera del comprovant (…/admin/checkin/TOKEN) →
     * n'extraiem l'últim segment del path.
     */
    private static function normalizeToken(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';
        if (str_contains($raw, '/')) {
            $path = parse_url($raw, PHP_URL_PATH);
            $path = rtrim((string) ($path !== false && $path !== null ? $path : $raw), '/');
            $pos = strrpos($path, '/');
            $raw = $pos !== false ? substr($path, $pos + 1) : $path;
        }
        // Per seguretat, només caràcters de token vàlids
        return preg_replace('/[^A-Za-z0-9_\-]/', '', $raw) ?? '';
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Verifica CSRF + permisos i retorna [user, inscrito]. Talla l'execució si no.
     * @return array{0:object, 1:array<string,mixed>}
     */
    private function guard(Request $req, array $params): array
    {
        $user = Auth::user();
        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();

        $id = (int) ($params['id'] ?? 0);
        $inscrito = Inscrito::findById($id);
        if ($inscrito === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, (int) $inscrito['evento_id'])) Response::forbidden();

        return [$user, $inscrito];
    }

    /**
     * Valida i assigna el dorsal. Si hi ha conflicte (ja l'usa un altre) o és
     * invàlid, posa un flash d'error i retorna false.
     */
    private function assignDorsal(array $inscrito, string $dorsalRaw): bool
    {
        if (!ctype_digit($dorsalRaw) || (int) $dorsalRaw <= 0) {
            Session::flash('error', 'El dorsal ha de ser un número positiu.');
            return false;
        }
        $dorsal = (int) $dorsalRaw;
        if (Inscrito::dorsalEnUs((int) $inscrito['evento_id'], $dorsal, (int) $inscrito['id'])) {
            Session::flash('error', 'El dorsal ' . $dorsal . ' ja està assignat a un altre inscrit.');
            return false;
        }
        try {
            Inscrito::setDorsal((int) $inscrito['id'], $dorsal);
        } catch (\PDOException $e) {
            // Xarxa de seguretat per la UNIQUE(evento_id, numero_dorsal)
            Session::flash('error', 'No s\'ha pogut assignar el dorsal ' . $dorsal . ' (potser ja existeix).');
            return false;
        }
        return true;
    }

    /**
     * Redirigeix de tornada al llistat conservant event + filtres.
     */
    private function back(Request $req, int $eventoId): void
    {
        $params = ['evento_id' => $eventoId];
        foreach (['recollida', 'search', 'page'] as $k) {
            $v = $req->post($k);
            if ($v !== null && $v !== '') $params[$k] = $v;
        }
        Response::redirect(base_url('/admin/recollida?' . http_build_query($params)));
    }

    /**
     * Esdeveniments que el user pot gestionar (per al select del filtre).
     * @return list<array<string,mixed>>
     */
    private static function listEventosForUser($user): array
    {
        if ($user->rol === 'superadmin') {
            return Database::getInstance()->query(
                "SELECT id, titulo, slug, fecha_evento
                 FROM eventos ORDER BY fecha_evento DESC, id DESC"
            )->fetchAll();
        }
        return Database::getInstance()->query(
            "SELECT e.id, e.titulo, e.slug, e.fecha_evento
             FROM eventos e
             WHERE e.propietario_id = ?
                OR EXISTS (SELECT 1 FROM organizador_evento oe WHERE oe.evento_id = e.id AND oe.usuario_id = ?)
             ORDER BY e.fecha_evento DESC, e.id DESC",
            [$user->id, $user->id]
        )->fetchAll();
    }

    /**
     * Només superadmin/organitzador poden assignar o editar el dorsal. El rol
     * 'recollida' (voluntaris) pot marcar recollit però no tocar el dorsal.
     */
    private static function pucEditarDorsal($user): bool
    {
        return in_array($user->rol ?? '', ['superadmin', 'organizador'], true);
    }

    /**
     * Calcula el calaix de sortida d'un inscrit a partir del camp 'franja_temps'
     * de l'esdeveniment i del valor que va triar. Retorna ['n'=>int, 'nom','color','text']
     * o null si no hi ha franja/mapeig.
     * @return array{n:int, nom:string, color:string, text:string}|null
     */
    private static function calaixPerInscrit(array $ins): ?array
    {
        $evento = Evento::findById((int) $ins['evento_id']);
        if ($evento === null) return null;

        $n = Evento::calaixDeFranja($evento, $ins['franja_temps'] ?? null, !empty($ins['tarifa_id']) ? (int) $ins['tarifa_id'] : null);
        if ($n === null) return null;

        $meta = \App\Models\CampoPersonalizado::CALAIX_COLORS[$n] ?? null;
        if ($meta === null) return null;
        return ['n' => $n, 'nom' => $meta['nom'], 'color' => $meta['color'], 'text' => $meta['text']];
    }
}
