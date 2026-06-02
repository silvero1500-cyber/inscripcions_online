<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;

final class LangController
{
    /**
     * Canvia l'idioma i redirigeix a on l'usuari estava.
     * GET o POST: /lang/{code}?ret=/eventos/algo
     */
    public function switch(Request $req, array $params): void
    {
        $code = strtolower((string) ($params['code'] ?? ''));
        if (in_array($code, Lang::ALLOWED_LOCALES, true)) {
            Lang::setCookie($code);
        }
        $ret = (string) $req->query('ret', '/');
        // Validar que el redirect sigui local (mai a domini extern)
        if (!str_starts_with($ret, '/') || str_starts_with($ret, '//')) {
            $ret = '/';
        }
        Response::redirect(base_url($ret));
    }
}
