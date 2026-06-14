<?php
/** @var array $pedido */
/** @var list<array> $lineas */
$estado = (string) $pedido['estado'];
$pagado = in_array($estado, ['pagado', 'entregado'], true);
$badge = match ($estado) {
    'pagado', 'entregado' => 'badge-success',
    'pendiente'           => 'badge-warning',
    default               => 'badge-muted',
};
?>
<div class="comanda-box panel" style="max-width:640px;margin:2rem auto;">
    <?php if ($pagado): ?>
        <div style="text-align:center;font-size:2.4rem;">✅</div>
        <h1 style="text-align:center;margin:.3rem 0;"><?= e(t('shop.order_thanks')) ?></h1>
    <?php else: ?>
        <h1 style="text-align:center;"><?= e(t('shop.order')) ?> <?= e($pedido['codigo']) ?></h1>
    <?php endif; ?>

    <p style="text-align:center;font-size:1.1rem;">
        <?= e(t('shop.order')) ?>: <strong><?= e($pedido['codigo']) ?></strong>
        · <span class="badge <?= $badge ?>"><?= e(t('shop.status_' . $estado)) ?></span>
    </p>

    <table class="data-table" style="margin:1rem 0;">
        <tbody>
        <?php foreach ($lineas as $l): ?>
            <tr>
                <td><?= (int) $l['cantidad'] ?>× <?= e($l['nombre']) ?><?php if (!empty($l['variante'])): ?> <span class="muted">(<?= e($l['variante']) ?>)</span><?php endif; ?></td>
                <td style="text-align:right;"><?= e(format_price((float) $l['precio_unit'] * (int) $l['cantidad'])) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr><td><strong><?= e(t('shop.total')) ?></strong></td><td style="text-align:right;"><strong><?= e(format_price((float) $pedido['total'])) ?></strong></td></tr>
        </tbody>
    </table>

    <?php if ($pagado): ?>
        <div class="alert alert-success" style="text-align:center;"><?= e(t('shop.pickup_info')) ?></div>
    <?php elseif ($estado === 'pendiente'): ?>
        <div class="alert alert-info" style="text-align:center;"><?= e(t('shop.order_pending_note')) ?></div>
    <?php endif; ?>

    <p style="text-align:center;margin-top:1rem;"><a class="btn" href="<?= e(base_url('/tienda')) ?>"><?= e(t('shop.continue')) ?></a></p>
</div>
