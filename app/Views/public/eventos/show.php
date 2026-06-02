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
use App\Models\Inscrito;
use App\Services\ImageUploader;

$val = fn(string $k, string $d = ''): string => (string)($old[$k] ?? $d);
$err = fn(string $k): ?string => $errors[$k][0] ?? null;
$img = ImageUploader::publicUrl($evento['imagen_portada']);
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
            <h2><?= e(t('event.info_title')) ?></h2>

            <div class="evt-info-section">
                <div class="evt-info-label"><?= e(format_date_ca((string)$evento['fecha_evento'], true)) ?></div>
                <div class="evt-info-box">
                    <strong><?= e(t('event.event_label')) ?>:</strong> <?= e($evento['titulo']) ?>
                </div>
            </div>

            <?php if (!empty($evento['localizacion'])): ?>
                <div class="evt-info-section">
                    <div class="evt-info-label"><?= e(t('event.location_label')) ?></div>
                    <div class="evt-info-box">
                        <strong><?= e(t('event.location_label')) ?>:</strong>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?= e(urlencode((string)$evento['localizacion'])) ?>"
                           target="_blank" rel="noopener"><?= e($evento['localizacion']) ?></a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($evento['fecha_limite_inscripcion'])): ?>
                <div class="evt-info-section">
                    <div class="evt-info-label"><?= e(t('event.dates_label')) ?></div>
                    <div class="evt-info-box">
                        <strong><?= e(t('event.dates_end')) ?>:</strong> <?= e(format_date_ca((string)$evento['fecha_limite_inscripcion'])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($plazasDisponibles !== null): ?>
                <div class="evt-info-section">
                    <div class="evt-info-label"><?= e(t('event.capacity_label')) ?></div>
                    <div class="evt-info-box">
                        <?= e(t('event.capacity_value', ['n' => (int)$plazasDisponibles])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="evt-tags">
                <span class="evt-tag evt-tag-primary">Running</span>
                <span class="evt-tag evt-tag-muted"><?= current_locale() === 'es' ? 'Carrera popular' : 'Cursa popular' ?></span>
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

    <?php if (!empty($evento['descripcion'])): ?>
        <div class="panel">
            <h2 class="panel-title"><?= e(t('event.description_title')) ?></h2>
            <div class="event-description"><?= nl2br(e((string)$evento['descripcion'])) ?></div>
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
    <?php else: ?>

    <form id="formulari" method="post" action="<?= e(base_url('/eventos/' . $evento['slug'] . '/inscriure')) ?>" class="form-publico" novalidate>
        <?= Csrf::field() ?>

        <div class="panel">
            <h2 class="panel-title"><?= e(t('form.tarifa.title')) ?></h2>
            <div class="form-row" style="margin:0;">
                <label for="tarifa_id"><?= e(t('form.tarifa.label')) ?> <span class="req">*</span></label>
                <select id="tarifa_id" name="tarifa_id" required>
                    <option value=""><?= e(t('form.tarifa.placeholder')) ?></option>
                    <?php foreach ($tarifas as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= (int)$val('tarifa_id') === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= e($t['nombre']) ?> · <?= e(format_price((float)$t['precio'])) ?><?php if (!empty($t['descripcion'])): ?> — <?= e($t['descripcion']) ?><?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($err('tarifa_id')): ?><div class="field-error"><?= e($err('tarifa_id')) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <fieldset>
                <legend><?= e(t('form.personal.title')) ?></legend>

                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="nombre"><?= e(t('form.label.name')) ?> <span class="req">*</span></label>
                        <input type="text" id="nombre" name="nombre" required maxlength="100" autocomplete="given-name"
                               value="<?= e($val('nombre')) ?>">
                        <?php if ($err('nombre')): ?><div class="field-error"><?= e($err('nombre')) ?></div><?php endif; ?>
                    </div>
                    <div class="form-row">
                        <label for="apellido"><?= e(t('form.label.surname')) ?> <span class="req">*</span></label>
                        <input type="text" id="apellido" name="apellido" required maxlength="150" autocomplete="family-name"
                               value="<?= e($val('apellido')) ?>">
                        <?php if ($err('apellido')): ?><div class="field-error"><?= e($err('apellido')) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="dni"><?= e(t('form.label.dni')) ?> <span class="req">*</span></label>
                        <input type="text" id="dni" name="dni" required maxlength="20" placeholder="12345678A"
                               value="<?= e(strtoupper($val('dni'))) ?>">
                        <?php if ($err('dni')): ?><div class="field-error"><?= e($err('dni')) ?></div><?php endif; ?>
                    </div>
                    <div class="form-row">
                        <label for="fecha_nacimiento"><?= e(t('form.label.birth_date')) ?> <span class="req">*</span></label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required
                               value="<?= e($val('fecha_nacimiento')) ?>">
                        <?php if ($err('fecha_nacimiento')): ?><div class="field-error"><?= e($err('fecha_nacimiento')) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="email"><?= e(t('form.label.email')) ?> <span class="req">*</span></label>
                        <input type="email" id="email" name="email" required maxlength="255" autocomplete="email"
                               value="<?= e($val('email')) ?>">
                        <?php if ($err('email')): ?><div class="field-error"><?= e($err('email')) ?></div><?php endif; ?>
                    </div>
                    <div class="form-row">
                        <label for="telefono"><?= e(t('form.label.phone')) ?> <span class="req">*</span></label>
                        <input type="tel" id="telefono" name="telefono" required maxlength="15" autocomplete="tel"
                               placeholder="600123456" value="<?= e($val('telefono')) ?>">
                        <?php if ($err('telefono')): ?><div class="field-error"><?= e($err('telefono')) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="sexo"><?= e(t('form.label.sex')) ?> <span class="req">*</span></label>
                        <select id="sexo" name="sexo" required>
                            <option value=""><?= e(t('form.label.sex.choose')) ?></option>
                            <option value="H" <?= $val('sexo') === 'H' ? 'selected' : '' ?>><?= e(t('form.label.sex.male')) ?></option>
                            <option value="M" <?= $val('sexo') === 'M' ? 'selected' : '' ?>><?= e(t('form.label.sex.female')) ?></option>
                            <option value="NB" <?= $val('sexo') === 'NB' ? 'selected' : '' ?>><?= e(t('form.label.sex.nonbinary')) ?></option>
                        </select>
                        <?php if ($err('sexo')): ?><div class="field-error"><?= e($err('sexo')) ?></div><?php endif; ?>
                    </div>
                    <div class="form-row">
                        <label for="talla_camiseta"><?= e(t('form.label.shirt')) ?></label>
                        <select id="talla_camiseta" name="talla_camiseta">
                            <option value=""><?= e(t('form.label.shirt.none')) ?></option>
                            <?php foreach (Inscrito::TALLAS as $t): ?>
                                <option value="<?= e($t) ?>" <?= $val('talla_camiseta') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="poblacion"><?= e(t('form.label.city')) ?></label>
                        <input type="text" id="poblacion" name="poblacion" maxlength="120" autocomplete="address-level2"
                               value="<?= e($val('poblacion')) ?>">
                    </div>
                    <div class="form-row">
                        <label for="codigo_postal"><?= e(t('form.label.postal_code')) ?></label>
                        <input type="text" id="codigo_postal" name="codigo_postal" maxlength="10" autocomplete="postal-code"
                               placeholder="08001" value="<?= e($val('codigo_postal')) ?>">
                        <?php if ($err('codigo_postal')): ?><div class="field-error"><?= e($err('codigo_postal')) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <label for="club"><?= e(t('form.label.club')) ?> (<?= e(t('common.optional')) ?>)</label>
                    <input type="text" id="club" name="club" maxlength="150" value="<?= e($val('club')) ?>">
                </div>
            </fieldset>
        </div>

        <?php if (count($campos) > 0): ?>
            <div class="panel">
                <fieldset>
                    <legend><?= e(t('form.label.custom_fields')) ?></legend>
                    <?php foreach ($campos as $c):
                        $key = 'campo_' . (int)$c['id'];
                        $errC = $err($key);
                        $valC = $val($key);
                        $opts = CampoPersonalizado::opcionesFromJson($c['opciones'] ?? null);
                        $req = (int)$c['requerido'] === 1;
                    ?>
                    <div class="form-row">
                        <label><?= e($c['etiqueta']) ?><?= $req ? ' <span class="req">*</span>' : '' ?></label>

                        <?php if ($c['tipo'] === 'textarea'): ?>
                            <textarea name="<?= e($key) ?>" rows="3" <?= $req ? 'required' : '' ?>><?= e($valC) ?></textarea>

                        <?php elseif ($c['tipo'] === 'select'): ?>
                            <select name="<?= e($key) ?>" <?= $req ? 'required' : '' ?>>
                                <option value="">— Tria —</option>
                                <?php foreach ($opts as $o): ?>
                                    <option value="<?= e($o) ?>" <?= $valC === $o ? 'selected' : '' ?>><?= e($o) ?></option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($c['tipo'] === 'radio'): ?>
                            <div class="radio-group">
                                <?php foreach ($opts as $o): ?>
                                    <label class="inline-check">
                                        <input type="radio" name="<?= e($key) ?>" value="<?= e($o) ?>" <?= $valC === $o ? 'checked' : '' ?> <?= $req ? 'required' : '' ?>>
                                        <?= e($o) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif ($c['tipo'] === 'checkbox' && count($opts) > 0): ?>
                            <div class="radio-group">
                                <?php foreach ($opts as $o): ?>
                                    <label class="inline-check">
                                        <input type="checkbox" name="<?= e($key) ?>[]" value="<?= e($o) ?>">
                                        <?= e($o) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif ($c['tipo'] === 'checkbox'): ?>
                            <label class="inline-check">
                                <input type="checkbox" name="<?= e($key) ?>" value="1" <?= $valC === '1' ? 'checked' : '' ?> <?= $req ? 'required' : '' ?>>
                                <?= e($c['etiqueta']) ?>
                            </label>

                        <?php else: ?>
                            <input type="<?= e($c['tipo']) ?>" name="<?= e($key) ?>"
                                   value="<?= e($valC) ?>"
                                   <?= $req ? 'required' : '' ?>
                                   <?= !empty($c['placeholder']) ? 'placeholder="' . e($c['placeholder']) . '"' : '' ?>>
                        <?php endif; ?>

                        <?php if (!empty($c['ayuda'])): ?>
                            <small class="muted"><?= e($c['ayuda']) ?></small>
                        <?php endif; ?>
                        <?php if ($errC): ?><div class="field-error"><?= e($errC) ?></div><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </fieldset>
            </div>
        <?php endif; ?>

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

        <button type="submit" class="btn btn-primary btn-block btn-large"><?= e(t('form.submit')) ?></button>
        <p class="form-note"><?= e(t('form.submit.note')) ?></p>
    </form>

    <?php endif; ?>
</section>

<?php if (!empty($mostraAutofill)): ?>
<script>
(function () {
    var btn = document.getElementById('btn-autofill');
    if (!btn) return;

    function dniAleatori() {
        var num = Math.floor(10000000 + Math.random() * 89999999);
        var lletres = 'TRWAGMYFPDXBNJZSQVHLCKE';
        return num + lletres.charAt(num % 23);
    }
    function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }
    function fillIfEmpty(el, val) {
        if (!el) return;
        if (el.tagName === 'SELECT') {
            for (var i = 0; i < el.options.length; i++) {
                if (el.options[i].value === val) { el.value = val; break; }
            }
        } else if (el.type === 'checkbox' || el.type === 'radio') {
            el.checked = true;
        } else {
            el.value = val;
        }
    }

    btn.addEventListener('click', function () {
        var noms = ['Joan', 'Marta', 'Pau', 'Laia', 'Marc', 'Anna', 'Pol', 'Júlia'];
        var cognoms = ['Garcia', 'Puig', 'Soler', 'Vila', 'Roca', 'Marti', 'Ferrer'];
        var dominis = ['example.com', 'test.cat', 'mailinator.com'];

        var nom = pick(noms);
        var cog = pick(cognoms);

        fillIfEmpty(document.getElementById('nombre'), nom);
        fillIfEmpty(document.getElementById('apellido'), cog + ' ' + pick(cognoms));
        fillIfEmpty(document.getElementById('dni'), dniAleatori());
        fillIfEmpty(document.getElementById('fecha_nacimiento'), '1990-' +
            String(Math.floor(Math.random() * 12) + 1).padStart(2, '0') + '-' +
            String(Math.floor(Math.random() * 28) + 1).padStart(2, '0'));
        fillIfEmpty(document.getElementById('email'),
            nom.toLowerCase() + '.' + cog.toLowerCase() + Math.floor(Math.random() * 999) + '@' + pick(dominis));
        fillIfEmpty(document.getElementById('telefono'), '6' + Math.floor(10000000 + Math.random() * 89999999));
        fillIfEmpty(document.getElementById('sexo'), pick(['H', 'M', 'NB']));
        fillIfEmpty(document.getElementById('talla_camiseta'), pick(['S', 'M', 'L', 'XL']));
        fillIfEmpty(document.getElementById('poblacion'), pick(['Barcelona', 'Girona', 'Lleida', 'Tarragona', 'Sabadell', 'Terrassa']));
        fillIfEmpty(document.getElementById('codigo_postal'), '0' + Math.floor(8000 + Math.random() * 1000));
        fillIfEmpty(document.getElementById('club'), 'Club Prova');

        document.querySelectorAll('input[name^="campo_"]').forEach(function (el) {
            if (el.type === 'radio' || el.type === 'checkbox') {
                if (!document.querySelector('input[name="' + el.name + '"]:checked')) el.checked = true;
            } else if (!el.value) {
                el.value = el.type === 'number' ? '1' : (el.type === 'date' ? '2026-06-15' : 'Prova');
            }
        });
        document.querySelectorAll('textarea[name^="campo_"]').forEach(function (el) { if (!el.value) el.value = 'Text de prova'; });
        document.querySelectorAll('select[name^="campo_"]').forEach(function (el) { if (!el.value && el.options.length > 1) el.selectedIndex = 1; });

        var tarifaSel = document.getElementById('tarifa_id');
        if (tarifaSel && !tarifaSel.value && tarifaSel.options.length > 1) tarifaSel.selectedIndex = 1;
    });
})();
</script>
<?php endif; ?>
