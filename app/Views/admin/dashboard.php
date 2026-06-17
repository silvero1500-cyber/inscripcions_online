<?php
/** @var App\Models\Usuario $user */
$isSuper = ($user->rol ?? '') === 'superadmin';
?>
<section class="page-head">
    <h1>Hola, <?= e($user->nombre) ?></h1>
    <p class="muted">Rol: <?= e($user->rol) ?></p>
</section>

<section class="cards">
    <div class="card">
        <div class="card-label">Esdeveniments</div>
        <div class="card-value"><?= (int)$stats['eventos'] ?></div>
        <a class="card-link" href="<?= e(base_url('/admin/eventos')) ?>">Gestiona</a>
    </div>

    <div class="card">
        <div class="card-label">Inscrits confirmats</div>
        <div class="card-value"><?= (int)$stats['inscritos'] ?></div>
        <a class="card-link" href="<?= e(base_url('/admin/inscritos')) ?>">Veure</a>
    </div>
</section>

<?php if ($isSuper): ?>
<section style="margin-top:1.4rem;display:flex;gap:.6rem;flex-wrap:wrap;">
    <a class="btn" href="<?= e(base_url('/admin/configuracio')) ?>">⚙ Configuració general</a>
    <a class="btn" href="<?= e(base_url('/admin/migracions')) ?>">🗄 Migracions de base de dades</a>
</section>
<?php endif; ?>

<?php if ($isSuper && (int)$stats['eventos'] === 0): ?>
<section class="empty-state">
    <p>Encara no hi ha cap esdeveniment creat. Comença afegint el primer per recollir inscripcions.</p>
    <a class="btn btn-primary" href="<?= e(base_url('/admin/eventos/nou')) ?>">+ Nou esdeveniment</a>
</section>
<?php endif; ?>
