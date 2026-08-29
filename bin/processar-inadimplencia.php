<?php

declare(strict_types=1);

use App\Repositories\AssinaturaPagamentoRepository;
use App\Repositories\AssinaturaRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\SystemAuditLogRepository;
use App\Services\AssinaturaService;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root =
    dirname(
        __DIR__
    );

require $root
    . DIRECTORY_SEPARATOR
    . 'vendor'
    . DIRECTORY_SEPARATOR
    . 'autoload.php';

Dotenv::createImmutable(
    $root
)->safeLoad();

$timezone =
    $_ENV['APP_TIMEZONE']
    ?? $_ENV['TIMEZONE']
    ?? 'America/Fortaleza';

if (
    !is_string($timezone)
    || !in_array(
        $timezone,
        timezone_identifiers_list(),
        true
    )
) {
    fwrite(
        STDERR,
        "Timezone inválido para o job financeiro.\n"
    );

    exit(1);
}

date_default_timezone_set(
    $timezone
);

try {
    $service =
        new AssinaturaService(
            new AssinaturaRepository(),
            new AssinaturaPagamentoRepository(),
            new AuditLogRepository(),
            new SystemAuditLogRepository()
        );

    $result =
        $service
            ->processarInadimplenciaAutomatica();

    /*
     * Saída deliberadamente mínima e sem dados de cliente,
     * SQL, credenciais ou payload financeiro.
     */
    $summary = [
        'timestamp' =>
            date(
                DATE_ATOM
            ),

        'executado' =>
            (bool) (
                $result['executado']
                ?? false
            ),

        'atrasadas' =>
            (int) (
                $result['atrasadas']
                ?? 0
            ),

        'suspensas' =>
            (int) (
                $result['suspensas']
                ?? 0
            ),

        'falhas' =>
            is_array(
                $result['falhas']
                ?? null
            )
                ? count(
                    $result['falhas']
                )
                : 0,
    ];

    echo json_encode(
        $summary,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    )
        . PHP_EOL;

    if (
        ($result['executado'] ?? false)
        !== true
    ) {
        /*
         * Outra execução já está em andamento.
         * Não é falha financeira.
         */
        exit(0);
    }

    exit(
        ($result['success'] ?? false)
            === true
                ? 0
                : 2
    );
} catch (Throwable $exception) {
    /*
     * Nunca imprimir exception message aqui:
     * mensagens de PDO podem conter detalhes internos.
     */
    fwrite(
        STDERR,
        json_encode(
            [
                'timestamp' =>
                    date(
                        DATE_ATOM
                    ),

                'status' =>
                    'ERRO',

                'tipo' =>
                    $exception::class,
            ],
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        )
            . PHP_EOL
    );

    exit(1);
}
