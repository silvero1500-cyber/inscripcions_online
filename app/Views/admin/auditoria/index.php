<?php
/** @var object $user */
/** @var list<array> $logs */
/** @var string|null $accion */
$labels = [
    'login_ok'        => ['✅ Login', 'badge-success'],
    'login_fail'      => ['⛔ Login fallit', 'badge-danger'],
    'usuari_creat'    => ['👤 Usuari creat', 'badge-info'],
    'usuari_esborrat' => ['🗑 Usuari esborrat', 'badge-warning'],
    'evento_esborrat' => ['🗑 Esdeveniment esborrat', 'badge-warning'],
    'evento_arxivat'  => ['🗄 Esdeveniment arxivat', 'badge-muted'],
    'inscrits_export' => ['📤 Exportació d\'inscrits', 'badge-info'],
    'config_desada'   => ['⚙️ Configuració desada', 'badge-muted'],
];
$filtres = [
    ''                => 'Totes les accions',
    'login_fail'      => 'Logins fallits',
    'login_ok'        => 'Logins correctes',
    'inscrits_export' => 'Exportacions',
    'usuari_creat'    => 'Usuaris creats',
    'usuari_esborrat' => 'Usuaris esborrats',
    'evento_esborrat' => 'Esdeveniments esborrats',
];
?>
<section class="page-head">
    <div>
        <h1>🛡️ Auditoria de seguretat</h1>
        <p class="muted">Accions sensibles: accessos, gestió d'usuaris i esdeveniments, exportacions i configuració.</p>
    </div>
</section>

<form method="get" action="" class="filtres-inscrits" style="margin-bottom:1rem;">
    <div class="filtres-grid">
        <div class="filtre">
            <label for="f-accio">Acció</label>
            <select id="f-accio" name="accion" onchange="this.form.submit()">
                <?php foreach ($filtres as $k => $lbl): ?>
                    <option value="<?= e($k) ?>" <?= (string) $accion === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<?php if (count($logs) === 0): ?>
    <div class="empty-state"><p>Encara no hi ha cap registre.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Data i hora</th><th>Acció</th><th>Usuari</th><th>IP</th><th>Detall</th></tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $l): [$lbl, $bdg] = $labels[$l['accion']] ?? [$l['accion'], 'badge-muted']; ?>
                <tr>
                    <td><?= e(format_datetime_local((string) $l['created_at'], 'd/m/Y H:i:s')) ?></td>
                    <td><span class="badge <?= $bdg ?>"><?= e($lbl) ?></span></td>
                    <td><?= e((string) ($l['usuario_nom'] ?? '') ?: '—') ?></td>
                    <td><span class="muted small"><?= e((string) ($l['ip'] ?? '—')) ?></span></td>
                    <td><?= e((string) ($l['detall'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="muted small" style="margin-top:.6rem;">Es mostren els últims 300 registres. Es conserven 1 any.</p>
<?php endif; ?>
