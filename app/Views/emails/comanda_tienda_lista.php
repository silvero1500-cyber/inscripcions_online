<?php
/** @var array $pedido */
/** @var list<array> $lineas */
/** @var string $baseUrl */
?>
<div style="font-family:Arial,Helvetica,sans-serif;color:#1f2937;max-width:560px;margin:0 auto;">
    <div style="text-align:center;font-size:2.4rem;">📦✅</div>
    <h2 style="color:#16a34a;margin:.2rem 0 .4rem;text-align:center;"><?= e(t('shop.email_lista_title')) ?></h2>
    <p style="font-size:15px;line-height:1.5;"><?= e(t('shop.email_lista_intro')) ?></p>

    <p style="font-size:18px;text-align:center;background:#f1f5f9;border-radius:10px;padding:14px;margin:1.2rem 0;">
        <?= e(t('shop.order')) ?>: <strong style="letter-spacing:.04em;"><?= e($pedido['codigo']) ?></strong>
    </p>

    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <?php foreach ($lineas as $l): ?>
            <tr>
                <td style="padding:6px 0;border-bottom:1px solid #eee;"><?= (int) $l['cantidad'] ?>× <?= e($l['nombre']) ?><?= !empty($l['variante']) ? ' (' . e($l['variante']) . ')' : '' ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <p style="font-size:14px;color:#374151;line-height:1.5;margin-top:1rem;">📍 <?= e(t('shop.pickup_info')) ?></p>
</div>
