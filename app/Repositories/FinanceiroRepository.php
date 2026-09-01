<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class FinanceiroRepository
{
    public function findSummary(
        string $inicioMes,
        string $fimMes,
        string $hoje
    ): array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            WITH pagamentos_mes AS (
                SELECT
                    COALESCE(SUM(ap.valor), 0::numeric) AS recebido_mes
                FROM public.assinatura_pagamentos AS ap
                WHERE ap.status = \'CONFIRMADO\'
                  AND (
                        ap.pago_em
                        AT TIME ZONE current_setting(\'TIMEZONE\')
                      )::date
                      BETWEEN :inicio_mes_pagamento
                          AND :fim_mes_pagamento
            ),

            cobrancas AS (
                SELECT
                    COALESCE(
                        SUM(
                            CASE
                                WHEN a.status = \'ATIVA\'
                                 AND a.proxima_cobranca_em
                                     BETWEEN :hoje_receber
                                         AND :fim_mes_receber
                                THEN a.valor_contratado
                                ELSE 0::numeric
                            END
                        ),
                        0::numeric
                    ) AS a_receber_mes,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN a.status IN (
                                    \'ATIVA\',
                                    \'ATRASADA\',
                                    \'SUSPENSA\'
                                )
                                 AND a.proxima_cobranca_em < :hoje_vencido
                                THEN a.valor_contratado
                                ELSE 0::numeric
                            END
                        ),
                        0::numeric
                    ) AS vencido

                FROM public.assinaturas AS a
                WHERE a.status IN (
                    \'ATIVA\',
                    \'ATRASADA\',
                    \'SUSPENSA\'
                )
            ),

            recorrencia AS (
                SELECT
                    COALESCE(
                        SUM(
                            CASE
                                WHEN a.status = \'ATIVA\'
                                THEN
                                    a.valor_contratado
                                    /
                                    CASE a.periodicidade
                                        WHEN \'MENSAL\' THEN 1
                                        WHEN \'TRIMESTRAL\' THEN 3
                                        WHEN \'SEMESTRAL\' THEN 6
                                        WHEN \'ANUAL\' THEN 12
                                        ELSE 1
                                    END
                                ELSE 0::numeric
                            END
                        ),
                        0::numeric
                    ) AS mrr_ativo,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN a.status IN (
                                    \'ATRASADA\',
                                    \'SUSPENSA\'
                                )
                                THEN
                                    a.valor_contratado
                                    /
                                    CASE a.periodicidade
                                        WHEN \'MENSAL\' THEN 1
                                        WHEN \'TRIMESTRAL\' THEN 3
                                        WHEN \'SEMESTRAL\' THEN 6
                                        WHEN \'ANUAL\' THEN 12
                                        ELSE 1
                                    END
                                ELSE 0::numeric
                            END
                        ),
                        0::numeric
                    ) AS mrr_risco,

                    COUNT(
                        DISTINCT CASE
                            WHEN a.status = \'ATIVA\'
                            THEN a.empresa_id
                            ELSE NULL
                        END
                    ) AS clientes_ativos,

                    COUNT(
                        DISTINCT CASE
                            WHEN a.status IN (
                                \'ATRASADA\',
                                \'SUSPENSA\'
                            )
                            THEN a.empresa_id
                            ELSE NULL
                        END
                    ) AS clientes_inadimplentes

                FROM public.assinaturas AS a
                WHERE a.status IN (
                    \'ATIVA\',
                    \'ATRASADA\',
                    \'SUSPENSA\'
                )
            )

            SELECT
                pagamentos_mes.recebido_mes,
                cobrancas.a_receber_mes,
                cobrancas.vencido,
                recorrencia.mrr_ativo,
                recorrencia.mrr_risco,
                recorrencia.mrr_ativo
                    + recorrencia.mrr_risco
                    AS mrr_contratado,
                recorrencia.mrr_ativo * 12
                    AS arr_ativo,
                recorrencia.clientes_ativos,
                recorrencia.clientes_inadimplentes,

                CASE
                    WHEN recorrencia.clientes_ativos > 0
                    THEN
                        recorrencia.mrr_ativo
                        / recorrencia.clientes_ativos
                    ELSE 0::numeric
                END AS ticket_medio,

                CASE
                    WHEN (
                        recorrencia.mrr_ativo
                        + recorrencia.mrr_risco
                    ) > 0
                    THEN
                        (
                            recorrencia.mrr_risco
                            /
                            (
                                recorrencia.mrr_ativo
                                + recorrencia.mrr_risco
                            )
                        ) * 100
                    ELSE 0::numeric
                END AS inadimplencia_percentual

            FROM pagamentos_mes
            CROSS JOIN cobrancas
            CROSS JOIN recorrencia
            '
        );

        $statement->bindValue(
            ':inicio_mes_pagamento',
            $inicioMes,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':fim_mes_pagamento',
            $fimMes,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':hoje_receber',
            $hoje,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':fim_mes_receber',
            $fimMes,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':hoje_vencido',
            $hoje,
            PDO::PARAM_STR
        );

        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    public function findMrrByProduct(): array
    {
        $pdo = Database::connection();

        $statement = $pdo->query(
            '
            SELECT
                p.id AS produto_id,
                p.codigo AS produto_codigo,
                p.nome AS produto_nome,

                COUNT(DISTINCT a.empresa_id) AS clientes,

                COALESCE(
                    SUM(
                        a.valor_contratado
                        /
                        CASE a.periodicidade
                            WHEN \'MENSAL\' THEN 1
                            WHEN \'TRIMESTRAL\' THEN 3
                            WHEN \'SEMESTRAL\' THEN 6
                            WHEN \'ANUAL\' THEN 12
                            ELSE 1
                        END
                    ),
                    0::numeric
                ) AS mrr_ativo

            FROM public.assinaturas AS a

            INNER JOIN public.produtos AS p
                ON p.id = a.produto_id

            WHERE a.status = \'ATIVA\'

            GROUP BY
                p.id,
                p.codigo,
                p.nome

            ORDER BY
                mrr_ativo DESC,
                p.nome ASC
            '
        );

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function findMrrByPlan(): array
    {
        $pdo = Database::connection();

        $statement = $pdo->query(
            '
            SELECT
                p.id AS produto_id,
                p.nome AS produto_nome,

                pl.id AS plano_id,
                pl.codigo AS plano_codigo,
                pl.nome AS plano_nome,

                COUNT(DISTINCT a.empresa_id) AS clientes,

                COALESCE(
                    SUM(
                        a.valor_contratado
                        /
                        CASE a.periodicidade
                            WHEN \'MENSAL\' THEN 1
                            WHEN \'TRIMESTRAL\' THEN 3
                            WHEN \'SEMESTRAL\' THEN 6
                            WHEN \'ANUAL\' THEN 12
                            ELSE 1
                        END
                    ),
                    0::numeric
                ) AS mrr_ativo

            FROM public.assinaturas AS a

            INNER JOIN public.produtos AS p
                ON p.id = a.produto_id

            INNER JOIN public.planos AS pl
                ON pl.id = a.plano_id
               AND pl.produto_id = a.produto_id

            WHERE a.status = \'ATIVA\'

            GROUP BY
                p.id,
                p.nome,
                pl.id,
                pl.codigo,
                pl.nome

            ORDER BY
                mrr_ativo DESC,
                p.nome ASC,
                pl.nome ASC
            '
        );

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function findForecast(
        string $hoje,
        string $fimHorizonte
    ): array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            WITH RECURSIVE previsao AS (
                SELECT
                    a.id AS assinatura_id,
                    a.valor_contratado,
                    a.periodicidade,
                    a.proxima_cobranca_em AS vencimento

                FROM public.assinaturas AS a

                WHERE a.status = \'ATIVA\'
                  AND a.proxima_cobranca_em IS NOT NULL
                  AND a.proxima_cobranca_em >= :hoje_base
                  AND a.proxima_cobranca_em <= :fim_base

                UNION ALL

                SELECT
                    p.assinatura_id,
                    p.valor_contratado,
                    p.periodicidade,

                    (
                        p.vencimento
                        +
                        CASE p.periodicidade
                            WHEN \'MENSAL\'
                                THEN INTERVAL \'1 month\'
                            WHEN \'TRIMESTRAL\'
                                THEN INTERVAL \'3 months\'
                            WHEN \'SEMESTRAL\'
                                THEN INTERVAL \'6 months\'
                            WHEN \'ANUAL\'
                                THEN INTERVAL \'12 months\'
                            ELSE INTERVAL \'1 month\'
                        END
                    )::date AS vencimento

                FROM previsao AS p

                WHERE (
                    p.vencimento
                    +
                    CASE p.periodicidade
                        WHEN \'MENSAL\'
                            THEN INTERVAL \'1 month\'
                        WHEN \'TRIMESTRAL\'
                            THEN INTERVAL \'3 months\'
                        WHEN \'SEMESTRAL\'
                            THEN INTERVAL \'6 months\'
                        WHEN \'ANUAL\'
                            THEN INTERVAL \'12 months\'
                        ELSE INTERVAL \'1 month\'
                    END
                )::date <= :fim_recursao
            )

            SELECT
                TO_CHAR(
                    DATE_TRUNC(
                        \'month\',
                        vencimento
                    ),
                    \'YYYY-MM\'
                ) AS mes,

                COALESCE(
                    SUM(valor_contratado),
                    0::numeric
                ) AS valor_previsto,

                COUNT(*) AS cobrancas_previstas

            FROM previsao

            GROUP BY
                DATE_TRUNC(
                    \'month\',
                    vencimento
                )

            ORDER BY
                DATE_TRUNC(
                    \'month\',
                    vencimento
                ) ASC
            '
        );

        $statement->bindValue(
            ':hoje_base',
            $hoje,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':fim_base',
            $fimHorizonte,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':fim_recursao',
            $fimHorizonte,
            PDO::PARAM_STR
        );

        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }
}
