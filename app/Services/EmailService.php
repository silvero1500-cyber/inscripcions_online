<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\View;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use RuntimeException;

/**
 * Servicio de envío de email vía SMTP (PHPMailer).
 * Soporta adjuntos inline (CID) para QR codes embebidos.
 */
final class EmailService
{
    /**
     * Envía un email HTML.
     *
     * @param string                          $to
     * @param string                          $subject
     * @param string                          $htmlBody
     * @param list<array{cid:string, bytes:string, name:string, type?:string}> $inlineAttachments
     */
    public static function send(
        string $to,
        string $subject,
        string $htmlBody,
        array $inlineAttachments = []
    ): void {
        $mailer = self::buildMailer();
        $mailer->addAddress($to);
        $mailer->Subject = $subject;
        $mailer->Body    = $htmlBody;
        $mailer->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody) ?? $htmlBody);

        foreach ($inlineAttachments as $att) {
            $mailer->addStringEmbeddedImage(
                $att['bytes'],
                $att['cid'],
                $att['name'],
                PHPMailer::ENCODING_BASE64,
                $att['type'] ?? 'image/png'
            );
        }

        try {
            $mailer->send();
        } catch (PHPMailerException $e) {
            error_log('[EmailService] Fallo envío: ' . $e->getMessage() . ' | ErrorInfo: ' . $mailer->ErrorInfo);
            throw new RuntimeException('No se pudo enviar el email: ' . $e->getMessage());
        }
    }

    /**
     * Envía el email de confirmación de inscripción con el QR de check-in.
     *
     * @param array  $inscrito  Fila de inscritos (incluye qr_token, nombre, email, ...)
     * @param array  $evento    Fila de eventos
     * @param array  $tarifa    Fila de tarifas_evento
     * @param array  $pago      Fila de pagos (opcional pero recomendable para mostrar order/auth)
     */
    public static function sendConfirmacionInscripcion(
        array $inscrito,
        array $evento,
        array $tarifa,
        array $pago = []
    ): void {
        $qrUrl   = base_url('/admin/checkin/' . $inscrito['qr_token']);
        $qrPng   = QrService::pngBytes($qrUrl, 300);
        $cidName = 'qr_checkin.png';
        $cidId   = 'qr_checkin';

        // Forçar locale del inscrit per renderitzar email + subject en el seu idioma
        $localeInscrit = !empty($inscrito['locale']) ? (string) $inscrito['locale'] : \App\Core\Lang::DEFAULT_LOCALE;
        $localeOriginal = $_COOKIE['IO_LOCALE'] ?? null;
        $_COOKIE['IO_LOCALE'] = $localeInscrit;
        \App\Core\Lang::reset();

        $html = View::renderToString('emails/confirmacion_inscripcion', [
            'inscrito'  => $inscrito,
            'evento'    => $evento,
            'tarifa'    => $tarifa,
            'pago'      => $pago,
            'qrCid'     => $cidId,
            'baseUrl'   => base_url('/'),
        ]);

        $subject = \App\Core\Lang::t('email.subject', ['event' => $evento['titulo']]);

        // Restaurar locale original
        if ($localeOriginal !== null) {
            $_COOKIE['IO_LOCALE'] = $localeOriginal;
        } else {
            unset($_COOKIE['IO_LOCALE']);
        }
        \App\Core\Lang::reset();

        self::send(
            (string) $inscrito['email'],
            $subject,
            $html,
            [[
                'cid'   => $cidId,
                'bytes' => $qrPng,
                'name'  => $cidName,
                'type'  => 'image/png',
            ]]
        );
    }

    /**
     * Envía un email de prueba al destinatario indicado.
     */
    public static function sendTest(string $to): void
    {
        $html = '<h2 style="font-family:sans-serif;color:#1e88c2;">WeRun Inscripcions · Test SMTP</h2>'
              . '<p style="font-family:sans-serif;color:#1f2937;">Aquest és un correu de prova enviat el '
              . date('d/m/Y H:i:s')
              . ' des de <strong>' . htmlspecialchars((string) Env::get('APP_URL', '')) . '</strong>.</p>'
              . '<p style="font-family:sans-serif;color:#6b7280;font-size:.9rem;">Si el reps, la configuració SMTP funciona correctament.</p>';

        self::send($to, '[Test] WeRun Inscripcions · SMTP OK', $html);
    }

    private static function buildMailer(): PHPMailer
    {
        $host = (string) Env::get('MAIL_HOST', '');
        $port = (int) Env::get('MAIL_PORT', 587);
        $user = (string) Env::get('MAIL_USERNAME', '');
        $pass = (string) Env::get('MAIL_PASSWORD', '');
        $enc  = strtolower((string) Env::get('MAIL_ENCRYPTION', 'tls'));
        $from = (string) Env::get('MAIL_FROM', '');
        $fromName = (string) Env::get('MAIL_FROM_NAME', 'WeRun Inscripcions');

        if ($host === '' || $user === '' || $pass === '' || $from === '') {
            throw new RuntimeException('Configuración SMTP incompleta (.env).');
        }

        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host       = $host;
        $mailer->Port       = $port;
        $mailer->SMTPAuth   = true;
        $mailer->Username   = $user;
        $mailer->Password   = $pass;
        $mailer->SMTPSecure = $enc === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->CharSet    = 'UTF-8';
        $mailer->Encoding   = 'base64';
        $mailer->isHTML(true);
        $mailer->setFrom($from, $fromName);
        $mailer->addReplyTo($from, $fromName);
        $mailer->Timeout    = 15;

        return $mailer;
    }
}
