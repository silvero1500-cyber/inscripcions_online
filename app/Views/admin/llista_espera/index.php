<?php
/** @var array $evento */
/** @var list<array> $entries */
/** @var array $flash */
$pendents = array_values(array_filter($entries, fn($e) => empty($e['notificado_at'])));
?>
<section class="page-head with-action">
    <div>
        <h1>Llista d'espera</h1>
        <p class="muted"><?= e($evento['titulo']) ?></p>
    </div>
    <a class="btn" href="<?= e(base_url('/admin/eventos')) ?>">← Esdeveniments</a>
</section>

<?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><?= e($flash['success']) ?></div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><?= e($flash['error']) ?></div>
<?php endif; ?>

<div class="kpi-grid kpi-grid-3" style="margin-bottom:1.2rem;">
    <div class="kpi-card">
        <div class="kpi-label">Total a la llista</div>
        <div class="kpi-value"><?= count($entries) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Pendents d'avisar</div>
        <div class="kpi-value" style="color:#1e88c2"><?= count($pendents) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Ja avisats</div>
        <div class="kpi-value" style="color:#6b7280"><?= count($entries) - count($pendents) ?></div>
    </div>
</div>

<?php if (count($pendents) > 0): ?>
    <form method="post" action="<?= e(base_url('/admin/eventos/' . (int)$evento['id'] . '/llista-espera/avisar')) ?>"
          onsubmit="return confirm('Enviar un correu a <?= count($pendents) ?> persona/es avisant que hi ha plaça?');"
          style="margin-bottom:1.2rem;">
        <?= \App\Core\Csrf::field() ?>
        <button type="submit" class="btn btn-primary">✉ Avisar els <?= count($pendents) ?> pendents</button>
        <span class="muted" style="margin-left:.6rem;">Els envia un correu amb l'enllaç per inscriure's i els marca com a avisats.</span>
    </form>
<?php endif; ?>

<?php if (count($entries) === 0): ?>
    <div class="empty-state">
        <p>Encara no hi ha ningú a la llista d'espera.</p>
        <p class="muted small">El formulari apareix al públic quan una tarifa o l'aforament està ple.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Correu</th>
                    <th>Tarifa preferida</th>
                    <th>Data</th>
                    <th>Estat</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $e): ?>
                <tr>
                    <td><?= e((string)($e['nombre'] ?? '') ?: '—') ?></td>
                    <td><?= e((string)$e['email']) ?></td>
                    <td><?= e((string)($e['tarifa_nombre'] ?? '') ?: 'Qualsevol') ?></td>
                    <td><?= e(format_datetime_local($e['created_at'], 'd/m/Y H:i')) ?></td>
                    <td>
                        <?php if (!empty($e['notificado_at'])): ?>
                            <span class="badge badge-success" title="<?= e(format_datetime_local($e['notificado_at'], 'd/m/Y H:i')) ?>">✓ Avisat</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Pendent</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
