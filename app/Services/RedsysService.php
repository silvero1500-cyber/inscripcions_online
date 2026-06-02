<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use RuntimeException;

/**
 * Integración con Redsys TPV Virtual (Bizum + tarjeta).
 *
 * Algoritmo de firma:
 *   1) Ds_MerchantParameters = base64( json(params) )       — alfabeto estándar
 *   2) clave_transaccion     = 3DES_encrypt(Ds_Merchant_Order, base64_decode(secret))
 *   3) Ds_Signature          = base64( HMAC_SHA256(Ds_MerchantParameters, clave_transaccion) )
 *
 * Para notificaciones entrantes (IPN) Redsys envía Ds_MerchantParameters y
 * Ds_Signature en base64 URL-safe (-_ en lugar de +/), y el JSON contiene
 * Ds_Order, Ds_Response, Ds_AuthorisationCode, etc.
 */
final class RedsysService
{
    private const PAYMENT_URL_PRODUCTION = 'https://sis.redsys.es/sis/realizarPago';
    private const PAYMENT_URL_SANDBOX    = 'https://sis-t.redsys.es:25443/sis/realizarPago';

    private readonly string $environment;
    private readonly string $merchantCode;
    private readonly string $terminal;
    private readonly string $secretKey;
    private readonly string $currency;

    public function __construct()
    {
        $env = strtolower((string) Env::get('REDSYS_ENVIRONMENT', 'sandbox'));
        $this->environment = $env === 'production' ? 'production' : 'sandbox';

        $this->merchantCode = trim((string) Env::get('REDSYS_MERCHANT_CODE', ''));
        $this->terminal     = trim((string) Env::get('REDSYS_TERMINAL', ''));
        $this->secretKey    = trim((string) Env::get('REDSYS_SECRET_KEY', ''));
        $this->currency     = trim((string) Env::get('REDSYS_CURRENCY', '978')); // EUR

        if ($this->merchantCode === '' || $this->terminal === '' || $this->secretKey === '') {
            throw new RuntimeException('Configuración de Redsys incompleta (.env).');
        }
    }

    public function paymentUrl(): string
    {
        return $this->environment === 'production'
            ? self::PAYMENT_URL_PRODUCTION
            : self::PAYMENT_URL_SANDBOX;
    }

    public function environment(): string
    {
        return $this->environment;
    }

    public const PAY_METHOD_CARD  = 'C';
    public const PAY_METHOD_BIZUM = 'z';

