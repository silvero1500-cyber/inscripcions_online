<?php
/** @var array $inscrito */
/** @var array $evento */
/** @var array $tarifa */
/** @var array $pago */
/** @var string $qrCid */
/** @var string $baseUrl */
?><!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= e(t('email.header_title')) ?></title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#1f2937;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;padding:24px 0;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.06);">

                <!-- Header -->
                <tr>
                    <td style="background:#1e88c2;padding:28px 32px;color:#ffffff;text-align:center;">
                        <h1 style="margin:0;font-size:22px;font-weight:800;letter-spacing:-.5px;"><?= e(t('email.header_title')) ?></h1>
                    </td>
                </tr>

                <!-- Saludo -->
                <tr>
                    <td style="padding:28px 32px 8px;">
                        <p style="margin:0;font-size:16px;line-height:1.6;color:#1f2937;">
                            <?= e(t('email.greeting', ['name' => $inscrito['nombre']])) ?>
                        </p>
                        <p style="margin:8px 0 0;font-size:15px;line-height:1.6;color:#374151;">
                            <?= e(t('email.intro', ['event' => $evento['titulo']])) ?>
                        </p>
                    </td>
                </tr>

                <!-- Resumen -->
                <tr>
                    <td style="padding:0 32px;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f9fafb;border-radius:8px;padding:18px 20px;margin-top:16px;">
                            <tr><td style="font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;padding-bottom:4px;"><?= e(t('email.field.event')) ?></td></tr>
                            <tr><td style="font-size:15px;color:#1f2937;font-weight:600;padding-bottom:14px;"><?= e($evento['titulo']) ?></td></tr>

                            <tr><td style="font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;padding-bottom:4px;"><?= e(t('email.field.date')) ?></td></tr>
                            <tr><td style="font-size:15px;color:#1f2937;font-weight:600;padding-bottom:14px;"><?= e(format_date_ca((string)$evento['fecha_evento'], true)) ?></td></tr>

                            <tr><td style="font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;padding-bottom:4px;"><?= e(t('email.field.tarifa')) ?></td></tr>
                            <tr><td style="font-size:15px;color:#1f2937;font-weight:600;padding-bottom:14px;"><?= e($tarifa['nombre']) ?> · <?= e(format_price((float)$tarifa['precio'])) ?></td></tr>

                            <tr><td style="font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;padding-bottom:4px;"><?= e(t('email.field.runner')) ?></td></tr>
                            <tr><td style="font-size:15px;color:#1f2937;font-weight:600;padding-bottom:14px;"><?= e(trim($inscrito['nombre'] . ' ' . (string)($inscrito['apellido'] ?? ''))) ?><?php if (!empty($inscrito['dni'])): ?> · DNI <?= e($inscrito['dni']) ?><?php endif; ?></td></tr>

                            <?php if (!empty($pago['ds_order'])): ?>
                                <tr><td style="font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;padding-bottom:4px;"><?= e(t('email.field.payment_ref')) ?></td></tr>
                                <tr><td style="font-size:14px;color:#1f2937;font-family:monospace;padding-bottom:6px;"><?= e($pago['ds_order']) ?><?php if (!empty($pago['ds_auth_code'])): ?> · auth <?= e($pago['ds_auth_code']) ?><?php endif; ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </td>
                </tr>

                <!-- QR -->
                <tr>
                    <td style="padding:28px 32px 16px;text-align:center;">
                        <h2 style="margin:0 0 12px;font-size:18px;color:#1f2937;font-weight:700;"><?= e(t('email.qr.title')) ?></h2>
                        <p style="margin:0 0 18px;font-size:14px;color:#6b7280;line-height:1.5;">
                            <?= e(t('email.qr.desc')) ?>
                        </p>
                        <img src="cid:<?= e($qrCid) ?>" alt="<?= e(t('email.qr.alt')) ?>" width="240" height="240" style="display:inline-block;border:1px solid #e5e7eb;border-radius:8px;padding:12px;background:#ffffff;">
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="padding:24px 32px 28px;border-top:1px solid #e5e7eb;text-align:center;color:#9ca3af;font-size:12px;line-height:1.6;">
                        <p style="margin:0 0 4px;"><?= e(t('email.footer.contact')) ?></p>
                        <p style="margin:0;">WeRun · <?= e($baseUrl) ?></p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
