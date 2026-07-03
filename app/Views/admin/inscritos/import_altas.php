<?php
/** @var array $evento */
/** @var list<array> $tarifas */
/** @var list<string> $obligatorios */
/** @var array $flash */

$labelOf = fn(string $k): string => match ($k) {
    'nombre' => 'Nom',
    'email'  => 'Email',
    'tarifa' => 'Tarifa',
    default  => \App\Models\CamposFijos::labelOf($k),
};
?>
<section class="page-head with-action">
    <div>
        <h1>Donar d'alta inscrits per importació</h1>
        <p class="muted"><?= e($evento['titulo']) ?></p>
    </div>
    <a class="btn" href="<?= e(base_url('/admin/inscritos?evento_id=' . (int)$evento['id'])) ?>">← Tornar al llistat</a>
</section>

<?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><?= e($flash['success']) ?></div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><?= e($flash['error']) ?></div>
<?php endif; ?>

<div class="alert alert-info">
    <strong>Com funciona:</strong>
    <ol style="margin:.5rem 0 0;padding-left:1.4rem;">
        <li>Prepara un CSV amb una fila per inscrit nou.</li>
        <li>Nomes calen les columnes <strong>obligatòries</strong> per a aquest esdeveniment (llistades sota).</li>
        <li>La columna <code>Tarifa</code> ha de coincidir amb el nom exacte d'una tarifa d'aquest evento.</li>
        <li>Els inscrits es creen com a <strong>confirmats</strong> i marcats amb origen "importació".</li>
    </ol>
    <p style="margin:.6rem 0 0;">
        <strong>Columnes obligatòries per a aquest event:</strong>
        <?php foreach ($obligatorios as $k): ?><code><?= e($labelOf($k)) ?></code> <?php endforeach; ?>
    </p>
    <p style="margin:.4rem 0 0;">
        <strong>Tarifes disponibles:</strong>
        <?php if (empty($tarifas)): ?><em>cap tarifa creada encara</em><?php else: ?>
            <?php foreach ($tarifas as $t): ?><code><?= e($t['nombre']) ?></code> <?php endforeach; ?>
        <?php endif; ?>
    </p>
</div>

<form method="post" action="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/inscritos/importar-altas/preview')) ?>"
      enctype="multipart/form-data" class="form-admin">
    <?= \App\Core\Csrf::field() ?>

    <div class="form-row">
        <label for="csv">Fitxer CSV <span class="req">*</span></label>
        <input type="file" id="csv" name="csv" accept=".csv,text/csv" required>
        <small class="muted">Màx 5 MB. Separador <code>;</code> o <code>,</code>. BOM UTF-8 suportat.</small>
    </div>

    <div class="form-actions" style="display:flex;gap:.6rem;justify-content:flex-end;margin-top:1.4rem;">
        <button type="submit" class="btn btn-primary">Pujar i previsualitzar</button>
    </div>
</form>
