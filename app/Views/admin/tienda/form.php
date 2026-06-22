<?php
/** @var array|null $producto */
/** @var list<array> $imagenes */
/** @var list<array> $atributos */
/** @var list<array> $variaciones */
/** @var array $flash */
use App\Core\Csrf;
use App\Services\ImageUploader;

$isEdit = $producto !== null;
$id     = $isEdit ? (int) $producto['id'] : 0;
$action = $isEdit ? base_url('/admin/tienda/' . $id) : base_url('/admin/tienda');
$tipo   = $isEdit ? (string) $producto['tipo'] : 'simple';
$val = fn(string $k, $d = '') => e((string) ($producto[$k] ?? $d));
?>
<section class="page-head with-action">
    <div>
        <h1><?= $isEdit ? 'Editar producte' : 'Nou producte' ?></h1>
        <p class="muted">Recollida en botiga · gestió de superadmin.</p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <?php if ($isEdit): ?>
            <a class="btn" href="<?= e(base_url('/tienda/' . $producto['slug'])) ?>" target="_blank" rel="noopener">👁️ Veure a la botiga</a>
        <?php endif; ?>
        <a class="btn" href="<?= e(base_url('/admin/tienda')) ?>">← Tornar</a>
    </div>
</section>

<?php if (!empty($flash['success'])): ?><div class="alert alert-success"><?= e($flash['success']) ?></div><?php endif; ?>
<?php if (!empty($flash['error'])): ?><div class="alert alert-error"><?= e($flash['error']) ?></div><?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="form-admin" id="producto-form">
    <?= Csrf::field() ?>

    <fieldset>
        <legend>Dades del producte</legend>
        <div class="form-row">
            <label for="nombre">Nom <span class="req">*</span></label>
            <input type="text" id="nombre" name="nombre" value="<?= $val('nombre') ?>" required maxlength="255">
        </div>
        <div class="form-row">
            <label for="descripcion">Descripció</label>
            <textarea id="descripcion" name="descripcion" rows="4"><?= $val('descripcion') ?></textarea>
        </div>

        <div class="form-grid-2">
            <div class="form-row">
                <label>Tipus de producte</label>
                <label class="inline-check"><input type="radio" name="tipo" value="simple" <?= $tipo === 'simple' ? 'checked' : '' ?>> Simple</label>
                <label class="inline-check"><input type="radio" name="tipo" value="variable" <?= $tipo === 'variable' ? 'checked' : '' ?>> Variable (colors, talles…)</label>
            </div>
            <div class="form-row">
                <label class="inline-check"><input type="checkbox" name="activo" value="1" <?= !$isEdit || (int)$producto['activo'] === 1 ? 'checked' : '' ?>> Actiu (visible a la botiga)</label>
                <label class="inline-check"><input type="checkbox" name="destacado" value="1" <?= $isEdit && !empty($producto['destacado']) ? 'checked' : '' ?>> Destacat</label>
            </div>
        </div>

        <!-- Producte SIMPLE: preu + stock -->
        <div class="form-grid-2 tipo-simple-only">
            <div class="form-row">
                <label for="precio">Preu (€) <span class="req">*</span></label>
                <input type="number" id="precio" name="precio" step="0.01" min="0" value="<?= $val('precio', '0') ?>">
            </div>
            <div class="form-row">
                <label for="stock">Stock <span class="muted">(buit = il·limitat)</span></label>
                <input type="number" id="stock" name="stock" min="0" value="<?= $isEdit && $producto['stock'] !== null ? (int)$producto['stock'] : '' ?>">
            </div>
        </div>
        <div class="form-row" style="max-width:200px;">
            <label for="orden">Ordre</label>
            <input type="number" id="orden" name="orden" value="<?= $val('orden', '0') ?>">
        </div>
    </fieldset>

    <!-- Producte VARIABLE: atributs + variacions -->
    <fieldset class="tipo-variable-only">
        <legend>Variants (producte variable)</legend>
        <p class="muted">Defineix els atributs (p. ex. Color, Talla) i després genera les combinacions amb el seu preu i stock.</p>

        <h4 style="margin:.6rem 0 .3rem;">Atributs</h4>
        <div id="atributos-list">
            <?php $arows = $atributos ?: [['nombre' => '', 'valores' => []]]; foreach ($arows as $i => $a): ?>
                <div class="attr-row" style="display:flex;gap:.6rem;margin-bottom:.5rem;align-items:flex-end;flex-wrap:wrap;">
                    <div style="flex:1;min-width:140px;"><label>Nom</label><input type="text" name="atributos[<?= $i ?>][nombre]" value="<?= e((string)$a['nombre']) ?>" placeholder="Color"></div>
                    <div style="flex:2;min-width:200px;"><label>Valors (separa amb |)</label><input type="text" name="atributos[<?= $i ?>][valores]" value="<?= e(implode(' | ', $a['valores'] ?? [])) ?>" placeholder="Roig | Blau | Negre"></div>
                    <button type="button" class="btn-small btn-danger attr-remove">✕</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-attr" class="btn btn-secondary" style="margin-bottom:1rem;">+ Afegir atribut</button>

        <h4 style="margin:.6rem 0 .3rem;">Variacions</h4>
        <button type="button" id="gen-var" class="btn btn-secondary" style="margin-bottom:.6rem;">⚙ Generar combinacions</button>
        <div class="table-wrap">
            <table class="data-table" id="var-table">
                <thead><tr><th>Combinació</th><th>Preu (€)</th><th>Stock</th><th>SKU</th><th></th></tr></thead>
                <tbody id="variaciones-body">
                    <?php foreach ($variaciones as $j => $v): ?>
                        <tr class="var-row">
                            <td><?= e($v['etiqueta']) ?><input type="hidden" name="variaciones[<?= $j ?>][combinacion]" value="<?= e(json_encode($v['combinacion'], JSON_UNESCAPED_UNICODE)) ?>"></td>
                            <td><input type="number" step="0.01" min="0" name="variaciones[<?= $j ?>][precio]" value="<?= e((string)$v['precio']) ?>" style="width:90px;"></td>
                            <td><input type="number" min="0" name="variaciones[<?= $j ?>][stock]" value="<?= $v['stock'] !== null ? (int)$v['stock'] : '' ?>" placeholder="∞" style="width:80px;"></td>
                            <td><input type="text" name="variaciones[<?= $j ?>][sku]" value="<?= e((string)($v['sku'] ?? '')) ?>" style="width:100px;"></td>
                            <td><button type="button" class="btn-small btn-danger var-remove">✕</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted small" id="var-empty" style="<?= count($variaciones) ? 'display:none;' : '' ?>">Encara no hi ha variacions. Defineix atributs i clica «Generar combinacions».</p>
    </fieldset>

    <fieldset>
        <legend>Imatges</legend>
        <?php if ($imagenes): ?>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.8rem;">
                <?php foreach ($imagenes as $img): ?>
                    <div style="position:relative;">
                        <img src="<?= e(ImageUploader::publicUrl($img['ruta'])) ?>" alt="" style="width:90px;height:90px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;">
                        <form method="post" action="<?= e(base_url('/admin/tienda/imatge/' . (int)$img['id'] . '/eliminar')) ?>" onsubmit="return confirm('Eliminar aquesta imatge?');" style="position:absolute;top:-8px;right:-8px;margin:0;">
                            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                            <button type="submit" title="Eliminar" style="background:#dc2626;color:#fff;border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;line-height:1;">✕</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="form-row">
            <label for="imagenes">Afegir imatges <span class="muted">(pots seleccionar-ne diverses · JPG/PNG/WEBP/GIF)</span></label>
            <input type="file" id="imagenes" name="imagenes[]" accept="image/*" multiple>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Desa els canvis' : 'Crea el producte' ?></button>
        <a href="<?= e(base_url('/admin/tienda')) ?>" class="btn">Cancel·la</a>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('producto-form');
    if (!form) return;

    // ── Mostrar simple vs variable ──
    function applyTipo() {
        var variable = form.querySelector('input[name="tipo"]:checked').value === 'variable';
        form.querySelectorAll('.tipo-simple-only').forEach(function (el) { el.style.display = variable ? 'none' : ''; });
        form.querySelectorAll('.tipo-variable-only').forEach(function (el) { el.style.display = variable ? '' : 'none'; });
    }
    form.querySelectorAll('input[name="tipo"]').forEach(function (r) { r.addEventListener('change', applyTipo); });
    applyTipo();

    // ── Atributs (afegir/eliminar) ──
    var attrList = document.getElementById('atributos-list');
    var attrIdx = attrList.querySelectorAll('.attr-row').length;
    document.getElementById('add-attr').addEventListener('click', function () {
        var i = attrIdx++;
        var div = document.createElement('div');
        div.className = 'attr-row';
        div.style.cssText = 'display:flex;gap:.6rem;margin-bottom:.5rem;align-items:flex-end;flex-wrap:wrap;';
        div.innerHTML = '<div style="flex:1;min-width:140px;"><label>Nom</label><input type="text" name="atributos[' + i + '][nombre]" placeholder="Color"></div>' +
            '<div style="flex:2;min-width:200px;"><label>Valors (separa amb |)</label><input type="text" name="atributos[' + i + '][valores]" placeholder="Roig | Blau"></div>' +
            '<button type="button" class="btn-small btn-danger attr-remove">✕</button>';
        attrList.appendChild(div);
    });
    attrList.addEventListener('click', function (e) {
        if (e.target.classList.contains('attr-remove')) e.target.closest('.attr-row').remove();
    });

    // ── Variacions ──
    var body = document.getElementById('variaciones-body');
    var emptyMsg = document.getElementById('var-empty');
    var varIdx = body.querySelectorAll('.var-row').length;

    function readAtributos() {
        var attrs = [];
        attrList.querySelectorAll('.attr-row').forEach(function (row) {
            var nombre = (row.querySelector('input[name$="[nombre]"]').value || '').trim();
            var vals = (row.querySelector('input[name$="[valores]"]').value || '').split('|').map(function (s) { return s.trim(); }).filter(Boolean);
            if (nombre && vals.length) attrs.push({ nombre: nombre, valores: vals });
        });
        return attrs;
    }

    function existingByCombo() {
        var map = {};
        body.querySelectorAll('.var-row').forEach(function (row) {
            var combo = row.querySelector('input[name$="[combinacion]"]').value;
            map[combo] = {
                precio: row.querySelector('input[name$="[precio]"]').value,
                stock: row.querySelector('input[name$="[stock]"]').value,
                sku: row.querySelector('input[name$="[sku]"]').value
            };
        });
        return map;
    }

    function addVarRow(combo, label, prev) {
        var i = varIdx++;
        var tr = document.createElement('tr');
        tr.className = 'var-row';
        var comboJson = JSON.stringify(combo);
        tr.innerHTML = '<td>' + label + '<input type="hidden" name="variaciones[' + i + '][combinacion]" value=\'' + comboJson.replace(/'/g, '&#39;') + '\'></td>' +
            '<td><input type="number" step="0.01" min="0" name="variaciones[' + i + '][precio]" value="' + (prev ? prev.precio : '') + '" style="width:90px;"></td>' +
            '<td><input type="number" min="0" name="variaciones[' + i + '][stock]" value="' + (prev ? prev.stock : '') + '" placeholder="∞" style="width:80px;"></td>' +
            '<td><input type="text" name="variaciones[' + i + '][sku]" value="' + (prev ? prev.sku : '') + '" style="width:100px;"></td>' +
            '<td><button type="button" class="btn-small btn-danger var-remove">✕</button></td>';
        body.appendChild(tr);
    }

    document.getElementById('gen-var').addEventListener('click', function () {
        var attrs = readAtributos();
        if (!attrs.length) { alert('Defineix almenys un atribut amb valors.'); return; }
        var prev = existingByCombo();
        // Producte cartesià
        var combos = [{}];
        attrs.forEach(function (a) {
            var next = [];
            combos.forEach(function (c) {
                a.valores.forEach(function (v) {
                    var nc = Object.assign({}, c); nc[a.nombre] = v; next.push(nc);
                });
            });
            combos = next;
        });
        body.innerHTML = '';
        varIdx = 0;
        combos.forEach(function (c) {
            var label = attrs.map(function (a) { return c[a.nombre]; }).join(' / ');
            addVarRow(c, label, prev[JSON.stringify(c)]);
        });
        emptyMsg.style.display = combos.length ? 'none' : '';
    });

    body.addEventListener('click', function (e) {
        if (e.target.classList.contains('var-remove')) {
            e.target.closest('.var-row').remove();
            if (!body.querySelectorAll('.var-row').length) emptyMsg.style.display = '';
        }
    });
})();
</script>
