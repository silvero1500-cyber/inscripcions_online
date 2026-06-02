<?php
/** @var list<array> $eventos */
/** @var array $flash */
?>
<section class="page-head with-action">
    <div>
        <h1>Esdeveniments</h1>
        <p class="muted">Llistat de carreres que pots gestionar.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(base_url('/admin/eventos/nou')) ?>">+ Nou esdeveniment</a>
</section>

<?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><?= e($flash['success']) ?></div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><?= e($flash['error']) ?></div>
<?php endif; ?>

<?php if (count($eventos) === 0): ?>
    <div class="empty-state">
        <p>Encara no hi ha cap esdeveniment. Crea el primer per començar.</p>
        <a class="btn btn-primary" href="<?= e(base_url('/admin/eventos/nou')) ?>">+ Nou esdeveniment</a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Títol</th>
                    <th>Data</th>
                    <th>Tarifes</th>
                    <th>Inscrits</th>
                    <th>Estat</th>
                    <th class="th-actions">Accions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($eventos as $ev): ?>
                <tr>
                    <td>
                        <strong><?= e($ev['titulo']) ?></strong>
                        <div class="row-sub">
                            <code><?= e($ev['slug']) ?></code>
                            <?php if (!empty($ev['propietario_nombre'])): ?>
                                · <?= e($ev['propietario_nombre']) ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?= e(date('d/m/Y', strtotime((string)$ev['fecha_evento']))) ?></td>
                    <td>
                        <?php if ((int)($ev['tarifas_activas'] ?? 0) === 0): ?>
                            <span class="muted">Sense tarifes</span>
                        <?php else: ?>
                            <?= (int)$ev['tarifas_activas'] ?>
                            <?php if ($ev['precio_min'] !== null): ?>
                                <span class="row-sub">des de <?= number_format((float)$ev['precio_min'], 2, ',', '.') ?> €</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$ev['_inscritos'] ?></td>
                    <td>
                        <?php if ((int)$ev['activo'] === 1 && (int)$ev['inscripciones_abiertas'] === 1): ?>
                            <span class="badge badge-success">Obert</span>
                        <?php elseif ((int)$ev['activo'] === 1): ?>
                            <span class="badge badge-warning">Tancat</span>
                        <?php else: ?>
                            <span class="badge badge-muted">Inactiu</span>
                        <?php endif; ?>
                    </td>
                    <td class="td-actions">
                        <a class="btn-small" href="<?= e(base_url('/eventos/' . $ev['slug'])) ?>" target="_blank" rel="noopener">👁️ Veure</a>
                        <a class="btn-small btn-kpi" href="<?= e(base_url('/admin/eventos/' . (int)$ev['id'] . '/kpis')) ?>">📊 KPIs</a>
                        <a class="btn-small" href="<?= e(base_url('/admin/eventos/' . (int)$ev['id'] . '/descuentos')) ?>">🏷️ Descomptes</a>
                        <a class="btn-small" href="<?= e(base_url('/admin/eventos/' . (int)$ev['id'] . '/editar')) ?>">Editar</a>
                        <form method="post" action="<?= e(base_url('/admin/eventos/' . (int)$ev['id'] . '/duplicar')) ?>" class="inline"
                              onsubmit="return confirm('Vols duplicar aquest esdeveniment? Es crearà una còpia inactiva.');">
                            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                            <button type="submit" class="btn-small">⧉ Duplicar</button>
                        </form>
                        <form method="post" action="<?= e(base_url('/admin/eventos/' . (int)$ev['id'] . '/eliminar')) ?>" class="inline"
                              onsubmit="return confirm('Vols esborrar aquest esdeveniment? Aquesta acció no es pot desfer.');">
                            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                            <button type="submit" class="btn-small btn-danger">Esborra</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
