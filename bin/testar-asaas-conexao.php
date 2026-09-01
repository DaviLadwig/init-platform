<?php

declare(strict_types=1);

use App\Integrations\Payments\PaymentGatewayFactory;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);

require $root
    . DIRECTORY_SEPARATOR
    . 'vendor'
    . DIRECTORY_SEPARATOR
    . 'autoload.php';

$dotenv = Dotenv::createImmutable(
    $root
);

$dotenv->safeLoad();

try {
    $gateway =
        PaymentGatewayFactory::asaasFromEnvironment();

    $connected =
        $gateway->testConnection();

    echo json_encode(
        [
            'ok' => $connected,
            'provider' =>
                $gateway->provider(),
            'environment' =>
                $_ENV['ASAAS_ENV']
                ?? null,
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRETTY_PRINT
        | JSON_THROW_ON_ERROR
    );

    echo PHP_EOL;

    exit(
        $connected
            ? 0
            : 1
    );
} catch (\Throwable) {
    /*
     * Nunca imprimir API key, response body completo
     * ou mensagem de exceção potencialmente sensível.
     */
    fwrite(
        STDERR,
        "Não foi possível validar a conexão com o Asaas.\n"
    );

    exit(1);
}
