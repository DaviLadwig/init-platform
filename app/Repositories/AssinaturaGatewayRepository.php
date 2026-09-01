<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class AssinaturaGatewayRepository
{
    private const LOCK_NAMESPACE = 817260831;

    public function findActiveSubscriptionContext(
        int $assinaturaId,
        string $gateway,
        string $ambiente
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                a.id AS assinatura_id,
                a.empresa_id,
                a.status,
                a.valor_contratado,
                a.moeda,
                a.periodicidade,
                a.proxima_cobranca_em,

                p.nome AS produto_nome,
                p.codigo AS produto_codigo,

                pl.nome AS plano_nome,
                pl.codigo AS plano_codigo,

                egc.gateway_customer_id

            FROM public.assinaturas AS a

            INNER JOIN public.produtos AS p
                ON p.id = a.produto_id

            INNER JOIN public.planos AS pl
                ON pl.id = a.plano_id
               AND pl.produto_id = a.produto_id

            INNER JOIN public.empresa_gateway_clientes AS egc
                ON egc.empresa_id = a.empresa_id
               AND egc.gateway = :gateway
               AND egc.ambiente = :ambiente

            WHERE a.id = :assinatura_id
              AND a.status = \'ATIVA\'
            LIMIT 1
            '
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
            ':assinatura_id',
            $assinaturaId,
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
        int $assinaturaId,
        string $gateway,
        string $ambiente
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                assinatura_id,
                gateway,
                ambiente,
                gateway_subscription_id,
                external_reference,
                billing_type,
                cycle,
                sincronizado_em,
                criado_em,
                atualizado_em

            FROM public.assinatura_gateway_assinaturas

            WHERE assinatura_id = :assinatura_id
              AND gateway = :gateway
              AND ambiente = :ambiente

            LIMIT 1
            '
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
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
        int $assinaturaId,
        string $gateway,
        string $ambiente,
        string $gatewaySubscriptionId,
        string $externalReference,
        string $billingType,
        string $cycle
    ): int {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            INSERT INTO public.assinatura_gateway_assinaturas (
                assinatura_id,
                gateway,
                ambiente,
                gateway_subscription_id,
                external_reference,
                billing_type,
                cycle,
                sincronizado_em
            )
            VALUES (
                :assinatura_id,
                :gateway,
                :ambiente,
                :gateway_subscription_id,
                :external_reference,
                :billing_type,
                :cycle,
                NOW()
            )
            RETURNING id
            '
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
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
            ':gateway_subscription_id',
            $gatewaySubscriptionId,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':external_reference',
            $externalReference,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':billing_type',
            $billingType,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':cycle',
            $cycle,
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
                'Não foi possível persistir o vínculo da assinatura com o gateway.'
            );
        }

        return (int) $id;
    }

    public function tryAcquireSubscriptionGatewayLock(
        int $assinaturaId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT pg_try_advisory_lock(
                :namespace,
                :assinatura_id
            )
            '
        );

        $statement->bindValue(
            ':namespace',
            self::LOCK_NAMESPACE,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
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

    public function releaseSubscriptionGatewayLock(
        int $assinaturaId
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT pg_advisory_unlock(
                :namespace,
                :assinatura_id
            )
            '
        );

        $statement->bindValue(
            ':namespace',
            self::LOCK_NAMESPACE,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
            PDO::PARAM_INT
        );

        $statement->execute();
    }
}
