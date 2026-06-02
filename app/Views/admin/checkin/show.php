<?php
/** @var object $user */
/** @var array $inscrito */
/** @var array $evento */
/** @var array $tarifa */
/** @var string $token */
/** @var array|null $marcadoPor */
/** @var string|null $flash */

$yaMarcado = !empty($inscrito['check_in_at']);
$justMarcado = ($flash === 'success');
$yaEstabaMarcado = ($flash === 'already');
?>
<section class="page-head with-action">
    <div>
        <h1>Check-in</h1>
        <p class="muted"><?= e($evento['titulo']) ?></p>
    </div>
    <a class="btn" href="<?= e(base_url('/admin/checkin')) ?>">← Escanejar un altre</a>
</section>

<div class="checkin-card <?= $yaMarcado ? 'checkin-done' : 'checkin-pending' ?>">

    <?php if ($justMarcado): ?>
        <div class="checkin-banner ok">✅ Check-in marcat correctament</div>
    <?php elseif ($yaEstabaMarcado): ?>
        <div class="checkin-banner warn">⚠️ Aquest corredor ja estava marcat</div>
    <?php endif; ?>

    <div class="checkin-status">
        <?php if ($yaMarcado): ?>
            <span class="status-badge status-done">JA VALIDAT</span>
            <p class="status-meta">
                <?= e(date('d/m/Y H:i', strtotime((string)$inscrito['check_in_at']))) ?>
                <?php if ($marcadoPor): ?>· per <?= e($marcadoPor['nombre']) ?><?php endif; ?>
            </p>
        <?php else: ?>
            <span class="status-badge status-pending">PENDENT DE VALIDAR</span>
        <?php endif; ?>
    </div>

    <div class="checkin-runner">
        <h2><?= e($inscrito['nombre']) ?> <?= e($inscrito['apellido']) ?></h2>
        <dl class="checkin-data">
            <dt>DNI</dt>           <dd><?= e($inscrito['dni']) ?></dd>
            <dt>Tarifa</dt>        <dd><?= e($tarifa['nombre']) ?> · <?= e(format_price((float)$tarifa['precio'])) ?></dd>
            <dt>Sexe</dt>          <dd><?= e($inscrito['sexo']) ?></dd>
            <?php if (!empty($inscrito['talla_camiseta'])): ?>
                <dt>Talla</dt>     <dd><?= e($inscrito['talla_camiseta']) ?></dd>
            <?php endif; ?>
            <?php if (!empty($inscrito['club'])): ?>
                <dt>Club</dt>      <dd><?= e($inscrito['club']) ?></dd>
            <?php endif; ?>
            <?php if (!empty($inscrito['poblacion'])): ?>
                <dt>Població</dt>  <dd><?= e($inscrito['poblacion']) ?></dd>
            <?php endif; ?>
            <dt>Estat pago</dt>    <dd><?= e($inscrito['estado']) ?></dd>
        </dl>
    </div>

    <?php if (!$yaMarcado): ?>
        <form method="post" action="<?= e(base_url('/admin/checkin/' . $token)) ?>" class="checkin-form">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="btn btn-primary btn-block btn-large">
                ✓ Marcar check-in
            </button>
        </form>
    <?php endif; ?>
</div>
