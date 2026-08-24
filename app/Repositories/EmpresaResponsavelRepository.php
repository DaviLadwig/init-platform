<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class EmpresaResponsavelRepository
{
    /**
     * Lista responsáveis pertencentes a uma empresa.
     */
    public function findByEmpresaId(
        int $empresaId
    ): array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                empresa_id,
                nome,
                email,
                telefone,
                cargo,
                principal,
                ativo,
                criado_em,
                atualizado_em
            FROM public.empresa_responsaveis
            WHERE empresa_id = :empresa_id
            ORDER BY
                principal DESC,
                ativo DESC,
                nome ASC
            '
        );

        $statement->execute([
            'empresa_id' => $empresaId,
        ]);

        $rows = $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

        return is_array($rows)
            ? $rows
            : [];
    }

    /**
     * Verifica se a empresa já possui responsável principal.
     */
    public function hasPrincipal(
        int $empresaId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT 1
            FROM public.empresa_responsaveis
            WHERE empresa_id = :empresa_id
              AND principal = true
            LIMIT 1
            '
        );

        $statement->execute([
            'empresa_id' => $empresaId,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Cadastra um responsável.
     */
    public function create(
        int $empresaId,
        string $nome,
        ?string $email,
        ?string $telefone,
        ?string $cargo,
        bool $principal
    ): int {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            INSERT INTO public.empresa_responsaveis (
                empresa_id,
                nome,
                email,
                telefone,
                cargo,
                principal,
                ativo
            )
            VALUES (
                :empresa_id,
                :nome,
                :email,
                :telefone,
                :cargo,
                :principal,
                true
            )
            RETURNING id
            '
        );

        $statement->execute([
            'empresa_id' => $empresaId,
            'nome' => $nome,
            'email' => $email,
            'telefone' => $telefone,
            'cargo' => $cargo,
            'principal' => $principal,
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'Não foi possível obter o ID do responsável criado.'
            );
        }

        return (int) $id;
    }
}
