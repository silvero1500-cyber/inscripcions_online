<?php
/** @var App\Models\Usuario $user */
/** @var array $stats */
/** @var array|null $proxima */
/** @var list<array> $proxims */
/** @var list<array> $chart */
/** @var int $chartMax */
$isSuper = ($user->rol ?? '') === 'superadmin';

$h = (int) date('H');
$salut = $h < 6 ? "Bona nit" : ($h < 14 ? "Bon dia" : ($h < 21 ? "Bona tarda" : "Bona nit"));
$rolLabel = match ($user->rol ?? '') {
    'superadmin' => 'Superadmin',
    'recollida'  => 'Recollida',
    default      => 'Organitzador',
};

$dies = ['diumenge','dilluns','dimarts','dimecres','dijous','divendres','dissabte'];
$mesos = ['','gener','febrer','març','abril','maig','juny','juliol','agost','setembre','octubre','novembre','desembre'];
$avui = $dies[(int) date('w')] . ', ' . date('j') . ' de ' . $mesos[(int) date('n')] . ' de ' . date('Y');

// Dies que falten per a la pròxima carrera
$diesProxima = null; $textProxima = '';
if ($proxima) {
    $hoy = new DateTime('today');
    $fev = new DateTime((string) $proxima['fecha_evento']);
    $diesProxima = $fev < $hoy ? 0 : (int) $hoy->diff($fev)->days;
    $textProxima = $diesProxima === 0 ? 'Avui!' : ($diesProxima === 1 ? 'Demà' : 'Falten ' . $diesProxima . ' dies');
}

$pctAforo = function (array $ev): ?int {
    $aforo = (int) ($ev['aforo_maximo'] ?? 0);
    if ($aforo <= 0) return null;
    return (int) min(100, round((int) $ev['inscritos'] / $aforo * 100));
};
?>
<section class="dash-hero">
    <div>
        <h1><?= e($salut) ?>, <?= e($user->nombre) ?> 👋</h1>
        <p class="dash-hero-sub"><span class="dash-rol"><?= e($rolLabel) ?></span> · <?= e($avui) ?></p>
    </div>
    <?php if ($isSuper): ?>
        <a class="btn btn-primary dash-hero-btn" href="<?= e(base_url('/admin/eventos/nou')) ?>">+ Nou esdeveniment</a>
    <?php endif; ?>
</section>

<?php if ($stats['eventos'] === 0 && !$proxima): ?>
    <section class="empty-state">
        <?php if ($isSuper): ?>
            <p>Encara no hi ha cap esdeveniment actiu. Comença afegint el primer per recollir inscripcions.</p>
            <a class="btn btn-primary" href="<?= e(base_url('/admin/eventos/nou')) ?>">+ Nou esdeveniment</a>
        <?php else: ?>
            <p>Encara no tens cap esdeveniment assignat.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<!-- ── Accessos ràpids ──────────────────────────────── -->
<section class="dash-quick">
    <h3>Accessos ràpids</h3>
    <div class="dash-quick-grid">
        <a class="dash-quick-btn" href="<?= e(base_url('/admin/recollida')) ?>">🎽 <span>Recollida de dorsals</span></a>
        <a class="dash-quick-btn" href="<?= e(base_url('/admin/inscritos')) ?>">📋 <span>Inscrits</span></a>
        <a class="dash-quick-btn" href="<?= e(base_url('/admin/eventos')) ?>">📅 <span>Esdeveniments</span></a>
        <?php if ($isSuper): ?>
            <a class="dash-quick-btn" href="<?= e(base_url('/admin/tienda')) ?>">🛍️ <span>Botiga</span></a>
            <a class="dash-quick-btn" href="<?= e(base_url('/admin/usuarios')) ?>">👥 <span>Usuaris</span></a>
            <a class="dash-quick-btn" href="<?= e(base_url('/admin/auditoria')) ?>">🛡️ <span>Auditoria</span></a>
            <a class="dash-quick-btn" href="<?= e(base_url('/admin/configuracio')) ?>">⚙️ <span>Configuració</span></a>
            <a class="dash-quick-btn" href="<?= e(base_url('/admin/migracions')) ?>">🗄️ <span>Migracions</span></a>
        <?php endif; ?>
    </div>
</section>

