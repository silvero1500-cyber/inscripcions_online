<?php
/** @var object $user */
/** @var array|null $usuario */
/** @var array $old */
/** @var array $errors */
/** @var list<array> $allEventos */
/** @var list<int> $assignedIds */

$isEdit = $usuario !== null;
$assignedSet = array_flip($assignedIds);
// Si hi ha old['eventos'] (form failed validation), usar-lo per preservar selecció
$oldEventos = isset($old['eventos']) && is_array($old['eventos'])
    ? array_flip(array_map('intval', $old['eventos']))
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
                <option value="superadmin" <?= $val('rol') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
            </select>
            <small class="muted">Superadmin: accés total. Organitzador: només els seus eventos. <strong>Recollida</strong>: NOMÉS la recollida de dorsals dels eventos assignats (res més).</small>
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

    <!-- ── Eventos assignats (només si és organizador) ── -->
    <?php
    // Per defecte, en creació de nou usuari el rol comença buit però el <select>
    // mostra "Organitzador" (primera opció). Mostrar la secció a no ser que
    // expressament hagi seleccionat "superadmin".
    $rolActual = $val('rol') ?: 'organizador';
    ?>
    <div id="eventos-section" style="margin-top:1.5rem;<?= in_array($rolActual, ['organizador', 'recollida'], true) ? '' : 'display:none;' ?>">
        <h3 style="font-size:1rem;margin:0 0 .8rem;">Eventos assignats</h3>
        <p class="muted" style="font-size:.85rem;margin:0 0 .8rem;">
            Marca els eventos que aquest organizador podrà veure i gestionar.
            (Els eventos on és propietari directe ja li donen accés sense necessitat de marcar-los.)
        </p>
        <?php if (count($allEventos) === 0): ?>
            <p class="muted">No hi ha cap evento creat encara.</p>
        <?php else: ?>
            <div style="margin-bottom:.5rem;display:flex;gap:.5rem;justify-content:flex-end;">
                <button type="button" class="btn-small" onclick="toggleAllEventos(true)">Marcar tots</button>
                <button type="button" class="btn-small" onclick="toggleAllEventos(false)">Desmarcar tots</button>
            </div>
            <div class="grid-wrap">
                <table class="data-grid" id="eventosGrid">
                    <thead>
                        <tr>
                            <th style="width:40px;"></th>
                            <th>Nom de l'evento</th>
                            <th>Data</th>
                            <th class="cell-right">Inscrits</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allEventos as $ev): ?>
                        <?php
                        $eid = (int)$ev['id'];
                        $checked = $oldEventos !== null ? isset($oldEventos[$eid]) : isset($assignedSet[$eid]);
                        ?>
                        <tr class="evento-row<?= $checked ? ' is-checked' : '' ?>" data-eid="<?= $eid ?>">
                            <td>
                                <input type="checkbox" name="eventos[]" value="<?= $eid ?>" <?= $checked ? 'checked' : '' ?>
                                       class="evento-check-input">
                            </td>
                            <td><strong><?= e($ev['titulo']) ?></strong></td>
                            <td><?= e(date('d/m/Y', strtotime((string)$ev['fecha_evento']))) ?></td>
                            <td class="cell-right"><?= (int)$ev['inscrits'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
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
    // Mostrar/amagar la secció d'eventos segons el rol
    var rolSelect = document.getElementById('rol');
    var section = document.getElementById('eventos-section');
    if (rolSelect && section) {
        rolSelect.addEventListener('change', function () {
            section.style.display = (rolSelect.value === 'organizador' || rolSelect.value === 'recollida') ? '' : 'none';
        });
    }

    // Fer tota la fila clicable per marcar/desmarcar el check
    var rows = document.querySelectorAll('.evento-row');
    rows.forEach(function (row) {
        var cb = row.querySelector('.evento-check-input');
        if (!cb) return;
        row.addEventListener('click', function (e) {
            if (e.target.tagName === 'INPUT') return; // no doblar el clic si va al check
            cb.checked = !cb.checked;
            row.classList.toggle('is-checked', cb.checked);
        });
        cb.addEventListener('change', function () {
            row.classList.toggle('is-checked', cb.checked);
        });
    });
})();

// Helpers de marcar tots / desmarcar tots
function toggleAllEventos(state) {
    document.querySelectorAll('.evento-check-input').forEach(function (cb) {
        cb.checked = state;
        cb.closest('tr').classList.toggle('is-checked', state);
    });
}
</script>