    /**
     * Construye los tres campos que deben POSTearse al TPV.
     *
     * pay_method:
     *   'C' = solo tarjeta
     *   'z' = solo Bizum
     *   null/omitido = todos los métodos habilitados para el comercio
     *
     * @param array{
     *   order: string,
     *   amount_cents: int,
     *   description: string,
     *   url_notification: string,
     *   url_ok: string,
     *   url_ko: string,
     *   titular?: string,
     *   language?: string,
     *   pay_method?: string|null
     * } $opts
     * @return array{url:string, fields: array<string,string>}
     */
    public function buildPaymentForm(array $opts): array
    {
        if ($opts['amount_cents'] < 1) {
            throw new RuntimeException('Importe inválido para Redsys.');
        }
        if (!preg_match('/^\d{4}[A-Za-z0-9]{0,8}$/', $opts['order'])) {
            throw new RuntimeException('Ds_Merchant_Order inválido: ' . $opts['order']);
        }

        $params = [
            'DS_MERCHANT_AMOUNT'             => (string) $opts['amount_cents'],
            'DS_MERCHANT_ORDER'              => $opts['order'],
            'DS_MERCHANT_MERCHANTCODE'       => $this->merchantCode,
            'DS_MERCHANT_CURRENCY'           => $this->currency,
            'DS_MERCHANT_TRANSACTIONTYPE'    => '0',
            'DS_MERCHANT_TERMINAL'           => $this->terminal,
            'DS_MERCHANT_MERCHANTURL'        => $opts['url_notification'],
            'DS_MERCHANT_URLOK'              => $opts['url_ok'],
            'DS_MERCHANT_URLKO'              => $opts['url_ko'],
            'DS_MERCHANT_PRODUCTDESCRIPTION' => mb_substr($opts['description'], 0, 125),
            'DS_MERCHANT_CONSUMERLANGUAGE'   => $opts['language'] ?? '003', // 003 = català
        ];
        if (!empty($opts['titular'])) {
            $params['DS_MERCHANT_TITULAR'] = mb_substr($opts['titular'], 0, 60);
        }
        $payMethod = $opts['pay_method'] ?? null;
        if ($payMethod === self::PAY_METHOD_CARD || $payMethod === self::PAY_METHOD_BIZUM) {
            $params['DS_MERCHANT_PAYMETHODS'] = $payMethod;
        }

        $merchantParameters = base64_encode(
            (string) json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $signature = $this->computeSignature($merchantParameters, $opts['order']);

        return [
            'url'    => $this->paymentUrl(),
            'fields' => [
                'Ds_SignatureVersion'   => 'HMAC_SHA256_V1',
                'Ds_MerchantParameters' => $merchantParameters,
                'Ds_Signature'          => $signature,
            ],
        ];
    }

    /**
     * Verifica y decodifica una notificación IPN entrante de Redsys.
     * Devuelve el array de parámetros si la firma es válida, null si no.
     *
     * @return array<string,mixed>|null
     */
    public function verifyNotification(string $merchantParameters, string $receivedSignature): ?array
    {
        if ($merchantParameters === '' || $receivedSignature === '') return null;

        // Los parámetros vienen en base64 URL-safe (-_) → normalizar antes de decodificar
        $jsonStr = base64_decode(strtr($merchantParameters, '-_', '+/'), true);
        if ($jsonStr === false || $jsonStr === '') return null;

        $params = json_decode($jsonStr, true);
        if (!is_array($params)) return null;

        // El order viene en Ds_Order (PascalCase) en notificaciones
        $order = (string) ($params['Ds_Order'] ?? $params['DS_ORDER'] ?? '');
        if ($order === '') return null;

        // Recomputar firma con la cadena recibida exacta (sin re-codificar)
        $expectedUrlSafe = $this->computeSignatureUrlSafe($merchantParameters, $order);
        $receivedUrlSafe = strtr($receivedSignature, '+/', '-_');

        if (!hash_equals($expectedUrlSafe, $receivedUrlSafe)) {
            return null;
        }

        return $params;
    }

    /** @return array{success:bool, code:string, auth_code:?string, raw:array<string,mixed>} */
    public static function interpretResponse(array $params): array
    {
        $code = (string) ($params['Ds_Response'] ?? $params['DS_RESPONSE'] ?? '9999');
        $authCode = isset($params['Ds_AuthorisationCode']) ? (string) $params['Ds_AuthorisationCode'] : null;

        // 0000-0099 = autorizado | 0900 = Bizum OK (devolución/anulación) | resto = denegado
        $codeInt = ctype_digit($code) ? (int) $code : 9999;
        $success = ($codeInt >= 0 && $codeInt <= 99);

        return [
            'success'   => $success,
            'code'      => str_pad($code, 4, '0', STR_PAD_LEFT),
            'auth_code' => $authCode,
            'raw'       => $params,
        ];
    }

    // ── Internos ──────────────────────────────────────────────

    private function computeSignature(string $merchantParameters, string $order): string
    {
        $key        = base64_decode($this->secretKey, true);
        if ($key === false) {
            throw new RuntimeException('REDSYS_SECRET_KEY no es base64 válido.');
        }
        $derivedKey = $this->encrypt3DES($order, $key);
        $hmac       = hash_hmac('sha256', $merchantParameters, $derivedKey, true);
        return base64_encode($hmac);
    }

    private function computeSignatureUrlSafe(string $merchantParameters, string $order): string
    {
        return strtr($this->computeSignature($merchantParameters, $order), '+/', '-_');
    }

    /**
     * 3DES-CBC con IV=0 y sin padding (NoPadding).
     * El mensaje (el Ds_Merchant_Order) se rellena con bytes nulos hasta
     * ser múltiplo de 8 antes de cifrar.
     */
    private function encrypt3DES(string $message, string $key): string
    {
        $remainder = strlen($message) % 8;
        if ($remainder !== 0) {
            $message .= str_repeat("\0", 8 - $remainder);
        }
        $iv        = str_repeat("\0", 8);
        $encrypted = openssl_encrypt(
            $message,
            'DES-EDE3-CBC',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_NO_PADDING,
            $iv
        );
        if ($encrypted === false) {
            throw new RuntimeException('Error en cifrado 3DES (Redsys).');
        }
        return $encrypted;
    }

    /**
     * Genera un Ds_Merchant_Order válido (12 chars: 4 dígitos + 8 alfanum).
     */
    public static function generateOrder(): string
    {
        $prefix = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $suffix = '';
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        for ($i = 0; $i < 8; $i++) {
            $suffix .= $alphabet[random_int(0, 35)];
        }
        return $prefix . $suffix;
    }
}
