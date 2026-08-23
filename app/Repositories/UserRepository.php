<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                u.id,
                u.nome,
                u.email,
                u.senha_hash,
                u.status,
                u.ultimo_login_em
            FROM public.usuarios u
            WHERE LOWER(u.email) = LOWER(:email)
            LIMIT 1
            '
        );

        $statement->execute([
            'email' => $email,
        ]);

        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($user)
            ? $user
            : null;
    }

    public function getRoles(int $userId): array
    {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                r.codigo
            FROM public.roles r

            INNER JOIN public.usuario_roles ur
                ON ur.role_id = r.id

            WHERE ur.usuario_id = :usuario_id
              AND r.ativo = TRUE

            ORDER BY r.codigo
            '
        );

        $statement->execute([
            'usuario_id' => $userId,
        ]);

        $roles = $statement->fetchAll(
            PDO::FETCH_COLUMN
        );

        return array_values(
            array_filter(
                $roles,
                static fn($role): bool =>
                is_string($role)
                    && $role !== ''
            )
        );
    }

    public function updateLastLogin(
        int $userId
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.usuarios
            SET
                ultimo_login_em = NOW(),
                atualizado_em = NOW()
            WHERE id = :id
            '
        );

        $statement->execute([
            'id' => $userId,
        ]);
    }

    public function updatePasswordHash(
        int $userId,
        string $passwordHash
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.usuarios
            SET
                senha_hash = :senha_hash,
                atualizado_em = NOW()
            WHERE id = :id
            '
        );

        $statement->execute([
            'senha_hash' => $passwordHash,
            'id' => $userId,
        ]);
    }
}
