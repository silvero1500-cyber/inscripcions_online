<?php
/** @var object $user */
/** @var string $mensaje */
?>
<section class="page-head">
    <div>
        <h1>Check-in</h1>
    </div>
</section>

<div class="checkin-card checkin-error">
    <div class="checkin-status">
        <span class="status-badge status-err">QR NO VÀLID</span>
    </div>
    <p style="margin: 1.5rem 0;"><?= e($mensaje) ?></p>
    <a class="btn btn-primary" href="<?= e(base_url('/admin/checkin')) ?>">← Tornar a escanejar</a>
</div>
