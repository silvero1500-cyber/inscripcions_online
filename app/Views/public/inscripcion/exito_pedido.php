<?php
/** @var array $evento */
/** @var array $pedido */
/** @var list<array{inscrito:array,tarifa:array|null,qrDataUri:?string}> $items */
$confirmado = ($pedido['estado'] ?? '') === 'confirmado';
?>
<section class="container exito-box">
    <div class="exito-icon">✓</div>
    <h1><?= e(t('success.received_title')) ?></h1>
    <p class="exito-lead">
        <?= e(t('group.success_lead', ['n' => count($items), 'event' => $evento['titulo']])) ?>
    </p>

    <div class="exito-resum">
        <dl>
            <dt><?= e(t('success.summary.email')) ?></dt><dd><?= e($pedido['email']) ?></dd>
            <dt><?= e(t('success.summary.event')) ?></dt><dd><?= e($evento['titulo']) ?> · <?= e(format_date_ca((string)$evento['fecha_evento'])) ?></dd>
            <dt><?= e(t('success.summary.status')) ?></dt>
            <dd>
                <?php if ($confirmado): ?>
                    <span class="badge badge-success"><?= e(t('success.summary.confirmed')) ?></span>
                <?php else: ?>
                    <span class="badge badge-warning"><?= e(t('success.summary.pending_payment')) ?></span>
                <?php endif; ?>
            </dd>
        </dl>
    </div>

    <?php if ($confirmado): ?>
        <div class="alert alert-success"><?= e(t('success.confirmed_note')) ?></div>

        <?php foreach ($items as $idx => $it): $ins = $it['inscrito']; ?>
            <div class="participant" style="text-align:center;margin-bottom:1.2rem;">
                <div style="font-weight:700;color:#1f2937;font-size:1.05rem;">
                    <span style="color:var(--color-primary);"><?= e(t('group.participant')) ?> <?= (int)$idx + 1 ?></span>
                    — <?= e(trim($ins['nombre'] . ' ' . (string)($ins['apellido'] ?? ''))) ?>
                </div>
                <div class="muted" style="margin:.2rem 0 .8rem;">
                    <?= e($it['tarifa']['nombre'] ?? '') ?><?php if (!empty($ins['talla_camiseta'])): ?> · <?= e(t('form.label.shirt')) ?>: <strong><?= e($ins['talla_camiseta']) ?></strong><?php endif; ?>
                </div>
                <?php if (!empty($it['qrDataUri'])): ?>
                    <img src="<?= e($it['qrDataUri']) ?>" alt="<?= e(t('email.qr.alt')) ?>" width="220" height="220">
                    <p style="margin:.6rem 0 0;">
                        <a class="btn btn-primary" href="<?= e(base_url('/comprovant/' . $ins['qr_token'])) ?>" target="_blank" rel="noopener"><?= e(t('comprovant.download')) ?></a>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-info"><?= e(t('success.payment_note')) ?></div>
    <?php endif; ?>

    <a class="btn btn-secondary" href="<?= e(base_url('/')) ?>"><?= e(t('common.back_to_list')) ?></a>
</section>
