<?php
/** @var object $user */
/** @var list<array> $eventos */
/** @var array $flash */
?>
<section class="page-head">
    <div>
        <h1>⬇️ Exportar inscrits</h1>
        <p class="muted">Descarrega el CSV complet d'inscrits de cada esdeveniment assignat.</p>
    </div>
</section>

<?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><?= e($flash['error']) ?></div>
<?php endif; ?>

<?php if (empty($eventos)): ?>
    <div class="panel"><p class="muted" style="margin:0;">No tens cap esdeveniment assignat. Contacta amb l'administrador.</p></div>
<?php else: ?>
    <div class="panel">
        <table class="data-table">
            <thead><tr><th>Esdeveniment</th><th>Data</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($eventos as $ev): ?>
                    <tr>
                        <td><strong><?= e($ev['titulo']) ?></strong></td>
                        <td><?= !empty($ev['fecha_evento']) ? e(date('d/m/Y', strtotime((string) $ev['fecha_evento']))) : '—' ?></td>
                        <td style="text-align:right;">
                            <a class="btn btn-primary btn-small" href="<?= e(base_url('/admin/export/' . (int) $ev['id'] . '/csv')) ?>">⬇️ Descarregar CSV</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
