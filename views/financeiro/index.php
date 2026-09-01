<?php

declare(strict_types=1);

$viewFinanceiro =
    isset($financeiro)
    && is_array($financeiro)
        ? $financeiro
        : [];

$indicadores =
    isset($viewFinanceiro['indicadores'])
    && is_array($viewFinanceiro['indicadores'])
        ? $viewFinanceiro['indicadores']
        : [];

$periodo =
    isset($viewFinanceiro['periodo'])
    && is_array($viewFinanceiro['periodo'])
        ? $viewFinanceiro['periodo']
        : [];

$porProduto =
    isset($viewFinanceiro['por_produto'])
    && is_array($viewFinanceiro['por_produto'])
        ? $viewFinanceiro['por_produto']
        : [];

$porPlano =
    isset($viewFinanceiro['por_plano'])
    && is_array($viewFinanceiro['por_plano'])
        ? $viewFinanceiro['por_plano']
        : [];

$previsao =
    isset($viewFinanceiro['previsao'])
    && is_array($viewFinanceiro['previsao'])
        ? $viewFinanceiro['previsao']
        : [];

$e = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES,
    'UTF-8'
);

/**
 * Arredonda uma string decimal para duas casas sem usar float
 * como base do cálculo financeiro.
 */
$decimalDuasCasas = static function (
    mixed $value
): string {
    if (is_int($value)) {
        return $value . '.00';
    }

    if (
        !is_string($value)
        || preg_match(
            '/^-?\d+(?:\.\d+)?$/',
            $value
        ) !== 1
    ) {
        return '0.00';
    }

    $negative = str_starts_with(
        $value,
        '-'
    );

    $normalized = $negative
        ? substr($value, 1)
        : $value;

    [$integer, $fraction] =
        array_pad(
            explode(
                '.',
                $normalized,
                2
            ),
            2,
            ''
        );

    $fraction = str_pad(
        $fraction,
        3,
        '0'
    );

    $cents = substr(
        $fraction,
        0,
        2
    );

    $thirdDigit = (int) (
        $fraction[2]
        ?? '0'
    );

    if ($thirdDigit >= 5) {
        $centValue = (int) $cents + 1;

        if ($centValue >= 100) {
            $centValue = 0;

            /*
             * Incremento decimal da parte inteira sem conversão
             * monetária para float.
             */
            $carry = 1;
            $digits = str_split(
                $integer
            );

            for (
                $i = count($digits) - 1;
                $i >= 0 && $carry === 1;
                $i--
            ) {
                $digit = (int) $digits[$i] + 1;

                if ($digit >= 10) {
                    $digits[$i] = '0';
                } else {
                    $digits[$i] = (string) $digit;
                    $carry = 0;
                }
            }

            if ($carry === 1) {
                array_unshift(
                    $digits,
                    '1'
                );
            }

            $integer = implode(
                '',
                $digits
            );
        }

        $cents = str_pad(
            (string) $centValue,
            2,
            '0',
            STR_PAD_LEFT
        );
    }

    $integer = ltrim(
        $integer,
        '0'
    );

    if ($integer === '') {
        $integer = '0';
    }

    return ($negative ? '-' : '')
        . $integer
        . '.'
        . $cents;
};

$formatMoney = static function (
    mixed $value
) use (
    $decimalDuasCasas
): string {
    $normalized = $decimalDuasCasas(
        $value
    );

    $negative = str_starts_with(
        $normalized,
        '-'
    );

    $normalized = $negative
        ? substr(
            $normalized,
            1
        )
        : $normalized;

    [$integer, $fraction] = explode(
        '.',
        $normalized,
        2
    );

    $groups = [];

    while (strlen($integer) > 3) {
        array_unshift(
            $groups,
            substr(
                $integer,
                -3
            )
        );

        $integer = substr(
            $integer,
            0,
            -3
        );
    }

    array_unshift(
        $groups,
        $integer
    );

    return ($negative ? '- ' : '')
        . 'R$ '
        . implode(
            '.',
            $groups
        )
        . ','
        . $fraction;
};

