<?php
/** @var array $pedido */
/** @var array $evento */
/** @var list<array{inscrito:array,tarifa:array|null,qrCid:string}> $items */
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

                <!-- Saludo + intro -->
                <tr>
                    <td style="padding:28px 32px 8px;">
                        <p style="margin:0;font-size:16px;line-height:1.6;color:#1f2937;">
                            <?= e(!empty($pedido['nombre']) ? t('email.greeting', ['name' => $pedido['nombre']]) : t('email.greeting_generic')) ?>
                        </p>
                        <p style="margin:8px 0 0;font-size:15px;line-height:1.6;color:#374151;">
                            <?= e(t('email.pedido_intro', ['event' => $evento['titulo'], 'n' => count($items)])) ?>
                        </p>
                    </td>
                </tr>

                <!-- Resumen evento -->
                <tr>
                    <td style="padding:0 32px;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f9fafb;border-radius:8px;padding:14px 20px;margin-top:14px;">
                            <tr><td style="font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;padding-bottom:4px;"><?= e(t('email.field.event')) ?></td></tr>
                            <tr><td style="font-size:15px;color:#1f2937;font-weight:600;padding-bottom:12px;"><?= e($evento['titulo']) ?></td></tr>
                            <tr><td style="font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;padding-bottom:4px;"><?= e(t('email.field.date')) ?></td></tr>
                            <tr><td style="font-size:15px;color:#1f2937;font-weight:600;"><?= e(format_date_ca((string)$evento['fecha_evento'], true)) ?></td></tr>
                        </table>
                    </td>
                </tr>

                <!-- Participants -->
                <?php foreach ($items as $idx => $it): $ins = $it['inscrito']; $comprovantUrl = base_url('/comprovant/' . $ins['qr_token']); ?>
                <tr>
                    <td style="padding:22px 32px 6px;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e5e7eb;border-radius:10px;">
                            <tr>
                                <td style="padding:16px 18px;">
                                    <div style="font-size:12px;color:#1e88c2;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">
                                        <?= e(t('group.participant')) ?> <?= (int)$idx + 1 ?>
                                    </div>
                                    <div style="font-size:17px;font-weight:700;color:#1f2937;margin:2px 0 6px;">
                                        <?= e(trim($ins['nombre'] . ' ' . (string)($ins['apellido'] ?? ''))) ?>
                                    </div>
                                    <div style="font-size:13px;color:#6b7280;">
                                        <?= e($it['tarifa']['nombre'] ?? '') ?><?php if (!empty($ins['talla_camiseta'])): ?> · <?= e(t('form.label.shirt')) ?>: <strong style="color:#374151;"><?= e($ins['talla_camiseta']) ?></strong><?php endif; ?><?php if (!empty($ins['dni'])): ?> · DNI <?= e($ins['dni']) ?><?php endif; ?>
                                    </div>
                                </td>
                                <td width="130" style="padding:12px 18px 12px 0;text-align:center;vertical-align:middle;">
                                    <img src="cid:<?= e($it['qrCid']) ?>" alt="QR" width="110" height="110" style="display:inline-block;border:1px solid #e5e7eb;border-radius:8px;padding:6px;background:#fff;">
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" style="padding:0 18px 16px;text-align:center;">
                                    <a href="<?= e($comprovantUrl) ?>" target="_blank" style="display:inline-block;background:#1e88c2;color:#ffffff;text-decoration:none;font-weight:700;font-size:13px;padding:9px 18px;border-radius:7px;">
                                        <?= e(t('email.qr.button')) ?>
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <?php endforeach; ?>

                <!-- Nota spam + footer -->
                <tr>
                    <td style="padding:14px 32px 0;">
                        <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.55;text-align:center;">
                            <?= e(t('email.qr.spam_note')) ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px 28px;border-top:1px solid #e5e7eb;text-align:center;color:#9ca3af;font-size:12px;line-height:1.6;">
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
