<?php
/** @var array $evento */
/** @var array $old */
/** @var array $errors */

$val = fn(string $k, string $d = ''): string => (string)($old[$k] ?? $d);
$err = fn(string $k): ?string => $errors[$k][0] ?? null;
?>
<section class="page-head">
    <h1>Generar codis de descompte</h1>
    <p class="muted"><?= e($evento['titulo']) ?></p>
</section>

<form method="post" action="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/descuentos/generar')) ?>" class="form-admin">
    <?= \App\Core\Csrf::field() ?>

    <div class="form-grid-2">
        <div class="form-row">
            <label for="cantidad">Quantitat de codis <span class="req">*</span></label>
            <input type="number" id="cantidad" name="cantidad" min="1" max="500" required
                   value="<?= e($val('cantidad', '10')) ?>">
            <small class="muted">Entre 1 i 500.</small>
            <?php if ($err('cantidad')): ?><div class="field-error"><?= e($err('cantidad')) ?></div><?php endif; ?>
        </div>
        <div class="form-row">
            <label for="porcentaje">% de descompte <span class="req">*</span></label>
            <input type="text" id="porcentaje" name="porcentaje" required placeholder="10"
                   value="<?= e($val('porcentaje', '10')) ?>">
            <small class="muted">De 0.01 a 100 (ex: 10 = 10%).</small>
            <?php if ($err('porcentaje')): ?><div class="field-error"><?= e($err('porcentaje')) ?></div><?php endif; ?>
        </div>
    </div>

    <div class="form-grid-2">
        <div class="form-row">
            <label for="prefijo">Prefix (opcional)</label>
            <input type="text" id="prefijo" name="prefijo" maxlength="15" placeholder="EARLY"
                   value="<?= e($val('prefijo')) ?>" style="text-transform:uppercase;">
            <small class="muted">Lletres i números, màx 15. Ex: <code>EARLY</code> generarà codis tipus <code>EARLY-A4F2X9B1</code>.</small>
            <?php if ($err('prefijo')): ?><div class="field-error"><?= e($err('prefijo')) ?></div><?php endif; ?>
        </div>
        <div class="form-row">
            <label for="usos_max">Usos màx per codi (opcional)</label>
            <input type="number" id="usos_max" name="usos_max" min="1" placeholder="1 = un sol ús"
                   value="<?= e($val('usos_max')) ?>">
            <small class="muted">Deixa buit = sense límit. Posa 1 si cada codi és d'un sol ús.</small>
        </div>
    </div>

    <div class="form-grid-2">
        <div class="form-row">
            <label for="valido_desde">Vàlid des de (opcional)</label>
            <input type="datetime-local" id="valido_desde" name="valido_desde"
                   value="<?= e($val('valido_desde')) ?>">
        </div>
        <div class="form-row">
            <label for="valido_hasta">Vàlid fins (opcional)</label>
            <input type="datetime-local" id="valido_hasta" name="valido_hasta"
                   value="<?= e($val('valido_hasta')) ?>">
        </div>
    </div>

    <div class="form-row">
        <label for="nota">Nota interna (opcional)</label>
        <input type="text" id="nota" name="nota" maxlength="255" placeholder="Ex: campanya newsletter abril"
               value="<?= e($val('nota')) ?>">
    </div>

    <div class="form-actions" style="margin-top:1.5rem;display:flex;gap:.6rem;justify-content:flex-end;">
        <a class="btn" href="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/descuentos')) ?>">Cancel·la</a>
        <button type="submit" class="btn btn-primary">Generar codis</button>
    </div>
</form>
