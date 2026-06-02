<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'host'     => (string) Env::get('DB_HOST', 'localhost'),
    'port'     => (int)    Env::get('DB_PORT', 3306),
    'database' => (string) Env::require('DB_NAME'),
    'user'     => (string) Env::require('DB_USER'),
    'password' => (string) Env::require('DB_PASS'),
];
