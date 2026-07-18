<?php
/** @var App\Models\Usuario $user */
/** @var array $carrera */
/** @var array $edicion */
/** @var list<array> $edicions */
/** @var array{inscritos:int,recollits:int,darrers7:int,pendents:int} $stats */
/** @var array $flash */

use App\Core\Auth;

$flash   = $flash ?? [];
$isSuper = ($user->rol ?? '') === 'superadmin';
$evId    = (int) $edicion['id'];
$anio    = !empty($edicion['anio_edicion']) ? (int) $edicion['anio_edicion'] : null;

// Dies que falten per a aquesta edició
$diesText = '';
try {
    $hoy = new DateTime('today');
    $fev = new DateTime((string) $edicion['fecha_evento']);
    $dies = $fev < $hoy ? -1 : (int) $hoy->diff($fev)->days;
    $diesText = $dies < 0 ? 'Ja celebrada' : ($dies === 0 ? 'Avui!' : ($dies === 1 ? 'Demà' : 'Falten ' . $dies . ' dies'));
} catch (\Throwable $e) {}

// % d'aforament
$aforo = (int) ($edicion['aforo_maximo'] ?? 0);
$pct = $aforo > 0 ? (int) min(100, round($stats['inscritos'] / $aforo * 100)) : null;
$pctRec = $stats['inscritos'] > 0 ? (int) round($stats['recollits'] / $stats['inscritos'] * 100) : 0;
?>
<section class="dash-hero">
    <div>
        <h1>🏁 <?= e($carrera['nombre']) ?></h1>
        <p class="dash-hero-sub">
            <span class="dash-rol">Edició <?= $anio !== null ? $anio : '—' ?></span>
            · <?= e($edicion['titulo']) ?>
            <?php if ($diesText !== ''): ?> · <?= e($diesText) ?><?php endif; ?>
        </p>
    </div>
</section>

<?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><?= e($flash['error']) ?></div>
<?php endif; ?>
<?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><?= e($flash['success']) ?></div>
<?php endif; ?>

<!-- ── Accessos ràpids d'aquesta edició ─────────────── -->
<section class="dash-quick">
    <h3>Accessos ràpids · <?= e($carrera['nombre']) ?> <?= $anio !== null ? $anio : '' ?></h3>
    <div class="dash-quick-grid">
        <?php if ($isSuper): ?>
            <a class="dash-quick-btn" href="<?= e(base_url('/admin/eventos/' . $evId . '/editar')) ?>">✏️ <span>Editar edició</span></a>
        <?php endif; ?>
        <a class="dash-quick-btn" href="<?= e(base_url('/admin/inscritos?evento_id=' . $evId)) ?>">📋 <span>Inscrits</span></a>
        <a class="dash-quick-btn" href="<?= e(base_url('/admin/eventos/' . $evId . '/kpis')) ?>">📊 <span>KPIs</span></a>
        <a class="dash-quick-btn" href="<?= e(base_url('/admin/recollida?evento_id=' . $evId)) ?>">🎽 <span>Recollida de dorsals</span></a>
        <a class="dash-quick-btn" href="<?= e(base_url('/admin/inscritos/export?evento_id=' . $evId)) ?>">⬇️ <span>Exportar CSV</span></a>
        <a class="dash-quick-btn" href="<?= e(base_url('/eventos/' . $edicion['slug'])) ?>" target="_blank" rel="noopener">👁️ <span>Veure pública</span></a>
    </div>
</section>

