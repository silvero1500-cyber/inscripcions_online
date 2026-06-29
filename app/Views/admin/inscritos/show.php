<?php
/** @var object $user */
/** @var array $inscrito */
/** @var array|null $evento */
/** @var array|null $tarifa */
/** @var list<array> $campos */
/** @var array<int,string> $valores */
/** @var list<array> $pagos */
/** @var list<array> $companys */
/** @var array $flash */

$flash = $flash ?? [];
$eventoId = (int) $inscrito['evento_id'];
$backUrl  = base_url('/admin/inscritos?' . http_build_query(['evento_id' => $eventoId]));

$sexLabels = ['H' => 'Home', 'M' => 'Dona', 'NB' => 'No binari'];
$estado = (string) $inscrito['estado'];
$estadoBadge = match ($estado) {
    'confirmado' => 'badge-success',
    'pendiente'  => 'badge-warning',
    default      => 'badge-muted',
};

// Helper de fila etiqueta → valor
$row = function (string $label, $value, bool $raw = false): void {
    $empty = ($value === null || $value === '');
    echo '<div class="detail-row"><span class="detail-label">' . e($label) . '</span>'
       . '<span class="detail-value' . ($empty ? ' muted' : '') . '">'
       . ($empty ? '—' : ($raw ? $value : e((string) $value)))
       . '</span></div>';
};
?>
<section class="page-head with-action">
    <div>
        <h1><?= e(trim($inscrito['nombre'] . ' ' . ($inscrito['apellido'] ?? ''))) ?>
            <span class="badge <?= $estadoBadge ?>" style="vertical-align:middle;"><?= e($estado) ?></span>
        </h1>
        <p class="muted">
            Inscrit #<?= (int) $inscrito['id'] ?>
            <?php if ($evento): ?> · <?= e($evento['titulo']) ?><?php endif; ?>
        </p>
    </div>
    <a class="btn" href="<?= e($backUrl) ?>">← Tornar al llistat</a>
</section>

<?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><?= e($flash['success']) ?></div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><?= e($flash['error']) ?></div>
<?php endif; ?>

<!-- ── Accions ── -->
<div class="detail-actions">
    <?php if ($estado === 'confirmado'): ?>
        <a class="btn btn-small" href="<?= e(base_url('/admin/inscritos/' . (int) $inscrito['id'] . '/comprovant')) ?>" target="_blank" rel="noopener">📄 Comprovant</a>
        <a class="btn btn-small" href="<?= e(base_url('/admin/inscritos/' . (int) $inscrito['id'] . '/qr')) ?>" target="_blank" rel="noopener">🔳 QR</a>
        <form method="post" action="<?= e(base_url('/admin/inscritos/' . (int) $inscrito['id'] . '/reenviar')) ?>" class="inline"
              onsubmit="return confirm('Reenviar l\'email de confirmació a <?= e($inscrito['email']) ?>?');">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
            <button type="submit" class="btn btn-small">✉ Reenviar confirmació</button>
        </form>
    <?php endif; ?>
    <?php if ($evento): ?>
        <a class="btn btn-small" href="<?= e(base_url('/eventos/' . $evento['slug'])) ?>" target="_blank" rel="noopener">👁️ Veure esdeveniment</a>
    <?php endif; ?>
</div>

