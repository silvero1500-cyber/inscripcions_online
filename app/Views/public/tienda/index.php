<?php
/** @var list<array> $productos */
use App\Services\ImageUploader;
?>
<section class="shop-hero">
    <h1><?= e(t('shop.title')) ?></h1>
    <p class="muted"><?= e(t('shop.subtitle')) ?></p>
</section>

<?php if (count($productos) === 0): ?>
    <div class="shop-empty"><p><?= e(t('shop.empty')) ?></p></div>
<?php else: ?>
    <div class="shop-grid">
        <?php foreach ($productos as $p): $img = ImageUploader::publicUrl($p['imagen'] ?? null); ?>
            <a class="shop-card" href="<?= e(base_url('/tienda/' . $p['slug'])) ?>">
                <div class="shop-card-img">
                    <?php if ($img): ?>
                        <img src="<?= e($img) ?>" alt="<?= e($p['nombre']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="shop-card-noimg">🛍️</span>
                    <?php endif; ?>
                </div>
                <div class="shop-card-body">
                    <h3><?= e($p['nombre']) ?></h3>
                    <p class="shop-card-price">
                        <?php if ($p['tipo'] === 'variable'): ?><span class="muted"><?= e(t('shop.from')) ?> </span><?php endif; ?>
                        <strong><?= e(format_price((float) $p['precio_desde'])) ?></strong>
                    </p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
