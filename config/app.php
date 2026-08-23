<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get(
        'APP_NAME',
        'Init SaaS Platform'
    ),

    'environment' => Env::get(
        'APP_ENV',
        'production'
    ),

    'debug' => Env::get(
        'APP_DEBUG',
        'false'
    ) === 'true',

    'url' => Env::required(
        'APP_URL'
    ),

    'timezone' => Env::get(
        'APP_TIMEZONE',
        'UTC'
    ),
];
