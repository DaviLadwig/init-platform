<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class PlanoLimiteRepository
{
    public function findByPlanoId(
        int $planoId
    ): array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                plano_id,
                chave,
                valor,
                unidade,
                criado_em,
                atualizado_em
            FROM public.plano_limites
            WHERE plano_id = :plano_id
            ORDER BY chave ASC
            '
        );

        $statement->execute([
            'plano_id' => $planoId,
        ]);

        $rows = $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

        return is_array($rows)
            ? $rows
            : [];
    }

    /**
     * Verifica se a chave já existe dentro do plano.
     */
    public function existsByPlanoAndChave(
        int $planoId,
        string $chave
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT 1
            FROM public.plano_limites
            WHERE plano_id = :plano_id
              AND chave = :chave
            LIMIT 1
            '
        );

        $statement->execute([
            'plano_id' => $planoId,
            'chave' => $chave,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Cria um limite.
     */
    public function create(
        int $planoId,
        string $chave,
        string $valor,
        ?string $unidade
    ): int {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            INSERT INTO public.plano_limites (
                plano_id,
                chave,
                valor,
                unidade
            )
            VALUES (
                :plano_id,
                :chave,
                :valor,
                :unidade
            )
            RETURNING id
            '
        );

        $statement->execute([
            'plano_id' => $planoId,
            'chave' => $chave,
            'valor' => $valor,
            'unidade' => $unidade,
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'Não foi possível obter o ID do limite criado.'
            );
        }

        return (int) $id;
    }

    /**
     * Busca um limite garantindo que ele pertença
     * ao plano informado.
     *
     * Essa validação também protege contra IDOR.
     */
    public function findByIdAndPlanoId(
        int $limiteId,
        int $planoId
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
        SELECT
            id,
            plano_id,
            chave,
            valor,
            unidade,
            criado_em,
            atualizado_em
        FROM public.plano_limites
        WHERE id = :limite_id
          AND plano_id = :plano_id
        LIMIT 1
        '
        );

        $statement->execute([
            'limite_id' => $limiteId,
            'plano_id' => $planoId,
        ]);

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }


    /**
     * Verifica duplicidade de chave,
     * ignorando o próprio limite.
     */
    public function existsByPlanoAndChaveExceptId(
        int $planoId,
        string $chave,
        int $limiteId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
        SELECT 1
        FROM public.plano_limites
        WHERE plano_id = :plano_id
          AND chave = :chave
          AND id <> :limite_id
        LIMIT 1
        '
        );

        $statement->execute([
            'plano_id' => $planoId,
            'chave' => $chave,
            'limite_id' => $limiteId,
        ]);

        return $statement->fetchColumn() !== false;
    }


    /**
     * Atualiza somente um limite pertencente
     * ao plano informado.
     *
     * O WHERE contém plano_id propositalmente.
     */
    public function update(
        int $limiteId,
        int $planoId,
        string $chave,
        string $valor,
        ?string $unidade
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
        UPDATE public.plano_limites
        SET
            chave = :chave,
            valor = :valor,
            unidade = :unidade,
            atualizado_em = NOW()
        WHERE id = :limite_id
          AND plano_id = :plano_id
        '
        );

        $statement->execute([
            'chave' => $chave,
            'valor' => $valor,
            'unidade' => $unidade,
            'limite_id' => $limiteId,
            'plano_id' => $planoId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'O limite não pôde ser atualizado.'
            );
        }
    }
}
