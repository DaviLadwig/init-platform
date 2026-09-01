<?php

declare(strict_types=1);

use App\Integrations\Payments\PaymentGatewayFactory;
use App\Repositories\EmpresaGatewayClienteRepository;
use App\Repositories\IntegrationAuditLogRepository;
use App\Services\EmpresaGatewayClienteService;
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

$empresaIdRaw =
    $argv[1]
    ?? '';

if (
    !is_string($empresaIdRaw)
    || !ctype_digit($empresaIdRaw)
    || (int) $empresaIdRaw <= 0
) {
    fwrite(
        STDERR,
        "Uso: php bin\\vincular-empresa-asaas.php <empresa_id>\n"
    );

    exit(1);
}

try {
    $gateway =
        PaymentGatewayFactory::asaasFromEnvironment();

    $environment =
        PaymentGatewayFactory::asaasEnvironment();

    $service =
        new EmpresaGatewayClienteService(
            new EmpresaGatewayClienteRepository(),
            new IntegrationAuditLogRepository(),
            $gateway,
            $environment
        );

    $result =
        $service->vincularEmpresa(
            (int) $empresaIdRaw
        );

    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRETTY_PRINT
        | JSON_THROW_ON_ERROR
    );

    echo PHP_EOL;

    exit(
        ($result['ok'] ?? false)
            ? 0
            : 2
    );
} catch (\RuntimeException $exception) {
    /*
     * Mensagens desta camada foram desenhadas para não conter
     * API key, headers, CNPJ, e-mail, telefone ou body do Asaas.
     */
    fwrite(
        STDERR,
        $exception->getMessage()
        . PHP_EOL
    );

    exit(1);
} catch (\Throwable) {
    fwrite(
        STDERR,
        "Não foi possível vincular a empresa ao Asaas.\n"
    );

    exit(1);
}
