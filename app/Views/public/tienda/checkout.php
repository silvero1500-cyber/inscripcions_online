<?php
/** @var list<array> $items */
/** @var float $total */
/** @var ?string $flashError */
use App\Core\Csrf;
use App\Services\RedsysService;
?>
<section class="shop-hero"><h1><?= e(t('shop.checkout_title')) ?></h1></section>

<?php if (!empty($flashError)): ?><div class="alert alert-error"><?= e($flashError) ?></div><?php endif; ?>

<div class="checkout-grid">
    <form method="post" action="<?= e(base_url('/tienda/checkout')) ?>" class="form-publico checkout-form">
        <?= Csrf::field() ?>
        <div class="panel">
            <h2 class="panel-title"><?= e(t('shop.contact_title')) ?></h2>
            <div class="form-row">
                <label for="nombre"><?= e(t('shop.name')) ?> <span class="req">*</span></label>
                <input type="text" id="nombre" name="nombre" required maxlength="150">
            </div>
            <div class="form-grid-2">
                <div class="form-row">
                    <label for="email"><?= e(t('shop.email')) ?> <span class="req">*</span></label>
                    <input type="email" id="email" name="email" required maxlength="255" autocomplete="email">
                </div>
                <div class="form-row">
                    <label for="telefono"><?= e(t('shop.phone')) ?></label>
                    <input type="tel" id="telefono" name="telefono" maxlength="20" autocomplete="tel">
                </div>
            </div>
        </div>

        <div class="panel">
            <h2 class="panel-title"><?= e(t('shop.pay')) ?></h2>
            <label class="inline-check"><input type="radio" name="pay_method" value="<?= e(RedsysService::PAY_METHOD_CARD) ?>" checked> 💳 <?= e(t('shop.pay_card')) ?></label>
            <label class="inline-check"><input type="radio" name="pay_method" value="<?= e(RedsysService::PAY_METHOD_BIZUM) ?>"> <?= e(t('shop.pay_bizum')) ?></label>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-large"><?= e(t('shop.pay_now')) ?> · <?= e(format_price($total)) ?></button>
        <p class="form-note"><?= e(t('shop.pickup_note')) ?> · <?= e(t('form.submit.note')) ?></p>
    </form>

    <aside class="panel checkout-summary">
        <h2 class="panel-title"><?= e(t('shop.order_summary')) ?></h2>
        <?php foreach ($items as $it): ?>
            <div class="checkout-line">
                <span><?= (int) $it['cantidad'] ?>× <?= e($it['nombre']) ?><?php if (!empty($it['variante'])): ?> <span class="muted">(<?= e($it['variante']) ?>)</span><?php endif; ?></span>
                <strong><?= e(format_price((float) $it['subtotal'])) ?></strong>
            </div>
        <?php endforeach; ?>
        <div class="checkout-line checkout-total"><span><?= e(t('shop.total')) ?></span> <strong><?= e(format_price($total)) ?></strong></div>
    </aside>
</div>
