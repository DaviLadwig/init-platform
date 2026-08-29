<?php

declare(strict_types=1);

$viewVencimentos = isset($vencimentos)
    && is_array($vencimentos)
        ? $vencimentos
        : [];

$viewResumo = isset($resumo)
    && is_array($resumo)
        ? $resumo
        : [];

$viewSuccess = isset($success)
    && is_string($success)
        ? $success
        : null;

$viewError = isset($error)
    && is_string($error)
        ? $error
        : null;

$viewAppUrl = isset($appUrl)
    && is_string($appUrl)
        ? rtrim($appUrl, '/')
        : '';

$viewCsrfToken = isset($csrfToken)
    && is_string($csrfToken)
        ? $csrfToken
        : '';

$formatDate = static function (mixed $value): string {
    if (!is_string($value) || $value === '') {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d/m/Y', $timestamp)
        : '—';
};

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Financeiro
        </span>

        <h1>
            Vencimentos
        </h1>

        <p>
            Cobranças vencidas, de hoje e dos próximos dois dias.
        </p>

    </div>

    <a
        href="<?= htmlspecialchars(
            $viewAppUrl . '/assinaturas',
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
        class="button-secondary"
    >
        Voltar às assinaturas
    </a>

</section>


<?php if (
    $viewSuccess !== null
    && $viewSuccess !== ''
): ?>

    <div
        class="form-alert form-alert-success page-alert"
        role="status"
    >
        <?= htmlspecialchars(
            $viewSuccess,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>

<?php endif; ?>


<?php if (
    $viewError !== null
    && $viewError !== ''
): ?>

    <div
        class="form-alert form-alert-error page-alert"
        role="alert"
    >
        <?= htmlspecialchars(
            $viewError,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>

<?php endif; ?>


<section class="due-summary">

    <article>
        <span>Total em atenção</span>
        <strong>
            <?= (int) ($viewResumo['total'] ?? 0) ?>
        </strong>
    </article>

    <article>
        <span>Vencem hoje</span>
        <strong>
            <?= (int) ($viewResumo['hoje'] ?? 0) ?>
        </strong>
    </article>

    <article>
        <span>Próximos 2 dias</span>
        <strong>
            <?= (int) ($viewResumo['proximos'] ?? 0) ?>
        </strong>
    </article>

    <article>
        <span>Vencidas</span>
        <strong>
            <?= (int) ($viewResumo['vencidos'] ?? 0) ?>
        </strong>
    </article>

</section>


<section class="data-panel">

    <header class="data-panel-header">

        <div>

            <h2>
                Central de cobrança
            </h2>

            <p>
                Use o WhatsApp somente quando houver uma cobrança real em atenção.
            </p>

        </div>

    </header>


    <?php if ($viewVencimentos === []): ?>

        <div class="empty-state">

            <h3>
                Nenhum vencimento em atenção
            </h3>

            <p>
                Não há cobranças vencidas ou previstas para os próximos dois dias.
            </p>

        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="data-table due-table">

                <thead>

                    <tr>
                        <th>Cliente</th>
                        <th>Produto / Plano</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                        <th>Situação</th>
                        <th>Ações</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($viewVencimentos as $item): ?>

                        <?php

                        if (!is_array($item)) {
                            continue;
                        }

                        $assinaturaId =
                            isset($item['id'])
                                ? (int) $item['id']
                                : 0;

                        $nomeFantasia =
                            isset($item['empresa_nome_fantasia'])
                            && is_string($item['empresa_nome_fantasia'])
                                ? trim($item['empresa_nome_fantasia'])
                                : '';

                        $razaoSocial =
                            isset($item['empresa_razao_social'])
                            && is_string($item['empresa_razao_social'])
                                ? trim($item['empresa_razao_social'])
                                : '';

                        $clienteNome =
                            $nomeFantasia !== ''
                                ? $nomeFantasia
                                : $razaoSocial;

                        $produtoNome =
                            isset($item['produto_nome'])
                            && is_string($item['produto_nome'])
                                ? $item['produto_nome']
                                : '';

                        $planoNome =
                            isset($item['plano_nome'])
                            && is_string($item['plano_nome'])
                                ? $item['plano_nome']
                                : '';

                        $moeda =
                            isset($item['moeda'])
                            && is_string($item['moeda'])
                                ? $item['moeda']
                                : 'BRL';

                        $valor =
                            isset($item['valor_contratado'])
                            && is_numeric($item['valor_contratado'])
                                ? (float) $item['valor_contratado']
                                : 0.0;

                        $dias =
                            isset($item['dias_para_vencer'])
                                ? (int) $item['dias_para_vencer']
                                : 0;

                        $situacao =
                            isset($item['situacao_vencimento'])
                            && is_string($item['situacao_vencimento'])
                                ? $item['situacao_vencimento']
                                : '';

                        if ($situacao === 'VENCIDA') {
                            $situacaoLabel =
                                abs($dias) === 1
                                    ? 'Vencida há 1 dia'
                                    : 'Vencida há '
                                        . abs($dias)
                                        . ' dias';

                            $situacaoClass =
                                'due-state-overdue';
                        } elseif ($situacao === 'HOJE') {
                            $situacaoLabel =
                                'Vence hoje';

                            $situacaoClass =
                                'due-state-today';
                        } else {
                            $situacaoLabel =
                                $dias === 1
                                    ? 'Vence amanhã'
                                    : 'Vence em '
                                        . $dias
                                        . ' dias';

                            $situacaoClass =
                                'due-state-upcoming';
                        }

                        ?>

                        <tr>

                            <td>
                                <strong class="due-client-name">
                                    <?= htmlspecialchars(
                                        $clienteNome,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <div class="due-product">
                                    <strong>
                                        <?= htmlspecialchars(
                                            $produtoNome,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>
                                    <span>
                                        <?= htmlspecialchars(
                                            $planoNome,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>
                                </div>
                            </td>

                            <td>
                                <strong class="due-value">
                                    <?= htmlspecialchars(
                                        $moeda === 'BRL'
                                            ? 'R$ '
                                                . number_format(
                                                    $valor,
                                                    2,
                                                    ',',
                                                    '.'
                                                )
                                            : $moeda
                                                . ' '
                                                . number_format(
                                                    $valor,
                                                    2,
                                                    ',',
                                                    '.'
                                                ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <span class="table-muted">
                                    <?= htmlspecialchars(
                                        $formatDate(
                                            $item['proxima_cobranca_em']
                                            ?? null
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <span
                                    class="due-state <?= htmlspecialchars(
                                        $situacaoClass,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >
                                    <?= htmlspecialchars(
                                        $situacaoLabel,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </td>

                            <td>

                                <?php if ($assinaturaId > 0): ?>

                                    <div class="due-actions">

                                        <form
                                            method="POST"
                                            action="<?= htmlspecialchars(
                                                $viewAppUrl
                                                    . '/assinaturas/'
                                                    . $assinaturaId
                                                    . '/lembrete-whatsapp',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            target="_blank"
                                        >

                                            <input
                                                type="hidden"
                                                name="_token"
                                                value="<?= htmlspecialchars(
                                                    $viewCsrfToken,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="whatsapp-reminder-button"
                                            >
                                                WhatsApp
                                            </button>

                                        </form>

                                        <a
                                            href="<?= htmlspecialchars(
                                                $viewAppUrl
                                                    . '/assinaturas/'
                                                    . $assinaturaId
                                                    . '/pagamento',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            class="subscription-action-link"
                                        >
                                            Registrar pagamento
                                        </a>

                                    </div>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>
