<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class ClienteRepository
{
    /**
     * Lista os clientes e resume suas assinaturas.
     */
    public function all(): array
    {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            "
            SELECT
                e.id,
                e.razao_social,
                e.nome_fantasia,
                e.cnpj,
                e.email,
                e.telefone,
                e.slug,
                e.status,
                e.criado_em,

                COUNT(DISTINCT a.id) AS total_assinaturas,

                COUNT(DISTINCT a.id)
                    FILTER (
                        WHERE a.status = 'ATIVA'
                    ) AS assinaturas_ativas,

                COUNT(DISTINCT a.id)
                    FILTER (
                        WHERE a.status = 'TRIAL'
                    ) AS assinaturas_trial,

                COUNT(DISTINCT a.id)
                    FILTER (
                        WHERE a.status = 'ATRASADA'
                    ) AS assinaturas_atrasadas,

                COUNT(DISTINCT a.id)
                    FILTER (
                        WHERE a.status = 'SUSPENSA'
                    ) AS assinaturas_suspensas,

                COUNT(DISTINCT a.id)
                    FILTER (
                        WHERE a.status = 'PENDENTE_ATIVACAO'
                    ) AS assinaturas_pendentes,

                COUNT(DISTINCT a.produto_id)
                    FILTER (
                        WHERE a.status <> 'CANCELADA'
                    ) AS total_produtos,

                STRING_AGG(
                    DISTINCT p.nome,
                    ', '
                ) FILTER (
                    WHERE
                        p.id IS NOT NULL
                        AND a.status <> 'CANCELADA'
                ) AS produtos

            FROM public.empresas e

            LEFT JOIN public.assinaturas a
                ON a.empresa_id = e.id

            LEFT JOIN public.produtos p
                ON p.id = a.produto_id

            GROUP BY
                e.id,
                e.razao_social,
                e.nome_fantasia,
                e.cnpj,
                e.email,
                e.telefone,
                e.slug,
                e.status,
                e.criado_em

            ORDER BY
                e.razao_social ASC
            "
        );

        $statement->execute();

        $rows = $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

        return is_array($rows)
            ? $rows
            : [];
    }

    /**
     * Verifica duplicidade de CNPJ.
     */
    public function existsByCnpj(
        string $cnpj
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT 1
            FROM public.empresas
            WHERE cnpj = :cnpj
            LIMIT 1
            '
        );

        $statement->execute([
            'cnpj' => $cnpj,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Verifica duplicidade de slug.
     */
    public function existsBySlug(
        string $slug
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT 1
            FROM public.empresas
            WHERE slug = :slug
            LIMIT 1
            '
        );

        $statement->execute([
            'slug' => $slug,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Cadastra nova empresa contratante.
     */
    public function create(
        string $razaoSocial,
        ?string $nomeFantasia,
        string $cnpj,
        ?string $email,
        ?string $telefone,
        string $slug
    ): int {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            "
            INSERT INTO public.empresas (
                razao_social,
                nome_fantasia,
                cnpj,
                email,
                telefone,
                slug,
                status
            )
            VALUES (
                :razao_social,
                :nome_fantasia,
                :cnpj,
                :email,
                :telefone,
                :slug,
                'ATIVA'
            )
            RETURNING id
            "
        );

        $statement->execute([
            'razao_social' => $razaoSocial,
            'nome_fantasia' => $nomeFantasia,
            'cnpj' => $cnpj,
            'email' => $email,
            'telefone' => $telefone,
            'slug' => $slug,
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'Não foi possível obter o ID do cliente criado.'
            );
        }

        return (int) $id;
    }
    /**
 * Busca um cliente pelo ID.
 */
public function findById(
    int $id
): ?array {
    $pdo = Database::connection();

    $statement = $pdo->prepare(
        '
        SELECT
            id,
            razao_social,
            nome_fantasia,
            cnpj,
            email,
            telefone,
            slug,
            status,
            criado_em,
            atualizado_em
        FROM public.empresas
        WHERE id = :id
        LIMIT 1
        '
    );

    $statement->execute([
        'id' => $id,
    ]);

    $row = $statement->fetch(
        PDO::FETCH_ASSOC
    );

    return is_array($row)
        ? $row
        : null;
}


/**
 * Verifica CNPJ duplicado ignorando
 * o próprio cliente.
 */
public function existsByCnpjExceptId(
    string $cnpj,
    int $clienteId
): bool {
    $pdo = Database::connection();

    $statement = $pdo->prepare(
        '
        SELECT 1
        FROM public.empresas
        WHERE cnpj = :cnpj
          AND id <> :cliente_id
        LIMIT 1
        '
    );

    $statement->execute([
        'cnpj' => $cnpj,
        'cliente_id' => $clienteId,
    ]);

    return $statement->fetchColumn() !== false;
}


/**
 * Verifica slug duplicado ignorando
 * o próprio cliente.
 */
public function existsBySlugExceptId(
    string $slug,
    int $clienteId
): bool {
    $pdo = Database::connection();

    $statement = $pdo->prepare(
        '
        SELECT 1
        FROM public.empresas
        WHERE slug = :slug
          AND id <> :cliente_id
        LIMIT 1
        '
    );

    $statement->execute([
        'slug' => $slug,
        'cliente_id' => $clienteId,
    ]);

    return $statement->fetchColumn() !== false;
}


/**
 * Atualiza somente os dados cadastrais.
 *
 * Status não é alterado aqui.
 */
public function update(
    int $id,
    string $razaoSocial,
    ?string $nomeFantasia,
    string $cnpj,
    ?string $email,
    ?string $telefone,
    string $slug
): void {
    $pdo = Database::connection();

    $statement = $pdo->prepare(
        '
        UPDATE public.empresas
        SET
            razao_social = :razao_social,
            nome_fantasia = :nome_fantasia,
            cnpj = :cnpj,
            email = :email,
            telefone = :telefone,
            slug = :slug,
            atualizado_em = NOW()
        WHERE id = :id
        '
    );

    $statement->execute([
        'id' => $id,
        'razao_social' => $razaoSocial,
        'nome_fantasia' => $nomeFantasia,
        'cnpj' => $cnpj,
        'email' => $email,
        'telefone' => $telefone,
        'slug' => $slug,
    ]);

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException(
            'O cliente não pôde ser atualizado.'
        );
    }
}
}