<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class DocumentoContratualRepository
{
    /**
     * Lista o catálogo contratual sem expor caminhos físicos.
     */
    public function findAll(): array
    {
        $pdo = Database::connection();

        $statement = $pdo->query(
            '
            SELECT
                dc.id,
                dc.produto_id,
                dc.tipo,
                dc.titulo,
                dc.versao,
                dc.descricao,
                dc.obrigatorio_ativacao,
                dc.ativo,
                dc.criado_em,
                dc.atualizado_em,
                p.codigo AS produto_codigo,
                p.nome AS produto_nome
            FROM public.documentos_contratuais AS dc
            LEFT JOIN public.produtos AS p
                ON p.id = dc.produto_id
            ORDER BY
                dc.tipo ASC,
                dc.produto_id NULLS FIRST,
                dc.ativo DESC,
                dc.id DESC
            '
        );

        $rows = $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

        return is_array($rows)
            ? $rows
            : [];
    }

    /**
     * Busca uma versão contratual específica.
     *
     * storage_key_modelo e hash_modelo são retornados somente
     * porque este método é destinado ao backend documental.
     * Eles nunca devem ser enviados diretamente ao navegador.
     */
    public function findById(
        int $documentoId
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                dc.id,
                dc.produto_id,
                dc.tipo,
                dc.titulo,
                dc.versao,
                dc.descricao,
                dc.obrigatorio_ativacao,
                dc.storage_key_modelo,
                dc.hash_modelo,
                dc.ativo,
                dc.criado_em,
                dc.atualizado_em,
                p.codigo AS produto_codigo,
                p.nome AS produto_nome
            FROM public.documentos_contratuais AS dc
            LEFT JOIN public.produtos AS p
                ON p.id = dc.produto_id
            WHERE dc.id = :documento_id
            LIMIT 1
            '
        );

        $statement->bindValue(
            ':documento_id',
            $documentoId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * Bloqueia uma versão contratual dentro de uma transação.
     */
    public function lockById(
        int $documentoId
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                produto_id,
                tipo,
                titulo,
                versao,
                descricao,
                obrigatorio_ativacao,
                storage_key_modelo,
                hash_modelo,
                ativo,
                criado_em,
                atualizado_em
            FROM public.documentos_contratuais
            WHERE id = :documento_id
            FOR UPDATE
            '
        );

        $statement->bindValue(
            ':documento_id',
            $documentoId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * Resolve os documentos ATIVOS aplicáveis a um produto.
     *
     * Regra:
     * - documento específico do produto prevalece sobre um global
     *   do mesmo tipo;
     * - tipos diferentes são cumulativos;
     * - opcionalmente retorna somente os obrigatórios para ativação.
     *
     * Exemplo:
     * GLOBAL / TERMO_CONTRATACAO_SAAS
     * CLINIC / TERMO_CONTRATACAO_SAAS
     *
     * Para Init Clinic, prevalece a versão específica do Clinic.
     */
    public function findApplicableActiveByProduct(
        int $produtoId,
        bool $onlyRequired = false
    ): array {
        $pdo = Database::connection();

        $requiredSql =
            $onlyRequired
                ? ' AND dc.obrigatorio_ativacao = TRUE '
                : '';

        $statement = $pdo->prepare(
            '
            SELECT DISTINCT ON (dc.tipo)
                dc.id,
                dc.produto_id,
                dc.tipo,
                dc.titulo,
                dc.versao,
                dc.descricao,
                dc.obrigatorio_ativacao,
                dc.storage_key_modelo,
                dc.hash_modelo,
                dc.ativo,
                dc.criado_em,
                dc.atualizado_em
            FROM public.documentos_contratuais AS dc
            WHERE dc.ativo = TRUE
              AND (
                    dc.produto_id IS NULL
                    OR dc.produto_id = :produto_id_scope
              )
              ' . $requiredSql . '
            ORDER BY
                dc.tipo ASC,
                CASE
                    WHEN dc.produto_id = :produto_id_order
                        THEN 0
                    ELSE 1
                END ASC,
                dc.id DESC
            '
        );

        $statement->bindValue(
            ':produto_id_scope',
            $produtoId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':produto_id_order',
            $produtoId,
            PDO::PARAM_INT
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
     * Cria uma nova versão no catálogo.
     *
     * O Service deve garantir que tipo/título/versão foram
     * normalizados e que modelo/hash foram validados.
     */
    public function create(
        ?int $produtoId,
        string $tipo,
        string $titulo,
        string $versao,
        ?string $descricao,
        bool $obrigatorioAtivacao,
        ?string $storageKeyModelo,
        ?string $hashModelo
    ): int {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            INSERT INTO public.documentos_contratuais (
                produto_id,
                tipo,
                titulo,
                versao,
                descricao,
                obrigatorio_ativacao,
                storage_key_modelo,
                hash_modelo,
                ativo
            )
            VALUES (
                :produto_id,
                :tipo,
                :titulo,
                :versao,
                :descricao,
                :obrigatorio_ativacao,
                :storage_key_modelo,
                :hash_modelo,
                TRUE
            )
            RETURNING id
            '
        );

        if ($produtoId === null) {
            $statement->bindValue(
                ':produto_id',
                null,
                PDO::PARAM_NULL
            );
        } else {
            $statement->bindValue(
                ':produto_id',
                $produtoId,
                PDO::PARAM_INT
            );
        }

        $statement->bindValue(
            ':tipo',
            $tipo,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':titulo',
            $titulo,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':versao',
            $versao,
            PDO::PARAM_STR
        );

        if ($descricao === null) {
            $statement->bindValue(
                ':descricao',
                null,
                PDO::PARAM_NULL
            );
        } else {
            $statement->bindValue(
                ':descricao',
                $descricao,
                PDO::PARAM_STR
            );
        }

        $statement->bindValue(
            ':obrigatorio_ativacao',
            $obrigatorioAtivacao,
            PDO::PARAM_BOOL
        );

        if ($storageKeyModelo === null) {
            $statement->bindValue(
                ':storage_key_modelo',
                null,
                PDO::PARAM_NULL
            );
        } else {
            $statement->bindValue(
                ':storage_key_modelo',
                $storageKeyModelo,
                PDO::PARAM_STR
            );
        }

        if ($hashModelo === null) {
            $statement->bindValue(
                ':hash_modelo',
                null,
                PDO::PARAM_NULL
            );
        } else {
            $statement->bindValue(
                ':hash_modelo',
                $hashModelo,
                PDO::PARAM_STR
            );
        }

        $statement->execute();

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'Não foi possível obter o ID da versão contratual.'
            );
        }

        $documentoId = (int) $id;

        if ($documentoId <= 0) {
            throw new RuntimeException(
                'ID inválido retornado ao criar versão contratual.'
            );
        }

        return $documentoId;
    }

    /**
     * Desativa uma versão sem apagar histórico.
     */
    public function deactivate(
        int $documentoId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.documentos_contratuais
            SET
                ativo = FALSE,
                atualizado_em = NOW()
            WHERE id = :documento_id
              AND ativo = TRUE
            '
        );

        $statement->bindValue(
            ':documento_id',
            $documentoId,
            PDO::PARAM_INT
        );

        $statement->execute();

        return $statement->rowCount() === 1;
    }
}
