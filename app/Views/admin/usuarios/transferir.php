<?php
/** @var object $user */
/** @var array $usuario */
/** @var list<array> $owned */
/** @var list<array> $targets */
/** @var array $flash */
?>
<section class="page-head">
    <h1>Transferir eventos propis</h1>
    <p class="muted">
        Reassigna els <?= count($owned) ?> evento(s) propis de
        <strong><?= e($usuario['nombre']) ?></strong> (<?= e($usuario['email']) ?>)
        a un altre usuari. Aquesta acció és irreversible (però pots tornar a transferir-los després).
    </p>
</section>

<?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><?= e($flash['error']) ?></div>
<?php endif; ?>

<div class="grid-wrap">
    <table class="data-grid">
        <thead>
            <tr>
                <th>Evento</th>
                <th>Data</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($owned as $ev): ?>
            <tr>
                <td><strong><?= e($ev['titulo']) ?></strong></td>
                <td><?= e(date('d/m/Y', strtotime((string)$ev['fecha_evento']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (count($targets) === 0): ?>
    <div class="alert alert-error" style="margin-top:1.2rem;">
        No hi ha cap altre usuari actiu al qual transferir. Crea o activa primer un altre superadmin/organizador.
    </div>
    <div class="form-actions" style="margin-top:1.2rem;display:flex;gap:.6rem;justify-content:flex-end;">
        <a class="btn" href="<?= e(base_url('/admin/usuarios')) ?>">Tornar</a>
    </div>
<?php else: ?>
    <form method="post"
          action="<?= e(base_url('/admin/usuarios/' . (int)$usuario['id'] . '/transferir')) ?>"
          class="form-admin" style="margin-top:1.2rem;"
          onsubmit="return confirm('Transferir <?= count($owned) ?> evento(s) al nou propietari?');">
        <?= \App\Core\Csrf::field() ?>

        <div class="form-row">
            <label for="target_id">Nou propietari <span class="req">*</span></label>
            <select id="target_id" name="target_id" required>
                <option value="">— Selecciona un usuari —</option>
                <?php foreach ($targets as $t): ?>
                    <option value="<?= (int)$t['id'] ?>">
                        <?= e($t['nombre']) ?> · <?= e($t['email']) ?>
                        (<?= $t['rol'] === 'superadmin' ? 'superadmin' : 'organitzador' ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="muted">
                El nou propietari tindrà accés total a aquests eventos (i a tots els inscrits, pagaments, etc.).
            </small>
        </div>

        <div class="form-actions" style="margin-top:1.2rem;display:flex;gap:.6rem;justify-content:flex-end;">
            <a class="btn" href="<?= e(base_url('/admin/usuarios')) ?>">Cancel·la</a>
            <button type="submit" class="btn btn-primary">Transferir eventos</button>
        </div>
    </form>
<?php endif; ?>
