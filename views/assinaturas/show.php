<?php

declare(strict_types=1);

$viewAssinatura = isset($assinatura)
    && is_array($assinatura)
        ? $assinatura
        : [];

$viewPagamentos = isset($pagamentos)
    && is_array($pagamentos)
        ? $pagamentos
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

$assinaturaId =
    isset($viewAssinatura['id'])
        ? (int) $viewAssinatura['id']
        : 0;

$status =
    isset($viewAssinatura['status'])
    && is_string($viewAssinatura['status'])
        ? $viewAssinatura['status']
        : '';

$statusData = match ($status) {
    'PENDENTE_ATIVACAO' => [
        'label' => 'Pendente de ativação',
        'class' => 'subscription-status-pending',
    ],
    'TRIAL' => [
        'label' => 'Trial',
        'class' => 'subscription-status-trial',
    ],
    'ATIVA' => [
        'label' => 'Ativa',
        'class' => 'subscription-status-active',
    ],
    'ATRASADA' => [
        'label' => 'Atrasada',
        'class' => 'subscription-status-overdue',
    ],
    'SUSPENSA' => [
        'label' => 'Suspensa',
        'class' => 'subscription-status-suspended',
    ],
    'CANCELADA' => [
        'label' => 'Cancelada',
        'class' => 'subscription-status-cancelled',
    ],
    default => [
        'label' => 'Indefinido',
        'class' => 'subscription-status-neutral',
    ],
};

$nomeFantasia =
    isset($viewAssinatura['empresa_nome_fantasia'])
    && is_string($viewAssinatura['empresa_nome_fantasia'])
        ? trim($viewAssinatura['empresa_nome_fantasia'])
        : '';

$razaoSocial =
    isset($viewAssinatura['empresa_razao_social'])
    && is_string($viewAssinatura['empresa_razao_social'])
        ? trim($viewAssinatura['empresa_razao_social'])
        : '';

$clienteNome =
    $nomeFantasia !== ''
        ? $nomeFantasia
        : $razaoSocial;

$produtoNome =
    isset($viewAssinatura['produto_nome'])
    && is_string($viewAssinatura['produto_nome'])
        ? $viewAssinatura['produto_nome']
        : '';

$planoNome =
    isset($viewAssinatura['plano_nome'])
    && is_string($viewAssinatura['plano_nome'])
        ? $viewAssinatura['plano_nome']
        : '';

$periodicidade =
    isset($viewAssinatura['periodicidade'])
    && is_string($viewAssinatura['periodicidade'])
        ? $viewAssinatura['periodicidade']
        : '';

$moeda =
    isset($viewAssinatura['moeda'])
    && is_string($viewAssinatura['moeda'])
        ? $viewAssinatura['moeda']
        : 'BRL';

$valor =
    isset($viewAssinatura['valor_contratado'])
    && is_numeric($viewAssinatura['valor_contratado'])
        ? (float) $viewAssinatura['valor_contratado']
        : 0.0;

$formatDate = static function (mixed $value): string {
    if (!is_string($value) || $value === '') {
        return '—';
    }

    $timestamp =
        strtotime($value);

    return $timestamp !== false
        ? date('d/m/Y', $timestamp)
        : '—';
};

$formatDateTime = static function (mixed $value): string {
    if (!is_string($value) || $value === '') {
        return '—';
    }

    $timestamp =
        strtotime($value);

    return $timestamp !== false
        ? date('d/m/Y H:i', $timestamp)
        : '—';
};

$formatMoney = static function (
    mixed $value,
    string $currency
): string {
    $amount =
        is_numeric($value)
            ? (float) $value
            : 0.0;

    $formatted =
        number_format(
            $amount,
            2,
            ',',
            '.'
        );

    return $currency === 'BRL'
        ? 'R$ ' . $formatted
        : $currency . ' ' . $formatted;
};

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Gestão comercial
        </span>

        <h1>
            Detalhes da assinatura
        </h1>

        <p>
            Consulte o contrato, o ciclo financeiro e o histórico de pagamentos.
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


