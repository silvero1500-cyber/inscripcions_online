<?php
/** @var object $user */
/** @var list<array> $eventos */
/** @var list<array> $inscritos */
/** @var array $filters */
/** @var array{total:int,recollits:int,pendents:int} $stats */
/** @var array<string,int> $tallas */
/** @var array|null $scanned */
/** @var string|null $scanError */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $from */
/** @var int $to */
/** @var array $flash */

use App\Core\Csrf;

$flash     = $flash ?? [];
$selEvento = (int) ($filters['evento_id'] ?? 0);
$recFilter = (string) ($filters['recollida'] ?? '');
$search    = (string) ($filters['search'] ?? '');
$pct = $stats['total'] > 0 ? (int) round($stats['recollits'] / $stats['total'] * 100) : 0;

// Hidden inputs comuns als formularis d'acció (per conservar filtres al tornar)
$actionHidden = function () use ($recFilter, $search, $page): string {
    $h  = Csrf::field();
    $h .= '<input type="hidden" name="recollida" value="' . e($recFilter) . '">';
    $h .= '<input type="hidden" name="search" value="' . e($search) . '">';
    $h .= '<input type="hidden" name="page" value="' . (int) $page . '">';
    return $h;
};

$pageUrl = function (int $p) use ($selEvento, $recFilter, $search): string {
    return '?' . http_build_query(array_filter([
        'evento_id' => $selEvento, 'recollida' => $recFilter, 'search' => $search, 'page' => $p,
    ], fn($v) => $v !== '' && $v !== null));
};
?>
<section class="page-head with-action">
    <div>
        <h1>Recollida dorsal</h1>
        <p class="muted">Entrega de dorsals i material. Cerca el corredor o escaneja el seu QR.</p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a class="btn btn-primary" href="<?= e(base_url('/admin/recollida/escanejar')) ?>">📷 Escanejar QR</a>
        <?php if (($user->rol ?? '') !== 'recollida'): ?>
            <a class="btn btn-secondary" href="<?= e(base_url('/admin/recollida/historial' . ($selEvento ? '?evento_id=' . $selEvento : ''))) ?>">📋 Historial</a>
            <a class="btn btn-secondary" href="<?= e(base_url('/admin/checkin')) ?>">✓ Check-in (dia de cursa)</a>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><?= e($flash['success']) ?></div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><?= e($flash['error']) ?></div>
<?php endif; ?>
<?php if ($scanError): ?>
    <div class="alert alert-error"><?= e($scanError) ?></div>
<?php endif; ?>

<?php /* ── Escaneig amb PISTOLA QR (lector HID = teclat: dispara i fa Enter) ── */ ?>
<form method="get" action="<?= e(base_url('/admin/recollida')) ?>" class="recollida-gun" id="gun-form" autocomplete="off">
    <?php if ($selEvento): ?><input type="hidden" name="evento_id" value="<?= $selEvento ?>"><?php endif; ?>
    <?php if ($recFilter !== ''): ?><input type="hidden" name="recollida" value="<?= e($recFilter) ?>"><?php endif; ?>
    <label for="gun-input">🔫 Escaneja amb pistola</label>
    <input type="text" name="token" id="gun-input" autocomplete="off" autofocus
           placeholder="Dispara el QR aquí… (o fes servir el botó 📷 de la càmera)">
</form>
<script>
(function () {
    var form = document.getElementById('gun-form');
    var input = document.getElementById('gun-input');
    if (!form || !input) return;
    // En enviar (la pistola fa Enter), extreu el token net si ha disparat una URL sencera
    form.addEventListener('submit', function () {
        var v = (input.value || '').trim();
        if (v.indexOf('/') !== -1) {
            v = v.split('?')[0].replace(/\/+$/, '');
            v = v.substring(v.lastIndexOf('/') + 1);
        }
        input.value = v;
    });
    // Mantén el focus al camp de la pistola (si no s'està escrivint en un altre camp)
    setTimeout(function () { input.focus(); }, 200);
})();
</script>

