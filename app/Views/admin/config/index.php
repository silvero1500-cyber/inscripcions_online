<?php
/** @var ?string $privacidadUrl */
/** @var array $flash */
use App\Core\Csrf;
?>
<section class="page-head with-action">
    <div>
        <h1>Configuració general</h1>
        <p class="muted">Ajustos de la plataforma.</p>
    </div>
    <a class="btn" href="<?= e(base_url('/admin')) ?>">← Panel</a>
</section>

<?php if (!empty($flash['success'])): ?><div class="alert alert-success"><?= e($flash['success']) ?></div><?php endif; ?>
<?php if (!empty($flash['error'])): ?><div class="alert alert-error"><?= e($flash['error']) ?></div><?php endif; ?>

<form method="post" action="<?= e(base_url('/admin/configuracio')) ?>" class="form-admin" style="max-width:640px;">
    <?= Csrf::field() ?>
    <fieldset>
        <legend>Privacitat (RGPD)</legend>
        <div class="form-row">
            <label for="privacidad_url">Enllaç a la política de privacitat</label>
            <input type="url" id="privacidad_url" name="privacidad_url" maxlength="500"
                   placeholder="https://werun.cat/politica-privacitat" value="<?= e((string) $privacidadUrl) ?>">
            <small class="muted">S'enllaça al checkbox obligatori "Accepto la política de privacitat" del formulari d'inscripció. Si es deixa buit, el text apareix sense enllaç.</small>
        </div>
    </fieldset>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Desa</button>
        <a href="<?= e(base_url('/admin')) ?>" class="btn">Cancel·la</a>
    </div>
</form>
