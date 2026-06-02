<?php
/** @var array $evento */
/** @var list<array> $descuentos */
/** @var string|null $loteFilter */
/** @var array $flash */
?>
<section class="page-head with-action">
    <div>
        <h1>Codis de descompte</h1>
        <p class="muted"><?= e($evento['titulo']) ?><?php if ($loteFilter): ?> · lot <code><?= e($loteFilter) ?></code><?php endif; ?></p>
    </div>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
        <a class="btn" href="<?= e(base_url('/admin/eventos')) ?>">← Eventos</a>
        <a class="btn btn-primary" href="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/descuentos/generar')) ?>">+ Generar lot</a>
        <?php if (count($descuentos) > 0): ?>
            <a class="btn" href="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/descuentos/export' . ($loteFilter ? '?lote=' . urlencode($loteFilter) : ''))) ?>">⬇ CSV</a>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><?= e($flash['success']) ?></div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><?= e($flash['error']) ?></div>
<?php endif; ?>

<?php if ($loteFilter): ?>
    <p style="margin-bottom:1rem;"><a href="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/descuentos')) ?>">← Veure tots els codis del evento</a></p>
<?php endif; ?>

<?php if (count($descuentos) === 0): ?>
    <div class="empty-state">
        <p>Encara no hi ha cap codi de descompte per a aquest evento.</p>
        <a class="btn btn-primary" href="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/descuentos/generar')) ?>">+ Generar el primer lot</a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Codi</th>
                    <th>% Descompte</th>
                    <th>Usos</th>
                    <th>Validesa</th>
                    <th>Estat</th>
                    <th>Lot</th>
                    <th class="th-actions">Accions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($descuentos as $d): ?>
                <tr>
                    <td><code style="font-size:.95rem;font-weight:600;"><?= e($d['codigo']) ?></code></td>
                    <td><strong><?= number_format((float)$d['porcentaje'], 2, ',', '.') ?>%</strong></td>
                    <td>
                        <?= (int)$d['usos_actuales'] ?>
                        <?php if ($d['usos_max'] !== null): ?> / <?= (int)$d['usos_max'] ?><?php else: ?> / ∞<?php endif; ?>
                    </td>
                    <td>
                        <?php if ($d['valido_desde'] || $d['valido_hasta']): ?>
                            <small>
                                <?php if ($d['valido_desde']): ?>des de <?= e(date('d/m/Y H:i', strtotime((string)$d['valido_desde']))) ?><br><?php endif; ?>
                                <?php if ($d['valido_hasta']): ?>fins <?= e(date('d/m/Y H:i', strtotime((string)$d['valido_hasta']))) ?><?php endif; ?>
                            </small>
                        <?php else: ?>
                            <span class="muted small">sense límit</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$d['activo'] === 1): ?>
                            <span class="badge badge-success">actiu</span>
                        <?php else: ?>
                            <span class="badge badge-muted">inactiu</span>
                        <?php endif; ?>
                    </td>
                    <td><?php if (!empty($d['lote'])): ?><code><?= e($d['lote']) ?></code><?php else: ?><span class="muted small">—</span><?php endif; ?></td>
                    <td class="td-actions">
                        <form method="post" action="<?= e(base_url('/admin/descuentos/' . (int)$d['id'] . '/toggle')) ?>" class="inline">
                            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                            <button type="submit" class="btn-small"><?= (int)$d['activo'] === 1 ? 'Desactiva' : 'Activa' ?></button>
                        </form>
                        <?php if ((int)$d['usos_actuales'] === 0): ?>
                            <form method="post" action="<?= e(base_url('/admin/descuentos/' . (int)$d['id'] . '/eliminar')) ?>" class="inline"
                                  onsubmit="return confirm('Esborrar <?= e($d['codigo']) ?>?');">
                                <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                                <button type="submit" class="btn-small btn-danger">Esborra</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
