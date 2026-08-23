<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\ExceptionHandler;
use App\Core\Router;
use App\Core\SecurityHeaders;
use App\Core\Session;

require dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'vendor'
    . DIRECTORY_SEPARATOR
    . 'autoload.php';

$basePath = dirname(__DIR__);

try {
    /*
     * 1. Ambiente
     */
    Env::load(
        $basePath
    );

    /*
     * 2. Configuração principal
     */
    $appConfig = require $basePath
        . DIRECTORY_SEPARATOR
        . 'config'
        . DIRECTORY_SEPARATOR
        . 'app.php';

    /*
     * Define o timezone de toda a aplicação.
     *
     * Isso afeta:
     * - logs
     * - DateTime
     * - timestamps gerados pelo PHP
     */
    date_default_timezone_set(
        $appConfig['timezone']
    );

    /*
     * 3. Cabeçalhos de segurança
     */
    SecurityHeaders::apply();

    /*
     * 4. Sessão segura
     */
    Session::start();

    /*
     * 5. URL base
     */
    $appUrl = Env::required(
        'APP_URL'
    );

    $appBasePath = parse_url(
        $appUrl,
        PHP_URL_PATH
    );

    if (!is_string($appBasePath)) {
        $appBasePath = '';
    }

    /*
     * 6. Router
     */
    $router = new Router(
        $appBasePath
    );

    /*
     * 7. Rotas
     */
    require $basePath
        . DIRECTORY_SEPARATOR
        . 'routes'
        . DIRECTORY_SEPARATOR
        . 'web.php';

    /*
     * 8. Processa requisição
     */
    $router->dispatch(
        $_SERVER['REQUEST_METHOD']
            ?? 'GET',

        $_SERVER['REQUEST_URI']
            ?? '/'
    );

} catch (Throwable $exception) {

    ExceptionHandler::handle(
        $exception,
        $basePath
    );
}