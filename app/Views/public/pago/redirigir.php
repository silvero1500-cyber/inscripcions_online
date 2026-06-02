<?php
/** @var string $url */
/** @var array<string,string> $fields */
/** @var array $evento */
/** @var array $tarifa */
/** @var array $inscrito */
/** @var float $importe */
/** @var string $pay_method */
$metodoNom = $pay_method === 'z' ? t('payment.method_name.bizum') : t('payment.method_name.card');
?>
<section class="container pago-redirigir">
    <div class="pago-card">
        <div class="pago-spinner"></div>
        <h1><?= e(t('payment.redirecting.title')) ?></h1>
        <p class="muted">
            <?= e(t('payment.redirecting.desc', ['price' => format_price($importe), 'method' => $metodoNom, 'event' => $evento['titulo']])) ?>
        </p>
        <p class="muted small"><?= e(t('payment.redirecting.fallback')) ?></p>

        <form id="redsysForm" method="post" action="<?= e($url) ?>" accept-charset="UTF-8">
            <?php foreach ($fields as $k => $v): ?>
                <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
            <?php endforeach; ?>
            <noscript>
                <button type="submit" class="btn btn-primary btn-large"><?= e(t('payment.redirecting.noscript')) ?></button>
            </noscript>
        </form>

        <button type="button" id="manualSubmit" class="btn btn-secondary"
                onclick="document.getElementById('redsysForm').submit()">
            <?= e(t('payment.redirecting.manual')) ?>
        </button>
    </div>
</section>

<script>
(function () {
    setTimeout(function () {
        document.getElementById('redsysForm').submit();
    }, 800);
})();
</script>
