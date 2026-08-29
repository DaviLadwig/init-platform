<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class AssinaturaPagamentoRepository
{

    /**
     * Histórico financeiro da assinatura.
     *
     * Não retorna credenciais, tokens, dados bancários ou informações
     * de cartão. gateway_payment_id também fica fora da interface.
     */
    public function findByAssinaturaId(
        int $assinaturaId
    ): array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                assinatura_id,
                vencimento_referencia,
                valor,
                moeda,
                status,
                origem,
                metodo,
                pago_em,
                gateway,
                criado_em
            FROM public.assinatura_pagamentos
            WHERE assinatura_id = :assinatura_id
            ORDER BY
                vencimento_referencia DESC,
                id DESC
            '
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
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
     * Registra um pagamento manual confirmado.
     *
     * Segurança:
     * - valor/moeda/vencimento são fornecidos pelo Service após
     *   leitura da assinatura bloqueada em transação;
     * - nenhuma informação de cartão ou credencial é persistida;
     * - origem permanece MANUAL e gateway permanece NULL.
     */
    public function createConfirmedManual(
        int $assinaturaId,
        string $vencimentoReferencia,
        string $valor,
        string $moeda,
        ?string $metodo,
        int $usuarioId
    ): int {
        $pdo = Database::connection();

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
                :vencimento_referencia,
                :valor,
                :moeda,
                \'CONFIRMADO\',
                \'MANUAL\',
                :metodo,
                NOW(),
                :registrado_por,
                NULL,
                NULL
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
            $vencimentoReferencia,
            PDO::PARAM_STR
        );

        /*
         * NUMERIC é enviado como string decimal.
         * Não convertemos valores monetários para float.
         */
        $statement->bindValue(
            ':valor',
            $valor,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':moeda',
            $moeda,
            PDO::PARAM_STR
        );

        if ($metodo === null) {
            $statement->bindValue(
                ':metodo',
                null,
                PDO::PARAM_NULL
            );
        } else {
            $statement->bindValue(
                ':metodo',
                $metodo,
                PDO::PARAM_STR
            );
        }

        $statement->bindValue(
            ':registrado_por',
            $usuarioId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'Não foi possível obter o ID do pagamento registrado.'
            );
        }

        $pagamentoId = (int) $id;

        if ($pagamentoId <= 0) {
            throw new RuntimeException(
                'ID inválido retornado ao registrar pagamento.'
            );
        }

        return $pagamentoId;
    }
}
