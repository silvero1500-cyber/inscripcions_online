<?php
/** @var object $user */
/** @var list<array> $incidencias */
/** @var string|null $estado */
/** @var int $noves */
/** @var array $flash */
$csrf = \App\Core\Csrf::token();
$filtres = ['' => 'Totes', 'nova' => 'Noves', 'resolta' => 'Resoltes'];
?>
<section class="page-head">
    <div>
        <h1>🛟 Incidències</h1>
        <p class="muted">Missatges enviats des de la bústia del formulari públic.<?= $noves > 0 ? ' Tens <strong>' . (int) $noves . '</strong> sense resoldre.' : '' ?></p>
    </div>
</section>

<?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><?= e($flash['success']) ?></div>
<?php endif; ?>

<form method="get" action="" class="filtres-inscrits" style="margin-bottom:1rem;">
    <div class="filtres-grid">
        <div class="filtre">
            <label for="f-estado">Estat</label>
            <select id="f-estado" name="estado" onchange="this.form.submit()">
                <?php foreach ($filtres as $k => $lbl): ?>
                    <option value="<?= e($k) ?>" <?= (string) $estado === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<?php if (count($incidencias) === 0): ?>
    <div class="empty-state"><p>Cap incidència.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Data</th><th>Esdeveniment</th><th>Missatge</th><th>Estat</th><th>IP</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($incidencias as $i): $nova = $i['estado'] === 'nova'; ?>
                <tr<?= $nova ? ' style="font-weight:600;"' : '' ?>>
                    <td style="white-space:nowrap;"><?= e(format_datetime_local((string) $i['created_at'], 'd/m/Y H:i')) ?></td>
                    <td><?= e((string) ($i['evento_nom'] ?? '—')) ?></td>
                    <td style="max-width:420px;white-space:pre-wrap;word-break:break-word;font-weight:400;"><?= e((string) $i['missatge']) ?></td>
                    <td>
                        <?php if ($nova): ?>
                            <span class="badge badge-warning">Nova</span>
                        <?php else: ?>
                            <span class="badge badge-muted">Resolta</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="muted small"><?= e((string) ($i['ip'] ?? '—')) ?></span></td>
                    <td style="white-space:nowrap;">
                        <?php if ($nova): ?>
                            <form method="post" action="<?= e(base_url('/admin/incidencies/' . (int) $i['id'] . '/resolta')) ?>" class="inline">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <button type="submit" class="btn-tiny btn-primary" title="Marcar com a resolta">✓ Resolta</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(base_url('/admin/incidencies/' . (int) $i['id'] . '/nova')) ?>" class="inline">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <button type="submit" class="btn-tiny" title="Reobrir">↩ Reobrir</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= e(base_url('/admin/incidencies/' . (int) $i['id'] . '/eliminar')) ?>" class="inline"
                              onsubmit="return confirm('Eliminar aquesta incidència?');">
                            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                            <button type="submit" class="btn-tiny btn-danger" title="Eliminar">🗑</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
