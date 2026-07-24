<?php
use App\Core\Auth;
use App\Core\Csrf;
$currentUser = $currentUser ?? Auth::user();

// Inicials per a l'avatar (primeres lletres del nom)
$initials = 'U';
if ($currentUser) {
    $parts = preg_split('/\s+/', trim((string) $currentUser->nombre)) ?: [];
    $ini = '';
    foreach (array_slice(array_filter($parts), 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    if ($ini !== '') $initials = $ini;
}

// Detecció de l'enllaç actiu segons la ruta actual
$prefix  = base_path_prefix();
$curPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($prefix !== '' && str_starts_with($curPath, $prefix)) {
    $curPath = substr($curPath, strlen($prefix));
}
$curPath = '/' . ltrim($curPath, '/');
$navActive = function (string $rel, bool $exact = false) use ($curPath): string {
    $rel = '/' . ltrim($rel, '/');
    $on = $exact ? ($curPath === $rel) : ($curPath === $rel || str_starts_with($curPath, $rel . '/'));
    return $on ? ' active' : '';
};
?><!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Panel · Inscripcions Online</title>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>?v=<?= @filemtime(dirname(__DIR__, 3) . '/public/assets/css/admin.css') ?: time() ?>">
</head>
<?php
// ── Carreres (marques) per a la barra superior ───────────────
// Cada pastilla obre l'edició activa d'aquella carrera. Marquem l'activa si la
// pàgina actual treballa amb una edició d'aquesta carrera (per ?evento_id o ruta).
$isRecollida = $currentUser && (($currentUser->rol ?? '') === 'recollida');
$isExport    = $currentUser && (($currentUser->rol ?? '') === 'export');
$carreres = (!$isRecollida && !$isExport && function_exists('current_carreres')) ? current_carreres() : [];
$activeCarreraId = null;
$ctxCarrera = null;   // carrera on estem treballant (per a la franja de context)
$ctxAny = null;       // any de l'edició, si es coneix
if (count($carreres) > 0) {
    $evId = isset($_GET['evento_id']) ? (int) $_GET['evento_id'] : 0;
    if ($evId === 0 && preg_match('#/admin/eventos/(\d+)(?:/|$)#', $curPath, $mm)) {
        $evId = (int) $mm[1];
    }
    if ($evId > 0) {
        $evRow = \App\Models\Evento::findById($evId);
        $activeCarreraId = $evRow['carrera_id'] ?? null;
        $ctxAny = $evRow['anio_edicion'] ?? null;
    }
    // També quan som a la home d'una carrera: /admin/carrera/{slug}
    if ($activeCarreraId === null && preg_match('#/admin/carrera/([^/?]+)#', $curPath, $sm)) {
        $slug = urldecode($sm[1]);
        foreach ($carreres as $c) {
            if ((string) $c['slug'] === $slug) { $activeCarreraId = (int) $c['id']; break; }
        }
    }
    if ($activeCarreraId !== null) {
        foreach ($carreres as $c) {
            if ((int) $c['id'] === (int) $activeCarreraId) { $ctxCarrera = $c; break; }
        }
    }
}
?>
<body class="layout-admin">
  <div class="topbar-sticky">
    <header class="topbar topbar-light">
        <a class="brand" href="<?= e(base_url('/admin')) ?>">
            <img class="brand-logo" src="<?= e(asset('img/werun-logo.png')) ?>" alt="WeRun">
        </a>
        <button type="button" class="nav-toggle" id="navToggle"
                aria-label="Menú" aria-controls="navMenu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <div class="topbar-collapse" id="navMenu">
            <?php if ($isRecollida): ?>
                <nav class="race-nav" aria-label="Recollida">
                    <a class="race-pill<?= trim($navActive('/admin/recollida')) ? ' active' : '' ?>"
                       href="<?= e(base_url('/admin/recollida')) ?>">Recollida de dorsals</a>
                </nav>
            <?php elseif (count($carreres) > 0): ?>
                <nav class="race-nav" aria-label="Curses">
                    <?php foreach ($carreres as $c): ?>
                        <a class="race-pill<?= ($activeCarreraId !== null && (int) $activeCarreraId === (int) $c['id']) ? ' active' : '' ?>"
                           href="<?= e(base_url('/admin/carrera/' . rawurlencode((string) $c['slug']))) ?>">
                            <?= e($c['nombre']) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <div class="user-menu">
                <?php if ($currentUser): ?>
                    <span class="user-avatar" aria-hidden="true"><?= e($initials) ?></span>
                    <span class="user-name"><?= e($currentUser->nombre) ?></span>
                    <form method="post" action="<?= e(base_url('/admin/logout')) ?>" class="inline">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn-link">Surt</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if ($ctxCarrera !== null):
        $ctxColor = !empty($ctxCarrera['color']) ? $ctxCarrera['color'] : '#1e88c2';
        $hx = ltrim($ctxColor, '#');
        if (strlen($hx) === 3) { $hx = $hx[0].$hx[0].$hx[1].$hx[1].$hx[2].$hx[2]; }
        $cr = strlen($hx) === 6 ? hexdec(substr($hx, 0, 2)) : 30;
        $cg = strlen($hx) === 6 ? hexdec(substr($hx, 2, 2)) : 136;
        $cb = strlen($hx) === 6 ? hexdec(substr($hx, 4, 2)) : 194;
        $ctxSoft = "rgba({$cr},{$cg},{$cb},0.13)";
    ?>
    <div class="carrera-context" style="--ctx:<?= e($ctxColor) ?>;--ctx-soft:<?= e($ctxSoft) ?>;">
        <span class="carrera-context-dot"></span>
        <strong><?= e($ctxCarrera['nombre']) ?></strong>
        <?php if (!empty($ctxAny)): ?><span class="carrera-context-any"><?= (int) $ctxAny ?></span><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

    <main class="content<?= !empty($wide) ? ' content-wide' : '' ?>">
        <?= $content ?? '' ?>
    </main>

    <footer class="footer">
        <small>&copy; <?= date('Y') ?> WeRun · Inscripcions Online</small>
    </footer>

    <script>
        (function () {
            var btn = document.getElementById('navToggle');
            var menu = document.getElementById('navMenu');
            if (!btn || !menu) return;
            btn.addEventListener('click', function () {
                var open = menu.classList.toggle('open');
                btn.classList.toggle('open', open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        })();

        // Clic a qualsevol part d'un camp de data → obre el selector natiu
        (function () {
            document.addEventListener('click', function (e) {
                var el = e.target;
                if (!el || el.tagName !== 'INPUT') return;
                var t = el.type;
                if ((t === 'date' || t === 'datetime-local' || t === 'time' || t === 'month')
                    && typeof el.showPicker === 'function') {
                    try { el.showPicker(); } catch (err) {}
                }
            });
        })();
    </script>
</body>
</html>
