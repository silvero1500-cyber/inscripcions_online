<?php
/** @var object $user */
/** @var list<array> $usuarios */
/** @var array $flash */
?>
<section class="page-head with-action">
    <div>
        <h1>Usuaris</h1>
        <p class="muted">Gestió d'organitzadors i superadmins.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(base_url('/admin/usuarios/nou')) ?>">+ Nou usuari</a>
</section>

<?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><?= e($flash['success']) ?></div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><?= e($flash['error']) ?></div>
<?php endif; ?>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Eventos</th>
                <th>Estat</th>
                <th>Últim login</th>
                <th class="th-actions">Accions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td>
                    <strong><?= e($u['nombre']) ?></strong>
                    <?php if ($u['id'] == $user->id): ?>
                        <span class="badge badge-success" style="margin-left:.4rem;">tu</span>
                    <?php endif; ?>
                </td>
                <td><?= e($u['email']) ?></td>
                <td>
                    <?php if ($u['rol'] === 'superadmin'): ?>
                        <span class="badge badge-warning">superadmin</span>
                    <?php else: ?>
                        <span class="badge badge-muted">organitzador</span>
                    <?php endif; ?>
                </td>
                <td><?= (int)$u['num_eventos'] ?></td>
                <td>
                    <?php if ((int)$u['activo'] === 1): ?>
                        <span class="badge badge-success">actiu</span>
                    <?php else: ?>
                        <span class="badge badge-muted">inactiu</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($u['ultimo_login'])): ?>
                        <?= e(date('d/m/Y H:i', strtotime((string)$u['ultimo_login']))) ?>
                    <?php else: ?>
                        <span class="muted small">mai</span>
                    <?php endif; ?>
                </td>
                <td class="td-actions">
                    <a class="btn-small" href="<?= e(base_url('/admin/usuarios/' . (int)$u['id'] . '/editar')) ?>">Editar</a>
                    <?php if ((int)($u['num_propios'] ?? 0) > 0): ?>
                        <a class="btn-small" href="<?= e(base_url('/admin/usuarios/' . (int)$u['id'] . '/transferir')) ?>"
                           title="Reassignar els <?= (int)$u['num_propios'] ?> evento(s) propis a un altre usuari">
                            Transferir
                        </a>
                    <?php endif; ?>
                    <?php if ($u['id'] != $user->id): ?>
                        <form method="post" action="<?= e(base_url('/admin/usuarios/' . (int)$u['id'] . '/eliminar')) ?>" class="inline"
                              onsubmit="return confirm('Esborrar a <?= e($u['nombre']) ?>?');">
                            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                            <button type="submit" class="btn-small btn-danger">Esborra</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
