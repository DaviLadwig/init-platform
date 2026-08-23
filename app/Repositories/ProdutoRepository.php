<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class ProdutoRepository
{
    /**
     * Lista todos os produtos.
     */
    public function all(): array
    {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                p.id,
                p.codigo,
                p.nome,
                p.slug,
                p.descricao,

                CASE
                    WHEN p.ativo = TRUE THEN 1
                    ELSE 0
                END AS ativo,

                p.criado_em,
                p.atualizado_em,

                (
                    SELECT COUNT(*)
                    FROM public.planos pl
                    WHERE pl.produto_id = p.id
                ) AS total_planos

            FROM public.produtos p

            ORDER BY
                p.ativo DESC,
                p.nome ASC
            '
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
     * Busca um produto pelo ID.
     */
    public function findById(
        int $id
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                codigo,
                nome,
                slug,
                descricao,
                ativo,
                criado_em,
                atualizado_em
            FROM public.produtos
            WHERE id = :id
            LIMIT 1
            '
        );

        $statement->execute([
            'id' => $id,
        ]);

        $produto = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($produto)
            ? $produto
            : null;
    }


    /**
     * Verifica se já existe produto
     * com determinado código.
     */
    public function existsByCodigo(
        string $codigo
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT 1
            FROM public.produtos
            WHERE codigo = :codigo
            LIMIT 1
            '
        );

        $statement->execute([
            'codigo' => $codigo,
        ]);

        return $statement->fetchColumn()
            !== false;
    }


    /**
     * Verifica se já existe produto
     * com determinado slug.
     */
    public function existsBySlug(
        string $slug
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT 1
            FROM public.produtos
            WHERE slug = :slug
            LIMIT 1
            '
        );

        $statement->execute([
            'slug' => $slug,
        ]);

        return $statement->fetchColumn()
            !== false;
    }


    /**
     * Verifica código duplicado durante edição,
     * ignorando o próprio produto.
     */
    public function existsByCodigoExceptId(
        string $codigo,
        int $id
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT 1
            FROM public.produtos
            WHERE codigo = :codigo
              AND id <> :id
            LIMIT 1
            '
        );

        $statement->execute([
            'codigo' => $codigo,
            'id' => $id,
        ]);

        return $statement->fetchColumn()
            !== false;
    }


    /**
     * Verifica slug duplicado durante edição,
     * ignorando o próprio produto.
     */
    public function existsBySlugExceptId(
        string $slug,
        int $id
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT 1
            FROM public.produtos
            WHERE slug = :slug
              AND id <> :id
            LIMIT 1
            '
        );

        $statement->execute([
            'slug' => $slug,
            'id' => $id,
        ]);

        return $statement->fetchColumn()
            !== false;
    }


    /**
     * Cria um novo produto.
     *
     * Retorna o ID criado pelo PostgreSQL.
     */
    public function create(
        string $codigo,
        string $nome,
        string $slug,
        ?string $descricao
    ): int {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            INSERT INTO public.produtos (
                codigo,
                nome,
                slug,
                descricao,
                ativo
            )
            VALUES (
                :codigo,
                :nome,
                :slug,
                :descricao,
                TRUE
            )
            RETURNING id
            '
        );

        $statement->execute([
            'codigo' => $codigo,
            'nome' => $nome,
            'slug' => $slug,
            'descricao' => $descricao,
        ]);

        $produtoId = $statement->fetchColumn();

        if ($produtoId === false) {
            throw new RuntimeException(
                'Não foi possível obter o ID do produto criado.'
            );
        }

        return (int) $produtoId;
    }


    /**
     * Atualiza um produto existente.
     *
     * O status ativo/inativo não é alterado aqui.
     * Essa operação terá fluxo separado.
     */
    public function update(
        int $id,
        string $codigo,
        string $nome,
        string $slug,
        ?string $descricao
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.produtos
            SET
                codigo = :codigo,
                nome = :nome,
                slug = :slug,
                descricao = :descricao,
                atualizado_em = NOW()
            WHERE id = :id
            '
        );

        $statement->execute([
            'codigo' => $codigo,
            'nome' => $nome,
            'slug' => $slug,
            'descricao' => $descricao,
            'id' => $id,
        ]);

        /*
         * Se nenhuma linha foi encontrada,
         * o Service já terá detectado isso através
         * do findById() antes da atualização.
         */
    }

    /**
     * Conta assinaturas ainda vinculadas ao ciclo operacional
     * do produto.
     *
     * CANCELADA não impede a desativação.
     */
    public function countAssinaturasVigentes(
        int $produtoId
    ): int {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
        SELECT COUNT(*)
        FROM public.assinaturas
        WHERE produto_id = :produto_id
          AND status IN (
              \'ATIVA\',
              \'ATRASADA\',
              \'SUSPENSA\'
          )
        '
        );

        $statement->execute([
            'produto_id' => $produtoId,
        ]);

        return (int) $statement->fetchColumn();
    }


    /**
     * Altera apenas o status operacional do produto.
     */
    public function updateStatus(
        int $id,
        bool $ativo
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
        UPDATE public.produtos
        SET
            ativo = :ativo,
            atualizado_em = NOW()
        WHERE id = :id
        '
        );

        $statement->bindValue(
            ':ativo',
            $ativo,
            PDO::PARAM_BOOL
        );

        $statement->bindValue(
            ':id',
            $id,
            PDO::PARAM_INT
        );

        $statement->execute();
    }
}
