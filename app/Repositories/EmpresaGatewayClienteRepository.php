<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class EmpresaGatewayClienteRepository
{
    private const LOCK_NAMESPACE = 817260830;

    public function findActiveCompanyById(
        int $empresaId
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
                status
            FROM public.empresas
            WHERE id = :id
              AND status = \'ATIVA\'
            LIMIT 1
            '
        );

        $statement->bindValue(
            ':id',
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

    public function findMapping(
        int $empresaId,
        string $gateway,
        string $ambiente
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                empresa_id,
                gateway,
                ambiente,
                gateway_customer_id,
                external_reference,
                sincronizado_em,
                criado_em,
                atualizado_em
            FROM public.empresa_gateway_clientes
            WHERE empresa_id = :empresa_id
              AND gateway = :gateway
              AND ambiente = :ambiente
            LIMIT 1
            '
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':gateway',
            $gateway,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':ambiente',
            $ambiente,
            PDO::PARAM_STR
        );

        $statement->execute();

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }

    public function createMapping(
        int $empresaId,
        string $gateway,
        string $ambiente,
        string $gatewayCustomerId,
        string $externalReference
    ): int {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            INSERT INTO public.empresa_gateway_clientes (
                empresa_id,
                gateway,
                ambiente,
                gateway_customer_id,
                external_reference,
                sincronizado_em
            )
            VALUES (
                :empresa_id,
                :gateway,
                :ambiente,
                :gateway_customer_id,
                :external_reference,
                NOW()
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
            ':gateway',
            $gateway,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':ambiente',
            $ambiente,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':gateway_customer_id',
            $gatewayCustomerId,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':external_reference',
            $externalReference,
            PDO::PARAM_STR
        );

        $statement->execute();

        $id = $statement->fetchColumn();

        if (
            !is_int($id)
            && !(
                is_string($id)
                && ctype_digit($id)
            )
        ) {
            throw new RuntimeException(
                'Não foi possível persistir o vínculo do gateway.'
            );
        }

        return (int) $id;
    }

    public function tryAcquireCompanyGatewayLock(
        int $empresaId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT pg_try_advisory_lock(
                :namespace,
                :empresa_id
            )
            '
        );

        $statement->bindValue(
            ':namespace',
            self::LOCK_NAMESPACE,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $value = $statement->fetchColumn();

        return in_array(
            $value,
            [
                true,
                1,
                '1',
                't',
                'true',
            ],
            true
        );
    }

    public function releaseCompanyGatewayLock(
        int $empresaId
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT pg_advisory_unlock(
                :namespace,
                :empresa_id
            )
            '
        );

        $statement->bindValue(
            ':namespace',
            self::LOCK_NAMESPACE,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->execute();
    }
}
