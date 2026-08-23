<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ClienteRepository
{
    /**
     * Lista empresas e resume sua situação comercial.
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
}