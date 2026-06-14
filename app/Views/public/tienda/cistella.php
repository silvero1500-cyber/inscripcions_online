<?php
/** @var list<array> $items */
/** @var float $total */
use App\Core\Csrf;
use App\Services\ImageUploader;
?>
<div class="container shop-page">
<section class="shop-hero">
    <h1><?= e(t('shop.cart')) ?></h1>
</section>

<?php if (count($items) === 0): ?>
    <div class="shop-empty">
        <p><?= e(t('shop.cart_empty')) ?></p>
        <a class="btn btn-primary" href="<?= e(base_url('/tienda')) ?>"><?= e(t('shop.continue')) ?></a>
    </div>
<?php else: ?>
    <div class="cart-wrap">
        <table class="cart-table">
            <thead>
                <tr><th colspan="2"><?= e(t('shop.product')) ?></th><th><?= e(t('shop.unit_price')) ?></th><th><?= e(t('shop.qty')) ?></th><th><?= e(t('shop.subtotal')) ?></th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($items as $it): $img = ImageUploader::publicUrl($it['imagen']); ?>
                <tr>
                    <td class="cart-img">
                        <?php if ($img): ?><img src="<?= e($img) ?>" alt=""><?php else: ?><span>🛍️</span><?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= e(base_url('/tienda/' . $it['slug'])) ?>"><strong><?= e($it['nombre']) ?></strong></a>
                        <?php if (!empty($it['variante'])): ?><div class="row-sub"><?= e($it['variante']) ?></div><?php endif; ?>
                        <?php if ($it['stock'] !== null): ?><div class="row-sub muted"><?= e(t('shop.stock_left', ['n' => $it['stock']])) ?></div><?php endif; ?>
                    </td>
                    <td><?= e(format_price((float) $it['precio'])) ?></td>
                    <td>
                        <form method="post" action="<?= e(base_url('/tienda/cistella/actualitzar')) ?>" class="cart-qty-form">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="key" value="<?= e($it['key']) ?>">
                            <input type="number" name="cantidad" value="<?= (int) $it['cantidad'] ?>" min="0" <?= $it['stock'] !== null ? 'max="' . (int) $it['stock'] . '"' : '' ?> style="width:70px;" onchange="this.form.submit()">
                        </form>
                    </td>
                    <td><strong><?= e(format_price((float) $it['subtotal'])) ?></strong></td>
                    <td>
                        <form method="post" action="<?= e(base_url('/tienda/cistella/treure')) ?>" class="inline">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="key" value="<?= e($it['key']) ?>">
                            <button type="submit" class="btn-small btn-danger" title="<?= e(t('shop.remove')) ?>">✕</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="cart-summary">
            <div class="cart-total"><span><?= e(t('shop.total')) ?></span> <strong><?= e(format_price($total)) ?></strong></div>
            <p class="muted small"><?= e(t('shop.pickup_note')) ?></p>
            <div class="cart-actions">
                <a class="btn" href="<?= e(base_url('/tienda')) ?>"><?= e(t('shop.continue')) ?></a>
                <a class="btn btn-primary btn-large" href="<?= e(base_url('/tienda/checkout')) ?>"><?= e(t('shop.checkout')) ?> →</a>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>
