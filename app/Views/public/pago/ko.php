<?php
/** @var array|null $pago */
/** @var array|null $inscrito */
/** @var array|null $evento */
/** @var array|null $tarifa */
?>
<section class="container exito-box">
    <div class="exito-icon" style="background:#dc2626">✗</div>
    <h1><?= e(t('payment.ko.title')) ?></h1>
    <p class="exito-lead">
        <?php if ($pago && !empty($pago['ds_response'])): ?>
            <?= e(t('payment.ko.bank_denied', ['code' => $pago['ds_response']])) ?>
        <?php else: ?>
            <?= e(t('payment.ko.generic_error')) ?>
        <?php endif; ?>
    </p>

    <?php if ($evento && $inscrito && $tarifa): ?>
        <div class="exito-resum">
            <dl>
                <dt><?= e(t('payment.ok.summary.event')) ?></dt><dd><?= e($evento['titulo']) ?></dd>
                <dt><?= e(t('payment.ok.summary.tarifa')) ?></dt><dd><?= e($tarifa['nombre']) ?> · <?= e(format_price((float)$tarifa['precio'])) ?></dd>
                <dt><?= e(t('payment.ok.summary.status')) ?></dt><dd><span class="badge badge-warning"><?= e(t('payment.ko.summary.status_pending')) ?></span></dd>
            </dl>
        </div>
    <?php endif; ?>

    <?php if ($evento): ?>
        <p class="muted"><?= e(t('payment.ko.kept')) ?></p>

        <div class="actions" style="display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap;">
            <a class="btn btn-primary" href="<?= e(base_url('/pago/metodo')) ?>"><?= e(t('payment.ko.retry')) ?></a>
            <a class="btn btn-secondary" href="<?= e(base_url('/eventos/' . $evento['slug'])) ?>"><?= e(t('payment.ko.back_event')) ?></a>
        </div>
    <?php else: ?>
        <a class="btn btn-secondary" href="<?= e(base_url('/')) ?>"><?= e(t('common.back_to_list')) ?></a>
    <?php endif; ?>
</section>
