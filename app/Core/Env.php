<?php

declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;
use RuntimeException;

final class Env
{
    private static bool $loaded = false;

    private function __construct()
    {
    }

    /**
     * Carrega o arquivo .env da raiz do projeto.
     */
    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        $envFile = $basePath . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($envFile)) {
            throw new RuntimeException(
                'Arquivo de configuração de ambiente não encontrado.'
            );
        }

        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->load();

        self::$loaded = true;
    }

    /**
     * Recupera uma variável de ambiente.
     */
    public static function get(
        string $key,
        ?string $default = null
    ): ?string {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    /**
     * Recupera uma variável obrigatória.
     */
    public static function required(string $key): string
    {
        $value = self::get($key);

        if ($value === null) {
            throw new RuntimeException(
                sprintf(
                    'Variável de ambiente obrigatória "%s" não configurada.',
                    $key
                )
            );
        }

        return $value;
    }
}