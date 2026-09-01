<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class AsaasWebhookRepository
{
    public function reserveEvent(
        string $gateway,
        string $ambiente,
        string $eventId,
        string $eventType,
        string $paymentId,
        string $gatewaySubscriptionId,
        string $dueDate,
        string $value,
        string $billingType,
        string $paymentStatus,
        ?string $paymentDate
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            INSERT INTO public.gateway_webhook_eventos (
                gateway,
                ambiente,
                event_id,
                event_type,
                payment_id,
                gateway_subscription_id,
                vencimento_referencia,
                valor,
                billing_type,
                payment_status,
                payment_date,
                status
            )
            VALUES (
                :gateway,
                :ambiente,
                :event_id,
                :event_type,
                :payment_id,
                :gateway_subscription_id,
                :vencimento_referencia,
                CAST(:valor AS numeric),
                :billing_type,
                :payment_status,
                CAST(:payment_date AS date),
                \'PENDENTE\'
            )
            ON CONFLICT (
                gateway,
                ambiente,
                event_id
            )
            DO NOTHING
            RETURNING id
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
            ':event_id',
            $eventId,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':event_type',
            $eventType,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':payment_id',
            $paymentId,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':gateway_subscription_id',
            $gatewaySubscriptionId,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':vencimento_referencia',
            $dueDate,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':valor',
            $value,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':billing_type',
            $billingType,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':payment_status',
            $paymentStatus,
            PDO::PARAM_STR
        );

        if ($paymentDate === null) {
            $statement->bindValue(
                ':payment_date',
                null,
                PDO::PARAM_NULL
            );
        } else {
            $statement->bindValue(
                ':payment_date',
                $paymentDate,
                PDO::PARAM_STR
            );
        }

        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    public function findPendingIds(
        int $limit = 20
    ): array {
        $pdo = Database::connection();

        $limit = max(
            1,
            min(
                100,
                $limit
            )
        );

        $statement = $pdo->prepare(
            '
            SELECT id
            FROM public.gateway_webhook_eventos
            WHERE status = \'PENDENTE\'
            ORDER BY recebido_em ASC, id ASC
            LIMIT :limit
            '
        );

        $statement->bindValue(
            ':limit',
            $limit,
            PDO::PARAM_INT
        );

        $statement->execute();

        $rows = $statement->fetchAll(
            PDO::FETCH_COLUMN
        );

        $ids = [];

        foreach ($rows as $row) {
            if (
                is_int($row)
                || (
                    is_string($row)
                    && ctype_digit($row)
                )
            ) {
                $ids[] = (int) $row;
            }
        }

        return $ids;
    }

    public function lockEvent(
        int $eventRowId
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                gateway,
                ambiente,
                event_id,
                event_type,
                payment_id,
                gateway_subscription_id,
                vencimento_referencia,
                valor::text AS valor,
                billing_type,
                payment_status,
                payment_date,
                status,
                tentativas
            FROM public.gateway_webhook_eventos
            WHERE id = :id
            FOR UPDATE
            '
        );

        $statement->bindValue(
            ':id',
            $eventRowId,
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

    public function markProcessing(
        int $eventRowId
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.gateway_webhook_eventos
            SET
                status = \'PROCESSANDO\',
                tentativas = tentativas + 1,
                atualizado_em = NOW()
            WHERE id = :id
              AND status = \'PENDENTE\'
            '
        );

        $statement->bindValue(
            ':id',
            $eventRowId,
            PDO::PARAM_INT
        );

        $statement->execute();
    }

    public function markProcessed(
        int $eventRowId,
        string $resultCode
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.gateway_webhook_eventos
            SET
                status = \'PROCESSADO\',
                resultado_codigo = :resultado_codigo,
                processado_em = NOW(),
                atualizado_em = NOW()
            WHERE id = :id
            '
        );

        $statement->bindValue(
            ':resultado_codigo',
            $resultCode,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':id',
            $eventRowId,
            PDO::PARAM_INT
        );

        $statement->execute();
    }

    public function markError(
        int $eventRowId,
        string $errorCode
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.gateway_webhook_eventos
            SET
                status = \'ERRO\',
                resultado_codigo = :resultado_codigo,
                atualizado_em = NOW()
            WHERE id = :id
            '
        );

        $statement->bindValue(
            ':resultado_codigo',
            $errorCode,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':id',
            $eventRowId,
            PDO::PARAM_INT
        );

        $statement->execute();
    }

    public function findSubscriptionByGatewayId(
        string $gateway,
        string $ambiente,
        string $gatewaySubscriptionId
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                aga.assinatura_id,
                a.empresa_id,
                a.status,
                a.valor_contratado::text
                    AS valor_contratado,
                a.moeda,
                a.periodicidade,
                a.proxima_cobranca_em,
                a.dias_tolerancia
            FROM public.assinatura_gateway_assinaturas
                AS aga
            INNER JOIN public.assinaturas AS a
                ON a.id = aga.assinatura_id
            WHERE aga.gateway = :gateway
              AND aga.ambiente = :ambiente
              AND aga.gateway_subscription_id =
                  :gateway_subscription_id
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
            ':gateway_subscription_id',
            $gatewaySubscriptionId,
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

    public function lockSubscription(
        int $assinaturaId
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                empresa_id,
                status,
                valor_contratado::text
                    AS valor_contratado,
                moeda,
                periodicidade,
                proxima_cobranca_em,
                ultimo_pagamento_em,
                suspenso_em
            FROM public.assinaturas
            WHERE id = :id
            FOR UPDATE
            '
        );

        $statement->bindValue(
            ':id',
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

    public function paymentAlreadyRegistered(
        string $gateway,
        string $gatewayPaymentId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT 1
            FROM public.assinatura_pagamentos
            WHERE gateway = :gateway
              AND gateway_payment_id =
                  :gateway_payment_id
              AND status = \'CONFIRMADO\'
            LIMIT 1
            '
        );

        $statement->bindValue(
            ':gateway',
            $gateway,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':gateway_payment_id',
            $gatewayPaymentId,
            PDO::PARAM_STR
        );

        $statement->execute();

        return $statement->fetchColumn()
            !== false;
    }

    public function createGatewayPayment(
        int $assinaturaId,
        string $dueDate,
        string $value,
        string $method,
        string $gateway,
        string $gatewayPaymentId,
        ?string $paymentDate
    ): int {
        $pdo = Database::connection();

        /*
         * IMPORTANTE:
         * :payment_date aparece apenas UMA vez.
         *
         * Com prepared statements nativos do PDO PostgreSQL, reutilizar
         * o mesmo placeholder nomeado mais de uma vez na mesma query
         * pode causar erro de parâmetros em runtime.
         */
        $statement = $pdo->prepare(
            '
            INSERT INTO public.assinatura_pagamentos (
                assinatura_id,
                vencimento_referencia,
                valor,
                moeda,
                status,
                origem,
                metodo,
                pago_em,
                registrado_por,
                gateway,
                gateway_payment_id
            )
            VALUES (
                :assinatura_id,
                CAST(:vencimento_referencia AS date),
                CAST(:valor AS numeric),
                \'BRL\',
                \'CONFIRMADO\',
                \'GATEWAY\',
                :metodo,
                COALESCE(
                    CAST(:payment_date AS date)::timestamptz,
                    NOW()
                ),
                NULL,
                :gateway,
                :gateway_payment_id
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
            ':vencimento_referencia',
            $dueDate,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':valor',
            $value,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':metodo',
            $method,
            PDO::PARAM_STR
        );

        if ($paymentDate === null) {
            $statement->bindValue(
                ':payment_date',
                null,
                PDO::PARAM_NULL
            );
        } else {
            $statement->bindValue(
                ':payment_date',
                $paymentDate,
                PDO::PARAM_STR
            );
        }

        $statement->bindValue(
            ':gateway',
            $gateway,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':gateway_payment_id',
            $gatewayPaymentId,
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
                'Não foi possível registrar o pagamento do gateway.'
            );
        }

        return (int) $id;
    }

    public function applyConfirmedPayment(
        int $assinaturaId,
        string $nextChargeDate
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.assinaturas
            SET
                status = \'ATIVA\',
                ultimo_pagamento_em = NOW(),
                proxima_cobranca_em =
                    CAST(:proxima_cobranca_em AS date),
                suspenso_em = NULL,
                atualizado_em = NOW()
            WHERE id = :id
              AND status IN (
                  \'ATIVA\',
                  \'ATRASADA\',
                  \'SUSPENSA\'
              )
            '
        );

        $statement->bindValue(
            ':proxima_cobranca_em',
            $nextChargeDate,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':id',
            $assinaturaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'A assinatura não pôde ser atualizada após o pagamento.'
            );
        }
    }
}
