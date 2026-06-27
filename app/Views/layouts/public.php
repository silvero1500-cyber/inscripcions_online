<?php
$currentLocale = current_locale();
$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
// Treure el prefix /inscripcions si hi és per al param ret
$basePrefix = base_path_prefix();
if ($basePrefix !== '' && str_starts_with($currentPath, $basePrefix)) {
    $currentPath = substr($currentPath, strlen($basePrefix));
}
$ret = urlencode($currentPath ?: '/');
$cartCount = \App\Services\Cart::count();
// Mostra la nav de botiga només si està activada I té algun producte actiu
$tiendaActiva = \App\Models\Ajuste::tiendaActiva() && \App\Models\Producto::hayActivos();
?><!DOCTYPE html>
<html lang="<?= e($currentLocale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? t('header.brand_full')) ?></title>
    <?php if (!empty($metaDescription)): ?>
        <meta name="description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>

    <?php
    // ── Open Graph / Twitter Card (preview en compartir) ──
    $ogTitle       = $ogTitle       ?? ($pageTitle ?? t('header.brand_full'));
    $ogDescription = $ogDescription ?? ($metaDescription ?? '');
    $ogType        = $ogType        ?? 'website';
    $ogUrl         = $ogUrl         ?? base_url($currentPath ?: '/');
    $ogImage       = $ogImage       ?? null;
    ?>
    <meta property="og:site_name" content="WeRun">
    <meta property="og:locale" content="<?= e($currentLocale === 'es' ? 'es_ES' : 'ca_ES') ?>">
    <meta property="og:type" content="<?= e($ogType) ?>">
    <meta property="og:title" content="<?= e($ogTitle) ?>">
    <?php if ($ogDescription !== ''): ?><meta property="og:description" content="<?= e($ogDescription) ?>"><?php endif; ?>
    <meta property="og:url" content="<?= e($ogUrl) ?>">
    <?php if (!empty($ogImage)): ?>
        <meta property="og:image" content="<?= e($ogImage) ?>">
        <meta name="twitter:card" content="summary_large_image">
    <?php else: ?>
        <meta name="twitter:card" content="summary">
    <?php endif; ?>
    <meta name="twitter:title" content="<?= e($ogTitle) ?>">
    <?php if ($ogDescription !== ''): ?><meta name="twitter:description" content="<?= e($ogDescription) ?>"><?php endif; ?>
    <?php if (!empty($ogImage)): ?><meta name="twitter:image" content="<?= e($ogImage) ?>"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/public.css')) ?>?v=<?= @filemtime(BASE_PATH . '/public/assets/css/public.css') ?: time() ?>">
</head>
<body class="layout-public">
    <header class="site-header">
        <div class="site-header-inner">
            <a class="site-brand" href="<?= e(base_url('/')) ?>">
                <img class="site-logo" src="<?= e(asset('img/werun-logo.png')) ?>" alt="WeRun">
            </a>
            <div class="site-header-actions">
                <?php if ($tiendaActiva): ?>
                <nav class="site-shopnav" aria-label="Botiga">
                    <a class="shopnav-link" href="<?= e(base_url('/tienda')) ?>"><?= e(t('shop.title')) ?></a>
                    <a class="shopnav-cart" href="<?= e(base_url('/tienda/cistella')) ?>" aria-label="<?= e(t('shop.cart')) ?>">🛒<?php if ($cartCount > 0): ?><span class="cart-badge"><?= $cartCount ?></span><?php endif; ?></a>
                </nav>
                <?php endif; ?>
                <nav class="lang-switcher" aria-label="Idioma">
                    <?php foreach (['ca' => 'CA', 'es' => 'ES'] as $code => $label): ?>
                        <a class="lang-link<?= $currentLocale === $code ? ' active' : '' ?>"
                           href="<?= e(base_url('/lang/' . $code . '?ret=' . $ret)) ?>"
                           <?= $currentLocale === $code ? 'aria-current="true"' : '' ?>>
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    </header>

    <main class="site-main">
        <?= $content ?? '' ?>
    </main>

    <footer class="site-footer">
        <div class="site-footer-inner">
            <small>&copy; <?= date('Y') ?> WeRun · <?= e(t('brand.suffix')) ?></small>
        </div>
    </footer>

    <script>
        // Clic a qualsevol part d'un camp de data → obre el selector natiu
        (function () {
            document.addEventListener('click', function (e) {
                var el = e.target;
                if (!el || el.tagName !== 'INPUT') return;
                var t = el.type;
                if ((t === 'date' || t === 'datetime-local' || t === 'time' || t === 'month')
                    && typeof el.showPicker === 'function') {
                    try { el.showPicker(); } catch (err) {}
                }
            });
        })();
    </script>
</body>
</html>
