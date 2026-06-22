<?php
/** @var ?string $lugar */
/** @var ?string $horario */
/** @var array $flash */
use App\Core\Csrf;
?>
<section class="page-head with-action">
    <div>
        <h1>Configuració de la botiga</h1>
        <p class="muted">Dades de recollida que es mostren al client (confirmació + correus).</p>
    </div>
    <a class="btn" href="<?= e(base_url('/admin/tienda')) ?>">← Productes</a>
</section>

<?php if (!empty($flash['success'])): ?><div class="alert alert-success"><?= e($flash['success']) ?></div><?php endif; ?>
<?php if (!empty($flash['error'])): ?><div class="alert alert-error"><?= e($flash['error']) ?></div><?php endif; ?>

<form method="post" action="<?= e(base_url('/admin/tienda/config')) ?>" class="form-admin" style="max-width:640px;">
    <?= Csrf::field() ?>
    <fieldset>
        <legend>Recollida en botiga</legend>
        <div class="form-row">
            <label for="lugar">Lloc de recollida <span class="muted">(adreça)</span></label>
            <textarea id="lugar" name="lugar" rows="2" maxlength="500" placeholder="Ex: Botiga WeRun · C/ Major 12, Sabadell"><?= e((string) $lugar) ?></textarea>
        </div>
        <div class="form-row">
            <label for="horario">Horari / instruccions <span class="muted">(opcional)</span></label>
            <textarea id="horario" name="horario" rows="2" maxlength="500" placeholder="Ex: De dilluns a divendres de 10 a 14 i de 17 a 20h"><?= e((string) $horario) ?></textarea>
        </div>
    </fieldset>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Desa</button>
        <a href="<?= e(base_url('/admin/tienda')) ?>" class="btn">Cancel·la</a>
    </div>
</form>
