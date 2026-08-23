<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class ExceptionHandler
{
    private function __construct() {}

    public static function handle(
        Throwable $exception,
        string $basePath
    ): void {
        $statusCode = 500;

        $publicMessage =
            'Ocorreu um erro interno.';

        $errorReference = null;

        /*
         * Erros HTTP controlados.
         *
         * Exemplo:
         * 403
         * 404
         */
        if (
            $exception instanceof HttpException
        ) {
            $statusCode =
                $exception->getStatusCode();

            $publicMessage =
                $exception->getPublicMessage();
        } else {
            /*
             * Erro inesperado.
             *
             * Criamos uma referência que será
             * mostrada ao usuário e registrada
             * internamente.
             */
            $errorReference = bin2hex(
                random_bytes(8)
            );

            Logger::exception(
                $exception,
                $errorReference
            );
        }

        http_response_code(
            $statusCode
        );

        header(
            'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
        );

        header(
            'Pragma: no-cache'
        );

        header(
            'Content-Type: text/html; charset=UTF-8'
        );

        $viewPath = $basePath
            . DIRECTORY_SEPARATOR
            . 'views'
            . DIRECTORY_SEPARATOR
            . 'errors'
            . DIRECTORY_SEPARATOR
            . $statusCode
            . '.php';

        if (is_file($viewPath)) {
            require $viewPath;
            return;
        }

        /*
         * Fallback seguro caso alguma view
         * de erro não exista.
         */
        echo '<!DOCTYPE html>';
        echo '<html lang="pt-BR">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<title>Erro</title>';
        echo '</head>';

        echo '<body>';
        echo '<h1>Erro</h1>';

        echo '<p>';

        echo htmlspecialchars(
            $publicMessage,
            ENT_QUOTES,
            'UTF-8'
        );

        echo '</p>';
        echo '</body>';
        echo '</html>';
    }
}
