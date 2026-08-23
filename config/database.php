<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'driver' => 'pgsql',

    'host' => Env::required('DB_HOST'),

    'port' => Env::get('DB_PORT', '5432'),

    'database' => Env::required('DB_DATABASE'),

    'username' => Env::required('DB_USERNAME'),

    'password' => Env::required('DB_PASSWORD'),

    'sslmode' => Env::get('DB_SSLMODE', 'prefer'),
];
