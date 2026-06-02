<!DOCTYPE html>
<html lang="ca"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Error</title>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head><body class="layout-error">
<main class="error-box">
    <h1>Error</h1>
    <p>Hi ha hagut un problema processant la teva petició. L'equip ha estat avisat.</p>
    <?php if (!empty($msg) && \App\Core\Env::get('APP_DEBUG') === true): ?>
        <pre style="text-align:left;white-space:pre-wrap;font-size:.85em;background:#1115;padding:1rem;border-radius:6px;"><?= e($msg) ?></pre>
    <?php endif; ?>
    <a href="<?= e(base_url('/')) ?>" class="btn btn-primary">Tornar</a>
</main>
</body></html>
