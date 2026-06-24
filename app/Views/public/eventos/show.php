<?php
/** @var array $evento */
/** @var list<array> $campos */
/** @var list<array> $tarifas */
/** @var bool $abierto */
/** @var int|null $plazasDisponibles */
/** @var array $old */
/** @var array $errors */
/** @var string|null $flashError */
/** @var bool $mostraAutofill */
use App\Core\Csrf;
use App\Models\CampoPersonalizado;
use App\Models\CamposFijos;
use App\Models\Inscrito;
use App\Services\ImageUploader;

$val = fn(string $k, string $d = ''): string => (string)($old[$k] ?? $d);
$err = fn(string $k): ?string => $errors[$k][0] ?? null;
$img = ImageUploader::publicUrl($evento['imagen_portada']);

// Consentiment RGPD obligatori (mateix per a individual i grup)
$privacidadUrl = \App\Models\Ajuste::get(\App\Models\Ajuste::PRIVACIDAD_URL);
$privacidadErr = $errors['acepta_privacidad'][0] ?? null;
$consentField = function () use ($privacidadUrl, $privacidadErr): void { ?>
    <div class="form-row consent-row">
        <label class="inline-check">
            <input type="checkbox" name="acepta_privacidad" value="1" required>
            <?= e(t('form.privacy.accept')) ?>
            <?php if (!empty($privacidadUrl)): ?>
                <a href="<?= e($privacidadUrl) ?>" target="_blank" rel="noopener"><?= e(t('form.privacy.link')) ?></a>
            <?php else: ?>
                <?= e(t('form.privacy.link')) ?>
            <?php endif; ?>
            <span class="req">*</span>
        </label>
        <?php if ($privacidadErr): ?><div class="field-error"><?= e($privacidadErr) ?></div><?php endif; ?>
    </div>
<?php };

// Configuració dels camps fixos d'aquest esdeveniment
$cf      = CamposFijos::resolve($evento['campos_fijos'] ?? null);
$cfVis   = fn(string $k): bool => CamposFijos::visible($cf, $k);
$cfReq   = fn(string $k): bool => CamposFijos::requerido($cf, $k);
$reqMark = fn(string $k): string => CamposFijos::requerido($cf, $k) ? ' <span class="req">*</span>' : '';
$reqAttr = fn(string $k): string => CamposFijos::requerido($cf, $k) ? 'required' : '';

// Inscripció de grup: si l'esdeveniment permet >1 participant per inscripció
$maxPart = (int) ($evento['max_participantes'] ?? 1);
$multi   = $maxPart >= 2;
// Ordre COMPLET dels camps del formulari (estàndard + personalitzats) + índex per id
$ordenFull  = CamposFijos::ordenComplet($evento['campos_orden'] ?? null, $campos);
$camposById = [];
foreach ($campos as $cc) $camposById[(int) $cc['id']] = $cc;
?>

