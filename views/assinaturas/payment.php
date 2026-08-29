<?php

declare(strict_types=1);

$viewAssinatura = isset($assinatura)
    && is_array($assinatura)
        ? $assinatura
        : [];

$viewErrors = isset($errors)
    && is_array($errors)
        ? $errors
        : [];

$viewFormData = isset($formData)
    && is_array($formData)
        ? $formData
        : [];

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

$moeda =
    isset($viewAssinatura['moeda'])
    && is_string($viewAssinatura['moeda'])
        ? $viewAssinatura['moeda']
        : 'BRL';

$valor =
    isset($viewAssinatura['valor_contratado'])
    && is_numeric($viewAssinatura['valor_contratado'])
        ? (string) $viewAssinatura['valor_contratado']
        : '0';

$valorFormatado =
    number_format(
        (float) $valor,
        2,
        ',',
        '.'
    );

$vencimento =
    isset($viewAssinatura['proxima_cobranca_em'])
    && is_string($viewAssinatura['proxima_cobranca_em'])
        ? $viewAssinatura['proxima_cobranca_em']
        : '';

$vencimentoFormatado =
    $vencimento !== ''
    && strtotime($vencimento) !== false
        ? date(
            'd/m/Y',
            strtotime($vencimento)
        )
        : '—';

$periodicidade =
    isset($viewAssinatura['periodicidade'])
    && is_string($viewAssinatura['periodicidade'])
        ? $viewAssinatura['periodicidade']
        : '';

$metodoSelecionado =
    isset($viewFormData['metodo'])
    && is_string($viewFormData['metodo'])
        ? $viewFormData['metodo']
        : '';

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Financeiro
        </span>

        <h1>
            Registrar pagamento
        </h1>

        <p>
            Confirme o recebimento da cobrança atual antes de avançar o ciclo da assinatura.
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


<section class="payment-confirmation">

    <div class="payment-confirmation-grid">

        <article>
            <span>Cliente</span>
            <strong>
                <?= htmlspecialchars(
                    $clienteNome,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>
        </article>

        <article>
            <span>Produto / Plano</span>
            <strong>
                <?= htmlspecialchars(
                    $produtoNome
                        . (
                            $planoNome !== ''
                                ? ' · ' . $planoNome
                                : ''
                        ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>
        </article>

        <article>
            <span>Valor da cobrança</span>
            <strong>
                <?= htmlspecialchars(
                    $moeda === 'BRL'
                        ? 'R$ ' . $valorFormatado
                        : $moeda . ' ' . $valorFormatado,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>
        </article>

        <article>
            <span>Vencimento</span>
            <strong>
                <?= htmlspecialchars(
                    $vencimentoFormatado,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>
        </article>

        <article>
            <span>Periodicidade</span>
            <strong>
                <?= htmlspecialchars(
                    $periodicidade,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>
        </article>

    </div>

</section>


<section class="form-panel payment-form-panel">

    <header class="form-panel-header">

        <h2>
            Confirmação financeira
        </h2>

        <p>
            Esta ação registra um recebimento real no histórico da assinatura.
        </p>

    </header>

    <form
        method="POST"
        action="<?= htmlspecialchars(
            $viewAppUrl
                . '/assinaturas/'
                . $assinaturaId
                . '/pagamento',
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
        class="form-content"
        novalidate
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

        <?php if (
            isset($viewErrors['geral'])
            && is_string($viewErrors['geral'])
        ): ?>

            <div
                class="form-alert form-alert-error payment-alert"
                role="alert"
            >
                <?= htmlspecialchars(
                    $viewErrors['geral'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>


        <div class="field-group payment-method-field">

            <label for="metodo">
                Método de pagamento
            </label>

            <select
                id="metodo"
                name="metodo"
            >

                <option
                    value=""
                    <?= $metodoSelecionado === ''
                        ? 'selected'
                        : '' ?>
                >
                    Não informado
                </option>

                <?php foreach (
                    [
                        'PIX' => 'Pix',
                        'BOLETO' => 'Boleto',
                        'CARTAO' => 'Cartão',
                        'TRANSFERENCIA' => 'Transferência',
                        'DINHEIRO' => 'Dinheiro',
                        'OUTRO' => 'Outro',
                    ]
                    as $metodoValor => $metodoLabel
                ): ?>

                    <option
                        value="<?= htmlspecialchars(
                            $metodoValor,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        <?= $metodoSelecionado === $metodoValor
                            ? 'selected'
                            : '' ?>
                    >
                        <?= htmlspecialchars(
                            $metodoLabel,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <span class="field-hint">
                Campo informativo. Não armazene número de cartão, CVV, senha ou dados bancários.
            </span>

            <?php if (
                isset($viewErrors['metodo'])
                && is_string($viewErrors['metodo'])
            ): ?>

                <span class="field-error">
                    <?= htmlspecialchars(
                        $viewErrors['metodo'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>

            <?php endif; ?>

        </div>


        <div class="financial-warning">

            <strong>
                Confirmação obrigatória
            </strong>

            <p>
                Ao confirmar, o sistema registrará a cobrança atual como paga e calculará a próxima data conforme a periodicidade contratada. O valor e o vencimento são obtidos novamente pelo backend e não podem ser alterados nesta tela.
            </p>

        </div>


        <div class="form-actions">

            <a
                href="<?= htmlspecialchars(
                    $viewAppUrl . '/assinaturas',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="button-secondary"
            >
                Cancelar
            </a>

            <button
                type="submit"
                class="button-primary"
            >
                Confirmar pagamento
            </button>

        </div>

    </form>

</section>
