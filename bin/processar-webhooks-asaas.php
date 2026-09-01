<?php

declare(strict_types=1);

use App\Integrations\Payments\PaymentGatewayFactory;
use App\Repositories\AsaasWebhookRepository;
use App\Repositories\IntegrationAuditLogRepository;
use App\Services\AsaasWebhookService;
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

try {
    $environment =
        PaymentGatewayFactory::asaasEnvironment();

    $service =
        new AsaasWebhookService(
            new AsaasWebhookRepository(),
            new IntegrationAuditLogRepository(),
            $environment
        );

    $result =
        $service->processPending(
            50
        );

    echo json_encode(
        [
            'timestamp' =>
                date(DATE_ATOM),
            'encontrados' =>
                $result['encontrados']
                ?? 0,
            'processados' =>
                $result['processados']
                ?? 0,
            'duplicados' =>
                $result['duplicados']
                ?? 0,
            'erros' =>
                $result['erros']
                ?? 0,
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );

    echo PHP_EOL;

    exit(
        ((int) (
            $result['erros']
            ?? 0
        )) === 0
            ? 0
            : 2
    );
} catch (\Throwable) {
    fwrite(
        STDERR,
        "Não foi possível processar os webhooks do Asaas.\n"
    );

    exit(1);
}
