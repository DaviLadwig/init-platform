<?php

declare(strict_types=1);

use App\Integrations\Payments\PaymentGatewayFactory;
use App\Repositories\AssinaturaGatewayRepository;
use App\Repositories\IntegrationAuditLogRepository;
use App\Services\AssinaturaGatewayService;
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

$timezoneName =
    $_ENV['APP_TIMEZONE']
    ?? $_ENV['TIMEZONE']
    ?? 'America/Fortaleza';

if (
    !is_string($timezoneName)
    || !in_array(
        $timezoneName,
        timezone_identifiers_list(),
        true
    )
) {
    fwrite(
        STDERR,
        "Timezone inválido.\n"
    );

    exit(1);
}

date_default_timezone_set(
    $timezoneName
);

$assinaturaIdRaw =
    $argv[1]
    ?? '';

if (
    !is_string($assinaturaIdRaw)
    || !ctype_digit($assinaturaIdRaw)
    || (int) $assinaturaIdRaw <= 0
) {
    fwrite(
        STDERR,
        "Uso: php bin\\vincular-assinatura-asaas.php <assinatura_id>\n"
    );

    exit(1);
}

try {
    $gateway =
        PaymentGatewayFactory::asaasFromEnvironment();

    $environment =
        PaymentGatewayFactory::asaasEnvironment();

    $service =
        new AssinaturaGatewayService(
            new AssinaturaGatewayRepository(),
            new IntegrationAuditLogRepository(),
            $gateway,
            $environment
        );

    $result =
        $service->vincularAssinatura(
            (int) $assinaturaIdRaw
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
    fwrite(
        STDERR,
        $exception->getMessage()
        . PHP_EOL
    );

    exit(1);
} catch (\Throwable) {
    fwrite(
        STDERR,
        "Não foi possível vincular a assinatura ao Asaas.\n"
    );

    exit(1);
}
