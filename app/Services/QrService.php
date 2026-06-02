<?php

declare(strict_types=1);

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Common\Version;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRImagick;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Generación de QR codes con chillerlan/php-qrcode v6.
 * Devuelve PNG en bytes binarios, listo para adjuntar a un email.
 */
final class QrService
{
    /**
     * Genera un PNG con el QR del contenido dado.
     * @return string  Bytes binarios PNG
     */
    public static function pngBytes(string $contenido, int $sizePx = 300): string
    {
        // Prioridad: Imagick si está disponible, sino GD (PNG)
        $outputInterface = extension_loaded('imagick') ? QRImagick::class : QRGdImagePNG::class;

        $options = new QROptions([
            'version'         => Version::AUTO,
            'versionMin'      => 5,
            'versionMax'      => 15,
            'outputInterface' => $outputInterface,
            'eccLevel'        => EccLevel::M,
            'outputBase64'    => false,
            'scale'           => max(4, (int) round($sizePx / 50)),
            'quietzoneSize'   => 2,
        ]);

        return (new QRCode($options))->render($contenido);
    }

    /**
     * Token único aleatorio para el QR (64 chars hex).
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
