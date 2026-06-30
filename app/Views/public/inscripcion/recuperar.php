<?php
/** @var array|null $evento */
/** @var string $old */
/** @var array $flash */
use App\Core\Csrf;
$evento = $evento ?? null;
$old = $old ?? '';
$flash = $flash ?? [];
?>
<section class="container" style="max-width:600px;">
    <div class="panel">
        <h1 class="panel-title" style="font-size:1.5rem;"><?= e(t('recover.title')) ?></h1>
        <?php if ($evento): ?>
            <p class="muted" style="margin:.35rem 0 0;font-weight:600;color:#1f2937;"><?= e($evento['titulo']) ?></p>
        <?php endif; ?>
        <p class="muted" style="margin-top:.5rem;"><?= e(t('recover.desc')) ?></p>

        <?php if (!empty($flash['success'])): ?>
            <div class="alert alert-success"><?= e($flash['success']) ?></div>
        <?php endif; ?>
        <?php if (!empty($flash['error'])): ?>
            <div class="alert alert-error"><?= e($flash['error']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url('/comprovant')) ?>" novalidate>
            <?= Csrf::field() ?>
            <?php if ($evento): ?>
                <input type="hidden" name="evento_slug" value="<?= e($evento['slug']) ?>">
            <?php endif; ?>

            <!-- Anti-bot honeypot: ha de quedar buit -->
            <div aria-hidden="true" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0;">
                <label for="website">No omplir aquest camp</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
            </div>

            <div class="form-row">
                <label for="dni_email"><?= e(t('recover.field')) ?></label>
                <input type="text" id="dni_email" name="dni_email" required autocomplete="off"
                       value="<?= e($old) ?>" placeholder="<?= e(t('recover.placeholder')) ?>">
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-large"><?= e(t('recover.submit')) ?></button>
        </form>
    </div>
</section>
