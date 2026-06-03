<?php
/** @var array $pago */
/** @var array $inscrito */
/** @var array $evento */
/** @var array $tarifa */
/** @var bool $esperando */
/** @var string|null $qrToken */
?>
<section class="container exito-box">
    <?php if ($esperando): ?>
        <div class="exito-icon" style="background:#f59e0b">⌛</div>
        <h1><?= e(t('payment.ok.processing.title')) ?></h1>
        <p class="exito-lead"><?= e(t('payment.ok.processing.desc')) ?></p>
        <p class="muted small"><?= e(t('payment.ok.processing.note', ['id' => (int) $pago['id']])) ?></p>
        <script>setTimeout(function(){ location.reload(); }, 5000);</script>
    <?php else: ?>
        <div class="exito-icon">✓</div>
        <h1><?= e(t('payment.ok.title')) ?></h1>
        <p class="exito-lead">
            <?= e(t('payment.ok.lead', ['name' => $inscrito['nombre'], 'event' => $evento['titulo']])) ?>
        </p>

        <div class="exito-resum">
            <dl>
                <dt><?= e(t('payment.ok.summary.event')) ?></dt><dd><?= e($evento['titulo']) ?> · <?= e(format_date_ca((string)$evento['fecha_evento'])) ?></dd>
                <dt><?= e(t('payment.ok.summary.tarifa')) ?></dt><dd><?= e($tarifa['nombre']) ?> · <?= e(format_price((float)$tarifa['precio'])) ?></dd>
                <dt><?= e(t('payment.ok.summary.reference')) ?></dt><dd><?= e($pago['ds_order']) ?></dd>
                <?php if (!empty($pago['ds_auth_code'])): ?>
                    <dt><?= e(t('payment.ok.summary.auth')) ?></dt><dd><?= e($pago['ds_auth_code']) ?></dd>
                <?php endif; ?>
                <dt><?= e(t('payment.ok.summary.status')) ?></dt><dd><span class="badge badge-success"><?= e(t('payment.ok.summary.confirmed')) ?></span></dd>
            </dl>
        </div>

        <div class="alert alert-success">
            <?= e(t('payment.ok.email_notice')) ?>
        </div>

        <?php if (!empty($qrToken)): ?>
            <p style="margin:0 0 1.2rem;">
                <a class="btn btn-primary" href="<?= e(base_url('/comprovant/' . $qrToken)) ?>" target="_blank" rel="noopener"><?= e(t('comprovant.download')) ?></a>
            </p>
        <?php endif; ?>

        <a class="btn btn-secondary" href="<?= e(base_url('/')) ?>"><?= e(t('common.back_to_list')) ?></a>
    <?php endif; ?>
</section>
