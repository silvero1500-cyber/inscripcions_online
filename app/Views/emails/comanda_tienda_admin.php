<?php
/** @var array $pedido */
/** @var list<array> $lineas */
/** @var string $baseUrl */
?>
<div style="font-family:Arial,Helvetica,sans-serif;color:#1f2937;max-width:560px;margin:0 auto;">
    <h2 style="color:#1e88c2;margin:0 0 .4rem;">🛍️ Nova comanda a la botiga</h2>
    <p style="font-size:15px;line-height:1.5;">
        Comanda <strong><?= e($pedido['codigo']) ?></strong> · <?= e(format_price((float) $pedido['total'])) ?>
    </p>

    <p style="font-size:14px;line-height:1.5;margin:.6rem 0;">
        <strong>Client:</strong> <?= e($pedido['nombre']) ?><br>
        <strong>Correu:</strong> <?= e($pedido['email']) ?><?php if (!empty($pedido['telefono'])): ?><br>
        <strong>Telèfon:</strong> <?= e($pedido['telefono']) ?><?php endif; ?>
    </p>

    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <?php foreach ($lineas as $l): ?>
            <tr>
                <td style="padding:6px 0;border-bottom:1px solid #eee;"><?= (int) $l['cantidad'] ?>× <?= e($l['nombre']) ?><?= !empty($l['variante']) ? ' (' . e($l['variante']) . ')' : '' ?></td>
                <td style="padding:6px 0;border-bottom:1px solid #eee;text-align:right;"><?= e(format_price((float) $l['precio_unit'] * (int) $l['cantidad'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr><td style="padding:8px 0;font-weight:700;">Total</td><td style="padding:8px 0;text-align:right;font-weight:700;"><?= e(format_price((float) $pedido['total'])) ?></td></tr>
    </table>

    <p style="text-align:center;margin:1.4rem 0;">
        <a href="<?= e(rtrim($baseUrl, '/') . '/admin/tienda/comandes') ?>"
           style="background:#1e88c2;color:#fff;text-decoration:none;padding:11px 22px;border-radius:8px;font-weight:700;display:inline-block;">
            Veure les comandes
        </a>
    </p>
    <p style="font-size:13px;color:#6b7280;">Prepara la comanda i, quan estigui llesta, marca-la com a «Llest per recollir» perquè el client rebi l'avís.</p>
</div>
