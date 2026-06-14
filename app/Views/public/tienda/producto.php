<?php
/** @var array $producto */
/** @var list<array> $imagenes */
/** @var list<array> $atributos */
/** @var list<array> $variaciones */
/** @var ?string $flashError */
use App\Core\Csrf;
use App\Services\ImageUploader;

$esVariable = $producto['tipo'] === 'variable';
$imgs = array_map(fn($i) => ImageUploader::publicUrl($i['ruta']), $imagenes);
$stockSimple = $producto['stock'] !== null ? (int) $producto['stock'] : null;
$agotadoSimple = !$esVariable && $stockSimple !== null && $stockSimple <= 0;

// Variacions per a JS: {combinacioJSON: {id, precio, stock}}
$varMap = [];
foreach ($variaciones as $v) {
    $varMap[json_encode($v['combinacion'], JSON_UNESCAPED_UNICODE)] = [
        'id'     => (int) $v['id'],
        'precio' => (float) $v['precio'],
        'stock'  => $v['stock'] !== null ? (int) $v['stock'] : null,
    ];
}
?>
<nav class="shop-breadcrumb"><a href="<?= e(base_url('/tienda')) ?>">← <?= e(t('shop.continue')) ?></a></nav>

<?php if (!empty($flashError)): ?><div class="alert alert-error"><?= e($flashError) ?></div><?php endif; ?>

<div class="shop-detail">
    <div class="shop-gallery">
        <div class="shop-gallery-main">
            <?php if ($imgs): ?>
                <img id="gallery-main" src="<?= e($imgs[0]) ?>" alt="<?= e($producto['nombre']) ?>">
            <?php else: ?>
                <span class="shop-card-noimg" style="font-size:4rem;">🛍️</span>
            <?php endif; ?>
        </div>
        <?php if (count($imgs) > 1): ?>
            <div class="shop-gallery-thumbs">
                <?php foreach ($imgs as $src): ?>
                    <button type="button" class="shop-thumb" data-src="<?= e($src) ?>"><img src="<?= e($src) ?>" alt=""></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="shop-info">
        <h1><?= e($producto['nombre']) ?></h1>
        <div class="shop-price" id="shop-price"><?= e(format_price(\App\Models\Producto::precioDesde($producto))) ?></div>

        <?php if (!empty($producto['descripcion'])): ?>
            <div class="shop-desc"><?= nl2br(e($producto['descripcion'])) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url('/tienda/cistella/afegir')) ?>" class="shop-buy" id="buy-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="slug" value="<?= e($producto['slug']) ?>">
            <input type="hidden" name="producto_id" value="<?= (int) $producto['id'] ?>">
            <input type="hidden" name="variacion_id" id="variacion_id" value="">

            <?php if ($esVariable): ?>
                <?php foreach ($atributos as $a): ?>
                    <div class="form-row">
                        <label for="attr-<?= e($a['nombre']) ?>"><?= e($a['nombre']) ?></label>
                        <select class="shop-attr" data-attr="<?= e($a['nombre']) ?>" id="attr-<?= e($a['nombre']) ?>">
                            <option value=""><?= e(t('shop.choose')) ?></option>
                            <?php foreach ($a['valores'] as $val): ?>
                                <option value="<?= e($val) ?>"><?= e($val) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
                <p class="shop-stock muted" id="shop-stock"></p>
            <?php elseif ($agotadoSimple): ?>
                <p class="shop-stock"><strong><?= e(t('shop.soldout')) ?></strong></p>
            <?php elseif ($stockSimple !== null): ?>
                <p class="shop-stock muted"><?= e(t('shop.stock_left', ['n' => $stockSimple])) ?></p>
            <?php endif; ?>

            <div class="form-row shop-qty">
                <label for="cantidad"><?= e(t('shop.qty')) ?></label>
                <input type="number" id="cantidad" name="cantidad" value="1" min="1" style="width:90px;">
            </div>

            <button type="submit" class="btn btn-primary btn-large" id="add-btn" <?= ($esVariable || $agotadoSimple) ? 'disabled' : '' ?>>
                <?= e(t('shop.add_to_cart')) ?>
            </button>
            <p class="form-note"><?= e(t('shop.pickup_note')) ?></p>
        </form>
    </div>
</div>

<script>
(function () {
    // Galeria
    document.querySelectorAll('.shop-thumb').forEach(function (b) {
        b.addEventListener('click', function () {
            var main = document.getElementById('gallery-main');
            if (main) main.src = b.getAttribute('data-src');
        });
    });

    var esVariable = <?= $esVariable ? 'true' : 'false' ?>;
    if (!esVariable) return;

    var VARS = <?= json_encode($varMap, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
    var attrNames = <?= json_encode(array_column($atributos, 'nombre'), JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
    var priceEl = document.getElementById('shop-price');
    var stockEl = document.getElementById('shop-stock');
    var varInput = document.getElementById('variacion_id');
    var addBtn = document.getElementById('add-btn');
    var qty = document.getElementById('cantidad');
    var fmt = <?= json_encode(['soldout' => t('shop.soldout'), 'left' => t('shop.stock_left', ['n' => '#'])], JSON_UNESCAPED_UNICODE) ?>;

    function selected() {
        var combo = {};
        var done = true;
        document.querySelectorAll('.shop-attr').forEach(function (s) {
            var v = s.value;
            if (!v) done = false; else combo[s.getAttribute('data-attr')] = v;
        });
        return done ? combo : null;
    }
    function keyFor(combo) {
        var ordered = {};
        attrNames.forEach(function (n) { ordered[n] = combo[n]; });
        return JSON.stringify(ordered);
    }
    function update() {
        var combo = selected();
        if (!combo) { varInput.value = ''; addBtn.disabled = true; if (stockEl) stockEl.textContent = ''; return; }
        var v = VARS[keyFor(combo)];
        if (!v) { varInput.value = ''; addBtn.disabled = true; if (stockEl) stockEl.textContent = '—'; return; }
        varInput.value = v.id;
        if (priceEl) priceEl.textContent = (v.precio.toFixed(2).replace('.', ',')) + ' €';
        if (v.stock !== null && v.stock <= 0) {
            addBtn.disabled = true; if (stockEl) stockEl.innerHTML = '<strong>' + fmt.soldout + '</strong>';
        } else {
            addBtn.disabled = false;
            if (stockEl) stockEl.textContent = (v.stock !== null) ? fmt.left.replace('#', v.stock) : '';
            if (qty && v.stock !== null) qty.max = v.stock;
        }
    }
    document.querySelectorAll('.shop-attr').forEach(function (s) { s.addEventListener('change', update); });
})();
</script>
