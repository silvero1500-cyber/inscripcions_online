<?php
/** @var list<array> $eventos */
/** @var int|null $eventoId */
/** @var list<array> $logs */
/** @var array $flash */
$accions = [
    'recollit'        => ['✅ Recollit', 'badge-success'],
    'desfet'          => ['↩ Recollida desfeta', 'badge-warning'],
    'dorsal'          => ['🔢 Dorsal assignat', 'badge-info'],
    'dorsal_esborrat' => ['🗑 Dorsal esborrat', 'badge-muted'],
];
?>
<section class="page-head with-action">
    <div>
        <h1>Historial de recollida</h1>
        <p class="muted">Qui ha fet cada acció i quan (auditoria).</p>
    </div>
    <a class="btn" href="<?= e(base_url('/admin/recollida' . ($eventoId ? '?evento_id=' . $eventoId : ''))) ?>">← Recollida</a>
</section>

<?php if (!empty($flash['error'])): ?><div class="alert alert-error"><?= e($flash['error']) ?></div><?php endif; ?>

<form method="get" action="" class="filtres-inscrits" style="margin-bottom:1rem;">
    <div class="filtres-grid">
        <div class="filtre">
            <label for="f-evento">Esdeveniment</label>
            <select id="f-evento" name="evento_id" onchange="this.form.submit()">
                <?php foreach ($eventos as $ev): ?>
                    <option value="<?= (int) $ev['id'] ?>" <?= $eventoId === (int) $ev['id'] ? 'selected' : '' ?>>
                        <?= e($ev['titulo']) ?> · <?= e(date('d/m/Y', strtotime((string) $ev['fecha_evento']))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<?php if (!$eventoId): ?>
    <div class="empty-state"><p>Tria un esdeveniment.</p></div>
<?php elseif (count($logs) === 0): ?>
    <div class="empty-state"><p>Encara no hi ha cap acció registrada per a aquest esdeveniment.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Data i hora</th><th>Acció</th><th>Inscrit</th><th>Dorsal</th><th>Qui ho ha fet</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): [$lbl, $bdg] = $accions[$l['accion']] ?? [$l['accion'], 'badge-muted']; ?>
                <tr>
                    <td><?= e(format_datetime_local((string) $l['created_at'], 'd/m/Y H:i:s')) ?></td>
                    <td><span class="badge <?= $bdg ?>"><?= e($lbl) ?></span></td>
                    <td><?= e((string) ($l['inscrito_nom'] ?? '') ?: '—') ?></td>
                    <td><?= !empty($l['dorsal']) ? '#' . (int) $l['dorsal'] : '—' ?></td>
                    <td><strong><?= e((string) ($l['usuario_nom'] ?? '') ?: 'Sistema') ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="muted small" style="margin-top:.6rem;">Es mostren les últimes 500 accions.</p>
<?php endif; ?>