$formatPercent = static function (
    mixed $value
) use (
    $decimalDuasCasas
): string {
    $normalized = $decimalDuasCasas(
        $value
    );

    return str_replace(
        '.',
        ',',
        $normalized
    ) . '%';
};

$formatMonth = static function (
    string $value
): string {
    if (
        preg_match(
            '/^(\d{4})-(\d{2})$/',
            $value,
            $matches
        ) !== 1
    ) {
        return $value;
    }

    $months = [
        '01' => 'Jan',
        '02' => 'Fev',
        '03' => 'Mar',
        '04' => 'Abr',
        '05' => 'Mai',
        '06' => 'Jun',
        '07' => 'Jul',
        '08' => 'Ago',
        '09' => 'Set',
        '10' => 'Out',
        '11' => 'Nov',
        '12' => 'Dez',
    ];

    $month =
        $months[$matches[2]]
        ?? $matches[2];

    return $month
        . '/'
        . substr(
            $matches[1],
            2,
            2
        );
};

$formatDate = static function (
    mixed $value
): string {
    if (
        !is_string($value)
        || preg_match(
            '/^(\d{4})-(\d{2})-(\d{2})$/',
            $value,
            $matches
        ) !== 1
    ) {
        return '—';
    }

    return $matches[3]
        . '/'
        . $matches[2]
        . '/'
        . $matches[1];
};

$received =
    $indicadores['recebido_mes']
    ?? '0';

$toReceive =
    $indicadores['a_receber_mes']
    ?? '0';

$overdue =
    $indicadores['vencido']
    ?? '0';

$mrrActive =
    $indicadores['mrr_ativo']
    ?? '0';

$mrrRisk =
    $indicadores['mrr_risco']
    ?? '0';

$mrrContracted =
    $indicadores['mrr_contratado']
    ?? '0';

$arrActive =
    $indicadores['arr_ativo']
    ?? '0';

$averageTicket =
    $indicadores['ticket_medio']
    ?? '0';

$delinquency =
    $indicadores['inadimplencia_percentual']
    ?? '0';

$activeClients = (int) (
    $indicadores['clientes_ativos']
    ?? 0
);

$delinquentClients = (int) (
    $indicadores['clientes_inadimplentes']
    ?? 0
);

?>

<section class="finance-header">

    <div>

        <span class="page-eyebrow">
            Gestão financeira
        </span>

        <h1>
            Financeiro
        </h1>

        <p>
            Visão consolidada da receita recorrente, recebimentos e projeções da operação SaaS.
        </p>

    </div>

    <div class="finance-period">

        <span>
            Referência
        </span>

        <strong>
            <?= $e(
                $formatDate(
                    $periodo['hoje']
                    ?? null
                )
            ) ?>
        </strong>

        <small>
            Previsão até
            <?= $e(
                $formatDate(
                    $periodo['fim_previsao']
                    ?? null
                )
            ) ?>
        </small>

    </div>

</section>


<section
    class="finance-section"
    aria-labelledby="finance-cash-heading"
>

    <header class="finance-section-header">

        <div>

            <span class="finance-section-kicker">
                Caixa
            </span>

            <h2 id="finance-cash-heading">
                Movimento do mês
            </h2>

            <p>
                Valores confirmados, pendentes e vencidos no ciclo financeiro atual.
            </p>

        </div>

    </header>


    <div class="finance-primary-grid">

        <article class="finance-metric finance-metric-primary">

            <span class="finance-metric-label">
                Recebido no mês
            </span>

            <strong>
                <?= $e(
                    $formatMoney(
                        $received
                    )
                ) ?>
            </strong>

            <small>
                Pagamentos confirmados
            </small>

        </article>


        <article class="finance-metric">

            <span class="finance-metric-label">
                A receber no mês
            </span>

            <strong>
                <?= $e(
                    $formatMoney(
                        $toReceive
                    )
                ) ?>
            </strong>

            <small>
                Cobranças ainda não vencidas
            </small>

        </article>


        <article class="finance-metric">

            <span class="finance-metric-label">
                Vencido
            </span>

            <strong>
                <?= $e(
                    $formatMoney(
                        $overdue
                    )
                ) ?>
            </strong>

            <small>
                Cobranças com vencimento anterior à referência
            </small>

        </article>

    </div>