<?php /* ── Targeta del corredor escanejat (QR) ───────────────── */ ?>
<?php if ($scanned): $ins = $scanned['inscrito']; $jaRecollit = !empty($ins['dorsal_recollit_at']); ?>
    <div class="recollida-scan-card <?= $jaRecollit ? 'is-done' : '' ?>">
        <div class="rsc-head">
            <span class="rsc-tag">QR escanejat</span>
            <h2><?= e($ins['nombre'] . ' ' . ($ins['apellido'] ?? '')) ?></h2>
        </div>
        <div class="rsc-facts">
            <div><span>DNI</span><strong><?= e($ins['dni'] ?? '—') ?></strong></div>
            <div><span>Talla</span><strong class="talla-big"><?= e($ins['talla_camiseta'] ?? '—') ?></strong></div>
            <div><span>Tarifa</span><strong><?= e($scanned['tarifa']['nombre'] ?? '—') ?></strong></div>
            <div><span>Club</span><strong><?= e($ins['club'] ?? '—') ?></strong></div>
        </div>

        <?php if ($jaRecollit): ?>
            <div class="rsc-done">
                ✓ Ja recollit el <?= e(format_datetime_local((string) $ins['dorsal_recollit_at'], 'd/m/Y H:i')) ?>
                <?php if (!empty($scanned['recollitPor'])): ?>
                    per <?= e($scanned['recollitPor']['nombre']) ?>
                <?php endif; ?>
                · Dorsal <strong><?= !empty($ins['numero_dorsal']) ? (int) $ins['numero_dorsal'] : '—' ?></strong>
            </div>
            <form method="post" action="<?= e(base_url('/admin/recollida/' . (int) $ins['id'] . '/desfer')) ?>" class="inline">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-secondary">↩ Desfer recollida</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(base_url('/admin/recollida/' . (int) $ins['id'] . '/marcar')) ?>" class="rsc-action" onsubmit="return confirmRecollit(this)">
                <?= Csrf::field() ?>
                <label>Dorsal
                    <input type="number" name="dorsal" min="1" inputmode="numeric"
                           value="<?= !empty($ins['numero_dorsal']) ? (int) $ins['numero_dorsal'] : '' ?>"
                           placeholder="—" style="width:7rem;">
                </label>
                <button type="submit" class="btn btn-primary btn-lg">✓ Marcar recollit</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php /* ── Filtres ────────────────────────────────────────────── */ ?>
<form method="get" action="" class="filtres-inscrits">
    <div class="filtres-grid">
        <div class="filtre">
            <label for="f-evento">Esdeveniment</label>
            <select id="f-evento" name="evento_id" onchange="this.form.submit()" required>
                <?php if (count($eventos) === 0): ?>
                    <option value="">No hi ha esdeveniments</option>
                <?php else: ?>
                    <option value="">— Tria —</option>
                    <?php foreach ($eventos as $ev): ?>
                        <option value="<?= (int) $ev['id'] ?>" <?= $selEvento === (int) $ev['id'] ? 'selected' : '' ?>>
                            <?= e($ev['titulo']) ?> · <?= e(date('d/m/Y', strtotime((string) $ev['fecha_evento']))) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <div class="filtre">
            <label for="f-rec">Estat recollida</label>
            <select id="f-rec" name="recollida" onchange="this.form.submit()">
                <option value="">Tots</option>
                <option value="pendent" <?= $recFilter === 'pendent' ? 'selected' : '' ?>>Pendents</option>
                <option value="fet"     <?= $recFilter === 'fet' ? 'selected' : '' ?>>Recollits</option>
            </select>
        </div>
        <div class="filtre">
            <label for="f-search">Cerca (nom / DNI / dorsal)</label>
            <input type="text" id="f-search" name="search" value="<?= e($search) ?>" placeholder="Marta, 12345678A, 102…">
        </div>
        <div class="filtre filtre-actions">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <?php if ($recFilter || $search): ?>
                <a class="btn" href="?evento_id=<?= $selEvento ?>">Netejar</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if (!$selEvento): ?>
    <div class="empty-state">
        <p>Tria un esdeveniment per gestionar la recollida de dorsals.</p>
    </div>
