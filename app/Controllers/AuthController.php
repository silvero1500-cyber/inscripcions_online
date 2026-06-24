<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\LoginThrottle;

final class AuthController
{
    public function showLogin(Request $req): void
    {
        if (Auth::check()) {
            Response::redirect(base_url('/admin'));
        }

        View::render('admin/login', [
            'error'    => Session::pullFlash('login_error'),
            'oldEmail' => Session::pullFlash('login_email'),
        ]);
    }

    public function login(Request $req): void
    {
        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('login_error', 'La sesión ha expirado. Vuelve a intentarlo.');
            Response::redirect(base_url('/admin/login'));
        }

        $email = (string) $req->post('email', '');
        $pass  = (string) $req->post('password', '');

        if ($email === '' || $pass === '') {
            Session::flash('login_error', 'Introduce email y contraseña.');
            Session::flash('login_email', $email);
            Response::redirect(base_url('/admin/login'));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('login_error', 'Email no válido.');
            Session::flash('login_email', $email);
            Response::redirect(base_url('/admin/login'));
        }

        // Throttling anti força bruta (persistent: per email i per IP)
        $ip = $req->ip;
        if (LoginThrottle::blocked($ip, $email)) {
            Session::flash('login_error', 'Demasiados intentos fallidos. Espera unos minutos antes de volver a intentarlo.');
            Session::flash('login_email', $email);
            Response::redirect(base_url('/admin/login'));
        }

        if (!Auth::attempt($email, $pass)) {
            LoginThrottle::recordFailure($ip, $email);
            AuditLog::registrar(AuditLog::LOGIN_FAIL, $email, null, null, $ip);
            Session::flash('login_error', 'Credenciales no válidas.');
            Session::flash('login_email', $email);
            Response::redirect(base_url('/admin/login'));
        }

        LoginThrottle::clearFailures($email);
        AuditLog::registrar(AuditLog::LOGIN_OK, $email, null, null, $ip);

        $next = Session::pullFlash('redirect_after_login');
        Response::redirect(base_url($next ?: '/admin'));
    }

    public function logout(Request $req): void
    {
        if ($req->isPost()) {
            if (!Csrf::verify($req->post('_csrf'))) {
                Response::forbidden();
            }
            Auth::logout();
        }
        Response::redirect(base_url('/admin/login'));
    }
}
