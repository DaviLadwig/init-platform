<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Env;
use App\Core\Session;

final class AuthMiddleware
{
    public function handle(callable $next): void
    {
        $auth = Session::get('auth');

        if (
            !is_array($auth)
            || !isset($auth['user_id'])
            || !is_int($auth['user_id'])
        ) {
            $this->redirectToLogin();
        }

        $next();
    }

    private function redirectToLogin(): never
    {
        $appUrl = rtrim(
            Env::required('APP_URL'),
            '/'
        );

        header(
            'Location: '
                . $appUrl
                . '/login',
            true,
            302
        );

        exit;
    }
}
