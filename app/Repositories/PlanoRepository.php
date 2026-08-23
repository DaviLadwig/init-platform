<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class PlanoRepository
{
    /**
     * Retorna todos os produtos com seus respectivos planos.
     *
     * LEFT JOIN é importante:
     * um produto precisa aparecer mesmo que ainda não tenha plano.
     */
    public function catalogo(): array
    {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                p.id AS produto_id,
                p.codigo AS produto_codigo,
                p.nome AS produto_nome,
                p.slug AS produto_slug,
                p.descricao AS produto_descricao,

                CASE
                    WHEN p.ativo = TRUE THEN 1
                    ELSE 0
                END AS produto_ativo,

                pl.id AS plano_id,
                pl.codigo AS plano_codigo,
                pl.nome AS plano_nome,
                pl.descricao AS plano_descricao,
                pl.valor AS plano_valor,
                pl.moeda AS plano_moeda,
                pl.periodicidade AS plano_periodicidade,

                CASE
                    WHEN pl.ativo = TRUE THEN 1
                    ELSE 0
                END AS plano_ativo

            FROM public.produtos p

            LEFT JOIN public.planos pl
                ON pl.produto_id = p.id

            ORDER BY
                p.nome ASC,
                pl.ativo DESC NULLS LAST,
                pl.valor ASC NULLS LAST,
                pl.nome ASC NULLS LAST
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
     * Produtos ativos disponíveis para cadastro de planos.
     */
    public function activeProducts(): array
    {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                codigo,
                nome
            FROM public.produtos
            WHERE ativo = TRUE
            ORDER BY nome ASC
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

    public function activeProductExists(
        int $produtoId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT 1
            FROM public.produtos
            WHERE id = :produto_id
              AND ativo = TRUE
            LIMIT 1
            '
        );

        $statement->execute([
            'produto_id' => $produtoId,
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function existsByCodigoAndProduto(
        string $codigo,
        int $produtoId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT 1
            FROM public.planos
            WHERE produto_id = :produto_id
              AND codigo = :codigo
            LIMIT 1
            '
        );

        $statement->execute([
            'produto_id' => $produtoId,
            'codigo' => $codigo,
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function create(
        int $produtoId,
        string $codigo,
        string $nome,
        ?string $descricao,
        string $valor,
        string $moeda,
        string $periodicidade
    ): int {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            INSERT INTO public.planos (
                produto_id,
                codigo,
                nome,
                descricao,
                valor,
                moeda,
                periodicidade,
                ativo
            )
            VALUES (
                :produto_id,
                :codigo,
                :nome,
                :descricao,
                :valor,
                :moeda,
                :periodicidade,
                TRUE
            )
            RETURNING id
            '
        );

        $statement->execute([
            'produto_id' => $produtoId,
            'codigo' => $codigo,
            'nome' => $nome,
            'descricao' => $descricao,
            'valor' => $valor,
            'moeda' => $moeda,
            'periodicidade' => $periodicidade,
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'Não foi possível obter o ID do plano criado.'
            );
        }

        return (int) $id;
    }

    /**
     * Busca um plano pelo ID junto com seu produto.
     */
    public function findById(
        int $id
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
        SELECT
            pl.id,
            pl.produto_id,
            pl.codigo,
            pl.nome,
            pl.descricao,
            pl.valor,
            pl.moeda,
            pl.periodicidade,
            pl.ativo,
            pl.criado_em,
            pl.atualizado_em,

            p.nome AS produto_nome,
            p.codigo AS produto_codigo

        FROM public.planos pl

        INNER JOIN public.produtos p
            ON p.id = pl.produto_id

        WHERE pl.id = :id

        LIMIT 1
        '
        );

        $statement->execute([
            'id' => $id,
        ]);

        $plano = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($plano)
            ? $plano
            : null;
    }


    /**
     * Verifica duplicidade de código no mesmo produto,
     * ignorando o próprio plano.
     */
    public function existsByCodigoAndProdutoExceptId(
        string $codigo,
        int $produtoId,
        int $planoId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
        SELECT 1
        FROM public.planos

        WHERE produto_id = :produto_id
          AND codigo = :codigo
          AND id <> :plano_id

        LIMIT 1
        '
        );

        $statement->execute([
            'produto_id' => $produtoId,
            'codigo' => $codigo,
            'plano_id' => $planoId,
        ]);

        return $statement->fetchColumn() !== false;
    }


    /**
     * Atualiza os dados comerciais do plano.
     *
     * O produto_id não é alterado.
     */
    public function update(
        int $id,
        string $codigo,
        string $nome,
        ?string $descricao,
        string $valor,
        string $periodicidade
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
        UPDATE public.planos

        SET
            codigo = :codigo,
            nome = :nome,
            descricao = :descricao,
            valor = :valor,
            periodicidade = :periodicidade,
            atualizado_em = NOW()

        WHERE id = :id
        '
        );

        $statement->execute([
            'codigo' => $codigo,
            'nome' => $nome,
            'descricao' => $descricao,
            'valor' => $valor,
            'periodicidade' => $periodicidade,
            'id' => $id,
        ]);
    }

    /**
     * Altera a disponibilidade comercial do plano.
     */
    public function updateStatus(
        int $id,
        bool $ativo
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
        UPDATE public.planos
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
