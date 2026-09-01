<?php

declare(strict_types=1);

use App\Integrations\Payments\PaymentGatewayFactory;
use App\Repositories\AsaasWebhookRepository;
use App\Repositories\IntegrationAuditLogRepository;
use App\Services\AsaasWebhookService;
use Dotenv\Dotenv;

$root = dirname(
    __DIR__,
    2
);

require $root
    . DIRECTORY_SEPARATOR
    . 'vendor'
    . DIRECTORY_SEPARATOR
    . 'autoload.php';

$dotenv = Dotenv::createImmutable(
    $root
);

$dotenv->safeLoad();

header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'Cache-Control: no-store'
);

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
) {
    http_response_code(405);

    echo '{"ok":false}';

    exit;
}

$contentType =
    $_SERVER['CONTENT_TYPE']
    ?? '';

if (
    !is_string($contentType)
    || stripos(
        $contentType,
        'application/json'
    ) !== 0
) {
    http_response_code(415);

    echo '{"ok":false}';

    exit;
}

$expectedToken =
    $_ENV['ASAAS_WEBHOOK_TOKEN']
    ?? null;

$receivedToken =
    $_SERVER[
        'HTTP_ASAAS_ACCESS_TOKEN'
    ]
    ?? null;

if (
    !is_string($expectedToken)
    || strlen($expectedToken) < 32
    || !is_string($receivedToken)
    || !hash_equals(
        $expectedToken,
        $receivedToken
    )
) {
    http_response_code(401);

    echo '{"ok":false}';

    exit;
}

$contentLength =
    isset($_SERVER['CONTENT_LENGTH'])
        ? (int) $_SERVER[
            'CONTENT_LENGTH'
        ]
        : 0;

if (
    $contentLength > 262144
) {
    http_response_code(413);

    echo '{"ok":false}';

    exit;
}

$rawBody = file_get_contents(
    'php://input'
);

if (
    !is_string($rawBody)
    || $rawBody === ''
    || strlen($rawBody) > 262144
) {
    http_response_code(400);

    echo '{"ok":false}';

    exit;
}

try {
    $payload = json_decode(
        $rawBody,
        true,
        64,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($payload)) {
        throw new RuntimeException(
            'Payload inválido.'
        );
    }

    $environment =
        PaymentGatewayFactory::asaasEnvironment();

    $service =
        new AsaasWebhookService(
            new AsaasWebhookRepository(),
            new IntegrationAuditLogRepository(),
            $environment
        );

    /*
     * Recebe e RESERVA o evento.
     * A regra financeira não é executada nesta requisição.
     */
    $service->receive(
        $payload
    );

    http_response_code(200);

    echo '{"ok":true}';
} catch (\Throwable) {
    /*
     * Não retornar detalhes internos ao provedor.
     */
    http_response_code(400);

    echo '{"ok":false}';
}
