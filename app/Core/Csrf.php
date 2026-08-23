<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    private function __construct() {}

    /**
     * Retorna o token CSRF atual.
     */
    public static function token(): string
    {
        $token = Session::get(
            self::SESSION_KEY
        );

        if (
            !is_string($token)
            || strlen($token) !== 64
        ) {
            $token = bin2hex(
                random_bytes(32)
            );

            Session::set(
                self::SESSION_KEY,
                $token
            );
        }

        return $token;
    }

    /**
     * Valida um token recebido.
     */
    public static function validate(
        ?string $token
    ): bool {
        if (
            $token === null
            || $token === ''
        ) {
            return false;
        }

        $sessionToken = Session::get(
            self::SESSION_KEY
        );

        if (
            !is_string($sessionToken)
            || $sessionToken === ''
        ) {
            return false;
        }

        return hash_equals(
            $sessionToken,
            $token
        );
    }

    /**
     * Gera novo token CSRF.
     */
    public static function regenerate(): string
    {
        $token = bin2hex(
            random_bytes(32)
        );

        Session::set(
            self::SESSION_KEY,
            $token
        );

        return $token;
    }

    /**
     * Interrompe uma requisição com CSRF inválido.
     */
    public static function enforce(
        ?string $token
    ): void {
        if (!self::validate($token)) {
            throw new HttpException(
                403,
                'A requisição não pôde ser validada.'
            );
        }
    }
}