<div class="detail-cols">
    <!-- ── Dades personals ── -->
    <div class="panel">
        <h2 class="panel-title">Dades personals</h2>
        <div class="detail-grid">
            <?php
            $row('Nom', $inscrito['nombre']);
            $row('Cognoms', $inscrito['apellido'] ?? null);
            $row('DNI', $inscrito['dni'] ?? null);
            $row('Sexe', !empty($inscrito['sexo']) ? ($sexLabels[$inscrito['sexo']] ?? $inscrito['sexo']) : null);
            $row('Data naixement', !empty($inscrito['fecha_nacimiento']) ? date('d/m/Y', strtotime((string) $inscrito['fecha_nacimiento'])) : null);
            $row('Email', $inscrito['email']);
            $row('Telèfon', $inscrito['telefono'] ?? null);
            $row('Població', $inscrito['poblacion'] ?? null);
            $row('Codi postal', $inscrito['codigo_postal'] ?? null);
            $row('Club', $inscrito['club'] ?? null);
            $row('Talla samarreta', $inscrito['talla_camiseta'] ?? null);
            $row('Franja de temps', $inscrito['franja_temps'] ?? null);
            if (!empty($inscrito['chip_groc'])) {
                $row('Xip groc', $inscrito['chip_groc'] === 'si' ? 'Sí' : 'No');
                if ($inscrito['chip_groc'] === 'si') $row('Núm. xip groc', $inscrito['chip_groc_num'] ?? null);
            }
            ?>
        </div>
        <?php if (!empty($inscrito['tutor_nombre']) || !empty($inscrito['tutor_apellido']) || !empty($inscrito['tutor_dni'])): ?>
            <h3 style="margin:1.2rem 0 .4rem;font-size:1rem;">Tutor/a legal</h3>
            <div class="detail-grid">
                <?php
                $row('Nom tutor/a', trim((string)($inscrito['tutor_nombre'] ?? '') . ' ' . (string)($inscrito['tutor_apellido'] ?? '')) ?: null);
                $row('DNI tutor/a', $inscrito['tutor_dni'] ?? null);
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Inscripció ── -->
    <div class="panel">
        <h2 class="panel-title">Inscripció</h2>
        <div class="detail-grid">
            <?php
            $row('Esdeveniment', $evento['titulo'] ?? ('#' . $eventoId));
            $row('Tarifa', $tarifa['nombre'] ?? '—');
            $row('Preu', isset($tarifa['precio']) ? format_price((float) $tarifa['precio']) : null);
            $row('Estat', '<span class="badge ' . $estadoBadge . '">' . e($estado) . '</span>', true);
            $row('Dorsal', !empty($inscrito['numero_dorsal']) ? (int) $inscrito['numero_dorsal'] : null);
            $row('Check-in', !empty($inscrito['check_in_at']) ? format_datetime_local((string) $inscrito['check_in_at'], 'd/m/Y H:i') : null);
            $row('Recollida dorsal', !empty($inscrito['dorsal_recollit_at']) ? format_datetime_local((string) $inscrito['dorsal_recollit_at'], 'd/m/Y H:i') : null);
            $row('Data inscripció', format_datetime_local((string) $inscrito['created_at'], 'd/m/Y H:i'));
            if (!empty($inscrito['pedido_id'])) {
                $row('Comanda (grup)', '#' . (int) $inscrito['pedido_id']);
            }
            if (!empty($inscrito['descuento_codigo'])) {
                $pct = !empty($inscrito['descuento_porcentaje']) ? ' (−' . (float) $inscrito['descuento_porcentaje'] . '%)' : '';
                $row('Descompte', $inscrito['descuento_codigo'] . $pct);
            }
            $row('Idioma', $inscrito['locale'] ?? null);
            $row('IP registre', $inscrito['ip_registro'] ?? null);
            ?>
        </div>
        <?php /* Early Bird: marca manual que es mostra a recollida */ ?>
        <form method="post" action="<?= e(base_url('/admin/inscritos/' . (int) $inscrito['id'] . '/early-bird')) ?>" class="inline" style="margin-top:1rem;">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
            <input type="hidden" name="early_bird" value="<?= !empty($inscrito['early_bird']) ? '0' : '1' ?>">
            <?php if (!empty($inscrito['early_bird'])): ?>
                <span class="badge badge-success">🐦 Early Bird</span>
                <button type="submit" class="btn-link" style="margin-left:.6rem;">Desmarcar</button>
            <?php else: ?>
                <button type="submit" class="btn btn-secondary btn-small">🐦 Marcar Early Bird</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ── Respostes camps personalitzats ── -->
<?php if (count($campos) > 0): ?>
    <div class="panel">
        <h2 class="panel-title">Respostes dels camps personalitzats</h2>
        <div class="detail-grid detail-grid-1">
            <?php foreach ($campos as $c):
                $cid = (int) $c['id'];
                $val = $valores[$cid] ?? null;
                $label = (string) $c['etiqueta'] . (!empty($c['oculto']) ? ' 🔒' : '');
                $row($label, $val);
            endforeach; ?>
        </div>
        <?php if (array_filter($campos, fn($c) => !empty($c['oculto']))): ?>
            <p class="muted small" style="margin:.6rem 0 0;">🔒 = camp ocult (no es mostra al formulari públic).</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ── Companys de comanda ── -->
<?php if (count($companys) > 0): ?>
    <div class="panel">
        <h2 class="panel-title">Altres participants de la comanda #<?= (int) $inscrito['pedido_id'] ?></h2>
        <ul class="detail-companys">
            <?php foreach ($companys as $co): ?>
                <li>
                    <a href="<?= e(base_url('/admin/inscritos/' . (int) $co['id'])) ?>">
                        <?= e(trim($co['nombre'] . ' ' . ($co['apellido'] ?? ''))) ?>
                    </a>
                    <span class="muted">· #<?= (int) $co['id'] ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- ── Pagaments ── -->
<?php if (count($pagos) > 0): ?>
    <div class="panel">
        <h2 class="panel-title">Pagaments</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Data</th><th>Comanda Redsys</th><th>Import</th><th>Resposta</th><th>Autorització</th></tr>
                </thead>
                <tbody>
                <?php foreach ($pagos as $p):
                    $resp = $p['ds_response'] ?? null;
                    $okPay = ($resp !== null && (int) $resp >= 0 && (int) $resp <= 99);
                ?>
                    <tr>
                        <td><?= e(format_datetime_local((string) $p['created_at'], 'd/m/Y H:i')) ?></td>
                        <td><?= e((string) ($p['ds_order'] ?? '—')) ?></td>
                        <td class="cell-right"><?= e(format_price((float) ($p['importe'] ?? 0))) ?></td>
                        <td>
                            <?php if ($resp === null || $resp === ''): ?>
                                <span class="badge badge-muted">—</span>
                            <?php elseif ($okPay): ?>
                                <span class="badge badge-success">OK (<?= e((string) $resp) ?>)</span>
                            <?php else: ?>
                                <span class="badge badge-warning">KO (<?= e((string) $resp) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) ($p['ds_auth_code'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
