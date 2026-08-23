<?php

declare(strict_types=1);

namespace App\Core;

use JsonException;
use Throwable;

final class Logger
{
    /**
     * Campos que jamais devem aparecer nos logs.
     */
    private const SENSITIVE_KEYS = [
        'password',
        'senha',
        'senha_hash',
        'passwd',

        'token',
        '_token',
        'csrf',
        'csrf_token',

        'access_token',
        'refresh_token',
        'id_token',

        'authorization',
        'cookie',
        'set-cookie',

        'secret',
        'client_secret',
        'api_key',
        'apikey',

        'database_password',
        'db_password',
        'db_pass',

        'private_key',
        'secret_key',
    ];

    private function __construct() {}

    /**
     * Registra informação.
     */
    public static function info(
        string $message,
        array $context = []
    ): void {
        self::write(
            'INFO',
            $message,
            $context
        );
    }

    /**
     * Registra aviso.
     */
    public static function warning(
        string $message,
        array $context = []
    ): void {
        self::write(
            'WARNING',
            $message,
            $context
        );
    }

    /**
     * Registra erro.
     */
    public static function error(
        string $message,
        array $context = []
    ): void {
        self::write(
            'ERROR',
            $message,
            $context
        );
    }

    /**
     * Registra exceção de forma controlada.
     *
     * Não registra:
     * - $_POST
     * - $_GET
     * - $_COOKIE
     * - $_SESSION
     * - headers completos
     * - stack trace completo
     */
    public static function exception(
        Throwable $exception,
        string $reference
    ): void {
        self::error(
            'Exceção não tratada.',
            [
                'error_reference' => $reference,

                'exception_type' =>
                get_class($exception),

                /*
                 * A mensagem ainda passa pelo filtro
                 * de strings sensíveis.
                 */
                'exception_message' =>
                self::sanitizeString(
                    $exception->getMessage()
                ),

                'exception_code' =>
                $exception->getCode(),

                /*
                 * O caminho completo não será armazenado.
                 */
                'file' =>
                basename(
                    $exception->getFile()
                ),

                'line' =>
                $exception->getLine(),
            ]
        );
    }

    /**
     * Escreve efetivamente no arquivo.
     */
    private static function write(
        string $level,
        string $message,
        array $context
    ): void {
        $logDirectory = dirname(
            __DIR__,
            2
        )
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'logs';

        if (!is_dir($logDirectory)) {
            mkdir(
                $logDirectory,
                0750,
                true
            );
        }

        $logFile = $logDirectory
            . DIRECTORY_SEPARATOR
            . 'app.log';

        $sanitizedContext = self::sanitizeArray(
            $context
        );

        $record = [
            'timestamp' =>
            date('c'),

            'level' =>
            strtoupper($level),

            'message' =>
            self::sanitizeString(
                $message
            ),

            'context' =>
            $sanitizedContext,
        ];

        try {
            $json = json_encode(
                $record,
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException) {
            /*
             * Fallback mínimo.
             *
             * Não incluímos o conteúdo original
             * que causou falha no JSON.
             */
            $json = sprintf(
                '{"timestamp":"%s","level":"ERROR","message":"Falha ao serializar log."}',
                date('c')
            );
        }

        file_put_contents(
            $logFile,
            $json . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * Sanitiza arrays recursivamente.
     */
    private static function sanitizeArray(
        array $data
    ): array {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower(
                (string) $key
            );

            if (
                self::isSensitiveKey(
                    $normalizedKey
                )
            ) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] =
                    self::sanitizeArray(
                        $value
                    );

                continue;
            }

            if (is_string($value)) {
                $sanitized[$key] =
                    self::sanitizeString(
                        $value
                    );

                continue;
            }

            if (
                is_int($value)
                || is_float($value)
                || is_bool($value)
                || $value === null
            ) {
                $sanitized[$key] = $value;
                continue;
            }

            /*
             * Objetos/resources não são
             * serializados diretamente.
             */
            $sanitized[$key] =
                '[UNSUPPORTED_TYPE]';
        }

        return $sanitized;
    }

    /**
     * Verifica se uma chave é sensível.
     */
    private static function isSensitiveKey(
        string $key
    ): bool {
        foreach (
            self::SENSITIVE_KEYS
            as $sensitiveKey
        ) {
            if ($key === $sensitiveKey) {
                return true;
            }

            /*
             * Também protege campos como:
             *
             * usuario_password
             * gateway_secret
             * db_password_confirmation
             */
            if (
                str_contains(
                    $key,
                    $sensitiveKey
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove padrões sensíveis que possam
     * aparecer dentro de strings.
     */
    private static function sanitizeString(
        string $value
    ): string {
        /*
         * Authorization: Bearer xxxxx
         */
        $value = preg_replace(
            '/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i',
            'Bearer [REDACTED]',
            $value
        ) ?? '[REDACTED]';

        /*
         * password=xxxxx
         * senha=xxxxx
         * token=xxxxx
         * secret=xxxxx
         */
        $value = preg_replace(
            '/\b(password|passwd|senha|token|secret|api[_-]?key|client[_-]?secret|db[_-]?password)\s*[=:]\s*[^\s;,]+/i',
            '$1=[REDACTED]',
            $value
        ) ?? '[REDACTED]';

        return $value;
    }
}
