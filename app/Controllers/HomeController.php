<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Env;
use App\Core\Session;

final class HomeController
{
    public function index(): void
    {
        /*
         * AuthMiddleware + RoleMiddleware
         * já validaram acesso.
         */
        $activeMenu = 'dashboard';

        $auth = Session::get('auth');

        $userName = is_array($auth)
            && isset($auth['name'])
            && is_string($auth['name'])
            ? $auth['name']
            : 'Usuário';

        $roles = is_array($auth)
            && isset($auth['roles'])
            && is_array($auth['roles'])
            ? $auth['roles']
            : [];

        $userRole = implode(
            ', ',
            $roles
        );

        $csrfToken = Csrf::token();

        $appUrl = rtrim(
            Env::required('APP_URL'),
            '/'
        );

        $title = 'Dashboard';

        /*
         * Renderiza conteúdo da página.
         */
        ob_start();

        require dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'views'
            . DIRECTORY_SEPARATOR
            . 'dashboard'
            . DIRECTORY_SEPARATOR
            . 'index.php';

        $content = ob_get_clean();

        if (!is_string($content)) {
            $content = '';
        }

        /*
         * Renderiza dentro do layout principal.
         */
        require dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'views'
            . DIRECTORY_SEPARATOR
            . 'layouts'
            . DIRECTORY_SEPARATOR
            . 'main.php';
    }
}
