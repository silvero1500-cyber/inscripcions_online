<?php
/** @var object $user */
/** @var list<array{filename:string, applied:bool, applied_at:?string}> $migrations */
/** @var array $flash */
$pendents = array_values(array_filter($migrations, fn($m) => !$m['applied']));
?>
<section class="page-head with-action">
    <div>
        <h1>Migracions de base de dades</h1>
        <p class="muted">Aplica els canvis d'esquema (<code>database/migration_*.sql</code>) de forma segura.</p>
    </div>
    <a class="btn" href="<?= e(base_url('/admin')) ?>">← Panel</a>
</section>

<?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><?= e($flash['success']) ?></div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><?= e($flash['error']) ?></div>
<?php endif; ?>

<div class="alert alert-info">
    Aquesta pàgina substitueix l'antic sistema de pujar scripts PHP temporals a l'arrel.
    Només executa els fitxers de migració del propi repositori i requereix ser <strong>superadmin</strong>.
</div>

<?php if (count($pendents) > 0): ?>
    <form method="post" action="<?= e(base_url('/admin/migracions/aplicar')) ?>"
          onsubmit="return confirm('Aplicar <?= count($pendents) ?> migració/ons pendent(s) a la base de dades?');"
          style="margin-bottom:1.2rem;">
        <?= \App\Core\Csrf::field() ?>
        <button type="submit" class="btn btn-primary">⚙ Aplicar <?= count($pendents) ?> migració/ons pendent(s)</button>
    </form>
<?php else: ?>
    <div class="alert alert-success">✓ La base de dades està al dia. No hi ha migracions pendents.</div>
<?php endif; ?>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>Migració</th><th>Estat</th><th>Aplicada</th></tr>
        </thead>
        <tbody>
        <?php foreach ($migrations as $m): ?>
            <tr>
                <td><code><?= e($m['filename']) ?></code></td>
                <td>
                    <?php if ($m['applied']): ?>
                        <span class="badge badge-success">✓ Aplicada</span>
                    <?php else: ?>
                        <span class="badge badge-warning">Pendent</span>
                    <?php endif; ?>
                </td>
                <td><?= $m['applied_at'] ? e(format_datetime_local((string) $m['applied_at'], 'd/m/Y H:i')) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