<?php else: ?>

    <?php /* ── KPIs + progrés ──────────────────────────────── */ ?>
    <div class="kpi-grid kpi-grid-3" style="margin-bottom:.8rem;">
        <div class="kpi-card"><div class="kpi-label">Confirmats</div><div class="kpi-value"><?= $stats['total'] ?></div></div>
        <div class="kpi-card"><div class="kpi-label">Recollits</div><div class="kpi-value" style="color:#16a34a"><?= $stats['recollits'] ?> <small style="font-size:1rem;color:#6b7280">· <?= $pct ?>%</small></div></div>
        <div class="kpi-card"><div class="kpi-label">Pendents</div><div class="kpi-value" style="color:#f59e0b"><?= $stats['pendents'] ?></div></div>
    </div>
    <div class="kpi-progress" title="<?= $pct ?>% recollit" style="margin-bottom:1rem;">
        <div class="kpi-progress-bar" style="width:<?= $pct ?>%;background:#16a34a;"></div>
    </div>

    <?php if (!empty($tallas)): ?>
        <div class="talla-chips" aria-label="Talles pendents d'entregar">
            <span class="talla-chips-label">Talles pendents:</span>
            <?php foreach ($tallas as $talla => $n): ?>
                <span class="talla-chip"><strong><?= e($talla) ?></strong> <?= (int) $n ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php
    $renderPagination = function () use ($page, $totalPages, $from, $to, $total, $pageUrl) {
        if ($totalPages <= 1) return;
        ?>
        <nav class="pager" aria-label="Paginació">
            <div class="pager-info">Mostrant <strong><?= $from ?>–<?= $to ?></strong> de <strong><?= $total ?></strong></div>
            <div class="pager-controls">
                <a class="pager-btn<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= $page > 1 ? e($pageUrl($page - 1)) : '#' ?>">‹</a>
                <?php
                $start = max(1, $page - 2); $end = min($totalPages, $start + 4); $start = max(1, $end - 4);
                for ($p = $start; $p <= $end; $p++): ?>
                    <a class="pager-btn<?= $p === $page ? ' active' : '' ?>" href="<?= e($pageUrl($p)) ?>"><?= $p ?></a>
                <?php endfor; ?>
                <a class="pager-btn<?= $page >= $totalPages ? ' disabled' : '' ?>" href="<?= $page < $totalPages ? e($pageUrl($page + 1)) : '#' ?>">›</a>
            </div>
        </nav>
        <?php
    };
    ?>

    <?php if ($total === 0): ?>
        <div class="empty-state"><p>Cap confirmat amb aquests filtres.</p></div>
    <?php else: ?>
        <?php $renderPagination(); ?>
        <div class="grid-wrap">
            <table class="data-grid recollida-grid">
                <thead>
                    <tr>
                        <th>Dorsal</th>
                        <th>Nom</th>
                        <th>DNI</th>
                        <th>Talla</th>
                        <th>Tarifa</th>
                        <th>Club</th>
                        <th>Recollida</th>
                        <th>Acció</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($inscritos as $i): $rec = !empty($i['dorsal_recollit_at']); ?>
                    <tr class="<?= $rec ? 'row-done' : '' ?>">
                        <td>
                            <form method="post" action="<?= e(base_url('/admin/recollida/' . (int) $i['id'] . '/dorsal')) ?>" class="dorsal-edit">
                                <?= $actionHidden() ?>
                                <input type="number" name="dorsal" min="1" inputmode="numeric"
                                       value="<?= !empty($i['numero_dorsal']) ? (int) $i['numero_dorsal'] : '' ?>" placeholder="—">
                                <button type="submit" class="btn-tiny" title="Desar dorsal">💾</button>
                            </form>
                        </td>
                        <td><strong><?= e($i['nombre']) ?></strong> <?= e($i['apellido'] ?? '') ?></td>
                        <td><?= e($i['dni'] ?? '—') ?></td>
                        <td><span class="talla-big"><?= e($i['talla_camiseta'] ?? '—') ?></span></td>
                        <td><?= e($i['tarifa_nombre']) ?></td>
                        <td><?= e($i['club'] ?? '—') ?></td>
                        <td>
                            <?php if ($rec): ?>
                                <span class="badge badge-success">✓ <?= e(format_datetime_local((string) $i['dorsal_recollit_at'], 'd/m H:i')) ?></span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="cell-actions">
                            <?php if ($rec): ?>
                                <form method="post" action="<?= e(base_url('/admin/recollida/' . (int) $i['id'] . '/desfer')) ?>" class="inline">
                                    <?= $actionHidden() ?>
                                    <button type="submit" class="btn-tiny" title="Desfer recollida">↩ Desfer</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= e(base_url('/admin/recollida/' . (int) $i['id'] . '/marcar')) ?>" class="inline" onsubmit="return confirmRecollit(this)">
                                    <?= $actionHidden() ?>
                                    <button type="submit" class="btn btn-primary btn-tiny">✓ Recollit</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php $renderPagination(); ?>
    <?php endif; ?>
<?php endif; ?>

<script>
// Avisa si es marca "recollit" sense cap dorsal assignat.
function confirmRecollit(form) {
    var own = form.querySelector('input[name="dorsal"]');          // targeta QR
    var row = form.closest('tr');                                   // fila del llistat
    var input = own || (row ? row.querySelector('input[name="dorsal"]') : null);
    var val = input ? input.value.trim() : '';
    if (val === '') {
        return confirm('Aquest corredor no té cap dorsal assignat.\n\nVols marcar-lo com a recollit igualment?');
    }
    return true;
}
</script>