</section>


<section
    class="finance-section"
    aria-labelledby="finance-recurring-heading"
>

    <header class="finance-section-header">

        <div>

            <span class="finance-section-kicker">
                Recorrência
            </span>

            <h2 id="finance-recurring-heading">
                Saúde da receita SaaS
            </h2>

            <p>
                Indicadores normalizados das assinaturas vigentes da plataforma.
            </p>

        </div>

    </header>


    <div class="finance-secondary-grid">

        <article class="finance-metric">

            <span class="finance-metric-label">
                MRR ativo
            </span>

            <strong>
                <?= $e(
                    $formatMoney(
                        $mrrActive
                    )
                ) ?>
            </strong>

            <small>
                Receita recorrente saudável
            </small>

        </article>


        <article class="finance-metric">

            <span class="finance-metric-label">
                MRR em risco
            </span>

            <strong>
                <?= $e(
                    $formatMoney(
                        $mrrRisk
                    )
                ) ?>
            </strong>

            <small>
                Atrasadas e suspensas
            </small>

        </article>


        <article class="finance-metric">

            <span class="finance-metric-label">
                MRR contratado
            </span>

            <strong>
                <?= $e(
                    $formatMoney(
                        $mrrContracted
                    )
                ) ?>
            </strong>

            <small>
                MRR ativo + em risco
            </small>

        </article>


        <article class="finance-metric">

            <span class="finance-metric-label">
                ARR ativo
            </span>

            <strong>
                <?= $e(
                    $formatMoney(
                        $arrActive
                    )
                ) ?>
            </strong>

            <small>
                MRR ativo projetado em 12 meses
            </small>

        </article>


        <article class="finance-metric">

            <span class="finance-metric-label">
                Ticket médio
            </span>

            <strong>
                <?= $e(
                    $formatMoney(
                        $averageTicket
                    )
                ) ?>
            </strong>

            <small>
                Por cliente ativo
            </small>

        </article>


        <article class="finance-metric">

            <span class="finance-metric-label">
                Inadimplência
            </span>

            <strong>
                <?= $e(
                    $formatPercent(
                        $delinquency
                    )
                ) ?>
            </strong>

            <small>
                <?= $delinquentClients ?>
                cliente<?= $delinquentClients === 1 ? '' : 's' ?>
                em risco
            </small>

        </article>

    </div>


    <div class="finance-context-line">

        <span>
            Clientes ativos
        </span>

        <strong>
            <?= $activeClients ?>
        </strong>

    </div>

</section>


