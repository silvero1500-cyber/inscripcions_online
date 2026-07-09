<?php
/** @var array $evento */
/** @var string|null $accesError */
use App\Core\Csrf;
?>
<section class="container" style="max-width:520px;padding-top:2.5rem;padding-bottom:3rem;">
    <div class="panel" style="text-align:center;">
        <h1 style="font-size:1.4rem;margin:0 0 .4rem;"><?= e($evento['titulo']) ?></h1>
        <p class="muted" style="margin:0 0 1.4rem;"><?= e(t('event.access.intro')) ?></p>

        <?php if (!empty($accesError)): ?>
            <div class="alert alert-error" style="text-align:left;"><?= e($accesError) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url('/eventos/' . $evento['slug'] . '/acces')) ?>">
            <?= Csrf::field() ?>
            <div class="form-row" style="text-align:left;">
                <label for="acces_password"><?= e(t('event.access.label')) ?></label>
                <input type="password" id="acces_password" name="acces_password" maxlength="100"
                       autocomplete="off" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary btn-block" style="margin-top:1rem;"><?= e(t('event.access.submit')) ?></button>
        </form>
    </div>
</section>
