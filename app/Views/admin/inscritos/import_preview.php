<?php
/** @var array $evento */
/** @var string $token */
/** @var int $totalRows */
/** @var list<array> $changes */
/** @var list<array{row:int,msg:string}> $errors */
/** @var int $skipped */
/** @var array<string,int> $changedCols */
/** @var bool $estadoIgnored */
$estadoIgnored = $estadoIgnored ?? false;

$colsLabels = [
    'numero_dorsal' => 'Dorsal',
    'talla_camiseta'=> 'Talla',
    'club'          => 'Club',
    'poblacion'     => 'Població',
    'codigo_postal' => 'Codi postal',
    'estado'        => 'Estat',
];
?>
<section class="page-head with-action">
    <div>
        <h1>Previsualització importació</h1>
        <p class="muted"><?= e($evento['titulo']) ?></p>
    </div>
    <a class="btn" href="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/inscritos/import')) ?>">← Tornar</a>
</section>

<?php if ($estadoIgnored): ?>
    <div class="alert alert-info">La columna <strong>Estat</strong> del CSV s'ha ignorat: només un superadmin pot canviar l'estat dels inscrits per importació.</div>
<?php endif; ?>

<!-- ── KPIs resum ─────────────────────────────────────────── -->
<div class="kpi-grid kpi-grid-4" style="margin-bottom:1.2rem;">
    <div class="kpi-card">
        <div class="kpi-label">Files al CSV</div>
        <div class="kpi-value"><?= $totalRows ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">A modificar</div>
        <div class="kpi-value" style="color:#1e88c2"><?= count($changes) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Sense canvis</div>
        <div class="kpi-value" style="color:#6b7280"><?= $skipped ?></div>
        <div class="kpi-sub">ja estaven igual</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Errors</div>
        <div class="kpi-value" style="color:#dc2626"><?= count($errors) ?></div>
    </div>
</div>

<?php if (count($changedCols) > 0): ?>
    <div class="alert alert-info">
        <strong>Columnes a actualitzar:</strong>
        <?php foreach ($changedCols as $col => $n): ?>
            <span class="badge" style="background:#e0f2fe;color:#1e88c2;margin-right:.4rem;">
                <?= e($colsLabels[$col] ?? $col) ?>: <?= $n ?>
            </span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (count($errors) > 0): ?>
    <details style="margin-bottom:1rem;" open>
        <summary><strong style="color:#dc2626;">⚠ <?= count($errors) ?> errors</strong> (les files amb error no s'aplicaran)</summary>
        <div class="table-wrap" style="margin-top:.8rem;">
            <table class="data-table">
                <thead><tr><th>Fila</th><th>Error</th></tr></thead>
                <tbody>
                    <?php foreach ($errors as $err): ?>
                        <tr><td><?= (int)$err['row'] ?></td><td><?= e($err['msg']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </details>
<?php endif; ?>

<?php if (count($changes) === 0): ?>
    <div class="empty-state">
        <p>No hi ha cap canvi per aplicar.</p>
    </div>
<?php else: ?>
    <details open>
        <summary><strong>Veure els <?= count($changes) ?> canvis</strong></summary>
        <div class="table-wrap" style="margin-top:.8rem;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Corredor</th>
                        <th>Canvis</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($changes, 0, 200) as $c): ?>
                    <tr>
                        <td><?= (int)$c['id'] ?></td>
                        <td>
                            <strong><?= e($c['nom']) ?></strong>
                            <div class="row-sub"><?= e($c['dni']) ?></div>
                        </td>
                        <td>
                            <?php foreach ($c['diff'] as $col => $d): ?>
                                <div style="margin-bottom:.2rem;">
                                    <strong><?= e($colsLabels[$col] ?? $col) ?>:</strong>
                                    <span style="color:#dc2626;text-decoration:line-through;"><?= e($d['from'] ?? '—') ?></span>
                                    →
                                    <span style="color:#16a34a;font-weight:600;"><?= e($d['to'] ?? '—') ?></span>
                                </div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($changes) > 200): ?>
            <p class="muted small" style="margin-top:.6rem;">Es mostren els primers 200 canvis. S'aplicaran tots <?= count($changes) ?> en confirmar.</p>
        <?php endif; ?>
    </details>

    <form method="post" action="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/inscritos/import/apply')) ?>"
          onsubmit="return confirm('Aplicar <?= count($changes) ?> canvis a la base de dades?');"
          style="margin-top:1.5rem;text-align:right;">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <a class="btn" href="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/inscritos/import')) ?>">Cancel·la</a>
        <button type="submit" class="btn btn-primary btn-large">✓ Aplicar <?= count($changes) ?> canvis</button>
    </form>
<?php endif; ?>
