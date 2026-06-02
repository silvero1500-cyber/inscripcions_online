<?php
/** @var array $evento */
/** @var array $inscrito */
/** @var array|null $tarifa */
?>
<section class="container exito-box">
    <div class="exito-icon">✓</div>
    <h1><?= e(t('success.received_title')) ?></h1>
    <p class="exito-lead">
        <?= e(t('success.lead', ['name' => $inscrito['nombre'], 'event' => $evento['titulo']])) ?>
    </p>

    <div class="exito-resum">
        <dl>
            <dt><?= e(t('success.summary.dni')) ?></dt><dd><?= e($inscrito['dni']) ?></dd>
            <dt><?= e(t('success.summary.email')) ?></dt><dd><?= e($inscrito['email']) ?></dd>
            <dt><?= e(t('success.summary.event')) ?></dt><dd><?= e($evento['titulo']) ?> · <?= e(format_date_ca((string)$evento['fecha_evento'])) ?></dd>
            <?php if ($tarifa): ?>
                <dt><?= e(t('success.summary.tarifa')) ?></dt><dd><?= e($tarifa['nombre']) ?> · <?= e(format_price((float)$tarifa['precio'])) ?></dd>
            <?php endif; ?>
            <dt><?= e(t('success.summary.status')) ?></dt><dd><span class="badge badge-warning"><?= e(t('success.summary.pending_payment')) ?></span></dd>
        </dl>
    </div>

    <div class="alert alert-info">
        <?= e(t('success.payment_note')) ?>
    </div>

    <a class="btn btn-secondary" href="<?= e(base_url('/')) ?>"><?= e(t('common.back_to_list')) ?></a>
</section>
