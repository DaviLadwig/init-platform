<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use PDOStatement;
use RuntimeException;

final class EmpresaResponsavelRepository
{
    /**
     * Lista todos os responsáveis vinculados à empresa.
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

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
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
     * Busca um responsável garantindo que ele
     * realmente pertence à empresa informada.
     *
     * A combinação de ID + empresa protege contra IDOR.
     */
    public function findByIdAndEmpresaId(
        int $responsavelId,
        int $empresaId
    ): ?array {
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
            WHERE id = :responsavel_id
              AND empresa_id = :empresa_id
            LIMIT 1
            '
        );

        $statement->bindValue(
            ':responsavel_id',
            $responsavelId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
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
     * Verifica se a empresa já possui
     * um responsável principal.
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

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        return $statement->fetchColumn()
            !== false;
    }

    /**
     * Bloqueia a empresa durante operações
     * que modificam o responsável principal.
     *
     * Deve ser chamado dentro de uma transação.
     */
    public function lockEmpresa(
        int $empresaId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT id
            FROM public.empresas
            WHERE id = :empresa_id
            FOR UPDATE
            '
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        return $statement->fetchColumn()
            !== false;
    }

    /**
     * Cadastra um responsável.
     *
     * O campo principal é vinculado explicitamente
     * como boolean para evitar que false seja enviado
     * ao PostgreSQL como string vazia.
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

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':nome',
            $nome,
            PDO::PARAM_STR
        );

        $this->bindNullableString(
            $statement,
            ':email',
            $email
        );

        $this->bindNullableString(
            $statement,
            ':telefone',
            $telefone
        );

        $this->bindNullableString(
            $statement,
            ':cargo',
            $cargo
        );

        $statement->bindValue(
            ':principal',
            $principal,
            PDO::PARAM_BOOL
        );

        $statement->execute();

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'Não foi possível obter o ID do responsável criado.'
            );
        }

        return (int) $id;
    }

    /**
     * Remove o status de principal de qualquer outro
     * responsável da mesma empresa.
     *
     * Retorna o primeiro registro alterado para
     * possibilitar a auditoria utilizada pelo service.
     */
    public function clearPrincipalExcept(
        int $empresaId,
        int $responsavelId
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.empresa_responsaveis
            SET
                principal = false,
                atualizado_em = NOW()
            WHERE empresa_id = :empresa_id
              AND id <> :responsavel_id
              AND principal = true
            RETURNING
                id,
                empresa_id,
                nome,
                email,
                telefone,
                cargo,
                ativo
            '
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':responsavel_id',
            $responsavelId,
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
     * Atualiza os dados cadastrais do responsável.
     *
     * O vínculo com a empresa faz parte do WHERE,
     * protegendo contra alteração de registros
     * pertencentes a outro cliente.
     *
     * O campo ativo não é modificado aqui.
     */
    public function update(
        int $responsavelId,
        int $empresaId,
        string $nome,
        ?string $email,
        ?string $telefone,
        ?string $cargo,
        bool $principal
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.empresa_responsaveis
            SET
                nome = :nome,
                email = :email,
                telefone = :telefone,
                cargo = :cargo,
                principal = :principal,
                atualizado_em = NOW()
            WHERE id = :responsavel_id
              AND empresa_id = :empresa_id
            '
        );

        $statement->bindValue(
            ':responsavel_id',
            $responsavelId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':nome',
            $nome,
            PDO::PARAM_STR
        );

        $this->bindNullableString(
            $statement,
            ':email',
            $email
        );

        $this->bindNullableString(
            $statement,
            ':telefone',
            $telefone
        );

        $this->bindNullableString(
            $statement,
            ':cargo',
            $cargo
        );

        $statement->bindValue(
            ':principal',
            $principal,
            PDO::PARAM_BOOL
        );

        $statement->execute();

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'O responsável não pôde ser atualizado.'
            );
        }
    }


    /**
     * Altera somente o status ativo do responsável.
     *
     * O responsável precisa pertencer à empresa informada,
     * mantendo a proteção contra IDOR também na escrita.
     */
    public function updateActiveStatus(
        int $responsavelId,
        int $empresaId,
        bool $ativo
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.empresa_responsaveis
            SET
                ativo = :ativo,
                atualizado_em = NOW()
            WHERE id = :responsavel_id
              AND empresa_id = :empresa_id
            '
        );

        $statement->bindValue(
            ':responsavel_id',
            $responsavelId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':ativo',
            $ativo,
            PDO::PARAM_BOOL
        );

        $statement->execute();

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'O status do responsável não pôde ser atualizado.'
            );
        }
    }

    /**
     * Vincula strings opcionais respeitando NULL no PostgreSQL.
     */
    private function bindNullableString(
        PDOStatement $statement,
        string $parameter,
        ?string $value
    ): void {
        if ($value === null) {
            $statement->bindValue(
                $parameter,
                null,
                PDO::PARAM_NULL
            );

            return;
        }

        $statement->bindValue(
            $parameter,
            $value,
            PDO::PARAM_STR
        );
    }
}
