<?php
/** @var list<array> $productos */
/** @var bool $tiendaActiva */
/** @var array $flash */
?>
<section class="page-head with-action">
    <div>
        <h1>Botiga
            <?php if ($tiendaActiva): ?>
                <span class="badge badge-success" style="vertical-align:middle;">Activa</span>
            <?php else: ?>
                <span class="badge badge-muted" style="vertical-align:middle;">Desactivada</span>
            <?php endif; ?>
        </h1>
        <p class="muted">Productes a la venda (recollida en botiga).</p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <form method="post" action="<?= e(base_url('/admin/tienda/toggle')) ?>" class="inline"
              onsubmit="return confirm('<?= $tiendaActiva ? 'Desactivar la botiga? Deixarà de veure\'s al públic (ni botó ni carret).' : 'Activar la botiga? Tornarà a ser visible al públic.' ?>');">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
            <?php if ($tiendaActiva): ?>
                <button type="submit" class="btn btn-danger">⏸ Desactivar botiga</button>
            <?php else: ?>
                <button type="submit" class="btn btn-primary">▶ Activar botiga</button>
            <?php endif; ?>
        </form>
        <a class="btn" href="<?= e(base_url('/admin/tienda/config')) ?>">⚙ Configuració</a>
        <a class="btn" href="<?= e(base_url('/admin/tienda/comandes')) ?>">📦 Comandes</a>
        <a class="btn btn-primary" href="<?= e(base_url('/admin/tienda/nou')) ?>">+ Nou producte</a>
    </div>
</section>

<?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><?= e($flash['success']) ?></div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><?= e($flash['error']) ?></div>
<?php endif; ?>
<?php if (!$tiendaActiva): ?>
    <div class="alert alert-warning">La botiga està <strong>desactivada</strong>: no es mostra al públic (ni el botó «Botiga» ni el carret) i no s'hi pot comprar. Pots seguir editant productes.</div>
<?php endif; ?>

<?php if (count($productos) === 0): ?>
    <div class="empty-state">
        <p>Encara no hi ha cap producte. Crea el primer per començar a vendre.</p>
        <a class="btn btn-primary" href="<?= e(base_url('/admin/tienda/nou')) ?>">+ Nou producte</a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th></th><th>Producte</th><th>Tipus</th><th>Preu</th><th>Stock</th><th>Estat</th><th class="th-actions">Accions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($productos as $p): ?>
                <tr>
                    <td style="width:54px;">
                        <?php if (!empty($p['imagen'])): ?>
                            <img src="<?= e(\App\Services\ImageUploader::publicUrl($p['imagen'])) ?>" alt="" style="width:46px;height:46px;object-fit:cover;border-radius:6px;">
                        <?php else: ?>
                            <span class="muted" style="font-size:1.4rem;">🛍️</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= e($p['nombre']) ?></strong></td>
                    <td>
                        <?php if ($p['tipo'] === 'variable'): ?>
                            <span class="badge" style="background:#e0e7ff;color:#4338ca;">Variable · <?= (int)$p['n_variaciones'] ?> var.</span>
                        <?php else: ?>
                            <span class="badge badge-muted">Simple</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(format_price(\App\Models\Producto::precioDesde($p))) ?><?= $p['tipo'] === 'variable' ? ' <span class="muted">des de</span>' : '' ?></td>
                    <td><?= $p['tipo'] === 'variable' ? '<span class="muted">per variació</span>' : ($p['stock'] === null ? '∞' : (int)$p['stock']) ?></td>
                    <td>
                        <?php if ((int)$p['activo'] === 1): ?>
                            <span class="badge badge-success">Actiu</span>
                        <?php else: ?>
                            <span class="badge badge-muted">Ocult</span>
                        <?php endif; ?>
                        <?php if (!empty($p['destacado'])): ?><span class="badge" style="background:#fef3c7;color:#b45309;">★</span><?php endif; ?>
                    </td>
                    <td class="td-actions">
                        <a class="btn-small" href="<?= e(base_url('/tienda/' . $p['slug'])) ?>" target="_blank" rel="noopener" title="Veure a la botiga">👁️ Veure</a>
                        <a class="btn-small" href="<?= e(base_url('/admin/tienda/' . (int)$p['id'] . '/editar')) ?>">Editar</a>
                        <form method="post" action="<?= e(base_url('/admin/tienda/' . (int)$p['id'] . '/eliminar')) ?>" class="inline"
                              onsubmit="return confirm('Eliminar el producte «<?= e($p['nombre']) ?>» i les seves imatges?');">
                            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                            <button type="submit" class="btn-small btn-danger">🗑</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