<!-- ── KPIs de l'edició ─────────────────────────────── -->
<section class="dash-kpis">
    <a class="dash-kpi kpi-green" href="<?= e(base_url('/admin/inscritos?evento_id=' . $evId)) ?>">
        <span class="dash-kpi-ico">✅</span>
        <span class="dash-kpi-val"><?= (int) $stats['inscritos'] ?></span>
        <span class="dash-kpi-lbl">Inscrits confirmats</span>
    </a>
    <a class="dash-kpi kpi-indigo" href="<?= e(base_url('/admin/recollida?evento_id=' . $evId)) ?>">
        <span class="dash-kpi-ico">🎽</span>
        <span class="dash-kpi-val"><?= (int) $stats['recollits'] ?></span>
        <span class="dash-kpi-lbl">Dorsals recollits</span>
    </a>
    <a class="dash-kpi kpi-amber" href="<?= e(base_url('/admin/recollida?evento_id=' . $evId . '&recollida=pendent')) ?>">
        <span class="dash-kpi-ico">⏳</span>
        <span class="dash-kpi-val"><?= (int) $stats['pendents'] ?></span>
        <span class="dash-kpi-lbl">Dorsals pendents</span>
    </a>
    <a class="dash-kpi kpi-blue" href="<?= e(base_url('/admin/inscritos?evento_id=' . $evId)) ?>">
        <span class="dash-kpi-ico">📈</span>
        <span class="dash-kpi-val"><?= (int) $stats['darrers7'] ?></span>
        <span class="dash-kpi-lbl">Inscripcions (7 dies)</span>
    </a>
</section>

<div class="dash-cols">
    <!-- ── Targeta operativa de l'edició ────────────── -->
    <section class="dash-next">
        <div class="dash-next-head">
            <span class="dash-next-tag">🏁 Edició <?= $anio !== null ? $anio : '' ?></span>
            <?php if ($diesText !== ''): ?><span class="dash-next-count"><?= e($diesText) ?></span><?php endif; ?>
        </div>
        <h2><?= e($edicion['titulo']) ?></h2>
        <p class="dash-next-date">📆 <?= e(date('d/m/Y', strtotime((string) $edicion['fecha_evento']))) ?></p>

        <div class="dash-next-stats">
            <div><strong><?= (int) $stats['inscritos'] ?></strong><span>inscrits</span></div>
            <div><strong><?= (int) $stats['recollits'] ?></strong><span>recollits (<?= $pctRec ?>%)</span></div>
            <?php if ($pct !== null): ?>
                <div><strong><?= $pct ?>%</strong><span>de l'aforament</span></div>
            <?php endif; ?>
        </div>
        <?php if ($pct !== null): ?>
            <div class="dash-aforo-bar"><span style="width:<?= $pct ?>%"></span></div>
        <?php endif; ?>

        <div class="dash-next-links">
            <a class="btn-small" href="<?= e(base_url('/eventos/' . $edicion['slug'])) ?>" target="_blank" rel="noopener">👁️ Veure</a>
            <a class="btn-small btn-kpi" href="<?= e(base_url('/admin/eventos/' . $evId . '/kpis')) ?>">📊 KPIs</a>
            <a class="btn-small" href="<?= e(base_url('/admin/recollida?evento_id=' . $evId)) ?>">🎽 Recollida</a>
            <a class="btn-small" href="<?= e(base_url('/admin/inscritos?evento_id=' . $evId)) ?>">📋 Inscrits</a>
        </div>
    </section>

    <!-- ── Altres edicions de la carrera ────────────── -->
    <section class="dash-list-box">
        <h3>Edicions de <?= e($carrera['nombre']) ?></h3>
        <div class="dash-list">
            <?php foreach ($edicions as $ed): $isActiva = (int) $ed['id'] === $evId; ?>
                <a class="dash-list-row<?= $isActiva ? ' is-current' : '' ?>"
                   href="<?= e(base_url('/admin/inscritos?evento_id=' . (int) $ed['id'])) ?>">
                    <span class="dash-list-date"><?= !empty($ed['anio_edicion']) ? (int) $ed['anio_edicion'] : e(date('Y', strtotime((string) $ed['fecha_evento']))) ?></span>
                    <span class="dash-list-title"><?= e($ed['titulo']) ?></span>
                    <?php if (!empty($ed['archivado_at'])): ?>
                        <span class="badge badge-muted">Arxivada</span>
                    <?php elseif ($isActiva): ?>
                        <span class="badge badge-success">Activa</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
            <?php if ($isSuper): ?>
                <a class="dash-list-row dash-list-add" href="<?= e(base_url('/admin/eventos/nou')) ?>">
                    <span class="dash-list-title">➕ Nova edició…</span>
                </a>
            <?php endif; ?>
        </div>
    </section>
</div>
