<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use RuntimeException;

/**
 * Sube imágenes de forma segura validando MIME real (finfo), tamaño y extensión.
 * Renombra a un nombre aleatorio sin información del original.
 */
final class ImageUploader
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    /**
     * Procesa un $_FILES[$key] y devuelve la ruta relativa pública (ej. "eventos/abc123.jpg").
     * Devuelve null si no se subió ningún fichero.
     * Lanza RuntimeException si la subida es inválida.
     *
     * @return string|null Ruta relativa dentro de /public/uploads/
     */
    public static function handleEventImage(array $file, string $subdir = 'eventos'): ?string
    {
        // No se subió fichero
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error en la pujada: codi ' . $file['error']);
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Fitxer no és una pujada vàlida.');
        }

        $maxSize = (int) Env::get('UPLOAD_MAX_SIZE_MB', 5) * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new RuntimeException('Mida màxima superada (' . Env::get('UPLOAD_MAX_SIZE_MB', 5) . ' MB).');
        }

        // Detectar MIME real del fichero (no fiarse del header del cliente)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw new RuntimeException('Tipus d\'imatge no permès. Usa JPG, PNG, WEBP o GIF.');
        }

        $ext = self::ALLOWED_MIME[$mime];
        $newName = bin2hex(random_bytes(16)) . '.' . $ext;

        $relDir  = trim($subdir, '/');
        $absDir  = dirname(__DIR__, 2) . '/public/uploads/' . $relDir;
        $absPath = $absDir . '/' . $newName;

        if (!is_dir($absDir)) {
            if (!mkdir($absDir, 0755, true) && !is_dir($absDir)) {
                throw new RuntimeException('No s\'ha pogut crear la carpeta de pujades.');
            }
        }

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            throw new RuntimeException('No s\'ha pogut moure el fitxer pujat.');
        }

        // Permisos restrictivos
        @chmod($absPath, 0644);

        return $relDir . '/' . $newName;
    }

    public static function deleteEventImage(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') return;
        // Sanitizar: solo permitir formato eventos/abc.ext
        if (!preg_match('#^[a-z0-9_-]+/[a-f0-9]{32}\.(jpg|png|webp|gif)$#i', $relativePath)) {
            return;
        }
        $abs = dirname(__DIR__, 2) . '/public/uploads/' . $relativePath;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    public static function publicUrl(?string $relativePath): ?string
    {
        if ($relativePath === null || $relativePath === '') return null;
        return rtrim((string) Env::get('APP_URL'), '/') . '/public/uploads/' . ltrim($relativePath, '/');
    }
}
