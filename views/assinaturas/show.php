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

$viewDocumentosContratuais =
    isset($documentosContratuais)
    && is_array($documentosContratuais)
        ? $documentosContratuais
        : [];

$viewModelosContratuais =
    isset($viewDocumentosContratuais['modelos'])
    && is_array($viewDocumentosContratuais['modelos'])
        ? $viewDocumentosContratuais['modelos']
        : [];

$viewResponsaveisContratuais =
    isset($viewDocumentosContratuais['responsaveis'])
    && is_array($viewDocumentosContratuais['responsaveis'])
        ? $viewDocumentosContratuais['responsaveis']
        : [];

$viewDocumentosCancelados =
    isset($viewDocumentosContratuais['cancelados'])
    && is_array($viewDocumentosContratuais['cancelados'])
        ? $viewDocumentosContratuais['cancelados']
        : [];

$viewRequisitosDocumentais =
    isset($viewDocumentosContratuais['requisitos_ativacao'])
    && is_array($viewDocumentosContratuais['requisitos_ativacao'])
        ? $viewDocumentosContratuais['requisitos_ativacao']
        : [
            'pronto' => true,
            'total_obrigatorios' => 0,
            'total_assinados' => 0,
            'pendencias' => [],
        ];

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


<section class="subscription-documents-panel">

    <header class="subscription-documents-header">

        <div>

            <span class="subscription-section-eyebrow">
                Documentação
            </span>

            <h2>
                Documentos contratuais
            </h2>

            <p>
                Termos vinculados à contratação e documentos assinados pelo responsável da empresa.
            </p>

        </div>

        <?php

        $documentacaoPronta =
            ($viewRequisitosDocumentais['pronto'] ?? false)
            === true;

        $totalObrigatorios =
            isset($viewRequisitosDocumentais['total_obrigatorios'])
                ? (int) $viewRequisitosDocumentais['total_obrigatorios']
                : 0;

        $totalAssinados =
            isset($viewRequisitosDocumentais['total_assinados'])
                ? (int) $viewRequisitosDocumentais['total_assinados']
                : 0;

        ?>

        <?php if ($totalObrigatorios > 0): ?>

            <div class="subscription-document-summary">

                <span
                    class="subscription-document-summary-state <?= $documentacaoPronta
                        ? 'is-complete'
                        : 'is-pending' ?>"
                >
                    <?= $documentacaoPronta
                        ? 'Documentação completa'
                        : 'Documentação pendente' ?>
                </span>

                <small>
                    <?= $totalAssinados ?>
                    de
                    <?= $totalObrigatorios ?>
                    obrigatório<?= $totalObrigatorios === 1 ? '' : 's' ?>
                    assinado<?= $totalObrigatorios === 1 ? '' : 's' ?>
                </small>

            </div>

        <?php endif; ?>

    </header>


    <?php if ($viewModelosContratuais === []): ?>

        <div class="subscription-document-empty">

            <strong>
                Nenhum termo ativo configurado
            </strong>

            <p>
                Cadastre uma versão contratual ativa para vinculá-la às assinaturas.
            </p>

        </div>

    <?php else: ?>

        <div class="subscription-document-list">

            <?php foreach ($viewModelosContratuais as $modelo): ?>

                <?php

                if (!is_array($modelo)) {
                    continue;
                }

                $modeloId =
                    isset($modelo['id'])
                        ? (int) $modelo['id']
                        : 0;

                $modeloTitulo =
                    isset($modelo['titulo'])
                    && is_string($modelo['titulo'])
                        ? $modelo['titulo']
                        : 'Documento contratual';

                $modeloVersao =
                    isset($modelo['versao'])
                    && is_string($modelo['versao'])
                        ? $modelo['versao']
                        : '';

                $modeloDescricao =
                    isset($modelo['descricao'])
                    && is_string($modelo['descricao'])
                        ? trim($modelo['descricao'])
                        : '';

                $modeloObrigatorio =
                    ($modelo['obrigatorio_ativacao'] ?? false)
                    === true;

                $tentativa =
                    isset($modelo['tentativa_vigente'])
                    && is_array($modelo['tentativa_vigente'])
                        ? $modelo['tentativa_vigente']
                        : null;

                $documentoId =
                    $tentativa !== null
                    && isset($tentativa['id'])
                        ? (int) $tentativa['id']
                        : 0;

                $documentoStatus =
                    $tentativa !== null
                    && isset($tentativa['status'])
                    && is_string($tentativa['status'])
                        ? $tentativa['status']
                        : null;

                $documentStatusData = match ($documentoStatus) {
                    'GERADO' => [
                        'label' => 'Gerado',
                        'class' => 'document-status-generated',
                    ],
                    'AGUARDANDO_ASSINATURA' => [
                        'label' => 'Aguardando assinatura',
                        'class' => 'document-status-waiting',
                    ],
                    'ASSINADO' => [
                        'label' => 'Assinado',
                        'class' => 'document-status-signed',
                    ],
                    default => [
                        'label' => 'Não gerado',
                        'class' => 'document-status-neutral',
                    ],
                };

                ?>

                <article class="subscription-document-item">

                    <div class="subscription-document-item-head">

                        <div>

                            <div class="subscription-document-title-line">

                                <h3>
                                    <?= htmlspecialchars(
                                        $modeloTitulo,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </h3>

                                <span class="subscription-document-version">
                                    v<?= htmlspecialchars(
                                        $modeloVersao,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                                <?php if ($modeloObrigatorio): ?>

                                    <span class="subscription-document-required">
                                        Obrigatório
                                    </span>

                                <?php endif; ?>

                            </div>

                            <?php if ($modeloDescricao !== ''): ?>

                                <p>
                                    <?= htmlspecialchars(
                                        $modeloDescricao,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </p>

                            <?php endif; ?>

                        </div>

                        <span
                            class="subscription-document-status <?= htmlspecialchars(
                                $documentStatusData['class'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                            <?= htmlspecialchars(
                                $documentStatusData['label'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                    </div>


                    <?php if (
                        $tentativa === null
                        && $status !== 'CANCELADA'
                        && $modeloId > 0
                    ): ?>

                        <?php if ($viewResponsaveisContratuais === []): ?>

                            <div class="subscription-document-warning">

                                <strong>
                                    Responsável necessário
                                </strong>

                                <p>
                                    Cadastre ou ative um responsável da empresa antes de gerar o termo.
                                </p>

                            </div>

                        <?php else: ?>

                            <form
                                method="POST"
                                action="<?= htmlspecialchars(
                                    $viewAppUrl
                                        . '/assinaturas/'
                                        . $assinaturaId
                                        . '/documentos',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                enctype="multipart/form-data"
                                class="subscription-document-form"
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

                                <input
                                    type="hidden"
                                    name="documento_contratual_id"
                                    value="<?= $modeloId ?>"
                                >

                                <div class="subscription-document-fields">

                                    <div class="field-group">

                                        <label
                                            for="responsavel-<?= $modeloId ?>"
                                        >
                                            Responsável que assinará
                                        </label>

                                        <select
                                            id="responsavel-<?= $modeloId ?>"
                                            name="responsavel_id"
                                            required
                                        >
                                            <option value="">
                                                Selecione
                                            </option>

                                            <?php foreach ($viewResponsaveisContratuais as $responsavel): ?>

                                                <?php

                                                if (!is_array($responsavel)) {
                                                    continue;
                                                }

                                                $responsavelId =
                                                    isset($responsavel['id'])
                                                        ? (int) $responsavel['id']
                                                        : 0;

                                                $responsavelNome =
                                                    isset($responsavel['nome'])
                                                    && is_string($responsavel['nome'])
                                                        ? trim($responsavel['nome'])
                                                        : '';

                                                $responsavelPrincipal =
                                                    ($responsavel['principal'] ?? false)
                                                    === true
                                                    || ($responsavel['principal'] ?? null) === 't'
                                                    || ($responsavel['principal'] ?? null) === '1';

                                                if (
                                                    $responsavelId <= 0
                                                    || $responsavelNome === ''
                                                ) {
                                                    continue;
                                                }

                                                ?>

                                                <option value="<?= $responsavelId ?>">
                                                    <?= htmlspecialchars(
                                                        $responsavelNome
                                                        . (
                                                            $responsavelPrincipal
                                                                ? ' — Principal'
                                                                : ''
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>


                                    <div class="field-group">

                                        <label
                                            for="documento-pdf-<?= $modeloId ?>"
                                        >
                                            PDF original do termo
                                        </label>

                                        <input
                                            id="documento-pdf-<?= $modeloId ?>"
                                            type="file"
                                            name="documento_pdf"
                                            accept=".pdf,application/pdf"
                                            required
                                        >

                                        <span class="field-hint">
                                            PDF de até 15 MB. O arquivo será armazenado de forma privada e receberá hash SHA-256.
                                        </span>

                                    </div>

                                </div>

                                <div class="subscription-document-form-actions">

                                    <button
                                        type="submit"
                                        class="button-primary"
                                    >
                                        Registrar termo
                                    </button>

                                </div>

                            </form>

                        <?php endif; ?>


                    <?php elseif (
                        $tentativa !== null
                        && $documentoId > 0
                    ): ?>

                        <?php

                        $responsavelNomeDocumento =
                            isset($tentativa['responsavel_nome'])
                            && is_string($tentativa['responsavel_nome'])
                                ? trim($tentativa['responsavel_nome'])
                                : '';

                        $responsavelDocumentoId =
                            isset($tentativa['responsavel_id'])
                                ? (int) $tentativa['responsavel_id']
                                : 0;

                        $provider =
                            isset($tentativa['provider'])
                            && is_string($tentativa['provider'])
                                ? $tentativa['provider']
                                : '';

                        ?>

                        <div class="subscription-document-meta">

                            <div>
                                <span>Responsável</span>
                                <strong>
                                    <?= htmlspecialchars(
                                        $responsavelNomeDocumento !== ''
                                            ? $responsavelNomeDocumento
                                            : '—',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                            </div>

                            <div>
                                <span>Provedor</span>
                                <strong>
                                    <?= htmlspecialchars(
                                        $provider !== ''
                                            ? $provider
                                            : 'Ainda não enviado',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                            </div>

                            <div>
                                <span>Enviado em</span>
                                <strong>
                                    <?= htmlspecialchars(
                                        $formatDateTime(
                                            $tentativa['enviado_em']
                                            ?? null
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                            </div>

                            <div>
                                <span>Assinado em</span>
                                <strong>
                                    <?= htmlspecialchars(
                                        isset($tentativa['assinado_data'])
                                        && is_string($tentativa['assinado_data'])
                                        && $tentativa['assinado_data'] !== ''
                                            ? $formatDate(
                                                $tentativa['assinado_data']
                                            )
                                            : $formatDate(
                                                $tentativa['assinado_em']
                                                ?? null
                                            ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                            </div>

                        </div>


                        <div class="subscription-document-actions">

                            <a
                                href="<?= htmlspecialchars(
                                    $viewAppUrl
                                        . '/assinaturas/'
                                        . $assinaturaId
                                        . '/documentos/'
                                        . $documentoId
                                        . '/original',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                class="button-secondary"
                            >
                                Baixar original
                            </a>


                            <?php if ($documentoStatus === 'GERADO'): ?>

                                <form
                                    method="POST"
                                    action="<?= htmlspecialchars(
                                        $viewAppUrl
                                            . '/assinaturas/'
                                            . $assinaturaId
                                            . '/documentos/'
                                            . $documentoId
                                            . '/enviar-autentique',
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
                                        Marcar envio ao Autentique
                                    </button>

                                </form>

                            <?php endif; ?>


                            <?php if ($documentoStatus === 'ASSINADO'): ?>

                                <a
                                    href="<?= htmlspecialchars(
                                        $viewAppUrl
                                            . '/assinaturas/'
                                            . $assinaturaId
                                            . '/documentos/'
                                            . $documentoId
                                            . '/assinado',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    class="button-secondary"
                                >
                                    Baixar assinado
                                </a>

                            <?php endif; ?>

                        </div>


                        <?php if (
                            $documentoStatus === 'GERADO'
                        ): ?>

                            <div class="subscription-document-guidance">

                                <strong>
                                    Próximo passo
                                </strong>

                                <p>
                                    Baixe o original, envie o arquivo manualmente pelo Autentique e, depois do envio, marque esta etapa no sistema.
                                </p>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            $documentoStatus === 'AGUARDANDO_ASSINATURA'
                            && $responsavelDocumentoId > 0
                        ): ?>

                            <form
                                method="POST"
                                action="<?= htmlspecialchars(
                                    $viewAppUrl
                                        . '/assinaturas/'
                                        . $assinaturaId
                                        . '/documentos/'
                                        . $documentoId
                                        . '/assinado',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                enctype="multipart/form-data"
                                class="subscription-document-form subscription-document-signed-form"
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

                                <input
                                    type="hidden"
                                    name="responsavel_id"
                                    value="<?= $responsavelDocumentoId ?>"
                                >

                                <div class="subscription-document-fields">

                                    <div class="field-group">

                                        <label
                                            for="documento-assinado-<?= $documentoId ?>"
                                        >
                                            PDF assinado
                                        </label>

                                        <input
                                            id="documento-assinado-<?= $documentoId ?>"
                                            type="file"
                                            name="documento_assinado_pdf"
                                            accept=".pdf,application/pdf"
                                            required
                                        >

                                    </div>

                                    <div class="field-group">

                                        <label
                                            for="assinado-data-<?= $documentoId ?>"
                                        >
                                            Data da assinatura
                                        </label>

                                        <div class="subscription-date-field">

                                            <input
                                                id="assinado-data-<?= $documentoId ?>"
                                                class="subscription-date-input"
                                                type="date"
                                                name="assinado_data"
                                                max="<?= htmlspecialchars(
                                                    date('Y-m-d'),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                                required
                                            >

                                        </div>

                                        <span class="field-hint">
                                            Informe somente a data exibida no documento/Autentique.
                                        </span>

                                    </div>

                                </div>

                                <div class="subscription-document-form-actions">

                                    <button
                                        type="submit"
                                        class="button-primary"
                                    >
                                        Registrar documento assinado
                                    </button>

                                </div>

                            </form>

                        <?php endif; ?>


                        <?php if (
                            in_array(
                                $documentoStatus,
                                [
                                    'GERADO',
                                    'AGUARDANDO_ASSINATURA',
                                ],
                                true
                            )
                        ): ?>

                            <form
                                method="POST"
                                action="<?= htmlspecialchars(
                                    $viewAppUrl
                                        . '/assinaturas/'
                                        . $assinaturaId
                                        . '/documentos/'
                                        . $documentoId
                                        . '/cancelar',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                class="subscription-document-cancel-form"
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
                                    class="subscription-document-cancel-button"
                                >
                                    Cancelar tentativa
                                </button>

                            </form>

                        <?php endif; ?>


                        <?php if ($documentoStatus === 'ASSINADO'): ?>

                            <div class="subscription-document-signed-info">

                                <span>
                                    Documento consolidado
                                </span>

                                <strong>
                                    <?= htmlspecialchars(
                                        isset($tentativa['signatario_nome'])
                                        && is_string($tentativa['signatario_nome'])
                                            ? $tentativa['signatario_nome']
                                            : 'Responsável registrado',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>

                                <p>
                                    O PDF assinado está preservado no storage privado e protegido contra alteração pelo banco de dados.
                                </p>

                            </div>

                        <?php endif; ?>

                    <?php endif; ?>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <?php if ($viewDocumentosCancelados !== []): ?>

        <details class="subscription-document-history">

            <summary>
                Tentativas canceladas
                (<?= count($viewDocumentosCancelados) ?>)
            </summary>

            <div>

                <?php foreach ($viewDocumentosCancelados as $cancelado): ?>

                    <?php

                    if (!is_array($cancelado)) {
                        continue;
                    }

                    ?>

                    <p>
                        <strong>
                            <?= htmlspecialchars(
                                isset($cancelado['titulo_snapshot'])
                                && is_string($cancelado['titulo_snapshot'])
                                    ? $cancelado['titulo_snapshot']
                                    : 'Documento contratual',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>

                        <span>
                            v<?= htmlspecialchars(
                                isset($cancelado['versao_snapshot'])
                                && is_string($cancelado['versao_snapshot'])
                                    ? $cancelado['versao_snapshot']
                                    : '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                            · cancelado em
                            <?= htmlspecialchars(
                                $formatDateTime(
                                    $cancelado['cancelado_em']
                                    ?? null
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>
                    </p>

                <?php endforeach; ?>

            </div>

        </details>

    <?php endif; ?>

</section>


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

            <?php

            $ativacaoDocumentalPermitida =
                ($viewRequisitosDocumentais['pronto'] ?? false)
                === true;

            ?>

            <div class="subscription-operation-row">

                <div>
                    <strong>
                        Ativar assinatura
                    </strong>

                    <?php if ($ativacaoDocumentalPermitida): ?>

                        <p>
                            Documentação obrigatória concluída. A assinatura está apta para iniciar o ciclo comercial.
                        </p>

                    <?php else: ?>

                        <p>
                            Conclua a assinatura dos documentos contratuais obrigatórios antes da ativação.
                        </p>

                    <?php endif; ?>
                </div>

                <?php if ($ativacaoDocumentalPermitida): ?>

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

                <?php else: ?>

                    <button
                        type="button"
                        class="button-secondary subscription-activation-disabled"
                        disabled
                        aria-disabled="true"
                    >
                        Aguardando documentação
                    </button>

                <?php endif; ?>

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
