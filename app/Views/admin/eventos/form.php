<?php
/** @var array|null $evento */
/** @var list<array> $campos */
/** @var list<array> $tarifas */
/** @var array $old */
/** @var array $errors */
use App\Core\Csrf;
use App\Models\CampoPersonalizado;
use App\Services\ImageUploader;

$isEdit = $evento !== null;
$titulo = $isEdit ? 'Editar esdeveniment' : 'Nou esdeveniment';
$action = $isEdit
    ? base_url('/admin/eventos/' . (int)$evento['id'])
    : base_url('/admin/eventos');

// Función para obtener valor: prioridad old > evento > default
$val = function (string $key, string $default = '') use ($old, $evento) {
    if (isset($old[$key])) return (string) $old[$key];
    if ($evento !== null && isset($evento[$key])) return (string) $evento[$key];
    return $default;
};

$err = fn(string $key): ?string => $errors[$key][0] ?? null;
?>
<section class="page-head with-action">
    <div>
        <h1><?= e($titulo) ?></h1>
        <p class="muted"><a href="<?= e(base_url('/admin/eventos')) ?>">&larr; Tornar al llistat</a></p>
    </div>
</section>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate class="form-stacked">
    <?= Csrf::field() ?>

    <fieldset>
        <legend>Dades de l'esdeveniment</legend>

        <div class="form-row">
            <label for="titulo">Títol *</label>
            <input type="text" id="titulo" name="titulo" required maxlength="255"
                   value="<?= e($val('titulo')) ?>">
            <?php if ($err('titulo')): ?><div class="field-error"><?= e($err('titulo')) ?></div><?php endif; ?>
        </div>

        <div class="form-grid-2">
            <div class="form-row">
                <label for="fecha_evento">Data de l'esdeveniment *</label>
                <input type="date" id="fecha_evento" name="fecha_evento" required
                       value="<?= e($val('fecha_evento')) ?>">
                <?php if ($err('fecha_evento')): ?><div class="field-error"><?= e($err('fecha_evento')) ?></div><?php endif; ?>
            </div>

            <div class="form-row">
                <label for="fecha_limite_inscripcion">Límit d'inscripció</label>
                <input type="datetime-local" id="fecha_limite_inscripcion" name="fecha_limite_inscripcion"
                       value="<?= e(str_replace(' ', 'T', substr($val('fecha_limite_inscripcion', ''), 0, 16))) ?>">
                <?php if ($err('fecha_limite_inscripcion')): ?><div class="field-error"><?= e($err('fecha_limite_inscripcion')) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="form-row">
            <label for="aforo_maximo">Aforament total de l'esdeveniment</label>
            <input type="number" id="aforo_maximo" name="aforo_maximo" min="1" max="100000"
                   placeholder="Sense límit" value="<?= e($val('aforo_maximo')) ?>">
            <small class="muted">Si està buit, no hi ha límit total. Cada tarifa pot tenir el seu propi aforament.</small>
            <?php if ($err('aforo_maximo')): ?><div class="field-error"><?= e($err('aforo_maximo')) ?></div><?php endif; ?>
        </div>

        <div class="form-row">
            <label for="descripcion">Descripció</label>
            <textarea id="descripcion" name="descripcion" rows="6"><?= e($val('descripcion')) ?></textarea>
        </div>

        <div class="form-row">
            <label for="imagen">Imatge de portada</label>
            <?php if ($isEdit && !empty($evento['imagen_portada'])): ?>
                <div class="current-image">
                    <img src="<?= e(ImageUploader::publicUrl($evento['imagen_portada'])) ?>" alt="Portada actual">
                    <label class="inline-check">
                        <input type="checkbox" name="eliminar_imagen" value="1">
                        Eliminar imatge actual
                    </label>
                </div>
            <?php endif; ?>
            <input type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp,image/gif">
            <small class="muted">JPG, PNG, WEBP o GIF · màx 5 MB</small>
            <?php if ($err('imagen')): ?><div class="field-error"><?= e($err('imagen')) ?></div><?php endif; ?>
        </div>

        <div class="form-grid-2">
            <label class="inline-check">
                <input type="checkbox" name="activo" value="1"
                    <?= ($isEdit ? (int)$evento['activo'] : 1) === 1 ? 'checked' : '' ?>>
                Esdeveniment actiu (visible)
            </label>

            <label class="inline-check">
                <input type="checkbox" name="inscripciones_abiertas" value="1"
                    <?= ($isEdit ? (int)$evento['inscripciones_abiertas'] : 1) === 1 ? 'checked' : '' ?>>
                Inscripcions obertes
            </label>
        </div>
    </fieldset>

    <fieldset>
        <legend>Tarifes</legend>
        <p class="muted">Defineix les diferents tarifes disponibles (per exemple: Adult, Infantil, VIP). Cada inscrit haurà de triar-ne una.</p>

        <div id="tarifas-list">
            <?php foreach ($tarifas as $idx => $t): ?>
                <div class="tarifa-row" data-index="<?= (int)$idx ?>">
                    <input type="hidden" name="tarifas[<?= (int)$idx ?>][id]" value="<?= e((string)$t['id']) ?>">
                    <div class="tarifa-grid">
                        <div>
                            <label>Nom *</label>
                            <input type="text" name="tarifas[<?= (int)$idx ?>][nombre]" value="<?= e((string)$t['nombre']) ?>" required maxlength="100">
                        </div>
                        <div>
                            <label>Preu (€) *</label>
                            <input type="text" name="tarifas[<?= (int)$idx ?>][precio]" value="<?= e(number_format((float)$t['precio'], 2, '.', '')) ?>" required inputmode="decimal" placeholder="0.00">
                        </div>
                        <div>
                            <label>Aforament</label>
                            <input type="number" name="tarifas[<?= (int)$idx ?>][aforo_maximo]" value="<?= e((string)($t['aforo_maximo'] ?? '')) ?>" min="1" placeholder="Sense límit">
                        </div>
                        <div>
                            <label>&nbsp;</label>
                            <label class="inline-check">
                                <input type="checkbox" name="tarifas[<?= (int)$idx ?>][activo]" value="1" <?= (int)$t['activo'] === 1 ? 'checked' : '' ?>>
                                Activa
                            </label>
                        </div>
                    </div>
                    <div class="tarifa-grid-2">
                        <div>
                            <label>Descripció (opcional)</label>
                            <input type="text" name="tarifas[<?= (int)$idx ?>][descripcion]" value="<?= e((string)($t['descripcion'] ?? '')) ?>" maxlength="500">
                        </div>
                        <div>
                            <label>Disponible des de</label>
                            <input type="datetime-local" name="tarifas[<?= (int)$idx ?>][fecha_inicio]" value="<?= e(str_replace(' ', 'T', substr((string)($t['fecha_inicio'] ?? ''), 0, 16))) ?>">
                        </div>
                        <div>
                            <label>Disponible fins a</label>
                            <input type="datetime-local" name="tarifas[<?= (int)$idx ?>][fecha_fin]" value="<?= e(str_replace(' ', 'T', substr((string)($t['fecha_fin'] ?? ''), 0, 16))) ?>">
                        </div>
                    </div>
                    <button type="button" class="btn-link btn-danger tarifa-remove">Eliminar tarifa</button>
                    <hr>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" id="add-tarifa" class="btn btn-secondary">+ Afegir tarifa</button>
    </fieldset>

    <fieldset>
        <legend>Camps personalitzats del formulari</legend>
        <p class="muted">A part dels camps estàndard del corredor (nom, DNI, email...), pots afegir camps extra que es mostraran al formulari d'inscripció.</p>

        <div id="campos-list">
            <?php foreach ($campos as $idx => $c): ?>
                <?php $opcionesArr = CampoPersonalizado::opcionesFromJson($c['opciones'] ?? null); ?>
                <div class="campo-row" data-index="<?= (int)$idx ?>">
                    <div class="campo-grid">
                        <div>
                            <label>Etiqueta (visible)</label>
                            <input type="text" name="campos[<?= (int)$idx ?>][etiqueta]" value="<?= e((string)$c['etiqueta']) ?>" required>
                        </div>
                        <div>
                            <label>Nom intern</label>
                            <input type="text" name="campos[<?= (int)$idx ?>][nombre_campo]" value="<?= e((string)$c['nombre_campo']) ?>" placeholder="auto">
                        </div>
                        <div>
                            <label>Tipus</label>
                            <select name="campos[<?= (int)$idx ?>][tipo]" class="campo-tipo">
                                <?php foreach (CampoPersonalizado::TIPOS_VALIDOS as $t): ?>
                                    <option value="<?= e($t) ?>" <?= $c['tipo'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Obligatori</label>
                            <label class="inline-check">
                                <input type="checkbox" name="campos[<?= (int)$idx ?>][requerido]" value="1" <?= (int)$c['requerido'] === 1 ? 'checked' : '' ?>>
                                Obligatori
                            </label>
                        </div>
                    </div>
                    <div class="campo-grid-2">
                        <div>
                            <label>Opcions (només select/radio/checkbox · separa amb |)</label>
                            <input type="text" name="campos[<?= (int)$idx ?>][opciones]" value="<?= e(implode(' | ', $opcionesArr)) ?>" placeholder="Opció 1 | Opció 2 | Opció 3">
                        </div>
                        <div>
                            <label>Text d'ajuda</label>
                            <input type="text" name="campos[<?= (int)$idx ?>][ayuda]" value="<?= e((string)($c['ayuda'] ?? '')) ?>">
                        </div>
                    </div>
                    <button type="button" class="btn-link btn-danger campo-remove">Eliminar camp</button>
                    <hr>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" id="add-campo" class="btn btn-secondary">+ Afegir camp personalitzat</button>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Desa els canvis' : 'Crea l\'esdeveniment' ?></button>
        <a href="<?= e(base_url('/admin/eventos')) ?>" class="btn">Cancel·la</a>
    </div>
</form>

<!-- Plantilla para nuevos campos (oculta) -->
<template id="campo-template">
    <div class="campo-row" data-index="__IDX__">
        <div class="campo-grid">
            <div>
                <label>Etiqueta (visible)</label>
                <input type="text" name="campos[__IDX__][etiqueta]" required>
            </div>
            <div>
                <label>Nom intern</label>
                <input type="text" name="campos[__IDX__][nombre_campo]" placeholder="auto">
            </div>
            <div>
                <label>Tipus</label>
                <select name="campos[__IDX__][tipo]" class="campo-tipo">
                    <?php foreach (CampoPersonalizado::TIPOS_VALIDOS as $t): ?>
                        <option value="<?= e($t) ?>"><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Obligatori</label>
                <label class="inline-check">
                    <input type="checkbox" name="campos[__IDX__][requerido]" value="1">
                    Obligatori
                </label>
            </div>
        </div>
        <div class="campo-grid-2">
            <div>
                <label>Opcions (només select/radio/checkbox · separa amb |)</label>
                <input type="text" name="campos[__IDX__][opciones]" placeholder="Opció 1 | Opció 2 | Opció 3">
            </div>
            <div>
                <label>Text d'ajuda</label>
                <input type="text" name="campos[__IDX__][ayuda]">
            </div>
        </div>
        <button type="button" class="btn-link btn-danger campo-remove">Eliminar camp</button>
        <hr>
    </div>
</template>

<!-- Plantilla para nuevas tarifas -->
<template id="tarifa-template">
    <div class="tarifa-row" data-index="__IDX__">
        <input type="hidden" name="tarifas[__IDX__][id]" value="">
        <div class="tarifa-grid">
            <div>
                <label>Nom *</label>
                <input type="text" name="tarifas[__IDX__][nombre]" required maxlength="100">
            </div>
            <div>
                <label>Preu (€) *</label>
                <input type="text" name="tarifas[__IDX__][precio]" required inputmode="decimal" placeholder="0.00">
            </div>
            <div>
                <label>Aforament</label>
                <input type="number" name="tarifas[__IDX__][aforo_maximo]" min="1" placeholder="Sense límit">
            </div>
            <div>
                <label>&nbsp;</label>
                <label class="inline-check">
                    <input type="checkbox" name="tarifas[__IDX__][activo]" value="1" checked>
                    Activa
                </label>
            </div>
        </div>
        <div class="tarifa-grid-2">
            <div>
                <label>Descripció (opcional)</label>
                <input type="text" name="tarifas[__IDX__][descripcion]" maxlength="500">
            </div>
            <div>
                <label>Disponible des de</label>
                <input type="datetime-local" name="tarifas[__IDX__][fecha_inicio]">
            </div>
            <div>
                <label>Disponible fins a</label>
                <input type="datetime-local" name="tarifas[__IDX__][fecha_fin]">
            </div>
        </div>
        <button type="button" class="btn-link btn-danger tarifa-remove">Eliminar tarifa</button>
        <hr>
    </div>
</template>

<script src="<?= e(asset('js/eventos.js')) ?>"></script>