<!-- ── KPIs ───────────────────────────────────────── -->
<section class="dash-kpis">
    <a class="dash-kpi kpi-blue" href="<?= e(base_url('/admin/eventos')) ?>">
        <span class="dash-kpi-ico">📅</span>
        <span class="dash-kpi-val"><?= (int) $stats['eventos'] ?></span>
        <span class="dash-kpi-lbl">Esdeveniments actius</span>
    </a>
    <a class="dash-kpi kpi-green" href="<?= e(base_url('/admin/inscritos')) ?>">
        <span class="dash-kpi-ico">✅</span>
        <span class="dash-kpi-val"><?= (int) $stats['inscritos'] ?></span>
        <span class="dash-kpi-lbl">Inscrits confirmats</span>
    </a>
    <a class="dash-kpi kpi-indigo" href="<?= e(base_url('/admin/recollida')) ?>">
        <span class="dash-kpi-ico">🎽</span>
        <span class="dash-kpi-val"><?= (int) $stats['recollits'] ?></span>
        <span class="dash-kpi-lbl">Dorsals recollits</span>
    </a>
    <a class="dash-kpi kpi-amber" href="<?= e(base_url('/admin/inscritos')) ?>">
        <span class="dash-kpi-ico">📈</span>
        <span class="dash-kpi-val"><?= (int) $stats['darrers7'] ?></span>
        <span class="dash-kpi-lbl">Inscripcions (7 dies)</span>
    </a>
</section>

<div class="dash-cols">
    <!-- ── Pròxima carrera ──────────────────────────── -->
    <?php if ($proxima): $pct = $pctAforo($proxima); ?>
        <section class="dash-next">
            <div class="dash-next-head">
                <span class="dash-next-tag">🏁 Pròxima carrera</span>
                <span class="dash-next-count"><?= e($textProxima) ?></span>
            </div>
            <h2><?= e($proxima['titulo']) ?></h2>
            <p class="dash-next-date">📆 <?= e(date('d/m/Y', strtotime((string) $proxima['fecha_evento']))) ?></p>

            <div class="dash-next-stats">
                <div><strong><?= (int) $proxima['inscritos'] ?></strong><span>inscrits</span></div>
                <?php if ($pct !== null): ?>
                    <div><strong><?= $pct ?>%</strong><span>de l'aforament</span></div>
                <?php endif; ?>
            </div>
            <?php if ($pct !== null): ?>
                <div class="dash-aforo-bar"><span style="width:<?= $pct ?>%"></span></div>
            <?php endif; ?>

            <div class="dash-next-links">
                <a class="btn-small" href="<?= e(base_url('/eventos/' . $proxima['slug'])) ?>" target="_blank" rel="noopener">👁️ Veure</a>
                <a class="btn-small btn-kpi" href="<?= e(base_url('/admin/eventos/' . (int) $proxima['id'] . '/kpis')) ?>">📊 KPIs</a>
                <a class="btn-small" href="<?= e(base_url('/admin/recollida?evento_id=' . (int) $proxima['id'])) ?>">🎽 Recollida</a>
                <a class="btn-small" href="<?= e(base_url('/admin/inscritos?evento_id=' . (int) $proxima['id'])) ?>">📋 Inscrits</a>
            </div>
        </section>
    <?php endif; ?>

    <!-- ── Mini-gràfic d'inscripcions ───────────────── -->
    <?php if ($chart): ?>
        <section class="dash-chart-box">
            <h3>Inscripcions · darrers 14 dies</h3>
            <div class="dash-chart">
                <?php foreach ($chart as $c): $n = (int) $c['n']; $bp = $chartMax > 0 ? round($n / $chartMax * 100) : 0; $d = strtotime($c['day']); ?>
                    <div class="dash-col" title="<?= e(date('d/m', $d)) ?>: <?= $n ?> inscripcions">
                        <span class="dash-col-val"><?= $n > 0 ? $n : '' ?></span>
                        <div class="dash-col-track">
                            <span class="dash-col-fill" style="height:<?= $n > 0 ? max($bp, 4) : 0 ?>%"></span>
                        </div>
                        <span class="dash-col-day"><?= e(date('j/n', $d)) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<!-- ── Pròxims esdeveniments ────────────────────────── -->
<?php if ($proxims): ?>
    <section class="dash-list-box">
        <h3>Altres pròxims esdeveniments</h3>
        <div class="dash-list">
            <?php foreach ($proxims as $ev): ?>
                <a class="dash-list-row" href="<?= e(base_url('/admin/inscritos?evento_id=' . (int) $ev['id'])) ?>">
                    <span class="dash-list-date"><?= e(date('d/m/Y', strtotime((string) $ev['fecha_evento']))) ?></span>
                    <span class="dash-list-title"><?= e($ev['titulo']) ?></span>
                    <span class="dash-list-ins"><?= (int) $ev['inscritos'] ?> inscrits</span>
                    <?php if ((int) $ev['activo'] === 1 && (int) $ev['inscripciones_abiertas'] === 1): ?>
                        <span class="badge badge-success">Obert</span>
                    <?php elseif ((int) $ev['activo'] === 1): ?>
                        <span class="badge badge-warning">Tancat</span>
                    <?php else: ?>
                        <span class="badge badge-muted">Inactiu</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
