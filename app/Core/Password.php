<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Password
{
    private function __construct() {}

    /**
     * Gera hash seguro para armazenamento.
     */
    public static function hash(string $password): string
    {
        self::validate($password);

        if (!defined('PASSWORD_ARGON2ID')) {
            throw new RuntimeException(
                'Argon2id não está disponível nesta instalação do PHP.'
            );
        }

        $hash = password_hash(
            $password,
            PASSWORD_ARGON2ID
        );

        if (!is_string($hash) || $hash === '') {
            throw new RuntimeException(
                'Não foi possível gerar o hash da senha.'
            );
        }

        return $hash;
    }

    /**
     * Verifica uma senha contra um hash armazenado.
     */
    public static function verify(
        string $password,
        string $hash
    ): bool {
        if ($password === '' || $hash === '') {
            return false;
        }

        return password_verify(
            $password,
            $hash
        );
    }

    /**
     * Verifica se um hash precisa ser atualizado.
     */
    public static function needsRehash(
        string $hash
    ): bool {
        if (!defined('PASSWORD_ARGON2ID')) {
            return false;
        }

        return password_needs_rehash(
            $hash,
            PASSWORD_ARGON2ID
        );
    }

    /**
     * Política inicial de senha administrativa.
     */
    private static function validate(
        string $password
    ): void {
        $length = mb_strlen(
            $password,
            'UTF-8'
        );

        if ($length < 12) {
            throw new RuntimeException(
                'A senha deve possuir pelo menos 12 caracteres.'
            );
        }

        if ($length > 128) {
            throw new RuntimeException(
                'A senha excede o tamanho permitido.'
            );
        }
    }
}
