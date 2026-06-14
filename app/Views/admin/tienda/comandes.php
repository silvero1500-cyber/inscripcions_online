<?php
/** @var list<array> $pedidos */
/** @var array<int,list<array>> $lineas */
/** @var array $flash */
$estats = ['pendiente' => ['Pendent', 'badge-warning'], 'pagado' => ['Pagada', 'badge-success'], 'entregado' => ['Recollida', 'badge-muted'], 'cancelado' => ['Cancel·lada', 'badge-muted']];
?>
<section class="page-head with-action">
    <div>
        <h1>Comandes de la tenda</h1>
        <p class="muted">Pedidos de la botiga · recollida en tenda.</p>
    </div>
    <a class="btn" href="<?= e(base_url('/admin/tienda')) ?>">← Productes</a>
</section>

<?php if (!empty($flash['success'])): ?><div class="alert alert-success"><?= e($flash['success']) ?></div><?php endif; ?>
<?php if (!empty($flash['error'])): ?><div class="alert alert-error"><?= e($flash['error']) ?></div><?php endif; ?>

<?php if (count($pedidos) === 0): ?>
    <div class="empty-state"><p>Encara no hi ha cap comanda.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Codi</th><th>Data</th><th>Client</th><th>Articles</th><th>Total</th><th>Estat</th><th class="th-actions">Accions</th></tr></thead>
            <tbody>
            <?php foreach ($pedidos as $p): [$lbl, $bdg] = $estats[$p['estado']] ?? [$p['estado'], 'badge-muted']; ?>
                <tr>
                    <td><strong><?= e($p['codigo']) ?></strong></td>
                    <td><?= e(format_datetime_local((string) $p['created_at'], 'd/m/Y H:i')) ?></td>
                    <td>
                        <?= e($p['nombre']) ?>
                        <div class="row-sub muted"><?= e($p['email']) ?><?= !empty($p['telefono']) ? ' · ' . e($p['telefono']) : '' ?></div>
                    </td>
                    <td>
                        <?php foreach (($lineas[(int) $p['id']] ?? []) as $l): ?>
                            <div><?= (int) $l['cantidad'] ?>× <?= e($l['nombre']) ?><?= !empty($l['variante']) ? ' <span class="muted">(' . e($l['variante']) . ')</span>' : '' ?></div>
                        <?php endforeach; ?>
                    </td>
                    <td><strong><?= e(format_price((float) $p['total'])) ?></strong></td>
                    <td>
                        <span class="badge <?= $bdg ?>"><?= e($lbl) ?></span>
                        <?php if (!empty($p['recollit_at'])): ?><div class="row-sub muted">✓ <?= e(format_datetime_local((string) $p['recollit_at'], 'd/m H:i')) ?></div><?php endif; ?>
                    </td>
                    <td class="td-actions">
                        <?php if ($p['estado'] === 'pagado'): ?>
                            <form method="post" action="<?= e(base_url('/admin/tienda/comandes/' . (int) $p['id'] . '/entregat')) ?>" class="inline"
                                  onsubmit="return confirm('Marcar la comanda <?= e($p['codigo']) ?> com a recollida?');">
                                <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                                <button type="submit" class="btn-small">📦 Recollida</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
