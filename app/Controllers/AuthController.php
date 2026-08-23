<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Env;
use App\Repositories\UserRepository;
use App\Services\AuthService;

final class AuthController
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService(
            new UserRepository()
        );
    }

    /**
     * Exibe o formulário de login.
     */
    public function showLogin(): void
    {
        if ($this->auth->check()) {
            $this->redirect('/');
        }

        $error = null;

        $csrfToken = Csrf::token();

        $appUrl = rtrim(
            Env::required('APP_URL'),
            '/'
        );

        $viewPath = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'views'
            . DIRECTORY_SEPARATOR
            . 'auth'
            . DIRECTORY_SEPARATOR
            . 'login.php';

        require $viewPath;
    }

    /**
     * Processa o login.
     */
    public function login(): void
    {
        $csrfToken = $_POST['_token']
            ?? null;

        Csrf::enforce(
            is_string($csrfToken)
                ? $csrfToken
                : null
        );

        $email = $_POST['email']
            ?? '';

        if (!is_string($email)) {
            $email = '';
        }

        $email = trim($email);

        $password = $_POST['password']
            ?? '';

        if (!is_string($password)) {
            $password = '';
        }

        $ipAddress = $_SERVER['REMOTE_ADDR']
            ?? 'unknown';

        if (!is_string($ipAddress)) {
            $ipAddress = 'unknown';
        }

        if (
            mb_strlen($email, 'UTF-8') > 255
            || strlen($password) > 1024
        ) {
            $password = '';

            $this->renderInvalidLogin();

            return;
        }

        $authenticated = $this->auth->attempt(
            $email,
            $password,
            $ipAddress
        );

        /*
         * Descarta a senha assim que possível.
         */
        $password = '';

        if (!$authenticated) {
            $this->renderInvalidLogin();

            return;
        }

        /*
         * 303 após POST.
         */
        $this->redirect(
            '/',
            303
        );
    }

    /**
     * Encerra uma sessão autenticada.
     */
    public function logout(): void
    {
        $csrfToken = $_POST['_token']
            ?? null;

        /*
         * Logout também altera estado.
         * Portanto exige CSRF válido.
         */
        Csrf::enforce(
            is_string($csrfToken)
                ? $csrfToken
                : null
        );

        $this->auth->logout();

        /*
         * A sessão foi destruída.
         * Na próxima requisição, uma nova
         * sessão será criada normalmente.
         */
        $this->redirect(
            '/login',
            303
        );
    }

    /**
     * Reexibe login com erro genérico.
     */
    private function renderInvalidLogin(): void
    {
        $error = 'E-mail ou senha inválidos.';

        $csrfToken = Csrf::token();

        $appUrl = rtrim(
            Env::required('APP_URL'),
            '/'
        );

        http_response_code(401);

        $viewPath = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'views'
            . DIRECTORY_SEPARATOR
            . 'auth'
            . DIRECTORY_SEPARATOR
            . 'login.php';

        require $viewPath;
    }

    /**
     * Redirecionamento interno controlado.
     */
    private function redirect(
        string $path,
        int $statusCode = 302
    ): never {
        $appUrl = rtrim(
            Env::required('APP_URL'),
            '/'
        );

        $path = '/'
            . ltrim(
                $path,
                '/'
            );

        header(
            'Location: '
                . $appUrl
                . $path,
            true,
            $statusCode
        );

        exit;
    }
}
