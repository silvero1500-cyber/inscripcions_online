<?php
/** @var array $pedido */
/** @var list<array> $lineas */
/** @var string $baseUrl */
?>
<div style="font-family:Arial,Helvetica,sans-serif;color:#1f2937;max-width:560px;margin:0 auto;">
    <h2 style="color:#1e88c2;margin:0 0 .4rem;"><?= e(t('shop.order_thanks')) ?></h2>
    <p style="font-size:15px;line-height:1.5;"><?= e(t('shop.email_intro')) ?></p>

    <p style="font-size:18px;text-align:center;background:#f1f5f9;border-radius:10px;padding:14px;margin:1.2rem 0;">
        <?= e(t('shop.order')) ?>: <strong style="letter-spacing:.04em;"><?= e($pedido['codigo']) ?></strong>
    </p>

    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <?php foreach ($lineas as $l): ?>
            <tr>
                <td style="padding:6px 0;border-bottom:1px solid #eee;"><?= (int) $l['cantidad'] ?>× <?= e($l['nombre']) ?><?= !empty($l['variante']) ? ' (' . e($l['variante']) . ')' : '' ?></td>
                <td style="padding:6px 0;border-bottom:1px solid #eee;text-align:right;"><?= e(format_price((float) $l['precio_unit'] * (int) $l['cantidad'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr><td style="padding:8px 0;font-weight:700;"><?= e(t('shop.total')) ?></td><td style="padding:8px 0;text-align:right;font-weight:700;"><?= e(format_price((float) $pedido['total'])) ?></td></tr>
    </table>

    <p style="font-size:14px;color:#374151;line-height:1.5;margin-top:1rem;">📍 <?= e(t('shop.pickup_info')) ?></p>
    <?php $lugar = \App\Models\Ajuste::get(\App\Models\Ajuste::TIENDA_LUGAR); $horario = \App\Models\Ajuste::get(\App\Models\Ajuste::TIENDA_HORARIO); ?>
    <?php if ($lugar): ?>
        <p style="font-size:14px;color:#1f2937;line-height:1.5;background:#f1f5f9;border-radius:8px;padding:10px;">
            <strong><?= e(t('shop.pickup_place')) ?>:</strong><br><?= nl2br(e($lugar)) ?><?php if ($horario): ?><br><?= nl2br(e($horario)) ?><?php endif; ?>
        </p>
    <?php endif; ?>
</div>
