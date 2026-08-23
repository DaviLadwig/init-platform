<?php

declare(strict_types=1);

namespace App\Core;

use DateInterval;
use DateTimeImmutable;
use PDO;
use Throwable;

final class RateLimiter
{
    private function __construct()
    {
    }

    /**
     * Verifica se determinado identificador
     * encontra-se temporariamente bloqueado.
     *
     * Exemplo futuro:
     *
     * login.ip
     * login.account
     */
    public static function ensureAllowed(
        string $action,
        string $identifier
    ): void {
        self::validateAction(
            $action
        );

        $keyHash = self::hashIdentifier(
            $action,
            $identifier
        );

        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                blocked_until
            FROM rate_limits
            WHERE action = :action
              AND key_hash = :key_hash
            LIMIT 1
            '
        );

        $statement->execute([
            'action' => $action,
            'key_hash' => $keyHash,
        ]);

        $row = $statement->fetch();

        if (!is_array($row)) {
            return;
        }

        $blockedUntil = $row['blocked_until']
            ?? null;

        if (
            !is_string($blockedUntil)
            || $blockedUntil === ''
        ) {
            return;
        }

        $now = new DateTimeImmutable();

        $blockedDate = new DateTimeImmutable(
            $blockedUntil
        );

        if ($blockedDate > $now) {
            Logger::warning(
                'Requisição limitada por rate limit.',
                [
                    'action' => $action,
                ]
            );

            throw new HttpException(
                429,
                'Muitas tentativas foram realizadas. Aguarde alguns minutos e tente novamente.'
            );
        }
    }

    /**
     * Registra uma tentativa malsucedida.
     *
     * A chamada deve ocorrer somente quando
     * uma operação realmente falhar.
     */
    public static function recordFailure(
        string $action,
        string $identifier,
        int $maxAttempts,
        int $windowSeconds,
        int $blockSeconds
    ): void {
        self::validateAction(
            $action
        );

        self::validateLimits(
            $maxAttempts,
            $windowSeconds,
            $blockSeconds
        );

        $keyHash = self::hashIdentifier(
            $action,
            $identifier
        );

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            /*
             * Garante que a linha exista.
             *
             * ON CONFLICT evita erro caso outra
             * requisição concorrente crie a mesma linha.
             */
            $insert = $pdo->prepare(
                '
                INSERT INTO rate_limits (
                    action,
                    key_hash,
                    attempts,
                    window_started_at,
                    updated_at
                )
                VALUES (
                    :action,
                    :key_hash,
                    0,
                    NOW(),
                    NOW()
                )
                ON CONFLICT (action, key_hash)
                DO NOTHING
                '
            );

            $insert->execute([
                'action' => $action,
                'key_hash' => $keyHash,
            ]);

            /*
             * Bloqueia a linha durante a atualização
             * para evitar corrida entre requisições.
             */
            $select = $pdo->prepare(
                '
                SELECT
                    attempts,
                    window_started_at,
                    blocked_until
                FROM rate_limits
                WHERE action = :action
                  AND key_hash = :key_hash
                FOR UPDATE
                '
            );

            $select->execute([
                'action' => $action,
                'key_hash' => $keyHash,
            ]);

            $row = $select->fetch();

            if (!is_array($row)) {
                throw new \RuntimeException(
                    'Registro de rate limit não encontrado.'
                );
            }

            $now = new DateTimeImmutable();

            /*
             * Se ainda estiver bloqueado,
             * apenas mantemos o estado atual.
             */
            $blockedUntilRaw =
                $row['blocked_until'] ?? null;

            if (
                is_string($blockedUntilRaw)
                && $blockedUntilRaw !== ''
            ) {
                $blockedUntil =
                    new DateTimeImmutable(
                        $blockedUntilRaw
                    );

                if ($blockedUntil > $now) {
                    $pdo->commit();
                    return;
                }
            }

            $windowStartedAt =
                new DateTimeImmutable(
                    (string) $row['window_started_at']
                );

            $windowExpiresAt =
                $windowStartedAt->add(
                    new DateInterval(
                        'PT'
                        . $windowSeconds
                        . 'S'
                    )
                );

            /*
             * Janela expirou:
             * iniciamos nova contagem.
             */
            if ($now >= $windowExpiresAt) {
                $attempts = 1;
                $newWindowStartedAt = $now;
            } else {
                $attempts =
                    ((int) $row['attempts']) + 1;

                $newWindowStartedAt =
                    $windowStartedAt;
            }

            $newBlockedUntil = null;

            if ($attempts >= $maxAttempts) {
                $newBlockedUntil =
                    $now->add(
                        new DateInterval(
                            'PT'
                            . $blockSeconds
                            . 'S'
                        )
                    );
            }

            $update = $pdo->prepare(
                '
                UPDATE rate_limits
                SET
                    attempts = :attempts,
                    window_started_at = :window_started_at,
                    blocked_until = :blocked_until,
                    updated_at = NOW()
                WHERE action = :action
                  AND key_hash = :key_hash
                '
            );

            $update->execute([
                'attempts' =>
                    $attempts,

                'window_started_at' =>
                    $newWindowStartedAt->format(
                        'Y-m-d H:i:sP'
                    ),

                'blocked_until' =>
                    $newBlockedUntil?->format(
                        'Y-m-d H:i:sP'
                    ),

                'action' =>
                    $action,

                'key_hash' =>
                    $keyHash,
            ]);

            $pdo->commit();

            /*
             * Não registramos identifier.
             *
             * Portanto e-mail/IP não aparecem no log.
             */
            if ($newBlockedUntil !== null) {
                Logger::warning(
                    'Limite de tentativas atingido.',
                    [
                        'action' =>
                            $action,

                        'attempts' =>
                            $attempts,
                    ]
                );
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Limpa as tentativas.
     *
     * Será usado, por exemplo, após
     * autenticação bem-sucedida.
     */
    public static function clear(
        string $action,
        string $identifier
    ): void {
        self::validateAction(
            $action
        );

        $keyHash = self::hashIdentifier(
            $action,
            $identifier
        );

        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            DELETE FROM rate_limits
            WHERE action = :action
              AND key_hash = :key_hash
            '
        );

        $statement->execute([
            'action' => $action,
            'key_hash' => $keyHash,
        ]);
    }

    /**
     * Cria chave irreversível para armazenamento.
     *
     * O identificador original não vai para
     * o PostgreSQL.
     */
    private static function hashIdentifier(
        string $action,
        string $identifier
    ): string {
        $secret = Env::required(
            'RATE_LIMIT_SECRET'
        );

        /*
         * Normalização deliberadamente simples.
         *
         * Para e-mail, o AuthService futuramente
         * enviará o valor já normalizado.
         */
        $payload = $action
            . '|'
            . trim($identifier);

        return hash_hmac(
            'sha256',
            $payload,
            $secret
        );
    }

    /**
     * Evita action arbitrária.
     */
    private static function validateAction(
        string $action
    ): void {
        if (
            preg_match(
                '/^[a-z0-9_.-]+$/',
                $action
            ) !== 1
        ) {
            throw new \InvalidArgumentException(
                'Ação de rate limit inválida.'
            );
        }
    }

    /**
     * Validação defensiva das configurações.
     */
    private static function validateLimits(
        int $maxAttempts,
        int $windowSeconds,
        int $blockSeconds
    ): void {
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException(
                'maxAttempts inválido.'
            );
        }

        if ($windowSeconds < 1) {
            throw new \InvalidArgumentException(
                'windowSeconds inválido.'
            );
        }

        if ($blockSeconds < 1) {
            throw new \InvalidArgumentException(
                'blockSeconds inválido.'
            );
        }
    }
}