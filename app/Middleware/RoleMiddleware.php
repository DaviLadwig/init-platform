<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Session;

final class RoleMiddleware
{
    /**
     * @param string[] $requiredRoles
     */
    public function __construct(
        private readonly array $requiredRoles
    ) {}

    public function handle(callable $next): void
    {
        $auth = Session::get('auth');

        if (
            !is_array($auth)
            || !isset($auth['roles'])
            || !is_array($auth['roles'])
        ) {
            throw new HttpException(
                403,
                'Você não possui permissão para acessar este recurso.'
            );
        }

        $userRoles = array_values(
            array_filter(
                $auth['roles'],
                static fn($role): bool =>
                is_string($role)
                    && $role !== ''
            )
        );

        foreach ($this->requiredRoles as $requiredRole) {
            if (
                in_array(
                    $requiredRole,
                    $userRoles,
                    true
                )
            ) {
                $next();
                return;
            }
        }

        throw new HttpException(
            403,
            'Você não possui permissão para acessar este recurso.'
        );
    }
}
