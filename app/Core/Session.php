<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Session
{
    private static bool $started = false;

    private function __construct() {}

    /**
     * Inicializa uma sessão segura.
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        if (headers_sent()) {
            throw new RuntimeException(
                'Não foi possível iniciar a sessão porque os headers já foram enviados.'
            );
        }

        $configPath = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'config'
            . DIRECTORY_SEPARATOR
            . 'session.php';

        $config = require $configPath;

        /*
         * PHP aceitará somente IDs de sessão gerados pelo servidor.
         */
        ini_set('session.use_strict_mode', '1');

        /*
         * Impede uso de Session ID pela URL.
         */
        ini_set('session.use_only_cookies', '1');

        /*
         * Não utilizar URL rewriting para propagar sessão.
         */
        ini_set('session.use_trans_sid', '0');

        /*
         * Cookie de sessão não pode ser acessado por JavaScript.
         */
        ini_set(
            'session.cookie_httponly',
            $config['httponly'] ? '1' : '0'
        );

        session_name($config['name']);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $config['path'],
            'domain' => $config['domain'] ?? '',
            'secure' => $config['secure'],
            'httponly' => $config['httponly'],
            'samesite' => $config['samesite'],
        ]);

        if (!session_start()) {
            throw new RuntimeException(
                'Não foi possível iniciar a sessão.'
            );
        }

        self::$started = true;

        /*
         * Controle simples de expiração por inatividade.
         */
        self::validateInactivity(
            (int) $config['lifetime']
        );
    }

    /**
     * Armazena um valor na sessão.
     */
    public static function set(
        string $key,
        mixed $value
    ): void {
        self::ensureStarted();

        $_SESSION[$key] = $value;
    }

    /**
     * Recupera um valor da sessão.
     */
    public static function get(
        string $key,
        mixed $default = null
    ): mixed {
        self::ensureStarted();

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Verifica se uma chave existe.
     */
    public static function has(string $key): bool
    {
        self::ensureStarted();

        return array_key_exists($key, $_SESSION);
    }

    /**
     * Remove uma chave específica.
     */
    public static function remove(string $key): void
    {
        self::ensureStarted();

        unset($_SESSION[$key]);
    }

    /**
     * Regenera o ID da sessão.
     *
     * Será utilizado principalmente após login,
     * alteração de privilégios e operações sensíveis.
     */
    public static function regenerate(): void
    {
        self::ensureStarted();

        if (!session_regenerate_id(true)) {
            throw new RuntimeException(
                'Não foi possível regenerar o ID da sessão.'
            );
        }
    }

    /**
     * Encerra completamente a sessão atual.
     */
    public static function destroy(): void
    {
        self::ensureStarted();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => (bool) $params['secure'],
                    'httponly' => (bool) $params['httponly'],
                    'samesite' => 'Lax',
                ]
            );
        }

        session_destroy();

        self::$started = false;
    }

    /**
     * Controla expiração por inatividade.
     */
    private static function validateInactivity(
        int $lifetime
    ): void {
        $now = time();

        $lastActivity = $_SESSION['_last_activity'] ?? null;

        if (
            is_int($lastActivity)
            && ($now - $lastActivity) > $lifetime
        ) {
            $_SESSION = [];

            session_regenerate_id(true);
        }

        $_SESSION['_last_activity'] = $now;
    }

    /**
     * Garante que a sessão tenha sido inicializada.
     */
    private static function ensureStarted(): void
    {
        if (
            !self::$started
            || session_status() !== PHP_SESSION_ACTIVE
        ) {
            throw new RuntimeException(
                'A sessão ainda não foi inicializada.'
            );
        }
    }
}
