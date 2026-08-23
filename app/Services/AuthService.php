<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Password;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Repositories\UserRepository;

final class AuthService
{
    /*
     * Rate limit inicial do MVP.
     */
    private const ACCOUNT_MAX_ATTEMPTS = 10;
    private const ACCOUNT_WINDOW_SECONDS = 900;
    private const ACCOUNT_BLOCK_SECONDS = 900;

    private const IP_MAX_ATTEMPTS = 30;
    private const IP_WINDOW_SECONDS = 900;
    private const IP_BLOCK_SECONDS = 1800;

    /*
     * Hash usado apenas para equilibrar o custo
     * quando um e-mail inexistente é informado.
     *
     * Ele é gerado em runtime e nunca representa
     * uma conta real.
     */
    private static ?string $dummyHash = null;

    public function __construct(
        private readonly UserRepository $users
    ) {}

    public function attempt(
        string $email,
        string $password,
        string $ipAddress
    ): bool {
        $email = mb_strtolower(
            trim($email),
            'UTF-8'
        );

        $ipAddress = trim($ipAddress);

        /*
         * Não armazenamos e-mail ou IP diretamente
         * na tabela de rate limit.
         *
         * RateLimiter transforma ambos em HMAC.
         */
        RateLimiter::ensureAllowed(
            'login.ip',
            $ipAddress
        );

        RateLimiter::ensureAllowed(
            'login.account',
            $email
        );

        $user = $this->users->findByEmail(
            $email
        );

        /*
         * Evita diferença grosseira de tempo entre:
         *
         * - e-mail existente;
         * - e-mail inexistente.
         */
        if ($user === null) {
            Password::verify(
                $password,
                self::dummyHash()
            );

            $this->recordFailure(
                $email,
                $ipAddress
            );

            return false;
        }

        $hash = isset($user['senha_hash'])
            && is_string($user['senha_hash'])
            ? $user['senha_hash']
            : '';

        $passwordValid = Password::verify(
            $password,
            $hash
        );

        /*
         * Não informamos se:
         *
         * - e-mail existe;
         * - senha está errada;
         * - usuário está bloqueado.
         *
         * O frontend receberá apenas
         * "credenciais inválidas".
         */
        if (
            !$passwordValid
            || ($user['status'] ?? null) !== 'ATIVO'
        ) {
            $this->recordFailure(
                $email,
                $ipAddress
            );

            return false;
        }

        $userId = (int) $user['id'];

        $roles = $this->users->getRoles(
            $userId
        );

        /*
         * Usuário administrativo sem role válida
         * não poderá entrar no Platform.
         */
        if ($roles === []) {
            Logger::warning(
                'Tentativa de autenticação de usuário sem role ativa.',
                [
                    'usuario_id' => $userId,
                ]
            );

            $this->recordFailure(
                $email,
                $ipAddress
            );

            return false;
        }

        /*
         * Se futuramente alterarmos o custo/algoritmo,
         * atualizamos o hash automaticamente após
         * uma autenticação válida.
         */
        if (Password::needsRehash($hash)) {
            $newHash = Password::hash(
                $password
            );

            $this->users->updatePasswordHash(
                $userId,
                $newHash
            );
        }

        /*
         * Mitiga session fixation.
         */
        Session::regenerate();

        /*
         * Também trocamos o CSRF depois da
         * mudança de estado de autenticação.
         */
        Csrf::regenerate();

        Session::set(
            'auth',
            [
                'user_id' => $userId,

                'name' => (string) $user['nome'],

                'email' => (string) $user['email'],

                'roles' => $roles,

                'authenticated_at' => time(),
            ]
        );

        /*
         * Falhas anteriores deixam de contar
         * após autenticação válida.
         */
        RateLimiter::clear(
            'login.account',
            $email
        );

        RateLimiter::clear(
            'login.ip',
            $ipAddress
        );

        $this->users->updateLastLogin(
            $userId
        );

        Logger::info(
            'Autenticação administrativa realizada.',
            [
                'usuario_id' => $userId,
            ]
        );

        return true;
    }

    public function check(): bool
    {
        $auth = Session::get('auth');

        return is_array($auth)
            && isset($auth['user_id'])
            && is_int($auth['user_id']);
    }

    public function user(): ?array
    {
        $auth = Session::get('auth');

        return is_array($auth)
            ? $auth
            : null;
    }

    public function hasRole(
        string $role
    ): bool {
        $auth = $this->user();

        if ($auth === null) {
            return false;
        }

        $roles = $auth['roles'] ?? null;

        if (!is_array($roles)) {
            return false;
        }

        return in_array(
            $role,
            $roles,
            true
        );
    }

    public function logout(): void
    {
        $auth = $this->user();

        if (
            is_array($auth)
            && isset($auth['user_id'])
        ) {
            Logger::info(
                'Sessão administrativa encerrada.',
                [
                    'usuario_id' =>
                    (int) $auth['user_id'],
                ]
            );
        }

        /*
         * Destrói toda a sessão.
         */
        Session::destroy();
    }

    private function recordFailure(
        string $email,
        string $ipAddress
    ): void {
        RateLimiter::recordFailure(
            'login.account',
            $email,
            self::ACCOUNT_MAX_ATTEMPTS,
            self::ACCOUNT_WINDOW_SECONDS,
            self::ACCOUNT_BLOCK_SECONDS
        );

        RateLimiter::recordFailure(
            'login.ip',
            $ipAddress,
            self::IP_MAX_ATTEMPTS,
            self::IP_WINDOW_SECONDS,
            self::IP_BLOCK_SECONDS
        );

        /*
         * Não registramos:
         *
         * e-mail
         * senha
         * IP
         */
        Logger::warning(
            'Tentativa de autenticação administrativa recusada.'
        );
    }

    private static function dummyHash(): string
    {
        if (self::$dummyHash !== null) {
            return self::$dummyHash;
        }

        self::$dummyHash = Password::hash(
            bin2hex(
                random_bytes(16)
            )
        );

        return self::$dummyHash;
    }
}
