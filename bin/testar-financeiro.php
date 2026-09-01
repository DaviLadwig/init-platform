<?php

declare(strict_types=1);

use App\Repositories\FinanceiroRepository;
use App\Services\FinanceiroService;
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
    $service = new FinanceiroService(
        new FinanceiroRepository()
    );

    $result = $service
        ->obterVisaoGeral(12);

    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRETTY_PRINT
        | JSON_THROW_ON_ERROR
    );

    echo PHP_EOL;

    exit(0);
} catch (\Throwable) {
    /*
     * Não exibimos a mensagem da exceção no terminal.
     * Erros PDO podem revelar SQL, schema ou outros detalhes internos.
     */
    fwrite(
        STDERR,
        "Não foi possível calcular a visão financeira.\n"
    );

    exit(1);
}