<section class="container">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(base_url('/')) ?>"><?= e(t('event.breadcrumb_home')) ?></a>
        <span class="sep">›</span>
        <a href="<?= e(base_url('/')) ?>"><?= e(t('event.breadcrumb_events')) ?></a>
        <span class="sep">›</span>
        <span class="current"><?= e($evento['titulo']) ?></span>
    </nav>

    <header class="evt-header">
        <h1 class="evt-title"><?= e($evento['titulo']) ?></h1>
        <div class="evt-meta">
            <span class="evt-meta-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <?= e(format_date_ca((string)$evento['fecha_evento'], true)) ?>
            </span>
        </div>
    </header>

    <!-- Capçalera tipus RockTheSport: poster + panel d'informació ── -->
    <div class="evt-grid">
        <?php if ($img): ?>
            <div class="evt-poster" style="background-image:url('<?= e($img) ?>')"></div>
        <?php else: ?>
            <div class="evt-poster-placeholder"></div>
        <?php endif; ?>

        <div class="evt-info">
            <ul class="evt-facts">
                <li class="evt-fact">
                    <span class="evt-fact-ic" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </span>
                    <span class="evt-fact-txt">
                        <span class="evt-fact-label"><?= current_locale() === 'es' ? 'Fecha' : 'Data' ?></span>
                        <span class="evt-fact-val"><?= e(format_date_ca((string)$evento['fecha_evento'], true)) ?></span>
                    </span>
                </li>

                <?php if (!empty($evento['localizacion'])): ?>
                <li class="evt-fact">
                    <span class="evt-fact-ic" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    </span>
                    <span class="evt-fact-txt">
                        <span class="evt-fact-label"><?= e(t('event.location_label')) ?></span>
                        <span class="evt-fact-val"><a href="https://www.google.com/maps/search/?api=1&query=<?= e(urlencode((string)$evento['localizacion'])) ?>" target="_blank" rel="noopener"><?= e($evento['localizacion']) ?></a></span>
                    </span>
                </li>
                <?php endif; ?>

                <?php if (!empty($evento['fecha_limite_inscripcion'])): ?>
                <li class="evt-fact">
                    <span class="evt-fact-ic" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><polyline points="12 7 12 12 15 14"></polyline></svg>
                    </span>
                    <span class="evt-fact-txt">
                        <span class="evt-fact-label"><?= e(t('event.dates_label')) ?></span>
                        <span class="evt-fact-val"><?= e(format_date_ca((string)$evento['fecha_limite_inscripcion'])) ?></span>
                    </span>
                </li>
                <?php endif; ?>

                <?php if ($plazasDisponibles !== null): ?>
                <li class="evt-fact">
                    <span class="evt-fact-ic" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"></path><line x1="12" y1="5" x2="12" y2="19" stroke-dasharray="2 3"></line></svg>
                    </span>
                    <span class="evt-fact-txt">
                        <span class="evt-fact-label"><?= e(t('event.capacity_label')) ?></span>
                        <span class="evt-fact-val"><?= e(t('event.capacity_value', ['n' => (int)$plazasDisponibles])) ?></span>
                    </span>
                </li>
                <?php endif; ?>
            </ul>

            <div class="evt-links">
                <?php if (!empty($evento['reglamento_url'])): ?>
                    <a class="btn btn-secondary" href="<?= e($evento['reglamento_url']) ?>" target="_blank" rel="noopener noreferrer">📋 <?= e(t('event.regulations')) ?></a>
                <?php endif; ?>
                <?php if (!empty($evento['web_oficial_url'])): ?>
                    <a class="btn btn-secondary" href="<?= e($evento['web_oficial_url']) ?>" target="_blank" rel="noopener noreferrer">🌐 <?= e(t('event.official_web')) ?></a>
                <?php endif; ?>
                <a class="btn btn-secondary" href="<?= e(base_url('/comprovant?e=' . urlencode((string)$evento['slug']))) ?>">📄 <?= e(t('recover.link')) ?></a>
            </div>
        </div>
    </div>

    <?php if ($flashError): ?>
        <div class="alert alert-error"><?= e($flashError) ?></div>
    <?php endif; ?>

    <?php if (!empty($mostraAutofill)): ?>
        <div class="alert alert-info" style="display:flex;align-items:center;gap:1rem;justify-content:space-between;flex-wrap:wrap;">
            <span><strong><?= e(t('form.test.banner')) ?></strong> — <?= e(t('form.test.banner_desc')) ?></span>
            <button type="button" id="btn-autofill" class="btn btn-secondary" style="margin:0;"><?= e(t('form.test.fill')) ?></button>
        </div>
    <?php endif; ?>


    <?php if (!$abierto): ?>
        <div class="panel">
            <h2 class="panel-title"><?= e(t('event.closed_title')) ?></h2>
            <p class="muted" style="margin:0;">
                <?php if (count($tarifas) === 0): ?>
                    <?= e(t('event.closed_no_tarifa')) ?>
                <?php elseif ($plazasDisponibles !== null && $plazasDisponibles <= 0): ?>
                    <?= e(t('event.closed_full')) ?>
                <?php else: ?>
                    <?= e(t('event.closed_generic')) ?>
                <?php endif; ?>
            </p>
        </div>
    <?php elseif (!$multi): ?>

    <form id="formulari" method="post" action="<?= e(base_url('/eventos/' . $evento['slug'] . '/inscriure')) ?>" class="form-publico" novalidate>
        <?= Csrf::field() ?>

        <!-- Anti-bot honeypot: ha de quedar buit; els humans no el veuen -->
        <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;">
            <label for="website">No omplir aquest camp</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
        </div>

        <div class="panel">
            <h2 class="panel-title"><?= e(t('form.tarifa.title')) ?></h2>
            <div class="form-row" style="margin:0;">
                <label for="tarifa_id"><?= e(t('form.tarifa.label')) ?> <span class="req">*</span></label>
                <select id="tarifa_id" name="tarifa_id" required>
                    <option value=""><?= e(t('form.tarifa.placeholder')) ?></option>
                    <?php foreach ($tarifas as $t): $ag = !empty($t['_agotada']);
                        $nMin = isset($t['anio_nac_min']) && $t['anio_nac_min'] !== null ? (int)$t['anio_nac_min'] : null;
                        $nMax = isset($t['anio_nac_max']) && $t['anio_nac_max'] !== null ? (int)$t['anio_nac_max'] : null;
                        $nacHint = '';
                        if ($nMin !== null && $nMax !== null)      $nacHint = t('form.tarifa.nac_hint_between', ['min' => $nMin, 'max' => $nMax]);
                        elseif ($nMin !== null)                    $nacHint = t('form.tarifa.nac_hint_from',  ['min' => $nMin]);
                        elseif ($nMax !== null)                    $nacHint = t('form.tarifa.nac_hint_until', ['max' => $nMax]);
                    ?>
                        <option value="<?= (int)$t['id'] ?>"
                                data-nac-min="<?= $nMin !== null ? $nMin : '' ?>"
                                data-nac-max="<?= $nMax !== null ? $nMax : '' ?>"
                                <?= $ag ? 'disabled' : '' ?>
                                <?= (!$ag && (int)$val('tarifa_id') === (int)$t['id']) ? 'selected' : '' ?>>
                            <?= e($t['nombre']) ?> · <?= e(format_price((float)($t['precio_actual'] ?? $t['precio']))) ?><?php if (!empty($t['descripcion'])): ?> — <?= e($t['descripcion']) ?><?php endif; ?><?php
                                if ($nacHint !== '') echo ' · ' . e($nacHint);
                                if ($ag) {
                                    echo ' — ' . e(t('form.tarifa.soldout'));
                                } elseif (isset($t['_plazas']) && $t['_plazas'] !== null && $t['_plazas'] <= 10) {
                                    echo ' · ' . e(t('form.tarifa.left', ['n' => (int)$t['_plazas']]));
                                }
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($err('tarifa_id')): ?><div class="field-error"><?= e($err('tarifa_id')) ?></div><?php endif; ?>
                <div id="tarifa-age-msg" class="field-error" style="display:none;margin-top:.4rem;"></div>
            </div>
        </div>

        <div class="panel">
            <fieldset>
                <legend><?= e(t('form.personal.title')) ?></legend>

                <?php
                // Camps estàndard en l'ordre configurat ($orden). Email + repetir van junts.
                $field = function (string $key) use ($val, $err, $cfVis, $reqMark, $reqAttr, $cfReq): void {
                    if (!in_array($key, CamposFijos::FIXOS, true) && !$cfVis($key)) return;
                    switch ($key):
                        case 'nombre': ?>
                            <div class="form-row">
                                <label for="nombre"><?= e(t('form.label.name')) ?> <span class="req">*</span></label>
                                <input type="text" id="nombre" name="nombre" required maxlength="100" autocomplete="given-name" value="<?= e($val('nombre')) ?>">
                                <?php if ($err('nombre')): ?><div class="field-error"><?= e($err('nombre')) ?></div><?php endif; ?>
                            </div>
                        <?php break;
                        case 'apellido': ?>
                            <div class="form-row">
                                <label for="apellido"><?= e(t('form.label.surname')) ?><?= $reqMark('apellido') ?></label>
                                <input type="text" id="apellido" name="apellido" <?= $reqAttr('apellido') ?> maxlength="150" autocomplete="family-name" value="<?= e($val('apellido')) ?>">
                                <?php if ($err('apellido')): ?><div class="field-error"><?= e($err('apellido')) ?></div><?php endif; ?>
                            </div>
                        <?php break;
                        case 'email': ?>
                            <div class="form-row-pair">
                                <div class="form-row">
                                    <label for="email"><?= e(t('form.label.email')) ?> <span class="req">*</span></label>
                                    <input type="email" id="email" name="email" required maxlength="255" autocomplete="email" value="<?= e($val('email')) ?>">
                                    <?php if ($err('email')): ?><div class="field-error"><?= e($err('email')) ?></div><?php endif; ?>
                                </div>
                                <div class="form-row">
                                    <label for="email_confirm"><?= e(t('form.label.email_confirm')) ?> <span class="req">*</span></label>
                                    <input type="email" id="email_confirm" name="email_confirm" required maxlength="255" autocomplete="off" onpaste="return false;" ondrop="return false;" value="<?= e($val('email_confirm')) ?>">
                                    <?php if ($err('email_confirm')): ?><div class="field-error"><?= e($err('email_confirm')) ?></div><?php endif; ?>
                                </div>
                            </div>
                        <?php break;
                        case 'dni': ?>
                            <div class="form-row">
                                <label for="dni"><?= e(t('form.label.dni')) ?><?= $reqMark('dni') ?></label>
                                <input type="text" id="dni" name="dni" <?= $reqAttr('dni') ?> maxlength="20" placeholder="12345678A" value="<?= e(strtoupper($val('dni'))) ?>">
                                <?php if ($err('dni')): ?><div class="field-error"><?= e($err('dni')) ?></div><?php endif; ?>
                            </div>
                        <?php break;
                        case 'fecha_nacimiento': ?>
                            <div class="form-row">
                                <label for="fecha_nacimiento"><?= e(t('form.label.birth_date')) ?><?= $reqMark('fecha_nacimiento') ?></label>
                                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" <?= $reqAttr('fecha_nacimiento') ?> value="<?= e($val('fecha_nacimiento')) ?>">
                                <?php if ($err('fecha_nacimiento')): ?><div class="field-error"><?= e($err('fecha_nacimiento')) ?></div><?php endif; ?>
                            </div>
                        <?php break;
                        case 'sexo': ?>
                            <div class="form-row">
                                <label for="sexo"><?= e(t('form.label.sex')) ?><?= $reqMark('sexo') ?></label>
                                <select id="sexo" name="sexo" <?= $reqAttr('sexo') ?>>
                                    <option value=""><?= e(t('form.label.sex.choose')) ?></option>
                                    <option value="H" <?= $val('sexo') === 'H' ? 'selected' : '' ?>><?= e(t('form.label.sex.male')) ?></option>
                                    <option value="M" <?= $val('sexo') === 'M' ? 'selected' : '' ?>><?= e(t('form.label.sex.female')) ?></option>
                                    <option value="NB" <?= $val('sexo') === 'NB' ? 'selected' : '' ?>><?= e(t('form.label.sex.nonbinary')) ?></option>
                                </select>
                                <?php if ($err('sexo')): ?><div class="field-error"><?= e($err('sexo')) ?></div><?php endif; ?>
                            </div>
                        <?php break;
                        case 'talla_camiseta': ?>
                            <div class="form-row">
                                <label for="talla_camiseta"><?= e(t('form.label.shirt')) ?><?= $reqMark('talla_camiseta') ?></label>
                                <select id="talla_camiseta" name="talla_camiseta" <?= $reqAttr('talla_camiseta') ?>>
                                    <option value=""><?= e(t('form.label.shirt.none')) ?></option>
                                    <?php foreach (Inscrito::TALLAS as $t): ?>
                                        <option value="<?= e($t) ?>" <?= $val('talla_camiseta') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($err('talla_camiseta')): ?><div class="field-error"><?= e($err('talla_camiseta')) ?></div><?php endif; ?>
                            </div>
                        <?php break;
                        case 'telefono': ?>
                            <div class="form-row">
                                <label for="telefono"><?= e(t('form.label.phone')) ?><?= $reqMark('telefono') ?></label>
                                <input type="tel" id="telefono" name="telefono" <?= $reqAttr('telefono') ?> maxlength="15" autocomplete="tel" placeholder="600123456" value="<?= e($val('telefono')) ?>">
                                <?php if ($err('telefono')): ?><div class="field-error"><?= e($err('telefono')) ?></div><?php endif; ?>
                            </div>
                        <?php break;
                        case 'poblacion': ?>
                            <div class="form-row">
                                <label for="poblacion"><?= e(t('form.label.city')) ?><?= $reqMark('poblacion') ?></label>
                                <input type="text" id="poblacion" name="poblacion" <?= $reqAttr('poblacion') ?> maxlength="120" autocomplete="address-level2" value="<?= e($val('poblacion')) ?>">
                            </div>
                        <?php break;
                        case 'codigo_postal': ?>
                            <div class="form-row">
                                <label for="codigo_postal"><?= e(t('form.label.postal_code')) ?><?= $reqMark('codigo_postal') ?></label>
                                <input type="text" id="codigo_postal" name="codigo_postal" <?= $reqAttr('codigo_postal') ?> maxlength="10" autocomplete="postal-code" placeholder="08001" value="<?= e($val('codigo_postal')) ?>">
                                <?php if ($err('codigo_postal')): ?><div class="field-error"><?= e($err('codigo_postal')) ?></div><?php endif; ?>
                            </div>
                        <?php break;
                        case 'club': ?>
                            <div class="form-row">
                                <label for="club"><?= e(t('form.label.club')) ?><?= $cfReq('club') ? $reqMark('club') : ' (' . e(t('common.optional')) . ')' ?></label>
                                <input type="text" id="club" name="club" <?= $reqAttr('club') ?> maxlength="150" value="<?= e($val('club')) ?>">
                            </div>
                        <?php break;
                    endswitch;
                };
                ?>
                <?php
                // Camp personalitzat (reutilitzable: pot anar ABANS o DESPRÉS dels estàndard)
                $customField = function (array $c) use ($val, $err): void {
                    $key  = 'campo_' . (int) $c['id'];
                    $errC = $err($key);
                    $valC = $val($key);
                    $opts = CampoPersonalizado::opcionesFromJson($c['opciones'] ?? null);
                    $req  = (int) $c['requerido'] === 1;
                    ?>
                    <div class="form-row<?= $c['tipo'] === 'textarea' ? ' form-row-wide' : '' ?>" data-camp-tarifes="<?= e(implode(',', CampoPersonalizado::tarifasDeCampo($c))) ?>">
                        <label><?= e($c['etiqueta']) ?><?= $req ? ' <span class="req">*</span>' : '' ?></label>
                        <?php if ($c['tipo'] === 'textarea'): ?>
                            <textarea name="<?= e($key) ?>" rows="3" <?= $req ? 'required' : '' ?>><?= e($valC) ?></textarea>
                        <?php elseif ($c['tipo'] === 'select'): ?>
                            <select name="<?= e($key) ?>" data-camp-id="<?= (int)$c['id'] ?>" <?= $req ? 'required' : '' ?>>
                                <option value="">— Tria —</option>
                                <?php foreach ($opts as $o): ?>
                                    <option value="<?= e($o) ?>" <?= $valC === $o ? 'selected' : '' ?>><?= e($o) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($c['tipo'] === 'radio'): ?>
                            <div class="radio-group">
                                <?php foreach ($opts as $o): ?>
                                    <label class="inline-check"><input type="radio" name="<?= e($key) ?>" value="<?= e($o) ?>" <?= $valC === $o ? 'checked' : '' ?> <?= $req ? 'required' : '' ?>> <?= e($o) ?></label>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($c['tipo'] === 'checkbox' && count($opts) > 0): ?>
                            <div class="radio-group">
                                <?php foreach ($opts as $o): ?>
                                    <label class="inline-check"><input type="checkbox" name="<?= e($key) ?>[]" value="<?= e($o) ?>"> <?= e($o) ?></label>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($c['tipo'] === 'checkbox'): ?>
                            <label class="inline-check"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= $valC === '1' ? 'checked' : '' ?> <?= $req ? 'required' : '' ?>> <?= e($c['etiqueta']) ?></label>
                        <?php else: ?>
                            <input type="<?= e($c['tipo']) ?>" name="<?= e($key) ?>" value="<?= e($valC) ?>" <?= $req ? 'required' : '' ?> <?= !empty($c['placeholder']) ? 'placeholder="' . e($c['placeholder']) . '"' : '' ?>>
                        <?php endif; ?>
                        <?php if (!empty($c['ayuda'])): ?><small class="muted"><?= e($c['ayuda']) ?></small><?php endif; ?>
                        <?php if ($errC): ?><div class="field-error"><?= e($errC) ?></div><?php endif; ?>
                    </div>
                    <?php
                };
                ?>
                <div class="form-fields-grid">
                    <?php foreach ($ordenFull as $key):
                        if (str_starts_with($key, 'campo_')) {
                            $cid = (int) substr($key, 6);
                            if (isset($camposById[$cid])) $customField($camposById[$cid]);
                        } else {
                            $field($key);
                        }
                    endforeach; ?>
                </div>
            </fieldset>
        </div>

        <!-- ── Codi de descompte (opcional) ── -->
        <div class="panel descompte-panel">
            <details>
                <summary><strong><?= e(t('form.label.discount_question')) ?></strong></summary>
                <div class="form-row" style="margin-top:1rem;">
                    <label for="descuento_codigo"><?= e(t('form.label.discount')) ?></label>
                    <input type="text" id="descuento_codigo" name="descuento_codigo"
                           maxlength="40" placeholder="EX: EARLY-A4F2X9B1"
                           style="text-transform:uppercase;"
                           value="<?= e(strtoupper($val('descuento_codigo'))) ?>">
                    <small class="muted"><?= e(t('form.label.discount.hint')) ?></small>
                </div>
            </details>
        </div>

        <?php $consentField(); ?>
        <button type="submit" class="btn btn-primary btn-block btn-large"><?= e(t('form.submit')) ?></button>
        <p class="form-note"><?= e(t('form.submit.note')) ?></p>
    </form>

    <?php else: /* ───────── Formulari de GRUP (max_participantes >= 2) ───────── */ ?>
    <?php
    $pold = is_array($old['participants'] ?? null) ? array_values($old['participants']) : [];
    $cval = fn(string $k, string $d = ''): string => (string) ($old['contacto'][$k] ?? $d);
    $cerr = fn(string $k): ?string => $errors["contacto.$k"][0] ?? null;

    // Renderitza UN participant. $i és int per als reals, '__I__' per a la plantilla.
    $renderParticipant = function ($i, array $pd = []) use ($cf, $cfVis, $campos, $tarifas, $errors, $ordenFull, $camposById): void {
        $isTpl = !is_int($i);
        $nm  = fn(string $f): string => 'participants[' . $i . '][' . $f . ']';
        $idf = fn(string $f): string => 'p_' . $i . '_' . $f;
        $pv  = fn(string $f, string $d = ''): string => (string) ($pd[$f] ?? $d);
        $pe  = fn(string $f): ?string => (!$isTpl && isset($errors["participants.$i.$f"])) ? (string) $errors["participants.$i.$f"][0] : null;
        $rm  = fn(string $k): string => CamposFijos::requerido($cf, $k) ? ' <span class="req">*</span>' : '';
        $ra  = fn(string $k): string => CamposFijos::requerido($cf, $k) ? 'required' : '';
        ?>
        <div class="participant" data-participant>
            <div class="participant-head">
                <h3 class="participant-title"><?= e(t('group.participant', ['n' => ''])) ?> <span class="participant-num"></span></h3>
                <button type="button" class="btn-link participant-remove" data-remove>🗑 <?= e(t('group.remove')) ?></button>
            </div>

            <div class="form-row">
                <label for="<?= e($idf('tarifa_id')) ?>"><?= e(t('form.tarifa.label')) ?> <span class="req">*</span></label>
                <select id="<?= e($idf('tarifa_id')) ?>" name="<?= e($nm('tarifa_id')) ?>" class="p-tarifa" required>
                    <option value="" data-precio="0"><?= e(t('form.tarifa.placeholder')) ?></option>
                    <?php foreach ($tarifas as $t): $ag = !empty($t['_agotada']);
                        $nMin = isset($t['anio_nac_min']) && $t['anio_nac_min'] !== null ? (int) $t['anio_nac_min'] : null;
                        $nMax = isset($t['anio_nac_max']) && $t['anio_nac_max'] !== null ? (int) $t['anio_nac_max'] : null;
                        $nacHint = '';
                        if ($nMin !== null && $nMax !== null)      $nacHint = t('form.tarifa.nac_hint_between', ['min' => $nMin, 'max' => $nMax]);
                        elseif ($nMin !== null)                    $nacHint = t('form.tarifa.nac_hint_from',  ['min' => $nMin]);
                        elseif ($nMax !== null)                    $nacHint = t('form.tarifa.nac_hint_until', ['max' => $nMax]);
                    ?>
                        <option value="<?= (int) $t['id'] ?>"
                                data-precio="<?= (float) ($t['precio_actual'] ?? $t['precio']) ?>"
                                data-nac-min="<?= $nMin !== null ? $nMin : '' ?>"
                                data-nac-max="<?= $nMax !== null ? $nMax : '' ?>"
                                <?= $ag ? 'disabled' : '' ?>
                                <?= (!$ag && (int) $pv('tarifa_id') === (int) $t['id']) ? 'selected' : '' ?>>
                            <?= e($t['nombre']) ?> · <?= e(format_price((float) ($t['precio_actual'] ?? $t['precio']))) ?><?php if (!empty($t['descripcion'])): ?> — <?= e($t['descripcion']) ?><?php endif; ?><?php
                                if ($nacHint !== '') echo ' · ' . e($nacHint);
                                if ($ag) echo ' — ' . e(t('form.tarifa.soldout'));
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php $e = $pe('tarifa_id'); if ($e): ?><div class="field-error"><?= e($e) ?></div><?php endif; ?>
                <div class="tarifa-age-msg field-error" style="display:none;margin-top:.4rem;"></div>
            </div>

            <?php
            // Camps estàndard del participant en l'ordre configurat (email/telèfon són del contacte → fora)
            $pfield = function (string $key) use ($nm, $idf, $pv, $pe, $ra, $rm, $cfVis): void {
                if ($key === 'email' || $key === 'telefono') return;
                if ($key !== 'nombre' && !$cfVis($key)) return;
                switch ($key):
                    case 'nombre': ?>
                        <div class="form-row">
                            <label for="<?= e($idf('nombre')) ?>"><?= e(t('form.label.name')) ?> <span class="req">*</span></label>
                            <input type="text" id="<?= e($idf('nombre')) ?>" name="<?= e($nm('nombre')) ?>" required maxlength="100" value="<?= e($pv('nombre')) ?>">
                            <?php $e = $pe('nombre'); if ($e): ?><div class="field-error"><?= e($e) ?></div><?php endif; ?>
                        </div>
                    <?php break;
                    case 'apellido': ?>
                        <div class="form-row">
                            <label for="<?= e($idf('apellido')) ?>"><?= e(t('form.label.surname')) ?><?= $rm('apellido') ?></label>
                            <input type="text" id="<?= e($idf('apellido')) ?>" name="<?= e($nm('apellido')) ?>" <?= $ra('apellido') ?> maxlength="150" value="<?= e($pv('apellido')) ?>">
                            <?php $e = $pe('apellido'); if ($e): ?><div class="field-error"><?= e($e) ?></div><?php endif; ?>
                        </div>
                    <?php break;
                    case 'dni': ?>
                        <div class="form-row">
                            <label for="<?= e($idf('dni')) ?>"><?= e(t('form.label.dni')) ?><?= $rm('dni') ?></label>
                            <input type="text" id="<?= e($idf('dni')) ?>" name="<?= e($nm('dni')) ?>" <?= $ra('dni') ?> maxlength="20" placeholder="12345678A" value="<?= e(strtoupper($pv('dni'))) ?>">
                            <?php $e = $pe('dni'); if ($e): ?><div class="field-error"><?= e($e) ?></div><?php endif; ?>
                        </div>
                    <?php break;
                    case 'fecha_nacimiento': ?>
                        <div class="form-row">
                            <label for="<?= e($idf('fecha_nacimiento')) ?>"><?= e(t('form.label.birth_date')) ?><?= $rm('fecha_nacimiento') ?></label>
                            <input type="date" id="<?= e($idf('fecha_nacimiento')) ?>" name="<?= e($nm('fecha_nacimiento')) ?>" class="p-fnac" <?= $ra('fecha_nacimiento') ?> value="<?= e($pv('fecha_nacimiento')) ?>">
                            <?php $e = $pe('fecha_nacimiento'); if ($e): ?><div class="field-error"><?= e($e) ?></div><?php endif; ?>
                        </div>
                    <?php break;
                    case 'sexo': ?>
                        <div class="form-row">
                            <label for="<?= e($idf('sexo')) ?>"><?= e(t('form.label.sex')) ?><?= $rm('sexo') ?></label>
                            <select id="<?= e($idf('sexo')) ?>" name="<?= e($nm('sexo')) ?>" class="p-sexo" <?= $ra('sexo') ?>>
                                <option value=""><?= e(t('form.label.sex.choose')) ?></option>
                                <option value="H" <?= $pv('sexo') === 'H' ? 'selected' : '' ?>><?= e(t('form.label.sex.male')) ?></option>
                                <option value="M" <?= $pv('sexo') === 'M' ? 'selected' : '' ?>><?= e(t('form.label.sex.female')) ?></option>
                                <option value="NB" <?= $pv('sexo') === 'NB' ? 'selected' : '' ?>><?= e(t('form.label.sex.nonbinary')) ?></option>
                            </select>
                        </div>
                    <?php break;
                    case 'talla_camiseta': ?>
                        <div class="form-row">
                            <label for="<?= e($idf('talla_camiseta')) ?>"><?= e(t('form.label.shirt')) ?><?= $rm('talla_camiseta') ?></label>
                            <select id="<?= e($idf('talla_camiseta')) ?>" name="<?= e($nm('talla_camiseta')) ?>" class="p-talla" <?= $ra('talla_camiseta') ?>>
                                <option value=""><?= e(t('form.label.shirt.none')) ?></option>
                                <?php foreach (Inscrito::TALLAS as $tl): ?>
                                    <option value="<?= e($tl) ?>" <?= $pv('talla_camiseta') === $tl ? 'selected' : '' ?>><?= e($tl) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php $e = $pe('talla_camiseta'); if ($e): ?><div class="field-error"><?= e($e) ?></div><?php endif; ?>
                        </div>
                    <?php break;
                    case 'poblacion': ?>
                        <div class="form-row">
                            <label for="<?= e($idf('poblacion')) ?>"><?= e(t('form.label.city')) ?><?= $rm('poblacion') ?></label>
                            <input type="text" id="<?= e($idf('poblacion')) ?>" name="<?= e($nm('poblacion')) ?>" <?= $ra('poblacion') ?> maxlength="120" value="<?= e($pv('poblacion')) ?>">
                        </div>
                    <?php break;
                    case 'codigo_postal': ?>
                        <div class="form-row">
                            <label for="<?= e($idf('codigo_postal')) ?>"><?= e(t('form.label.postal_code')) ?><?= $rm('codigo_postal') ?></label>
                            <input type="text" id="<?= e($idf('codigo_postal')) ?>" name="<?= e($nm('codigo_postal')) ?>" <?= $ra('codigo_postal') ?> maxlength="10" placeholder="08001" value="<?= e($pv('codigo_postal')) ?>">
                            <?php $e = $pe('codigo_postal'); if ($e): ?><div class="field-error"><?= e($e) ?></div><?php endif; ?>
                        </div>
                    <?php break;
                    case 'club': ?>
                        <div class="form-row">
                            <label for="<?= e($idf('club')) ?>"><?= e(t('form.label.club')) ?></label>
                            <input type="text" id="<?= e($idf('club')) ?>" name="<?= e($nm('club')) ?>" <?= $ra('club') ?> maxlength="150" value="<?= e($pv('club')) ?>">
                        </div>
                    <?php break;
                endswitch;
            };
            // Camp personalitzat del participant (pot anar ABANS o DESPRÉS dels estàndard)
            $pCustomField = function (array $c) use ($nm, $pv, $pe): void {
                $ckey  = 'campo_' . (int) $c['id'];
                $copts = CampoPersonalizado::opcionesFromJson($c['opciones'] ?? null);
                $creq  = (int) $c['requerido'] === 1;
                $cvalC = $pv($ckey);
                ?>
                <div class="form-row<?= $c['tipo'] === 'textarea' ? ' form-row-wide' : '' ?>" data-camp-tarifes="<?= e(implode(',', CampoPersonalizado::tarifasDeCampo($c))) ?>">
                    <label><?= e($c['etiqueta']) ?><?= $creq ? ' <span class="req">*</span>' : '' ?></label>
                    <?php if ($c['tipo'] === 'textarea'): ?>
                        <textarea name="<?= e($nm($ckey)) ?>" rows="3" <?= $creq ? 'required' : '' ?>><?= e($cvalC) ?></textarea>
                    <?php elseif ($c['tipo'] === 'select'): ?>
                        <select name="<?= e($nm($ckey)) ?>" data-camp-id="<?= (int)$c['id'] ?>" <?= $creq ? 'required' : '' ?>>
                            <option value="">— Tria —</option>
                            <?php foreach ($copts as $o): ?>
                                <option value="<?= e($o) ?>" <?= $cvalC === $o ? 'selected' : '' ?>><?= e($o) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($c['tipo'] === 'radio'): ?>
                        <div class="radio-group">
                            <?php foreach ($copts as $o): ?>
                                <label class="inline-check"><input type="radio" name="<?= e($nm($ckey)) ?>" value="<?= e($o) ?>" <?= $cvalC === $o ? 'checked' : '' ?> <?= $creq ? 'required' : '' ?>> <?= e($o) ?></label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($c['tipo'] === 'checkbox' && count($copts) > 0): ?>
                        <div class="radio-group">
                            <?php foreach ($copts as $o): ?>
                                <label class="inline-check"><input type="checkbox" name="<?= e($nm($ckey)) ?>[]" value="<?= e($o) ?>"> <?= e($o) ?></label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($c['tipo'] === 'checkbox'): ?>
                        <label class="inline-check"><input type="checkbox" name="<?= e($nm($ckey)) ?>" value="1" <?= $cvalC === '1' ? 'checked' : '' ?> <?= $creq ? 'required' : '' ?>> <?= e($c['etiqueta']) ?></label>
                    <?php else: ?>
                        <input type="<?= e($c['tipo']) ?>" name="<?= e($nm($ckey)) ?>" value="<?= e($cvalC) ?>" <?= $creq ? 'required' : '' ?> <?= !empty($c['placeholder']) ? 'placeholder="' . e($c['placeholder']) . '"' : '' ?>>
                    <?php endif; ?>
                    <?php if (!empty($c['ayuda'])): ?><small class="muted"><?= e($c['ayuda']) ?></small><?php endif; ?>
                    <?php $e = $pe($ckey); if ($e): ?><div class="field-error"><?= e($e) ?></div><?php endif; ?>
                </div>
                <?php
            };
            ?>
            <div class="form-fields-grid">
                <?php foreach ($ordenFull as $key):
                    if (str_starts_with($key, 'campo_')) {
                        $cid = (int) substr($key, 6);
                        if (isset($camposById[$cid])) $pCustomField($camposById[$cid]);
                    } else {
                        $pfield($key);
                    }
                endforeach; ?>
            </div>

            <details class="participant-discount">
                <summary><?= e(t('form.label.discount_question')) ?></summary>
                <div class="form-row" style="margin-top:.6rem;margin-bottom:0;">
                    <input type="text" name="<?= e($nm('descuento_codigo')) ?>" maxlength="40" placeholder="EX: EARLY-A4F2X9B1" style="text-transform:uppercase;" value="<?= e(strtoupper($pv('descuento_codigo'))) ?>">
                    <?php $e = $pe('descuento_codigo'); if ($e): ?><div class="field-error"><?= e($e) ?></div><?php endif; ?>
                </div>
            </details>
        </div>
        <?php
    };

    $initial = count($pold) > 0 ? $pold : [[]];
    ?>

    <form id="formulari-grup" method="post" action="<?= e(base_url('/eventos/' . $evento['slug'] . '/inscriure')) ?>" class="form-publico" novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="grupo" value="1">

        <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;">
            <label for="website">No omplir aquest camp</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
        </div>

        <!-- ── Contacte ── -->
        <div class="panel">
            <h2 class="panel-title"><?= e(t('group.contact_title')) ?></h2>
            <p class="muted" style="margin-top:-.4rem;"><?= e(t('group.contact_desc')) ?></p>
            <div class="form-grid-2">
                <div class="form-row">
                    <label for="c_email"><?= e(t('form.label.email')) ?> <span class="req">*</span></label>
                    <input type="email" id="c_email" name="contacto[email]" required maxlength="255" autocomplete="email" value="<?= e($cval('email')) ?>">
                    <?php if ($cerr('email')): ?><div class="field-error"><?= e($cerr('email')) ?></div><?php endif; ?>
                </div>
                <div class="form-row">
                    <label for="c_email_confirm"><?= e(t('form.label.email_confirm')) ?> <span class="req">*</span></label>
                    <input type="email" id="c_email_confirm" name="contacto[email_confirm]" required maxlength="255" autocomplete="off" onpaste="return false;" ondrop="return false;" value="<?= e($cval('email_confirm')) ?>">
                    <?php if ($cerr('email_confirm')): ?><div class="field-error"><?= e($cerr('email_confirm')) ?></div><?php endif; ?>
                </div>
            </div>
            <?php if ($cfVis('telefono')): ?>
            <div class="form-grid-2">
                <div class="form-row">
                    <label for="c_telefono"><?= e(t('form.label.phone')) ?><?= $reqMark('telefono') ?></label>
                    <input type="tel" id="c_telefono" name="contacto[telefono]" <?= $reqAttr('telefono') ?> maxlength="15" autocomplete="tel" placeholder="600123456" value="<?= e($cval('telefono')) ?>">
                    <?php if ($cerr('telefono')): ?><div class="field-error"><?= e($cerr('telefono')) ?></div><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Participants ── -->
        <div class="panel">
            <h2 class="panel-title"><?= e(t('group.participants_title')) ?></h2>
            <div id="participants" data-max="<?= (int) $maxPart ?>">
                <?php foreach ($initial as $idx => $pd) $renderParticipant((int) $idx, is_array($pd) ? $pd : []); ?>
            </div>
            <button type="button" id="add-participant" class="btn btn-secondary">➕ <?= e(t('group.add')) ?> <span class="muted">(<span id="pcount"><?= count($initial) ?></span>/<?= (int) $maxPart ?>)</span></button>
        </div>

        <div class="panel group-total-panel">
            <div class="group-total"><span><?= e(t('group.total')) ?></span> <strong id="group-total-val">—</strong></div>
        </div>

        <?php $consentField(); ?>
        <button type="submit" class="btn btn-primary btn-block btn-large"><?= e(t('form.submit')) ?></button>
        <p class="form-note"><?= e(t('form.submit.note')) ?></p>
    </form>

    <template id="participant-tpl"><?php $renderParticipant('__I__'); ?></template>

    <script>
    (function () {
        var cont = document.getElementById('participants');
        var tpl  = document.getElementById('participant-tpl');
        var addBtn = document.getElementById('add-participant');
        var form = document.getElementById('formulari-grup');
        if (!cont || !tpl || !form) return;

        var MAX = parseInt(cont.getAttribute('data-max'), 10) || 1;
        var seq = cont.querySelectorAll('[data-participant]').length; // següent índex per a nous
        var T = {
            participant: <?= json_encode(t('group.participant', ['n' => '%N%'])) ?>,
            between: <?= json_encode(t('form.tarifa.nac_msg_between')) ?>,
            from:    <?= json_encode(t('form.tarifa.nac_msg_from')) ?>,
            until:   <?= json_encode(t('form.tarifa.nac_msg_until')) ?>,
            need:    <?= json_encode(t('form.tarifa.nac_msg_need')) ?>
        };

        function fmtPrice(n) {
            return n.toLocaleString('ca-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
        }
        function recalcTotal() {
            var tot = 0;
            cont.querySelectorAll('.p-tarifa').forEach(function (s) {
                var o = s.options[s.selectedIndex];
                if (o) tot += parseFloat(o.getAttribute('data-precio') || '0') || 0;
            });
            var el = document.getElementById('group-total-val');
            if (el) el.textContent = fmtPrice(tot);
        }
        function renumber() {
            var blocks = cont.querySelectorAll('[data-participant]');
            blocks.forEach(function (b, idx) {
                var num = b.querySelector('.participant-num');
                if (num) num.textContent = (idx + 1);
                var rm = b.querySelector('[data-remove]');
                if (rm) rm.style.display = blocks.length > 1 ? '' : 'none';
            });
            var pc = document.getElementById('pcount');
            if (pc) pc.textContent = blocks.length;
            addBtn.disabled = blocks.length >= MAX;
            addBtn.style.opacity = addBtn.disabled ? '.5' : '';
        }

        function birthYearOf(block) {
            var fn = block.querySelector('.p-fnac');
            if (!fn || !fn.value) return null;
            var m = String(fn.value).match(/^(\d{4})-\d{2}-\d{2}$/);
            return m ? parseInt(m[1], 10) : null;
        }
        function checkAge(block, onSubmit) {
            var sel = block.querySelector('.p-tarifa');
            var msg = block.querySelector('.tarifa-age-msg');
            if (!sel || !msg) return true;
            var opt = sel.options[sel.selectedIndex];
            var mn = opt ? opt.getAttribute('data-nac-min') : '';
            var mx = opt ? opt.getAttribute('data-nac-max') : '';
            var min = mn ? parseInt(mn, 10) : null;
            var max = mx ? parseInt(mx, 10) : null;
            if (min === null && max === null) { msg.style.display = 'none'; return true; }
            var by = birthYearOf(block);
            if (by === null) {
                if (onSubmit) { msg.textContent = T.need; msg.style.display = ''; return false; }
                msg.style.display = 'none'; return true;
            }
            var bad = (min !== null && by < min) || (max !== null && by > max);
            if (bad) {
                var tplStr = (min !== null && max !== null) ? T.between : (min !== null ? T.from : T.until);
                msg.textContent = tplStr.replace('{min}', min).replace('{max}', max);
                msg.style.display = '';
                return false;
            }
            msg.style.display = 'none'; return true;
        }

        function bind(block) {
            var sel = block.querySelector('.p-tarifa');
            var fn  = block.querySelector('.p-fnac');
            if (sel) sel.addEventListener('change', function () { checkAge(block, false); recalcTotal(); if (window.filterCampsByTarifa) window.filterCampsByTarifa(block, sel.value); });
            if (sel && window.filterCampsByTarifa) window.filterCampsByTarifa(block, sel.value);
            if (fn) { fn.addEventListener('change', function () { checkAge(block, false); }); fn.addEventListener('input', function () { checkAge(block, false); }); }
            var sx = block.querySelector('.p-sexo'), tll = block.querySelector('.p-talla');
            if (sx && tll && window.filterTallasBySexo) { window.filterTallasBySexo(sx, tll); sx.addEventListener('change', function () { window.filterTallasBySexo(sx, tll); }); }
            var rm = block.querySelector('[data-remove]');
            if (rm) rm.addEventListener('click', function () {
                if (cont.querySelectorAll('[data-participant]').length <= 1) return;
                block.remove(); renumber(); recalcTotal();
            });
        }

        cont.querySelectorAll('[data-participant]').forEach(bind);

        addBtn.addEventListener('click', function () {
            if (cont.querySelectorAll('[data-participant]').length >= MAX) return;
            var html = tpl.innerHTML.replace(/__I__/g, String(seq++));
            var wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            var block = wrap.firstElementChild;
            cont.appendChild(block);
            bind(block);
            renumber(); recalcTotal();
        });

        form.addEventListener('submit', function (ev) {
            var ok = true, first = null;
            cont.querySelectorAll('[data-participant]').forEach(function (b) {
                if (!checkAge(b, true) && !first) { ok = false; first = b; }
            });
            if (!ok) {
                ev.preventDefault();
                if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        renumber(); recalcTotal();
    })();
    </script>

    <?php endif; ?>

    <?php
    // ── Llista d'espera: visible si hi ha alguna tarifa esgotada o l'aforament és ple ──
    $hayAgotadas = false;
    foreach ($tarifas as $tt) { if (!empty($tt['_agotada'])) { $hayAgotadas = true; break; } }
    $plenes = ($plazasDisponibles !== null && $plazasDisponibles <= 0);
    $agotadasList = array_values(array_filter($tarifas, fn($t) => !empty($t['_agotada'])));
    if ($hayAgotadas || $plenes):
    ?>
    <div class="panel waitlist-panel" id="llista-espera">
        <h2 class="panel-title"><?= e(t('waitlist.title')) ?></h2>
        <p class="muted" style="margin-top:-.3rem;"><?= e(t('waitlist.desc')) ?></p>

        <?php if (!empty($esperaOk)): ?>
            <div class="alert alert-success"><?= e($esperaOk) ?></div>
        <?php endif; ?>
        <?php if (!empty($esperaError)): ?>
            <div class="alert alert-error"><?= e($esperaError) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url('/eventos/' . $evento['slug'] . '/llista-espera')) ?>" class="form-publico waitlist-form">
            <?= Csrf::field() ?>
            <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;">
                <label for="le_website">No omplir</label>
                <input type="text" id="le_website" name="website" tabindex="-1" autocomplete="off" value="">
            </div>
            <div class="form-grid-2">
                <div class="form-row">
                    <label for="le_nombre"><?= e(t('waitlist.name')) ?></label>
                    <input type="text" id="le_nombre" name="nombre" maxlength="150" value="">
                </div>
                <div class="form-row">
                    <label for="le_email"><?= e(t('waitlist.email')) ?> <span class="req">*</span></label>
                    <input type="email" id="le_email" name="email" required maxlength="255" autocomplete="email" value="">
                </div>
            </div>
            <?php if (count($agotadasList) > 1): ?>
            <div class="form-row">
                <label for="le_tarifa"><?= e(t('waitlist.tarifa')) ?></label>
                <select id="le_tarifa" name="tarifa_id">
                    <option value=""><?= e(t('waitlist.tarifa_any')) ?></option>
                    <?php foreach ($agotadasList as $t): ?>
                        <option value="<?= (int) $t['id'] ?>"><?= e($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary"><?= e(t('waitlist.submit')) ?></button>
        </form>
    </div>
    <?php endif; ?>
</section>

<script>
// Restricció d'any de naixement per tarifa (validació en viu + bloqueig a l'enviar).
(function () {
    var sel  = document.getElementById('tarifa_id');
    var fn   = document.getElementById('fecha_nacimiento');
    var msg  = document.getElementById('tarifa-age-msg');
    var form = document.getElementById('formulari');
    if (!sel || !msg) return;

    var M = {
        between: <?= json_encode(t('form.tarifa.nac_msg_between')) ?>,
        from:    <?= json_encode(t('form.tarifa.nac_msg_from')) ?>,
        until:   <?= json_encode(t('form.tarifa.nac_msg_until')) ?>,
        need:    <?= json_encode(t('form.tarifa.nac_msg_need')) ?>
    };

    function birthYear() {
        if (!fn || !fn.value) return null;
        var m = String(fn.value).match(/^(\d{4})-\d{2}-\d{2}$/);
        return m ? parseInt(m[1], 10) : null;
    }
    function selRange() {
        var opt = sel.options[sel.selectedIndex];
        var mn = opt ? opt.getAttribute('data-nac-min') : '';
        var mx = opt ? opt.getAttribute('data-nac-max') : '';
        return { min: mn ? parseInt(mn, 10) : null, max: mx ? parseInt(mx, 10) : null };
    }
    function show(text) { msg.textContent = text; msg.style.display = ''; }
    function hide() { msg.textContent = ''; msg.style.display = 'none'; }

    function check(onSubmit) {
        var r = selRange();
        if (r.min === null && r.max === null) { hide(); return true; }
        var by = birthYear();
        if (by === null) {
            if (onSubmit) { show(M.need); return false; }
            hide(); return true;
        }
        var bad = (r.min !== null && by < r.min) || (r.max !== null && by > r.max);
        if (bad) {
            var tpl = (r.min !== null && r.max !== null) ? M.between : (r.min !== null ? M.from : M.until);
            show(tpl.replace('{min}', r.min).replace('{max}', r.max));
            return false;
        }
        hide(); return true;
    }

    sel.addEventListener('change', function () { check(false); });
    if (fn) {
        fn.addEventListener('change', function () { check(false); });
        fn.addEventListener('input',  function () { check(false); });
    }
    if (form) {
        form.addEventListener('submit', function (e) {
            if (!check(true)) {
                e.preventDefault();
                msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }
})();
</script>

<?php if (!empty($mostraAutofill)): ?>
<script>
(function () {
    var btn = document.getElementById('btn-autofill');
    if (!btn) return;

    var noms = ['Joan', 'Marta', 'Pau', 'Laia', 'Marc', 'Anna', 'Pol', 'Júlia'];
    var cognoms = ['Garcia', 'Puig', 'Soler', 'Vila', 'Roca', 'Marti', 'Ferrer'];
    var dominis = ['example.com', 'test.cat', 'mailinator.com'];
    var ciutats = ['Barcelona', 'Girona', 'Lleida', 'Tarragona', 'Sabadell', 'Terrassa'];

    function pad2(n) { return String(n).padStart(2, '0'); }
    function pick(a) { return a[Math.floor(Math.random() * a.length)]; }
    function dniAleatori() {
        var n = Math.floor(10000000 + Math.random() * 89999999);
        return n + 'TRWAGMYFPDXBNJZSQVHLCKE'.charAt(n % 23);
    }
    function randTel() { return '6' + Math.floor(10000000 + Math.random() * 89999999); }
    function randDate() { return '1990-' + pad2(Math.floor(Math.random() * 12) + 1) + '-' + pad2(Math.floor(Math.random() * 28) + 1); }
    function randEmail(nom, cog) { return nom.toLowerCase() + '.' + cog.toLowerCase() + Math.floor(Math.random() * 999) + '@' + pick(dominis); }

    function setVal(el, val) { if (el && !el.value) el.value = val; }
    function setSelVal(el, val) {
        if (!el || el.tagName !== 'SELECT' || el.value) return;
        for (var i = 0; i < el.options.length; i++) {
            if (el.options[i].value === val && !el.options[i].disabled) { el.selectedIndex = i; return; }
        }
    }
    function setSelAny(el) {
        if (!el || el.tagName !== 'SELECT' || el.value) return;
        var idx = [];
        for (var i = 0; i < el.options.length; i++) {
            if (el.options[i].value && !el.options[i].disabled) idx.push(i);
        }
        if (idx.length) el.selectedIndex = pick(idx);
    }
    function fire(el, type) { if (el) el.dispatchEvent(new Event(type, { bubbles: true })); }
    // Camp dins d'un àmbit: accepta name="f" (individual) o name$="[f]" (grup)
    function q(scope, f) { return scope.querySelector('[name="' + f + '"], [name$="[' + f + ']"]'); }

    function fillCustom(scope) {
        scope.querySelectorAll('input[name^="campo_"], input[name*="[campo_"]').forEach(function (el) {
            if (el.type === 'radio' || el.type === 'checkbox') {
                if (!scope.querySelector('input[name="' + CSS.escape(el.name) + '"]:checked')) el.checked = true;
            } else if (!el.value) {
                el.value = el.type === 'number' ? '1' : (el.type === 'date' ? '2026-06-15' : 'Prova');
            }
        });
        scope.querySelectorAll('textarea[name^="campo_"], textarea[name*="[campo_"]').forEach(function (el) { if (!el.value) el.value = 'Text de prova'; });
        scope.querySelectorAll('select[name^="campo_"], select[name*="[campo_"]').forEach(function (el) { if (!el.value && el.options.length > 1) el.selectedIndex = 1; });
    }

    // Omple una persona dins d'un àmbit (individual: document; grup: un [data-participant])
    function fillPerson(scope) {
        var nom = pick(noms), cog = pick(cognoms);
        // Tarifa primer: dispara canvis (camps condicionals, total, talles per sexe...)
        var tar = q(scope, 'tarifa_id');
        if (tar) { setSelAny(tar); fire(tar, 'change'); }
        setVal(q(scope, 'nombre'), nom);
        setVal(q(scope, 'apellido'), cog + ' ' + pick(cognoms));
        setVal(q(scope, 'dni'), dniAleatori());
        setVal(q(scope, 'fecha_nacimiento'), randDate());
        var sx = q(scope, 'sexo');
        if (sx) { setSelVal(sx, pick(['H', 'M', 'NB'])); fire(sx, 'change'); }
        setSelAny(q(scope, 'talla_camiseta')); // després de sexo (opcions ja filtrades)
        setVal(q(scope, 'poblacion'), pick(ciutats));
        setVal(q(scope, 'codigo_postal'), '0' + Math.floor(8000 + Math.random() * 1000));
        setVal(q(scope, 'club'), 'Club Prova');
        fillCustom(scope);
        return { nom: nom, cog: cog };
    }

    btn.addEventListener('click', function () {
        var grup = document.getElementById('formulari-grup');
        if (grup) {
            // Contacte del grup
            var em = document.getElementById('c_email');
            if (em && !em.value) em.value = randEmail(pick(noms), pick(cognoms));
            var emc = document.getElementById('c_email_confirm');
            if (em && emc) emc.value = em.value;
            setVal(document.getElementById('c_telefono'), randTel());
            // Tots els participants presents
            grup.querySelectorAll('[data-participant]').forEach(function (block) { fillPerson(block); });
        } else {
            // Formulari individual
            var p = fillPerson(document);
            var em2 = document.getElementById('email');
            if (em2 && !em2.value) em2.value = randEmail(p.nom, p.cog);
            var emc2 = document.getElementById('email_confirm');
            if (em2 && emc2) emc2.value = em2.value;
            setVal(document.getElementById('telefono'), randTel());
        }
    });
})();
</script>
<?php endif; ?>

<script>
// Talla de samarreta segons el sexe (filtra les opcions disponibles)
(function () {
    window.IO_TALLAS_ALL  = <?= json_encode(array_values(\App\Models\Inscrito::TALLAS)) ?>;
    window.IO_TALLAS_SEXO = <?= json_encode(\App\Models\Inscrito::tallasSexoDecode($evento['tallas_sexo'] ?? null)) ?>;
    window.IO_SEXO_LABELS = <?= json_encode(['H' => t('form.label.sex.male'), 'M' => t('form.label.sex.female')], JSON_UNESCAPED_UNICODE) ?>;
    window.filterTallasBySexo = function (sexoEl, tallaEl) {
        if (!sexoEl || !tallaEl) return;
        var map = window.IO_TALLAS_SEXO || {};
        var sx = sexoEl.value;
        var hasSpecific = !!(map[sx] && map[sx].length);
        var allow = hasSpecific ? map[sx] : window.IO_TALLAS_ALL;
        // Si aquest sexe té talles pròpies configurades, etiqueta-les (p.ex. "M (Dona)")
        var suffix = (hasSpecific && window.IO_SEXO_LABELS[sx]) ? ' (' + window.IO_SEXO_LABELS[sx] + ')' : '';
        var cur = tallaEl.value;
        var fv = tallaEl.options.length ? tallaEl.options[0].value : '';
        var ft = tallaEl.options.length ? tallaEl.options[0].text : '—';
        tallaEl.innerHTML = '';
        var o0 = document.createElement('option'); o0.value = fv; o0.text = ft; tallaEl.appendChild(o0);
        allow.forEach(function (t) { var o = document.createElement('option'); o.value = t; o.text = t + suffix; if (t === cur) o.selected = true; tallaEl.appendChild(o); });
    };
    // ── Camps condicionals segons la tarifa (mostra/amaga + activa/desactiva) ──
    // Opcions d'un camp segons la tarifa: {campId: {def:[...], byTarifa:{tarifaId:[...]}}}
    window.IO_CAMP_OPTS = <?php
        $campOptsJs = [];
        foreach ($campos as $cc) {
            $por = CampoPersonalizado::opcionesPorTarifa($cc);
            if (!empty($por)) {
                $campOptsJs[(int) $cc['id']] = [
                    'def'      => CampoPersonalizado::opcionesFromJson($cc['opciones'] ?? null),
                    'byTarifa' => $por,
                ];
            }
        }
        echo json_encode($campOptsJs, JSON_UNESCAPED_UNICODE) ?: '{}';
    ?>;
    window.applyCampOptions = function (scope, tarifaVal) {
        if (!scope || !window.IO_CAMP_OPTS) return;
        scope.querySelectorAll('select[data-camp-id]').forEach(function (sel) {
            var cfg = window.IO_CAMP_OPTS[sel.getAttribute('data-camp-id')];
            if (!cfg) return;
            var opts = (cfg.byTarifa && cfg.byTarifa[String(tarifaVal)]) ? cfg.byTarifa[String(tarifaVal)] : (cfg.def || []);
            var cur = sel.value;
            var firstTxt = sel.options.length ? sel.options[0].text : '— Tria —';
            sel.innerHTML = '';
            var o0 = document.createElement('option'); o0.value = ''; o0.text = firstTxt; sel.appendChild(o0);
            opts.forEach(function (o) { var op = document.createElement('option'); op.value = o; op.text = o; if (o === cur) op.selected = true; sel.appendChild(op); });
        });
    };

    window.filterCampsByTarifa = function (scope, tarifaVal) {
        if (!scope) return;
        scope.querySelectorAll('[data-camp-tarifes]').forEach(function (row) {
            var t = row.getAttribute('data-camp-tarifes');
            if (!t) return; // buit = sempre visible
            var show = t.split(',').indexOf(String(tarifaVal)) !== -1;
            row.style.display = show ? '' : 'none';
            row.querySelectorAll('input, select, textarea').forEach(function (el) {
                if (show) { if (el.getAttribute('data-was-req') === '1') el.required = true; el.disabled = false; }
                else { if (el.required) { el.setAttribute('data-was-req', '1'); el.required = false; } el.disabled = true; }
            });
        });
        if (window.applyCampOptions) window.applyCampOptions(scope, tarifaVal);
    };

    // Formulari individual
    var s = document.getElementById('sexo'), tl = document.getElementById('talla_camiseta');
    if (s && tl) { window.filterTallasBySexo(s, tl); s.addEventListener('change', function () { window.filterTallasBySexo(s, tl); }); }
    var fform = document.getElementById('formulari'), tsel = document.getElementById('tarifa_id');
    if (fform && tsel) { window.filterCampsByTarifa(fform, tsel.value); tsel.addEventListener('change', function () { window.filterCampsByTarifa(fform, tsel.value); }); }

    // Participants inicials del formulari de grup
    document.querySelectorAll('#participants [data-participant]').forEach(function (block) {
        var sx = block.querySelector('.p-sexo'), tll = block.querySelector('.p-talla');
        if (sx && tll) { window.filterTallasBySexo(sx, tll); sx.addEventListener('change', function () { window.filterTallasBySexo(sx, tll); }); }
        var pt = block.querySelector('.p-tarifa');
        if (pt) window.filterCampsByTarifa(block, pt.value);
    });
})();
</script>
