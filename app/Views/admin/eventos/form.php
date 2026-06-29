<?php
/** @var array|null $evento */
/** @var list<array> $campos */
/** @var list<array> $tarifas */
/** @var list<array> $grupos */
/** @var list<array> $carreres */
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

<?php if ($isEdit): $barEvento = $evento; $barActual = 'editar'; require __DIR__ . '/../partials/cursa_bar.php'; endif; ?>

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

        <?php /* Carrera (marca) + any d'aquesta edició */ ?>
        <div class="form-grid-2">
            <div class="form-row">
                <label for="carrera_id">Cursa</label>
                <select id="carrera_id" name="carrera_id">
                    <option value="">— Cap (esdeveniment solt) —</option>
                    <?php foreach (($carreres ?? []) as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (string) $val('carrera_id') === (string) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="muted">La marca a què pertany aquesta edició (apareix a la barra superior).</small>
                <?php if ($err('carrera_id')): ?><div class="field-error"><?= e($err('carrera_id')) ?></div><?php endif; ?>
            </div>
            <div class="form-row">
                <label for="anio_edicion">Any de l'edició</label>
                <input type="number" id="anio_edicion" name="anio_edicion" min="2000" max="2100"
                       placeholder="Ex: 2026" value="<?= e($val('anio_edicion')) ?>">
                <small class="muted">Si es deixa buit, s'agafa l'any de la data de l'esdeveniment.</small>
                <?php if ($err('anio_edicion')): ?><div class="field-error"><?= e($err('anio_edicion')) ?></div><?php endif; ?>
            </div>
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
            <small class="muted">JPG, PNG, WEBP o GIF · màx 5 MB · <strong>mida recomanada 1600×900 px (proporció 16:9)</strong> — es mostra sencera tant a la llista com a la fitxa.</small>
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
                                    <label>Preu (€)</label>
                                    <input type="text" name="tarifas[<?= (int)$idx ?>][precio]" value="<?= e(number_format((float)$t['precio'], 2, '.', '')) ?>" inputmode="decimal" placeholder="0.00 (gratis)">
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
                            <div class="tarifa-tramos" style="margin-top:.6rem;">
                                <label>Preus per trams <span class="muted">(opcional · inscripció anticipada)</span></label>
                                <textarea name="tarifas[<?= (int)$idx ?>][tramos_text]" rows="3" placeholder="2026-03-01 | 15&#10;2026-04-01 | 20&#10;| 25"><?php foreach (\App\Models\Tarifa::tramos((int)$t['id']) as $tr): ?><?= ($tr['fecha_hasta'] ? substr((string)$tr['fecha_hasta'], 0, 10) : '') . ' | ' . number_format((float)$tr['precio'], 2, '.', '') . "\n" ?><?php endforeach; ?></textarea>
                                <small class="muted">Una línia per tram: <code>data límit | preu</code> (ex. <code>2026-03-01 | 15</code>). L'última sense data = preu final. Buit = s'usa el preu de dalt.</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="empty-hint" data-empty-for="tarifas-list">Encara no has afegit cap tarifa. Fes clic a «Afegir tarifa».</p>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Camps del formulari</legend>
        <p class="muted">Arrossega (⠿) o fes servir les fletxes per <strong>ordenar tots els camps</strong> (estàndard i personalitzats) com vulguis. <strong>Nom</strong> i <strong>email</strong> són sempre obligatoris; <strong>email + repetir</strong> van junts. Amb «+ Afegir camp» crees camps extra i els col·loques on vulguis.</p>
        <?php
        $ordenFull = CamposFijos::ordenComplet($evento['campos_orden'] ?? null, $campos);
        $camposByIdEditor = [];
        foreach ($campos as $cc) $camposByIdEditor[(int) $cc['id']] = $cc;

        // Checkboxes de tarifes per al camp condicional (només tarifes ja desades)
        $tarifaCondChecks = function (array $selectedIds, $idx) use ($tarifas): string {
            if (count(array_filter($tarifas, fn($t) => !empty($t['id']))) === 0) {
                return '<span class="muted">Desa l\'esdeveniment per poder assignar tarifes a aquest camp.</span>';
            }
            $h = '';
            foreach ($tarifas as $t) {
                if (empty($t['id'])) continue;
                $checked = in_array((int) $t['id'], $selectedIds, true) ? ' checked' : '';
                $h .= '<label class="inline-check" style="margin-right:.8rem;"><input type="checkbox" name="campos[' . $idx . '][tarifa_ids][]" value="' . (int) $t['id'] . '"' . $checked . '> ' . e((string) $t['nombre']) . '</label>';
            }
            return $h;
        };
        // Inputs d'opcions diferents per tarifa (select/radio/checkbox)
        $opcTarifaInputs = function (array $campo, $idx) use ($tarifas): string {
            if (count(array_filter($tarifas, fn($t) => !empty($t['id']))) === 0) return '';
            $map = \App\Models\CampoPersonalizado::opcionesPorTarifa($campo);
            $h  = '<details class="campo-opc-tarifa" style="margin-top:.6rem;"><summary style="cursor:pointer;color:var(--color-primary,#1e88c2);">Opcions diferents per tarifa (opcional)</summary>';
            $h .= '<small class="muted" style="display:block;margin:.3rem 0 .5rem;">Per a select/radio/checkbox: si omples les opcions d\'una tarifa, substitueixen les generals. Separa amb <code>|</code>.</small>';
            foreach ($tarifas as $t) {
                if (empty($t['id'])) continue;
                $tid = (int) $t['id'];
                $val = isset($map[$tid]) ? implode(' | ', $map[$tid]) : '';
                $h .= '<div style="margin-bottom:.4rem;"><label style="font-size:.85rem;font-weight:600;">' . e((string) $t['nombre']) . '</label>';
                $h .= '<input type="text" name="campos[' . $idx . '][opciones_tarifa][' . $tid . ']" value="' . e($val) . '" placeholder="Opció 1 | Opció 2 | Opció 3"></div>';
            }
            return $h . '</details>';
        };
        // Mini-editor "Calaix per opció" (per a select/radio): cada opció → calaix 1..4.
        // El contingut es regenera amb JS quan canvien les opcions; aquí es pinta l'estat desat.
        $calaixInputs = function (array $campo, $idx): string {
            $opts = \App\Models\CampoPersonalizado::opcionesFromJson($campo['opciones'] ?? null);
            $map  = \App\Models\CampoPersonalizado::calaixMap($campo);
            $colors = \App\Models\CampoPersonalizado::CALAIX_COLORS;
            $rows = '';
            foreach ($opts as $opt) {
                $sel = $map[$opt] ?? 0;
                $optsHtml = '<option value="0">— Sense calaix —</option>';
                foreach ($colors as $n => $meta) {
                    $optsHtml .= '<option value="' . $n . '"' . ($sel === $n ? ' selected' : '') . '>' . e($meta['nom']) . '</option>';
                }
                $rows .= '<div class="calaix-opt-row"><span class="calaix-opt-name">' . e($opt) . '</span>'
                    . '<select name="campos[' . $idx . '][calaix_map][' . e($opt) . ']">' . $optsHtml . '</select></div>';
            }
            $h  = '<details class="campo-calaix" style="margin-top:.6rem;"><summary style="cursor:pointer;color:var(--color-primary,#1e88c2);">Calaix de sortida per opció (per a recollida)</summary>';
            $h .= '<small class="muted" style="display:block;margin:.3rem 0 .5rem;">Assigna un calaix (1-4) a cada opció. A recollida es mostrarà el calaix del color corresponent segons l\'opció triada pel corredor.</small>';
            $h .= '<div class="calaix-opts" data-idx="' . $idx . '">' . ($rows !== '' ? $rows : '<p class="muted" style="margin:0;">Primer escriu les opcions a dalt.</p>') . '</div>';
            return $h . '</details>';
        };
        // Targeta plegable d'un camp personalitzat ($c = null per a la plantilla)
        $renderCampoCard = function ($idx, ?array $c) use ($tarifaCondChecks, $opcTarifaInputs, $calaixInputs): void {
            $opcArr   = CampoPersonalizado::opcionesFromJson($c['opciones'] ?? null);
            $tipo     = (string) ($c['tipo'] ?? 'text');
            $etiqueta = (string) ($c['etiqueta'] ?? '');
            ?>
            <div class="campo-row card-item field-item collapsed" data-index="<?= $idx ?>">
                <input type="hidden" name="campos_orden[]" value="__CUSTOM__">
                <div class="item-head">
                    <span class="drag-handle" title="Arrossega per ordenar" aria-hidden="true">⠿</span>
                    <span class="item-title"><?= e($etiqueta !== '' ? $etiqueta : 'Camp personalitzat') ?></span>
                    <span class="item-badge muted"><?= !empty($c['oculto']) ? '🔒 ocult (CSV)' : 'camp extra' ?></span>
                    <span class="item-tools">
                        <button type="button" class="btn-move campo-toggle" title="Desplegar / plegar opcions">⌄</button>
                        <button type="button" class="btn-move move-up" title="Pujar" aria-label="Pujar">↑</button>
                        <button type="button" class="btn-move move-down" title="Baixar" aria-label="Baixar">↓</button>
                        <button type="button" class="btn-link btn-danger campo-remove" title="Eliminar camp" aria-label="Eliminar camp">✕</button>
                    </span>
                </div>
                <div class="campo-body">
                    <div class="campo-grid">
                        <div><label>Etiqueta (visible)</label><input type="text" name="campos[<?= $idx ?>][etiqueta]" value="<?= e($etiqueta) ?>" required></div>
                        <div><label>Nom intern</label><input type="text" name="campos[<?= $idx ?>][nombre_campo]" value="<?= e((string) ($c['nombre_campo'] ?? '')) ?>" placeholder="auto"></div>
                        <div><label>Tipus</label>
                            <select name="campos[<?= $idx ?>][tipo]" class="campo-tipo">
                                <?php foreach (CampoPersonalizado::TIPOS_VALIDOS as $tp): ?>
                                    <option value="<?= e($tp) ?>" <?= $tipo === $tp ? 'selected' : '' ?>><?= e($tp) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><label>Obligatori</label><label class="inline-check"><input type="checkbox" name="campos[<?= $idx ?>][requerido]" value="1" <?= !empty($c['requerido']) ? 'checked' : '' ?>> Obligatori</label></div>
                    </div>
                    <div class="campo-grid-2">
                        <div><label>Opcions (només select/radio/checkbox · separa amb |)</label><input type="text" name="campos[<?= $idx ?>][opciones]" value="<?= e(implode(' | ', $opcArr)) ?>" placeholder="Opció 1 | Opció 2 | Opció 3"></div>
                        <div><label>Text d'ajuda</label><input type="text" name="campos[<?= $idx ?>][ayuda]" value="<?= e((string) ($c['ayuda'] ?? '')) ?>"></div>
                    </div>
                    <div class="campo-grid-2">
                        <div style="grid-column:1 / -1;">
                            <label class="inline-check"><input type="checkbox" name="campos[<?= $idx ?>][oculto]" value="1" <?= !empty($c['oculto']) ? 'checked' : '' ?>> <strong>Camp ocult</strong> — no apareix al formulari públic; només surt com a columna al CSV (per omplir-lo a mà i importar-lo)</label>
                        </div>
                    </div>
                    <div class="campo-grid-2">
                        <div style="grid-column:1 / -1;">
                            <label>Mostrar només per a aquestes tarifes <span class="muted">(condicional)</span></label>
                            <div class="campo-tarifes-checks"><?= $tarifaCondChecks(CampoPersonalizado::tarifasDeCampo($c ?? []), $idx) ?></div>
                            <small class="muted">Marca una o més tarifes i el camp només apareix quan se'n tria alguna. Cap marcada = sempre.</small>
                            <?= $opcTarifaInputs($c ?? [], $idx) ?>
                            <?= $calaixInputs($c ?? [], $idx) ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        };
        ?>

        <button type="button" id="add-campo" class="btn btn-secondary" style="margin-bottom:.8rem;">+ Afegir camp personalitzat</button>
        <div id="camps-fixos-list" class="sortable-list cf-sortable">
            <?php $cidx = 0; foreach ($ordenFull as $key): ?>
                <?php if (str_starts_with($key, 'campo_')): ?>
                    <?php $cId = (int) substr($key, 6); if (!isset($camposByIdEditor[$cId])) continue; ?>
                    <?php $renderCampoCard($cidx++, $camposByIdEditor[$cId]); ?>
                <?php else: $isFix = in_array($key, CamposFijos::FIXOS, true); $st = $isFix ? null : $cfState($key); ?>
                    <div class="cf-row card-item field-item" data-key="<?= e($key) ?>">
                        <span class="drag-handle" title="Arrossega per ordenar" aria-hidden="true">⠿</span>
                        <input type="hidden" name="campos_orden[]" value="<?= e($key) ?>">
                        <span class="cf-label"><?= e(CamposFijos::labelOf($key)) ?></span>
                        <?php if ($isFix): ?>
                            <span class="badge badge-muted">Sempre obligatori</span>
                        <?php else: ?>
                            <select name="campos_fijos[<?= e($key) ?>]" class="cf-select">
                                <option value="obligatori" <?= $st === 'obligatori' ? 'selected' : '' ?>>Obligatori</option>
                                <option value="opcional"   <?= $st === 'opcional'   ? 'selected' : '' ?>>Opcional</option>
                                <option value="ocult"      <?= $st === 'ocult'      ? 'selected' : '' ?>>Ocult</option>
                            </select>
                        <?php endif; ?>
                        <span class="item-tools">
                            <button type="button" class="btn-move move-up" title="Pujar" aria-label="Pujar">↑</button>
                            <button type="button" class="btn-move move-down" title="Baixar" aria-label="Baixar">↓</button>
                        </span>
                    </div>
                <?php endif; ?>
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

    <?php
    // ── Franges de temps + calaix de sortida (camp fix "franja_temps") ──
    // Una FILA = franja (text) + tarifa + calaix. Si una franja val per a
    // diverses tarifes, hi ha una fila per tarifa.
    $tarifesAmbId = array_values(array_filter($tarifas, fn($t) => !empty($t['id'])));

    // Aplanar la config desada en files [label, tarifa_id, calaix]
    $franjaFiles = [];
    foreach (\App\Models\Evento::franjasConfig($evento ?? []) as $f) {
        if (!empty($f['tarifes'])) {
            foreach ($f['tarifes'] as $tid => $cal) {
                $franjaFiles[] = ['label' => $f['label'], 'tarifa' => (int) $tid, 'calaix' => (int) $cal];
            }
        } else {
            // dada vella sense tarifa → fila sense tarifa (l'usuari l'haurà d'assignar)
            $franjaFiles[] = ['label' => $f['label'], 'tarifa' => 0, 'calaix' => (int) $f['calaix']];
        }
    }
    // Reprendre del POST si la validació ha fallat
    if (isset($old['franjas']) && is_array($old['franjas'])) {
        $franjaFiles = [];
        foreach ($old['franjas'] as $f) {
            if (!is_array($f)) continue;
            $lab = trim((string) ($f['label'] ?? ''));
            if ($lab === '') continue;
            $franjaFiles[] = ['label' => $lab, 'tarifa' => (int) ($f['tarifa'] ?? 0), 'calaix' => (int) ($f['calaix'] ?? 0)];
        }
    }

    $calaixOptions = function (int $sel): string {
        $h = '<option value="0">— Sense calaix —</option>';
        foreach (\App\Models\CampoPersonalizado::CALAIX_COLORS as $n => $meta) {
            $h .= '<option value="' . $n . '"' . ($sel === $n ? ' selected' : '') . '>' . e($meta['nom']) . '</option>';
        }
        return $h;
    };
    $tarifaOptions = function (int $sel) use ($tarifesAmbId): string {
        $h = '<option value="0">— Tria tarifa —</option>';
        foreach ($tarifesAmbId as $t) {
            $tid = (int) $t['id'];
            $h .= '<option value="' . $tid . '"' . ($sel === $tid ? ' selected' : '') . '>' . e((string) $t['nombre']) . '</option>';
        }
        return $h;
    };
    $calaixColor = fn(int $n): string => \App\Models\CampoPersonalizado::CALAIX_COLORS[$n]['color'] ?? 'transparent';
    ?>
    <fieldset>
        <legend>Franges de temps i calaixos de sortida</legend>
        <p class="muted" style="margin:0 0 1rem;">
            Cada línia és una <strong>franja de temps</strong> + la <strong>tarifa</strong> (distància) on apareix
            + el seu <strong>calaix de sortida</strong> (color). Si una franja val per a diverses distàncies
            (5K, 10K…), afegeix una línia per cada tarifa. A <strong>recollida</strong> es mostrarà el calaix
            segons la franja i la tarifa del corredor.
        </p>
        <?php if (count($tarifesAmbId) === 0): ?>
            <p class="muted">Desa primer les tarifes per poder assignar franges.</p>
        <?php endif; ?>
        <div class="cf-franjes" id="franjes-wrap">
            <div class="franja-head" style="font-weight:700;font-size:.85rem;color:#6b7280;margin-bottom:.4rem;">
                <span>Franja (text que veu el corredor)</span>
                <span>Tarifa</span>
                <span>Calaix</span>
                <span></span>
            </div>
            <div id="franjes-list">
                <?php foreach ($franjaFiles as $i => $f): ?>
                    <div class="franja-row">
                        <input type="text" name="franjas[<?= $i ?>][label]" value="<?= e($f['label']) ?>" placeholder="Ex: Menys de 40 min" maxlength="60">
                        <select name="franjas[<?= $i ?>][tarifa]" class="franja-tarifa-sel"><?= $tarifaOptions((int) $f['tarifa']) ?></select>
                        <span class="calaix-swatch" data-swatch style="background:<?= e($calaixColor((int) $f['calaix'])) ?>;"></span>
                        <select name="franjas[<?= $i ?>][calaix]" class="franja-calaix"><?= $calaixOptions((int) $f['calaix']) ?></select>
                        <button type="button" class="btn-link btn-danger franja-remove" title="Eliminar línia" aria-label="Eliminar">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add-franja" class="btn btn-secondary" style="margin-top:.4rem;">+ Afegir línia</button>
            <template id="franja-template">
                <div class="franja-row">
                    <input type="text" name="franjas[__IDX__][label]" value="" placeholder="Ex: Menys de 40 min" maxlength="60">
                    <select name="franjas[__IDX__][tarifa]" class="franja-tarifa-sel"><?= $tarifaOptions(0) ?></select>
                    <span class="calaix-swatch" data-swatch style="background:transparent;"></span>
                    <select name="franjas[__IDX__][calaix]" class="franja-calaix"><?= $calaixOptions(0) ?></select>
                    <button type="button" class="btn-link btn-danger franja-remove" title="Eliminar línia" aria-label="Eliminar">✕</button>
                </div>
            </template>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Desa els canvis' : 'Crea l\'esdeveniment' ?></button>
        <a href="<?= e(base_url('/admin/eventos')) ?>" class="btn">Cancel·la</a>
    </div>
</form>

<!-- Plantilla para nuevos campos (oculta) -->
<template id="campo-template">
    <?php $renderCampoCard('__IDX__', null); ?>
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
                <label>Preu (€)</label>
                <input type="text" name="tarifas[__IDX__][precio]" inputmode="decimal" placeholder="0.00 (gratis)">
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
        <div class="tarifa-tramos" style="margin-top:.6rem;">
            <label>Preus per trams <span class="muted">(opcional · inscripció anticipada)</span></label>
            <textarea name="tarifas[__IDX__][tramos_text]" rows="3" placeholder="2026-03-01 | 15&#10;2026-04-01 | 20&#10;| 25"></textarea>
            <small class="muted">Una línia per tram: <code>data límit | preu</code> (ex. <code>2026-03-01 | 15</code>). L'última sense data = preu final. Buit = s'usa el preu de dalt.</small>
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
<script>
// ── Calaix per opció: regenera els selectors quan canvien les opcions del camp ──
(function () {
    var CAL = [
        { v: 1, t: 'Calaix 1' }, { v: 2, t: 'Calaix 2' }, { v: 3, t: 'Calaix 3' }, { v: 4, t: 'Calaix 4' }
    ];
    function escAttr(s) { return String(s).replace(/"/g, '&quot;'); }

    function rebuild(optionsInput) {
        // idx del camp a partir del name campos[IDX][opciones]
        var m = optionsInput.name.match(/^campos\[([^\]]+)\]\[opciones\]$/);
        if (!m) return;
        var idx = m[1];
        var card = optionsInput.closest('.campo-row, .field-item');
        if (!card) return;
        var box = card.querySelector('.calaix-opts[data-idx="' + CSS.escape(idx) + '"]') || card.querySelector('.calaix-opts');
        if (!box) return;

        // Selecció prèvia (per preservar) a partir dels selects actuals
        var prev = {};
        box.querySelectorAll('select').forEach(function (s) {
            var mm = s.name.match(/\[calaix_map\]\[(.+)\]$/);
            if (mm) prev[mm[1]] = s.value;
        });

        var opts = optionsInput.value.split('|').map(function (o) { return o.trim(); }).filter(Boolean);
        if (opts.length === 0) { box.innerHTML = '<p class="muted" style="margin:0;">Primer escriu les opcions a dalt.</p>'; return; }

        var html = '';
        opts.forEach(function (opt) {
            var cur = prev[opt] || '0';
            var sel = '<select name="campos[' + idx + '][calaix_map][' + escAttr(opt) + ']"><option value="0">— Sense calaix —</option>';
            CAL.forEach(function (c) { sel += '<option value="' + c.v + '"' + (String(c.v) === String(cur) ? ' selected' : '') + '>' + c.t + '</option>'; });
            sel += '</select>';
            html += '<div class="calaix-opt-row"><span class="calaix-opt-name">' + (opt.replace(/</g, '&lt;')) + '</span>' + sel + '</div>';
        });
        box.innerHTML = html;
    }

    document.addEventListener('input', function (e) {
        var el = e.target;
        if (el && el.name && /^campos\[[^\]]+\]\[opciones\]$/.test(el.name)) rebuild(el);
    });

    // ── Franges de temps: afegir / eliminar + mostra de color del calaix ──
    (function () {
        var list = document.getElementById('franjes-list');
        var add = document.getElementById('add-franja');
        var tpl = document.getElementById('franja-template');
        if (!list || !add || !tpl) return;
        var COLORS = <?= json_encode(array_map(fn($m) => $m['color'], \App\Models\CampoPersonalizado::CALAIX_COLORS)) ?>;
        var seq = list.querySelectorAll('.franja-row').length;

        function updateSwatch(sel) {
            var row = sel.closest('.franja-row');
            if (!row) return;
            var sw = row.querySelector('[data-swatch]');
            if (sw) sw.style.background = COLORS[sel.value] || 'transparent';
        }
        add.addEventListener('click', function () {
            var html = tpl.innerHTML.replace(/__IDX__/g, String(seq++));
            var wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            list.appendChild(wrap.firstElementChild);
        });
        list.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('.franja-remove') : null;
            if (b) { var row = b.closest('.franja-row'); if (row) row.remove(); }
        });
        list.addEventListener('change', function (e) {
            if (e.target && e.target.classList.contains('franja-calaix')) updateSwatch(e.target);
        });
    })();
    // En clonar un camp nou des de la plantilla, regenerar el seu mapa
    document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'add-campo') {
            setTimeout(function () {
                document.querySelectorAll('input[name$="[opciones]"]').forEach(function (inp) {
                    if (inp.name.match(/^campos\[/)) rebuild(inp);
                });
            }, 50);
        }
    });
})();
</script>
