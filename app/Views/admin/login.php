<?php
use App\Core\Csrf;
?><!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Accés · Inscripcions Online</title>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="layout-login">
    <main class="login-box">
        <h1 class="login-brand">Inscripcions <strong>Online</strong></h1>
        <p class="login-sub">Accés per a organitzadors</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url('/admin/login')) ?>" novalidate>
            <?= Csrf::field() ?>

            <label for="email">Correu electrònic</label>
            <input type="email" id="email" name="email" required autofocus
                   autocomplete="username"
                   value="<?= e($oldEmail ?? '') ?>">

            <label for="password">Contrasenya</label>
            <input type="password" id="password" name="password" required
                   autocomplete="current-password">

            <button type="submit" class="btn btn-primary btn-block">Entra</button>
        </form>
    </main>
</body>
</html>
