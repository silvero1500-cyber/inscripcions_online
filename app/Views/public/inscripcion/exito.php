<?php
/** @var array $evento */
/** @var array $inscrito */
/** @var array|null $tarifa */
/** @var string|null $qrDataUri */
$confirmada = ($inscrito['estado'] ?? '') === 'confirmado';
?>
<section class="container exito-box">
    <div class="exito-icon">✓</div>
    <h1><?= e(t('success.received_title')) ?></h1>
    <p class="exito-lead">
        <?= e(t('success.lead', ['name' => $inscrito['nombre'], 'event' => $evento['titulo']])) ?>
    </p>

    <div class="exito-resum">
        <dl>
            <?php if (!empty($inscrito['dni'])): ?>
                <dt><?= e(t('success.summary.dni')) ?></dt><dd><?= e($inscrito['dni']) ?></dd>
            <?php endif; ?>
            <dt><?= e(t('success.summary.email')) ?></dt><dd><?= e($inscrito['email']) ?></dd>
            <dt><?= e(t('success.summary.event')) ?></dt><dd><?= e($evento['titulo']) ?> · <?= e(format_date_ca((string)$evento['fecha_evento'])) ?></dd>
            <?php if ($tarifa): ?>
                <dt><?= e(t('success.summary.tarifa')) ?></dt><dd><?= e($tarifa['nombre']) ?> · <?= e(format_price((float)$tarifa['precio'])) ?></dd>
            <?php endif; ?>
            <dt><?= e(t('success.summary.status')) ?></dt>
            <dd>
                <?php if ($confirmada): ?>
                    <span class="badge badge-success"><?= e(t('success.summary.confirmed')) ?></span>
                <?php else: ?>
                    <span class="badge badge-warning"><?= e(t('success.summary.pending_payment')) ?></span>
                <?php endif; ?>
            </dd>
        </dl>
    </div>

    <?php if ($confirmada): ?>
        <div class="alert alert-success"><?= e(t('success.confirmed_note')) ?></div>

        <?php if (!empty($qrDataUri)): ?>
            <div class="exito-qr">
                <h2><?= e(t('email.qr.title')) ?></h2>
                <p class="muted"><?= e(t('email.qr.desc')) ?></p>
                <img src="<?= e($qrDataUri) ?>" alt="<?= e(t('email.qr.alt')) ?>" width="280" height="280">
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info"><?= e(t('success.payment_note')) ?></div>
    <?php endif; ?>

    <a class="btn btn-secondary" href="<?= e(base_url('/')) ?>"><?= e(t('common.back_to_list')) ?></a>
</section>
