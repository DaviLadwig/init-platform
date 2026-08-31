<?php

declare(strict_types=1);

/*
 * Diagnóstico local seguro do storage de contratos.
 *
 * Uso:
 * php bin/verificar-storage-contratos.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot =
    dirname(
        __DIR__
    );

$autoload =
    $projectRoot
    . DIRECTORY_SEPARATOR
    . 'vendor'
    . DIRECTORY_SEPARATOR
    . 'autoload.php';

if (!is_file($autoload)) {
    fwrite(
        STDERR,
        "Autoload do Composer não encontrado.\n"
    );

    exit(1);
}

require $autoload;

use App\Services\ContratoStorageService;

try {
    $storage =
        new ContratoStorageService(
            $projectRoot
        );

    $result =
        $storage->healthCheck();

    $healthy =
        ($result['contracts_directory_exists'] ?? false) === true
        && ($result['contracts_directory_writable'] ?? false) === true
        && ($result['outside_public'] ?? false) === true
        && ($result['fileinfo_available'] ?? false) === true;

    $output = [
        'ok' =>
            $healthy,

        'contracts_directory_exists' =>
            (bool) (
                $result['contracts_directory_exists']
                ?? false
            ),

        'contracts_directory_writable' =>
            (bool) (
                $result['contracts_directory_writable']
                ?? false
            ),

        'outside_public' =>
            (bool) (
                $result['outside_public']
                ?? false
            ),

        'fileinfo_available' =>
            (bool) (
                $result['fileinfo_available']
                ?? false
            ),

        'max_upload_mb' =>
            (int) (
                $result['max_upload_mb']
                ?? 0
            ),
    ];

    echo json_encode(
        $output,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    echo PHP_EOL;

    exit(
        $healthy
            ? 0
            : 2
    );
} catch (Throwable) {
    /*
     * Não imprimir mensagem interna de exceção.
     * Paths/ambiente podem conter detalhes que não precisamos expor.
     */
    echo json_encode(
        [
            'ok' => false,
            'error' => 'STORAGE_CHECK_FAILED',
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    echo PHP_EOL;

    exit(1);
}
