<?php
/** @var array $evento */
/** @var array $inscrito */
/** @var array|null $tarifa */
/** @var string|null $qrDataUri */
$loc = current_locale();
$confirmada = ($inscrito['estado'] ?? '') === 'confirmado';
?><!DOCTYPE html>
<html lang="<?= e($loc) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e(t('comprovant.title')) ?> · <?= e($evento['titulo']) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; color: #1f2937; background: #f3f4f6; }
        .toolbar { max-width: 720px; margin: 24px auto 0; text-align: center; }
        .btn-print {
            display: inline-block; background: #1e88c2; color: #fff; border: 0;
            padding: 12px 24px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer;
        }
        .btn-print:hover { background: #1769a0; }
        .sheet {
            max-width: 720px; margin: 18px auto 40px; background: #fff;
            border-radius: 12px; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,.08);
        }
        .sheet-head { background: #1e88c2; color: #fff; padding: 24px 28px; }
        .sheet-head h1 { margin: 0; font-size: 20px; letter-spacing: -.3px; }
        .sheet-head p { margin: 6px 0 0; opacity: .92; font-size: 14px; }
        .sheet-body { padding: 26px 28px; display: grid; grid-template-columns: 1fr 260px; gap: 28px; align-items: start; }
        dl { margin: 0; }
        dt { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin-top: 14px; }
        dt:first-child { margin-top: 0; }
        dd { margin: 2px 0 0; font-size: 15px; font-weight: 600; color: #1f2937; }
        .qr-box { text-align: center; }
        .qr-box img { width: 240px; max-width: 100%; height: auto; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px; background: #fff; }
        .badge { display: inline-block; margin-top: 12px; padding: 4px 12px; border-radius: 999px; font-size: 13px; font-weight: 600; }
        .badge-ok { background: #dcfce7; color: #166534; }
        .badge-pend { background: #fef9c3; color: #854d0e; }
        .note { padding: 0 28px 24px; color: #6b7280; font-size: 13px; line-height: 1.5; }
        @media (max-width: 600px) { .sheet-body { grid-template-columns: 1fr; } .qr-box { order: -1; } }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet { box-shadow: none; margin: 0; max-width: none; border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn-print" onclick="window.print()"><?= e(t('comprovant.print')) ?></button>
    </div>

    <div class="sheet">
        <div class="sheet-head">
            <h1><?= e($evento['titulo']) ?></h1>
            <p>
                <?= e(format_date_ca((string)$evento['fecha_evento'], true)) ?><?php if (!empty($evento['localizacion'])): ?> · <?= e($evento['localizacion']) ?><?php endif; ?>
            </p>
        </div>

        <div class="sheet-body">
            <dl>
                <dt><?= e(t('email.field.runner')) ?></dt>
                <dd><?= e(trim($inscrito['nombre'] . ' ' . (string)($inscrito['apellido'] ?? ''))) ?></dd>

                <?php if (!empty($inscrito['dni'])): ?>
                    <dt><?= e(t('success.summary.dni')) ?></dt><dd><?= e($inscrito['dni']) ?></dd>
                <?php endif; ?>

                <dt><?= e(t('success.summary.email')) ?></dt><dd><?= e($inscrito['email']) ?></dd>

                <?php if ($tarifa): ?>
                    <dt><?= e(t('success.summary.tarifa')) ?></dt>
                    <dd><?= e($tarifa['nombre']) ?> · <?= e(format_price((float)$tarifa['precio'])) ?></dd>
                <?php endif; ?>

                <?php if (!empty($inscrito['talla_camiseta'])): ?>
                    <dt><?= e(t('form.label.shirt')) ?></dt><dd><?= e($inscrito['talla_camiseta']) ?></dd>
                <?php endif; ?>

                <?php if (!empty($inscrito['numero_dorsal'])): ?>
                    <dt><?= e(t('comprovant.dorsal')) ?></dt><dd>#<?= (int)$inscrito['numero_dorsal'] ?></dd>
                <?php endif; ?>
            </dl>

            <div class="qr-box">
                <?php if (!empty($qrDataUri)): ?>
                    <img src="<?= e($qrDataUri) ?>" alt="<?= e(t('email.qr.alt')) ?>">
                <?php endif; ?>
                <div>
                    <?php if ($confirmada): ?>
                        <span class="badge badge-ok">✓ <?= e(t('success.summary.confirmed')) ?></span>
                    <?php else: ?>
                        <span class="badge badge-pend"><?= e(t('success.summary.pending_payment')) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <p class="note"><?= e(t('comprovant.instructions')) ?> · WeRun</p>
    </div>
</body>
</html>