<section class="subscription-detail-hero">

    <div class="subscription-detail-main">

        <span>
            Cliente
        </span>

        <h2>
            <?= htmlspecialchars(
                $clienteNome,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </h2>

        <?php if (
            $razaoSocial !== ''
            && $razaoSocial !== $clienteNome
        ): ?>

            <p>
                <?= htmlspecialchars(
                    $razaoSocial,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

        <?php endif; ?>

    </div>

    <div class="subscription-detail-status">

        <span
            class="subscription-status <?= htmlspecialchars(
                $statusData['class'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >
            <?= htmlspecialchars(
                $statusData['label'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </span>

        <strong>
            <?= htmlspecialchars(
                $formatMoney(
                    $valor,
                    $moeda
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>

        <small>
            <?= htmlspecialchars(
                $periodicidade,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </small>

    </div>

</section>


<section class="subscription-detail-grid">

    <article class="subscription-detail-panel">

        <header>
            <h2>
                Contrato
            </h2>
        </header>

        <dl class="subscription-detail-list">

            <div>
                <dt>Produto</dt>
                <dd>
                    <?= htmlspecialchars(
                        $produtoNome,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </dd>
            </div>

            <div>
                <dt>Plano</dt>
                <dd>
                    <?= htmlspecialchars(
                        $planoNome,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </dd>
            </div>

            <div>
                <dt>Início</dt>
                <dd>
                    <?= htmlspecialchars(
                        $formatDate(
                            $viewAssinatura['inicio_em']
                            ?? null
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </dd>
            </div>

            <div>
                <dt>Encerramento</dt>
                <dd>
                    <?= htmlspecialchars(
                        $formatDate(
                            $viewAssinatura['vencimento_em']
                            ?? null
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </dd>
            </div>

        </dl>

    </article>


    <article class="subscription-detail-panel">

        <header>
            <h2>
                Cobrança
            </h2>
        </header>

        <dl class="subscription-detail-list">

            <div>
                <dt>Último pagamento</dt>
                <dd>
                    <?= htmlspecialchars(
                        $formatDateTime(
                            $viewAssinatura['ultimo_pagamento_em']
                            ?? null
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </dd>
            </div>

            <div>
                <dt>Próxima cobrança</dt>
                <dd>
                    <?= htmlspecialchars(
                        $formatDate(
                            $viewAssinatura['proxima_cobranca_em']
                            ?? null
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </dd>
            </div>

            <div>
                <dt>Tolerância</dt>
                <dd>
                    <?= (int) (
                        $viewAssinatura['dias_tolerancia']
                        ?? 0
                    ) ?>
                    dias
                </dd>
            </div>

            <div>
                <dt>Suspensa em</dt>
                <dd>
                    <?= htmlspecialchars(
                        $formatDateTime(
                            $viewAssinatura['suspenso_em']
                            ?? null
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </dd>
            </div>

        </dl>

    </article>

</section>


<?php if ($status === 'CANCELADA'): ?>

    <section class="subscription-cancelled-info">

        <div>
            <span>
                Assinatura encerrada
            </span>

            <strong>
                <?= htmlspecialchars(
                    $formatDateTime(
                        $viewAssinatura['cancelado_em']
                        ?? null
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>
        </div>

        <?php

        $cancelamentoMotivo =
            isset($viewAssinatura['cancelamento_motivo'])
            && is_string($viewAssinatura['cancelamento_motivo'])
                ? trim($viewAssinatura['cancelamento_motivo'])
                : '';

        ?>

        <?php if ($cancelamentoMotivo !== ''): ?>

            <p>
                <?= nl2br(
                    htmlspecialchars(
                        $cancelamentoMotivo,
                        ENT_QUOTES,
                        'UTF-8'
                    )
                ) ?>
            </p>

        <?php endif; ?>

    </section>

<?php endif; ?>


<section class="data-panel subscription-history-panel">

    <header class="data-panel-header">

        <div>

            <h2>
                Histórico financeiro
            </h2>

            <p>
                <?= count($viewPagamentos) ?>
                pagamento<?= count($viewPagamentos) === 1 ? '' : 's' ?>
                registrado<?= count($viewPagamentos) === 1 ? '' : 's' ?>
            </p>

        </div>

        <?php if (
            in_array(
                $status,
                [
                    'ATIVA',
                    'ATRASADA',
                    'SUSPENSA',
                ],
                true
            )
            && $assinaturaId > 0
        ): ?>

            <a
                href="<?= htmlspecialchars(
                    $viewAppUrl
                        . '/assinaturas/'
                        . $assinaturaId
                        . '/pagamento',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="button-secondary"
            >
                Registrar pagamento
            </a>

        <?php endif; ?>

    </header>


    <?php if ($viewPagamentos === []): ?>

        <div class="empty-state">

            <h3>
                Nenhum pagamento registrado
            </h3>

            <p>
                O histórico financeiro será exibido aqui após a primeira confirmação de pagamento.
            </p>

        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="data-table subscription-history-table">

                <thead>
                    <tr>
                        <th>Referência</th>
                        <th>Valor</th>
                        <th>Método</th>
                        <th>Origem</th>
                        <th>Pago em</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($viewPagamentos as $pagamento): ?>

                        <?php

                        if (!is_array($pagamento)) {
                            continue;
                        }

                        $pagamentoMoeda =
                            isset($pagamento['moeda'])
                            && is_string($pagamento['moeda'])
                                ? $pagamento['moeda']
                                : 'BRL';

                        $metodo =
                            isset($pagamento['metodo'])
                            && is_string($pagamento['metodo'])
                                ? $pagamento['metodo']
                                : '—';

                        $origem =
                            isset($pagamento['origem'])
                            && is_string($pagamento['origem'])
                                ? $pagamento['origem']
                                : '—';

                        $pagamentoStatus =
                            isset($pagamento['status'])
                            && is_string($pagamento['status'])
                                ? $pagamento['status']
                                : '';

                        ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    $formatDate(
                                        $pagamento['vencimento_referencia']
                                        ?? null
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <strong class="history-payment-value">
                                    <?= htmlspecialchars(
                                        $formatMoney(
                                            $pagamento['valor']
                                            ?? null,
                                            $pagamentoMoeda
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $metodo,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $origem === 'MANUAL'
                                        ? 'Manual'
                                        : (
                                            $origem === 'GATEWAY'
                                                ? 'Gateway'
                                                : $origem
                                        ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $formatDateTime(
                                        $pagamento['pago_em']
                                        ?? null
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <span
                                    class="payment-history-status <?= $pagamentoStatus === 'CONFIRMADO'
                                        ? 'payment-history-confirmed'
                                        : 'payment-history-reversed' ?>"
                                >
                                    <?= htmlspecialchars(
                                        $pagamentoStatus === 'CONFIRMADO'
                                            ? 'Confirmado'
                                            : (
                                                $pagamentoStatus === 'ESTORNADO'
                                                    ? 'Estornado'
                                                    : $pagamentoStatus
                                            ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>


<?php if (
    $status !== 'CANCELADA'
    && $assinaturaId > 0
): ?>

    <section class="subscription-operations-panel">

        <header>

            <h2>
                Operações administrativas
            </h2>

            <p>
                Ações desta área alteram o estado comercial do contrato e ficam registradas na auditoria.
            </p>

        </header>


        <?php if ($status === 'PENDENTE_ATIVACAO'): ?>

            <div class="subscription-operation-row">

                <div>
                    <strong>
                        Ativar assinatura
                    </strong>

                    <p>
                        Inicia o ciclo comercial e define a primeira cobrança.
                    </p>
                </div>

                <form
                    method="POST"
                    action="<?= htmlspecialchars(
                        $viewAppUrl
                            . '/assinaturas/'
                            . $assinaturaId
                            . '/ativar',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
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
                        class="button-primary"
                    >
                        Ativar assinatura
                    </button>

                </form>

            </div>

        <?php endif; ?>


        <div class="subscription-cancel-operation">

            <div class="subscription-operation-copy">

                <strong>
                    Cancelar assinatura
                </strong>

                <p>
                    O cancelamento é definitivo para este contrato. O histórico financeiro será preservado e uma nova contratação poderá ser criada posteriormente.
                </p>

            </div>

            <form
                method="POST"
                action="<?= htmlspecialchars(
                    $viewAppUrl
                        . '/assinaturas/'
                        . $assinaturaId
                        . '/cancelar',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="subscription-cancel-form"
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

                <div class="field-group">

                    <label for="motivo">
                        Motivo do cancelamento
                    </label>

                    <textarea
                        id="motivo"
                        name="motivo"
                        rows="3"
                        maxlength="500"
                        required
                        placeholder="Informe o motivo administrativo ou comercial."
                    ></textarea>

                    <span class="field-hint">
                        Entre 3 e 500 caracteres. Esta informação ficará vinculada ao encerramento do contrato.
                    </span>

                </div>

                <label class="subscription-confirm-check">

                    <input
                        type="checkbox"
                        name="confirmacao"
                        value="1"
                        required
                    >

                    <span>
                        Confirmo que desejo encerrar esta assinatura.
                    </span>

                </label>

                <div class="subscription-cancel-actions">

                    <button
                        type="submit"
                        class="subscription-danger-button"
                    >
                        Cancelar assinatura
                    </button>

                </div>

            </form>

        </div>

    </section>

<?php endif; ?>
