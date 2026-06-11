<?php
/** @var array $evento */
/** @var array $flash */
?>
<section class="page-head with-action">
    <div>
        <h1>Importar CSV d'inscrits</h1>
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
        <li>Descarrega el CSV des de <a href="<?= e(base_url('/admin/inscritos/export?evento_id=' . (int)$evento['id'])) ?>">l'exportació</a>.</li>
        <li>Edita'l a Excel/Sheets: emplena el camp <code>Dorsal</code> (o altres editables).</li>
        <li>Torna a pujar-lo aquí.</li>
        <li>Veuràs una previsualització dels canvis abans d'aplicar-los.</li>
    </ol>
    <p style="margin:.6rem 0 0;">
        <strong>Columnes editables:</strong> <code>Dorsal</code>, <code>Talla</code>, <code>Club</code>, <code>Població</code>, <code>Codi postal</code>, <code>Estat</code>,
        i qualsevol <strong>camp personalitzat</strong> de l'esdeveniment (inclosos els <strong>ocults</strong>) — emplena la columna amb el nom del camp.
        La resta s'ignoren. La identificació és per la columna <code>ID</code>.
    </p>
</div>

<form method="post" action="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/inscritos/import/preview')) ?>"
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
