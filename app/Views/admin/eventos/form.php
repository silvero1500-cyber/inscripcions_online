<?php
/** @var array|null $evento */
/** @var list<array> $campos */
/** @var list<array> $tarifas */
/** @var list<array> $grupos */
/** @var array $old */
/** @var array $errors */
use App\Core\Csrf;
use App\Models\CampoPersonalizado;
use App\Models\CamposFijos;
use App\Services\ImageUploader;

$isEdit = $evento !== null;
$grupos = $grupos ?? [];
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

// Configuració actual dels camps fixos (amb fallback a $old si la validació ha fallat)
$camposFijosCfg = CamposFijos::resolve($evento['campos_fijos'] ?? null);
$cfState = function (string $key) use ($old, $camposFijosCfg): string {
    $o = $old['campos_fijos'][$key] ?? null;
    return in_array($o, CamposFijos::ESTADOS, true) ? (string)$o : $camposFijosCfg[$key];
};
?>
<section class="page-head with-action">
    <div>
        <h1><?= e($titulo) ?></h1>
        <p class="muted"><a href="<?= e(base_url('/admin/eventos')) ?>">&larr; Tornar al llistat</a></p>
    </div>
</section>

<form id="evento-form" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate class="form-stacked">
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
            <label for="max_participantes">Màxim de participants per inscripció</label>
            <input type="number" id="max_participantes" name="max_participantes" min="1" max="50"
                   placeholder="1 (individual)" value="<?= e($val('max_participantes')) ?>">
            <small class="muted">Permet inscriure diverses persones en una sola compra (família/grup). Buit o 1 = inscripció individual.</small>
            <?php if ($err('max_participantes')): ?><div class="field-error"><?= e($err('max_participantes')) ?></div><?php endif; ?>
        </div>

        <div class="form-row">
            <label for="localizacion">Localització</label>
            <input type="text" id="localizacion" name="localizacion" maxlength="255"
                   placeholder="Ex: Plaça Major, 08001 Barcelona" value="<?= e($val('localizacion')) ?>">
            <small class="muted">Lloc o adreça on es celebra l'esdeveniment.</small>
            <?php if ($err('localizacion')): ?><div class="field-error"><?= e($err('localizacion')) ?></div><?php endif; ?>
        </div>

        <div class="form-grid-2">
            <div class="form-row">
                <label for="reglamento_url">Enllaç al reglament</label>
                <input type="url" id="reglamento_url" name="reglamento_url" maxlength="500"
                       placeholder="https://..." value="<?= e($val('reglamento_url')) ?>">
                <small class="muted">Opcional. Es mostrarà com a botó a la pàgina pública.</small>
                <?php if ($err('reglamento_url')): ?><div class="field-error"><?= e($err('reglamento_url')) ?></div><?php endif; ?>
            </div>
            <div class="form-row">
                <label for="web_oficial_url">Web oficial</label>
                <input type="url" id="web_oficial_url" name="web_oficial_url" maxlength="500"
                       placeholder="https://..." value="<?= e($val('web_oficial_url')) ?>">
                <small class="muted">Opcional. Es mostrarà com a botó a la pàgina pública.</small>
                <?php if ($err('web_oficial_url')): ?><div class="field-error"><?= e($err('web_oficial_url')) ?></div><?php endif; ?>
            </div>
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
        <legend>Grups d'aforament compartit (opcional)</legend>
        <p class="muted">Crea grups perquè diverses tarifes restin d'un <strong>mateix cupo</strong> (p. ex. «Cursa 10km» = 100 places compartides entre Adult i Infantil). Després assigna cada tarifa a un grup a la seva targeta. Una tarifa amb grup <strong>ignora el seu aforament propi</strong>.</p>

        <div class="builder">
            <aside class="builder-side">
                <button type="button" id="add-grupo" class="btn btn-secondary btn-block">+ Afegir grup</button>
                <p class="builder-count"><strong data-count-for="grupos-list">0</strong> grups</p>
            </aside>
            <div class="builder-main">
                <div id="grupos-list" class="sortable-list">
                    <?php foreach ($grupos as $gidx => $g): $gcid = 'g' . (int)$g['id']; ?>
                        <div class="grupo-row card-item" data-index="<?= (int)$gidx ?>" data-cid="<?= e($gcid) ?>">
                            <div class="item-head">
                                <span class="item-title"><?= e((string)$g['nombre'] !== '' ? (string)$g['nombre'] : 'Grup') ?></span>
                                <span class="item-tools">
                                    <button type="button" class="btn-link btn-danger grupo-remove" title="Eliminar grup" aria-label="Eliminar grup">✕</button>
                                </span>
                            </div>
                            <input type="hidden" name="grupos[<?= (int)$gidx ?>][id]" value="<?= (int)$g['id'] ?>">
                            <input type="hidden" name="grupos[<?= (int)$gidx ?>][cid]" value="<?= e($gcid) ?>">
                            <div class="grupo-grid">
                                <div>
                                    <label>Nom del grup *</label>
                                    <input type="text" name="grupos[<?= (int)$gidx ?>][nombre]" value="<?= e((string)$g['nombre']) ?>" class="grupo-nombre" required maxlength="100" placeholder="Ex: Cursa 10km">
                                </div>
                                <div>
                                    <label>Aforament compartit *</label>
                                    <input type="number" name="grupos[<?= (int)$gidx ?>][aforo_maximo]" value="<?= (int)$g['aforo_maximo'] ?>" min="1" placeholder="Ex: 100">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="empty-hint" data-empty-for="grupos-list">Cap grup. Les tarifes faran servir el seu aforament propi.</p>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Tarifes</legend>
        <p class="muted">Defineix les diferents tarifes disponibles (per exemple: Adult, Infantil, VIP). Cada inscrit haurà de triar-ne una. Arrossega les targetes pel punt <span aria-hidden="true">⠿</span> o fes servir les fletxes per ordenar-les.</p>

        <div class="builder">
            <aside class="builder-side">
                <button type="button" id="add-tarifa" class="btn btn-secondary btn-block">+ Afegir tarifa</button>
                <p class="builder-count"><strong data-count-for="tarifas-list">0</strong> tarifes</p>
            </aside>
            <div class="builder-main">
                <div id="tarifas-list" class="sortable-list">
                    <?php foreach ($tarifas as $idx => $t): ?>
                        <div class="tarifa-row card-item" data-index="<?= (int)$idx ?>">
                            <div class="item-head">
                                <span class="drag-handle" title="Arrossega per ordenar" aria-hidden="true">⠿</span>
                                <span class="item-title"><?= e((string)$t['nombre'] !== '' ? (string)$t['nombre'] : 'Tarifa') ?></span>
                                <span class="item-tools">
                                    <button type="button" class="btn-move move-up" title="Pujar" aria-label="Pujar">↑</button>
                                    <button type="button" class="btn-move move-down" title="Baixar" aria-label="Baixar">↓</button>
                                    <button type="button" class="btn-link btn-danger tarifa-remove" title="Eliminar tarifa" aria-label="Eliminar tarifa">✕</button>
                                </span>
                            </div>
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
                            <div class="tarifa-grupo">
                                <label>Aforament compartit (grup)</label>
                                <select name="tarifas[<?= (int)$idx ?>][grupo_cid]" class="tarifa-grupo-select">
                                    <option value="">— Independent (aforament propi) —</option>
                                    <?php foreach ($grupos as $g): $gc = 'g' . (int)$g['id']; ?>
                                        <option value="<?= e($gc) ?>" <?= ((int)($t['grupo_aforo_id'] ?? 0) === (int)$g['id']) ? 'selected' : '' ?>><?= e((string)$g['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="tarifa-edat">
                                <span class="tarifa-edat-title">Restricció per any de naixement (opcional)</span>
                                <div class="tarifa-grid-2">
                                    <div>
                                        <label>Nascuts des de (any)</label>
                                        <input type="number" name="tarifas[<?= (int)$idx ?>][anio_nac_min]" value="<?= e((string)($t['anio_nac_min'] ?? '')) ?>" min="1900" max="<?= (int)date('Y') ?>" placeholder="Sense límit">
                                    </div>
                                    <div>
                                        <label>Nascuts fins a (any)</label>
                                        <input type="number" name="tarifas[<?= (int)$idx ?>][anio_nac_max]" value="<?= e((string)($t['anio_nac_max'] ?? '')) ?>" min="1900" max="<?= (int)date('Y') ?>" placeholder="Sense límit">
                                    </div>
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
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="empty-hint" data-empty-for="tarifas-list">Encara no has afegit cap tarifa. Fes clic a «Afegir tarifa».</p>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Camps estàndard del corredor</legend>
        <p class="muted">Tria quins camps demanar al formulari d'inscripció. <strong>Nom</strong> i <strong>email</strong> són sempre obligatoris.</p>

        <div class="campos-fijos">
            <div class="cf-row cf-fixed">
                <span class="cf-label">Nom · Email · Talla de samarreta</span>
                <span class="badge badge-muted">Sempre obligatori</span>
            </div>
            <?php foreach (CamposFijos::CAMPS as $key => $meta): $st = $cfState($key); ?>
                <div class="cf-row">
                    <label class="cf-label" for="cf-<?= e($key) ?>"><?= e($meta['label']) ?></label>
                    <select id="cf-<?= e($key) ?>" name="campos_fijos[<?= e($key) ?>]" class="cf-select">
                        <option value="obligatori" <?= $st === 'obligatori' ? 'selected' : '' ?>>Obligatori</option>
                        <option value="opcional"   <?= $st === 'opcional'   ? 'selected' : '' ?>>Opcional</option>
                        <option value="ocult"      <?= $st === 'ocult'      ? 'selected' : '' ?>>Ocult</option>
                    </select>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
        // Talles per sexe (opcional) — config a eventos.tallas_sexo
        $tsMap = \App\Models\Inscrito::tallasSexoDecode($evento['tallas_sexo'] ?? null);
        if (isset($old['tallas_sexo']) && is_array($old['tallas_sexo'])) {
            $tsMap = [];
            foreach (['H', 'M'] as $s) {
                if (!empty($old['tallas_sexo'][$s]) && is_array($old['tallas_sexo'][$s])) {
                    $tsMap[$s] = array_values(array_intersect(\App\Models\Inscrito::TALLAS, $old['tallas_sexo'][$s]));
                }
            }
        }
        $tsChecked = fn(string $s, string $t): bool => !isset($tsMap[$s]) || in_array($t, $tsMap[$s], true);
        ?>
        <div class="cf-tallas-sexe" style="margin-top:1.2rem;">
            <label style="font-weight:600;">Talles de samarreta per sexe <span class="muted">(opcional)</span></label>
            <small class="muted" style="display:block;margin:.2rem 0 .6rem;">Marca quines talles s'ofereixen a cada sexe. Si les deixes <strong>totes</strong> marcades, no hi ha restricció. «No binari» sempre veu totes.</small>
            <?php foreach (['H' => 'Home', 'M' => 'Dona'] as $sx => $lbl): ?>
                <div class="tallas-sexe-row" style="margin-bottom:.4rem;">
                    <strong style="display:inline-block;min-width:3.5rem;"><?= e($lbl) ?>:</strong>
                    <?php foreach (\App\Models\Inscrito::TALLAS as $tl): ?>
                        <label class="inline-check" style="margin-right:.7rem;"><input type="checkbox" name="tallas_sexo[<?= $sx ?>][]" value="<?= e($tl) ?>" <?= $tsChecked($sx, $tl) ? 'checked' : '' ?>> <?= e($tl) ?></label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Camps personalitzats del formulari</legend>
        <p class="muted">A part dels camps estàndard del corredor (nom, DNI, email...), pots afegir camps extra que es mostraran al formulari d'inscripció. Arrossega'ls o fes servir les fletxes per ordenar-los.</p>
        <?php
        // Opcions del selector "només per a la tarifa" (condicional). Només tarifes ja desades.
        $tarifaCondOptions = function (?int $sel) use ($tarifas): string {
            $h = '<option value="">— Totes les tarifes —</option>';
            foreach ($tarifas as $t) {
                if (empty($t['id'])) continue;
                $h .= '<option value="' . (int) $t['id'] . '"' . ((int) $sel === (int) $t['id'] ? ' selected' : '') . '>' . e((string) $t['nombre']) . '</option>';
            }
            return $h;
        };
        ?>

        <div class="builder">
            <aside class="builder-side">
                <button type="button" id="add-campo" class="btn btn-secondary btn-block">+ Afegir camp</button>
                <p class="builder-count"><strong data-count-for="campos-list">0</strong> camps</p>
            </aside>
            <div class="builder-main">
                <div id="campos-list" class="sortable-list">
                    <?php foreach ($campos as $idx => $c): ?>
                        <?php $opcionesArr = CampoPersonalizado::opcionesFromJson($c['opciones'] ?? null); ?>
                        <div class="campo-row card-item" data-index="<?= (int)$idx ?>">
                            <div class="item-head">
                                <span class="drag-handle" title="Arrossega per ordenar" aria-hidden="true">⠿</span>
                                <span class="item-title"><?= e((string)$c['etiqueta'] !== '' ? (string)$c['etiqueta'] : 'Camp') ?></span>
                                <span class="item-tools">
                                    <button type="button" class="btn-move move-up" title="Pujar" aria-label="Pujar">↑</button>
                                    <button type="button" class="btn-move move-down" title="Baixar" aria-label="Baixar">↓</button>
                                    <button type="button" class="btn-link btn-danger campo-remove" title="Eliminar camp" aria-label="Eliminar camp">✕</button>
                                </span>
                            </div>
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
                            <div class="campo-grid-2">
                                <div>
                                    <label>Mostrar només per a la tarifa <span class="muted">(condicional)</span></label>
                                    <select name="campos[<?= (int)$idx ?>][tarifa_id]"><?= $tarifaCondOptions(isset($c['tarifa_id']) ? (int)$c['tarifa_id'] : null) ?></select>
                                    <small class="muted">Si tries una tarifa, el camp només apareix quan s'hi inscriu (p.ex. franja horària per a la Mitja marató).</small>
                                </div>
                                <div></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="empty-hint" data-empty-for="campos-list">Encara no has afegit cap camp.</p>
            </div>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Desa els canvis' : 'Crea l\'esdeveniment' ?></button>
        <a href="<?= e(base_url('/admin/eventos')) ?>" class="btn">Cancel·la</a>
    </div>
</form>

<!-- Plantilla para nuevos campos (oculta) -->
<template id="campo-template">
    <div class="campo-row card-item" data-index="__IDX__">
        <div class="item-head">
            <span class="drag-handle" title="Arrossega per ordenar" aria-hidden="true">⠿</span>
            <span class="item-title">Camp</span>
            <span class="item-tools">
                <button type="button" class="btn-move move-up" title="Pujar" aria-label="Pujar">↑</button>
                <button type="button" class="btn-move move-down" title="Baixar" aria-label="Baixar">↓</button>
                <button type="button" class="btn-link btn-danger campo-remove" title="Eliminar camp" aria-label="Eliminar camp">✕</button>
            </span>
        </div>
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
        <div class="campo-grid-2">
            <div>
                <label>Mostrar només per a la tarifa <span class="muted">(condicional)</span></label>
                <select name="campos[__IDX__][tarifa_id]"><?= $tarifaCondOptions(null) ?></select>
                <small class="muted">Si tries una tarifa, el camp només apareix quan s'hi inscriu (p.ex. franja horària per a la Mitja marató).</small>
            </div>
            <div></div>
        </div>
    </div>
</template>

<!-- Plantilla para nuevas tarifas -->
<template id="tarifa-template">
    <div class="tarifa-row card-item" data-index="__IDX__">
        <div class="item-head">
            <span class="drag-handle" title="Arrossega per ordenar" aria-hidden="true">⠿</span>
            <span class="item-title">Tarifa</span>
            <span class="item-tools">
                <button type="button" class="btn-move move-up" title="Pujar" aria-label="Pujar">↑</button>
                <button type="button" class="btn-move move-down" title="Baixar" aria-label="Baixar">↓</button>
                <button type="button" class="btn-link btn-danger tarifa-remove" title="Eliminar tarifa" aria-label="Eliminar tarifa">✕</button>
            </span>
        </div>
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
        <div class="tarifa-grupo">
            <label>Aforament compartit (grup)</label>
            <select name="tarifas[__IDX__][grupo_cid]" class="tarifa-grupo-select">
                <option value="">— Independent (aforament propi) —</option>
            </select>
        </div>
        <div class="tarifa-edat">
            <span class="tarifa-edat-title">Restricció per any de naixement (opcional)</span>
            <div class="tarifa-grid-2">
                <div>
                    <label>Nascuts des de (any)</label>
                    <input type="number" name="tarifas[__IDX__][anio_nac_min]" min="1900" max="<?= (int)date('Y') ?>" placeholder="Sense límit">
                </div>
                <div>
                    <label>Nascuts fins a (any)</label>
                    <input type="number" name="tarifas[__IDX__][anio_nac_max]" min="1900" max="<?= (int)date('Y') ?>" placeholder="Sense límit">
                </div>
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
    </div>
</template>

<!-- Plantilla per a nous grups d'aforament -->
<template id="grupo-template">
    <div class="grupo-row card-item" data-index="__IDX__" data-cid="__CID__">
        <div class="item-head">
            <span class="item-title">Grup</span>
            <span class="item-tools">
                <button type="button" class="btn-link btn-danger grupo-remove" title="Eliminar grup" aria-label="Eliminar grup">✕</button>
            </span>
        </div>
        <input type="hidden" name="grupos[__IDX__][id]" value="">
        <input type="hidden" name="grupos[__IDX__][cid]" value="__CID__">
        <div class="grupo-grid">
            <div>
                <label>Nom del grup *</label>
                <input type="text" name="grupos[__IDX__][nombre]" class="grupo-nombre" required maxlength="100" placeholder="Ex: Cursa 10km">
            </div>
            <div>
                <label>Aforament compartit *</label>
                <input type="number" name="grupos[__IDX__][aforo_maximo]" min="1" placeholder="Ex: 100">
            </div>
        </div>
    </div>
</template>

<script src="<?= e(asset('js/eventos.js')) ?>?v=<?= @filemtime(BASE_PATH . '/public/assets/js/eventos.js') ?: time() ?>"></script>
