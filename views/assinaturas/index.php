<?php

declare(strict_types=1);

$viewAssinaturas = isset($assinaturas)
    && is_array($assinaturas)
        ? $assinaturas
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


$viewCsrfToken = isset($csrfToken)
    && is_string($csrfToken)
        ? $csrfToken
        : '';

$viewAppUrl = isset($appUrl)
    && is_string($appUrl)
        ? rtrim($appUrl, '/')
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

$statusPresentation = static function (string $status): array {
    return match ($status) {
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
};

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Gestão comercial
        </span>

        <h1>
            Assinaturas
        </h1>

        <p>
            Contratos comerciais dos clientes, produtos e planos da plataforma.
        </p>

    </div>

    <div class="subscription-heading-actions">

        <form
            method="POST"
            action="<?= htmlspecialchars(
                $viewAppUrl . '/assinaturas/processar-inadimplencia',
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            class="subscription-status-sync-form"
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
                class="button-secondary subscription-status-sync-button"
            >
                Atualizar situação
            </button>
        </form>

        <a
            href="<?= htmlspecialchars(
                $viewAppUrl . '/assinaturas/vencimentos',
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            class="button-secondary subscription-due-button"
        >
            Vencimentos

            <?php if (
                (int) ($viewResumo['vencimentos'] ?? 0) > 0
            ): ?>

                <span>
                    <?= (int) $viewResumo['vencimentos'] ?>
                </span>

            <?php endif; ?>
        </a>

        <a
            href="<?= htmlspecialchars(
                $viewAppUrl . '/assinaturas/novo',
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            class="button-primary"
        >
            Nova assinatura
        </a>

    </div>

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


<section class="subscription-summary">

    <article class="subscription-summary-item">
        <span>Total</span>
        <strong>
            <?= (int) ($viewResumo['total'] ?? 0) ?>
        </strong>
    </article>

    <article class="subscription-summary-item">
        <span>Ativas</span>
        <strong>
            <?= (int) ($viewResumo['ativas'] ?? 0) ?>
        </strong>
    </article>

    <article class="subscription-summary-item">
        <span>Pendentes</span>
        <strong>
            <?= (int) ($viewResumo['pendentes'] ?? 0) ?>
        </strong>
    </article>

    <article class="subscription-summary-item">
        <span>Atrasadas</span>
        <strong>
            <?= (int) ($viewResumo['atrasadas'] ?? 0) ?>
        </strong>
    </article>

    <article class="subscription-summary-item">
        <span>Suspensas</span>
        <strong>
            <?= (int) ($viewResumo['suspensas'] ?? 0) ?>
        </strong>
    </article>

</section>


<section class="data-panel">

    <header class="data-panel-header">

        <div>

            <h2>
                Contratos cadastrados
            </h2>

            <p>
                <?= count($viewAssinaturas) ?>
                assinatura<?= count($viewAssinaturas) === 1 ? '' : 's' ?>
                registrada<?= count($viewAssinaturas) === 1 ? '' : 's' ?>
            </p>

        </div>

    </header>


    <?php if ($viewAssinaturas === []): ?>

        <div class="empty-state">

            <h3>
                Nenhuma assinatura cadastrada
            </h3>

            <p>
                Crie o primeiro contrato comercial para começar.
            </p>

        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="data-table subscription-table">

                <thead>

                    <tr>
                        <th>Cliente</th>
                        <th>Produto / Plano</th>
                        <th>Valor</th>
                        <th>Início</th>
                        <th>Próxima cobrança</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($viewAssinaturas as $assinatura): ?>

                        <?php

                        if (!is_array($assinatura)) {
                            continue;
                        }


                        $assinaturaId =
                            isset($assinatura['id'])
                                ? (int) $assinatura['id']
                                : 0;

                        $nomeFantasia =
                            isset($assinatura['empresa_nome_fantasia'])
                            && is_string($assinatura['empresa_nome_fantasia'])
                                ? trim($assinatura['empresa_nome_fantasia'])
                                : '';

                        $razaoSocial =
                            isset($assinatura['empresa_razao_social'])
                            && is_string($assinatura['empresa_razao_social'])
                                ? trim($assinatura['empresa_razao_social'])
                                : '';

                        $clienteNome =
                            $nomeFantasia !== ''
                                ? $nomeFantasia
                                : $razaoSocial;

                        $produtoNome =
                            isset($assinatura['produto_nome'])
                            && is_string($assinatura['produto_nome'])
                                ? $assinatura['produto_nome']
                                : '';

                        $produtoCodigo =
                            isset($assinatura['produto_codigo'])
                            && is_string($assinatura['produto_codigo'])
                                ? $assinatura['produto_codigo']
                                : '';

                        $planoNome =
                            isset($assinatura['plano_nome'])
                            && is_string($assinatura['plano_nome'])
                                ? $assinatura['plano_nome']
                                : '';

                        $periodicidade =
                            isset($assinatura['periodicidade'])
                            && is_string($assinatura['periodicidade'])
                                ? $assinatura['periodicidade']
                                : '';

                        $moeda =
                            isset($assinatura['moeda'])
                            && is_string($assinatura['moeda'])
                                ? $assinatura['moeda']
                                : 'BRL';

                        $valor =
                            isset($assinatura['valor_contratado'])
                            && is_numeric($assinatura['valor_contratado'])
                                ? (float) $assinatura['valor_contratado']
                                : 0.0;

                        $status =
                            isset($assinatura['status'])
                            && is_string($assinatura['status'])
                                ? $assinatura['status']
                                : '';

                        $statusData =
                            $statusPresentation(
                                $status
                            );

                        ?>

                        <tr>

                            <td>

                                <div class="subscription-client">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $clienteNome,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>

                                    <?php if (
                                        $nomeFantasia !== ''
                                        && $razaoSocial !== ''
                                        && $nomeFantasia !== $razaoSocial
                                    ): ?>

                                        <span>
                                            <?= htmlspecialchars(
                                                $razaoSocial,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </td>

                            <td>

                                <div class="subscription-plan">

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

                                        <?php if ($produtoCodigo !== ''): ?>
                                            ·
                                            <?= htmlspecialchars(
                                                $produtoCodigo,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        <?php endif; ?>
                                    </span>

                                </div>

                            </td>

                            <td>

                                <div class="subscription-value">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $moeda === 'BRL'
                                                ? 'R$ ' . number_format(
                                                    $valor,
                                                    2,
                                                    ',',
                                                    '.'
                                                )
                                                : $moeda . ' ' . number_format(
                                                    $valor,
                                                    2,
                                                    ',',
                                                    '.'
                                                ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars(
                                            $periodicidade !== ''
                                                ? mb_convert_case(
                                                    mb_strtolower(
                                                        $periodicidade,
                                                        'UTF-8'
                                                    ),
                                                    MB_CASE_TITLE,
                                                    'UTF-8'
                                                )
                                                : '—',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                </div>

                            </td>

                            <td>
                                <span class="table-muted">
                                    <?= htmlspecialchars(
                                        $formatDate(
                                            $assinatura['inicio_em']
                                            ?? null
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <span class="table-muted">
                                    <?= htmlspecialchars(
                                        $formatDate(
                                            $assinatura['proxima_cobranca_em']
                                            ?? null
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </td>

                            <td>

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

                            </td>


                            <td>

                                <div class="subscription-actions">

                                    <?php if ($assinaturaId > 0): ?>

                                        <a
                                            href="<?= htmlspecialchars(
                                                $viewAppUrl
                                                    . '/assinaturas/'
                                                    . $assinaturaId,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            class="subscription-action-link"
                                        >
                                            Ver
                                        </a>

                                    <?php endif; ?>

                                    <?php if (
                                        $status === 'PENDENTE_ATIVACAO'
                                        && $assinaturaId > 0
                                    ): ?>

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
                                                class="subscription-action-button"
                                            >
                                                Ativar
                                            </button>

                                        </form>

                                    <?php elseif (
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
                                            class="subscription-action-link"
                                        >
                                            Registrar pagamento
                                        </a>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>
