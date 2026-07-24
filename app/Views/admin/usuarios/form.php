<?php
/** @var object $user */
/** @var array|null $usuario */
/** @var array $old */
/** @var array $errors */
/** @var list<array> $allCarreres */
/** @var list<int> $assignedCarreraIds */

$isEdit = $usuario !== null;
$assignedSet = array_flip($assignedCarreraIds);
// Si hi ha old['carreres'] (form failed validation), usar-lo per preservar selecció
$oldCarreres = isset($old['carreres']) && is_array($old['carreres'])
    ? array_flip(array_map('intval', $old['carreres']))
    : null;
$val = function (string $key, string $default = '') use ($old, $usuario): string {
    if (isset($old[$key])) return (string) $old[$key];
    if ($usuario !== null && array_key_exists($key, $usuario)) return (string) $usuario[$key];
    return $default;
};
$err = fn(string $k): ?string => $errors[$k][0] ?? null;
$action = $isEdit
    ? base_url('/admin/usuarios/' . (int)$usuario['id'])
    : base_url('/admin/usuarios');
?>
<section class="page-head">
    <h1><?= $isEdit ? 'Editar usuari' : 'Nou usuari' ?></h1>
    <?php if ($isEdit): ?>
        <p class="muted">Modificant <?= e($usuario['email']) ?></p>
    <?php endif; ?>
</section>

<form method="post" action="<?= e($action) ?>" class="form-admin" novalidate>
    <?= \App\Core\Csrf::field() ?>

    <div class="form-grid-2">
        <div class="form-row">
            <label for="nombre">Nom <span class="req">*</span></label>
            <input type="text" id="nombre" name="nombre" required maxlength="100"
                   value="<?= e($val('nombre')) ?>">
            <?php if ($err('nombre')): ?><div class="field-error"><?= e($err('nombre')) ?></div><?php endif; ?>
        </div>
        <div class="form-row">
            <label for="email">Email <span class="req">*</span></label>
            <input type="email" id="email" name="email" required maxlength="255"
                   value="<?= e($val('email')) ?>">
            <?php if ($err('email')): ?><div class="field-error"><?= e($err('email')) ?></div><?php endif; ?>
        </div>
    </div>

    <div class="form-grid-2">
        <div class="form-row">
            <label for="rol">Rol <span class="req">*</span></label>
            <select id="rol" name="rol" required>
                <option value="organizador" <?= $val('rol') === 'organizador' ? 'selected' : '' ?>>Organitzador</option>
                <option value="recollida" <?= $val('rol') === 'recollida' ? 'selected' : '' ?>>Recollida de dorsals</option>
                <option value="export" <?= $val('rol') === 'export' ? 'selected' : '' ?>>Només exportar CSV</option>
                <option value="superadmin" <?= $val('rol') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
            </select>
            <small class="muted">Superadmin: accés total. Organitzador: només els seus eventos. <strong>Recollida</strong>: NOMÉS la recollida de dorsals dels eventos assignats. <strong>Només exportar CSV</strong>: NOMÉS descarregar el CSV d'inscrits dels eventos assignats.</small>
            <?php if ($err('rol')): ?><div class="field-error"><?= e($err('rol')) ?></div><?php endif; ?>
        </div>
        <div class="form-row">
            <label for="password">
                Contrasenya
                <?php if (!$isEdit): ?><span class="req">*</span><?php endif; ?>
            </label>
            <input type="password" id="password" name="password" minlength="8" autocomplete="new-password"
                   <?= $isEdit ? 'placeholder="Deixa buit per no canviar"' : 'required' ?>>
            <small class="muted">Mínim 8 caràcters.</small>
            <?php if ($err('password')): ?><div class="field-error"><?= e($err('password')) ?></div><?php endif; ?>
        </div>
    </div>

    <div class="form-row">
        <label class="inline-check">
            <input type="checkbox" name="activo" value="1" <?= (int)$val('activo', '1') === 1 ? 'checked' : '' ?>>
            Usuari actiu (pot iniciar sessió)
        </label>
    </div>

    <!-- ── Carreres assignades (organizador/recollida/export) ── -->
    <?php
    // Per defecte, en creació de nou usuari el rol comença buit però el <select>
    // mostra "Organitzador" (primera opció). Mostrar la secció a no ser que
    // expressament hagi seleccionat "superadmin".
    $rolActual = $val('rol') ?: 'organizador';
    ?>
    <div id="eventos-section" style="margin-top:1.5rem;<?= in_array($rolActual, ['organizador', 'recollida', 'export'], true) ? '' : 'display:none;' ?>">
        <h3 style="font-size:1rem;margin:0 0 .8rem;">Curses amb accés</h3>
        <p class="muted" style="font-size:.85rem;margin:0 0 .8rem;">
            Marca les curses que aquest usuari podrà gestionar. Tindrà accés a
            <strong>totes les edicions</strong> de la cursa (també les inactives o passades).
        </p>
        <?php if (count($allCarreres) === 0): ?>
            <p class="muted">No hi ha cap cursa creada encara.</p>
        <?php else: ?>
            <div class="carreres-checks" style="display:flex;flex-direction:column;gap:.5rem;">
                <?php foreach ($allCarreres as $c): ?>
                    <?php
                    $cid = (int) $c['id'];
                    $checked = $oldCarreres !== null ? isset($oldCarreres[$cid]) : isset($assignedSet[$cid]);
                    $inactiva = (int) ($c['activa'] ?? 1) !== 1;
                    ?>
                    <label class="inline-check" style="display:flex;align-items:center;gap:.5rem;">
                        <input type="checkbox" name="carreres[]" value="<?= $cid ?>" <?= $checked ? 'checked' : '' ?>>
                        <strong><?= e($c['nombre']) ?></strong>
                        <?php if ($inactiva): ?><span class="badge badge-muted" style="font-size:.75rem;">inactiva</span><?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="form-actions" style="margin-top:1.5rem;display:flex;gap:.6rem;justify-content:flex-end;">
        <a class="btn" href="<?= e(base_url('/admin/usuarios')) ?>">Cancel·la</a>
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Desa canvis' : 'Crea usuari' ?></button>
    </div>
</form>

<script>
(function () {
    // Mostrar/amagar la secció de curses segons el rol
    var rolSelect = document.getElementById('rol');
    var section = document.getElementById('eventos-section');
    if (rolSelect && section) {
        rolSelect.addEventListener('change', function () {
            section.style.display = ['organizador', 'recollida', 'export'].indexOf(rolSelect.value) !== -1 ? '' : 'none';
        });
    }
})();
</script>
