<?php
use App\Core\Auth;
use App\Core\Csrf;
$currentUser = $currentUser ?? Auth::user();
?><!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Panel · Inscripcions Online</title>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>?v=<?= @filemtime(dirname(__DIR__, 3) . '/public/assets/css/admin.css') ?: time() ?>">
</head>
<body class="layout-admin">
    <header class="topbar">
        <a class="brand" href="<?= e(base_url('/admin')) ?>">Inscripcions <strong>Online</strong></a>
        <button type="button" class="nav-toggle" id="navToggle"
                aria-label="Menú" aria-controls="navMenu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <div class="topbar-collapse" id="navMenu">
            <nav class="topnav">
                <a href="<?= e(base_url('/admin')) ?>">Panel</a>
                <a href="<?= e(base_url('/admin/eventos')) ?>">Esdeveniments</a>
                <a href="<?= e(base_url('/admin/checkin')) ?>">Check-in</a>
                <a href="<?= e(base_url('/admin/inscritos')) ?>">Inscrits</a>
                <?php if ($currentUser && $currentUser->rol === 'superadmin'): ?>
                    <a href="<?= e(base_url('/admin/usuarios')) ?>">Usuaris</a>
                <?php endif; ?>
            </nav>
            <div class="user-menu">
                <?php if ($currentUser): ?>
                    <span class="user-name"><?= e($currentUser->nombre) ?></span>
                    <form method="post" action="<?= e(base_url('/admin/logout')) ?>" class="inline">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn-link">Surt</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="content">
        <?= $content ?? '' ?>
    </main>

    <footer class="footer">
        <small>&copy; <?= date('Y') ?> WeRun · Inscripcions Online</small>
    </footer>

    <script>
        (function () {
            var btn = document.getElementById('navToggle');
            var menu = document.getElementById('navMenu');
            if (!btn || !menu) return;
            btn.addEventListener('click', function () {
                var open = menu.classList.toggle('open');
                btn.classList.toggle('open', open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        })();

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
