<?php
/** @var array $evento */
/** @var string $token */
/** @var int $totalRows */
/** @var list<array{row:int,fields:array,resumen:string}> $altas */
/** @var list<array{row:int,msg:string}> $errors */
?>
<section class="page-head with-action">
    <div>
        <h1>Previsualització d'altes noves</h1>
        <p class="muted"><?= e($evento['titulo']) ?></p>
    </div>
    <a class="btn" href="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/inscritos/importar-altas')) ?>">← Tornar</a>
</section>

<div class="kpi-grid kpi-grid-3" style="margin-bottom:1.2rem;">
    <div class="kpi-card">
        <div class="kpi-label">Files al CSV</div>
        <div class="kpi-value"><?= $totalRows ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Altes a crear</div>
        <div class="kpi-value" style="color:#16a34a"><?= count($altas) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Errors</div>
        <div class="kpi-value" style="color:#dc2626"><?= count($errors) ?></div>
    </div>
</div>

<?php if (count($errors) > 0): ?>
    <details style="margin-bottom:1rem;" open>
        <summary><strong style="color:#dc2626;">⚠ <?= count($errors) ?> errors</strong> (les files amb error no es crearan)</summary>
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

<?php if (count($altas) === 0): ?>
    <div class="empty-state">
        <p>No hi ha cap alta vàlida per crear.</p>
    </div>
<?php else: ?>
    <details open>
        <summary><strong>Veure les <?= count($altas) ?> altes noves</strong></summary>
        <div class="table-wrap" style="margin-top:.8rem;">
            <table class="data-table">
                <thead><tr><th>Fila</th><th>Inscrit</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($altas, 0, 200) as $a): ?>
                    <tr>
                        <td><?= (int)$a['row'] ?></td>
                        <td><?= e($a['resumen']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($altas) > 200): ?>
            <p class="muted small" style="margin-top:.6rem;">Es mostren les primeres 200. Es crearan totes les <?= count($altas) ?> en confirmar.</p>
        <?php endif; ?>
    </details>

    <form method="post" action="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/inscritos/importar-altas/apply')) ?>"
          onsubmit="return confirm('Crear <?= count($altas) ?> inscrits nous com a CONFIRMATS?');"
          style="margin-top:1.5rem;text-align:right;">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <a class="btn" href="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/inscritos/importar-altas')) ?>">Cancel·la</a>
        <button type="submit" class="btn btn-primary btn-large">✓ Crear <?= count($altas) ?> inscrits</button>
    </form>
<?php endif; ?>