<section class="finance-columns">

    <article
        class="finance-section"
        aria-labelledby="finance-product-heading"
    >

        <header class="finance-section-header">

            <div>

                <span class="finance-section-kicker">
                    Produtos
                </span>

                <h2 id="finance-product-heading">
                    Receita recorrente por produto
                </h2>

            </div>

        </header>


        <div class="finance-table-wrap">

            <table class="finance-table">

                <thead>

                    <tr>
                        <th>Produto</th>
                        <th>Clientes</th>
                        <th>MRR ativo</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if ($porProduto === []): ?>

                        <tr>
                            <td
                                colspan="3"
                                class="finance-empty"
                            >
                                Nenhuma receita recorrente ativa.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($porProduto as $item): ?>

                            <?php if (!is_array($item)) {
                                continue;
                            } ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?= $e(
                                            is_string(
                                                $item['produto_nome']
                                                ?? null
                                            )
                                                ? $item['produto_nome']
                                                : 'Produto'
                                        ) ?>
                                    </strong>

                                    <?php if (
                                        isset($item['produto_codigo'])
                                        && is_string(
                                            $item['produto_codigo']
                                        )
                                        && $item['produto_codigo'] !== ''
                                    ): ?>

                                        <span>
                                            <?= $e(
                                                $item['produto_codigo']
                                            ) ?>
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= (int) (
                                        $item['clientes']
                                        ?? 0
                                    ) ?>
                                </td>

                                <td>
                                    <?= $e(
                                        $formatMoney(
                                            $item['mrr_ativo']
                                            ?? '0'
                                        )
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </article>


    <article
        class="finance-section"
        aria-labelledby="finance-plan-heading"
    >

        <header class="finance-section-header">

            <div>

                <span class="finance-section-kicker">
                    Planos
                </span>

                <h2 id="finance-plan-heading">
                    Receita recorrente por plano
                </h2>

            </div>

        </header>


        <div class="finance-table-wrap">

            <table class="finance-table">

                <thead>

                    <tr>
                        <th>Plano</th>
                        <th>Clientes</th>
                        <th>MRR ativo</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if ($porPlano === []): ?>

                        <tr>
                            <td
                                colspan="3"
                                class="finance-empty"
                            >
                                Nenhum plano com receita ativa.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($porPlano as $item): ?>

                            <?php if (!is_array($item)) {
                                continue;
                            } ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?= $e(
                                            is_string(
                                                $item['plano_nome']
                                                ?? null
                                            )
                                                ? $item['plano_nome']
                                                : 'Plano'
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= $e(
                                            is_string(
                                                $item['produto_nome']
                                                ?? null
                                            )
                                                ? $item['produto_nome']
                                                : ''
                                        ) ?>
                                    </span>

                                </td>

                                <td>
                                    <?= (int) (
                                        $item['clientes']
                                        ?? 0
                                    ) ?>
                                </td>

                                <td>
                                    <?= $e(
                                        $formatMoney(
                                            $item['mrr_ativo']
                                            ?? '0'
                                        )
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </article>

</section>


<section
    class="finance-section"
    aria-labelledby="finance-forecast-heading"
>

    <header class="finance-section-header finance-section-header-inline">

        <div>

            <span class="finance-section-kicker">
                Previsão
            </span>

            <h2 id="finance-forecast-heading">
                Recebimentos projetados
            </h2>

            <p>
                Projeção baseada nas próximas cobranças das assinaturas ativas e em suas periodicidades.
            </p>

        </div>

        <span class="finance-readonly-note">
            Somente leitura
        </span>

    </header>


    <div class="finance-forecast-list">

        <?php if ($previsao === []): ?>

            <div class="finance-empty finance-empty-block">
                Nenhuma cobrança futura encontrada no horizonte atual.
            </div>

        <?php else: ?>

            <?php foreach ($previsao as $item): ?>

                <?php

                if (!is_array($item)) {
                    continue;
                }

                $month =
                    isset($item['mes'])
                    && is_string(
                        $item['mes']
                    )
                        ? $item['mes']
                        : '';

                ?>

                <div class="finance-forecast-row">

                    <div>

                        <strong>
                            <?= $e(
                                $formatMonth(
                                    $month
                                )
                            ) ?>
                        </strong>

                        <span>
                            <?= (int) (
                                $item['cobrancas_previstas']
                                ?? 0
                            ) ?>
                            cobrança<?= (int) (
                                $item['cobrancas_previstas']
                                ?? 0
                            ) === 1 ? '' : 's' ?>
                        </span>

                    </div>

                    <strong>
                        <?= $e(
                            $formatMoney(
                                $item['valor_previsto']
                                ?? '0'
                            )
                        ) ?>
                    </strong>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</section>
