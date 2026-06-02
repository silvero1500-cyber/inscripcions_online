<?php
/** @var array $evento */
/** @var array $tarifa */
/** @var array $inscrito */
/** @var float $importe */
/** @var string|null $error */
use App\Core\Csrf;
?>
<section class="container pago-metodo">
    <div class="pago-card">
        <h1><?= e(t('payment.choose_title')) ?></h1>
        <p class="muted pago-resum">
            <strong><?= e($evento['titulo']) ?></strong> · <?= e($tarifa['nombre']) ?>
            · <strong><?= e(format_price($importe)) ?></strong>
        </p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url('/pago/metodo')) ?>" class="pago-metodo-form">
            <?= Csrf::field() ?>

            <div class="metodo-grid">
                <label class="metodo-option">
                    <input type="radio" name="metodo" value="C" required>
                    <span class="metodo-card">
                        <span class="metodo-icon">💳</span>
                        <span class="metodo-name"><?= e(t('payment.method.card')) ?></span>
                        <span class="metodo-desc"><?= e(t('payment.method.card.desc')) ?></span>
                    </span>
                </label>

                <label class="metodo-option">
                    <input type="radio" name="metodo" value="z" required>
                    <span class="metodo-card">
                        <span class="metodo-icon">📱</span>
                        <span class="metodo-name"><?= e(t('payment.method.bizum')) ?></span>
                        <span class="metodo-desc"><?= e(t('payment.method.bizum.desc')) ?></span>
                    </span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-large"><?= e(t('common.continue')) ?></button>
            <p class="form-note muted"><?= e(t('payment.method.note')) ?></p>
        </form>
    </div>
</section>
