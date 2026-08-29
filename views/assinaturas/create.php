<?php

declare(strict_types=1);

$viewEmpresas = isset($empresas)
    && is_array($empresas)
    ? $empresas
    : [];

$viewCatalogo = isset($catalogo)
    && is_array($catalogo)
    ? $catalogo
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

$empresaSelecionada =
    isset($viewFormData['empresa_id'])
    && is_scalar($viewFormData['empresa_id'])
    ? (string) $viewFormData['empresa_id']
    : '';

$produtoSelecionado =
    isset($viewFormData['produto_id'])
    && is_scalar($viewFormData['produto_id'])
    ? (string) $viewFormData['produto_id']
    : '';

$planoSelecionado =
    isset($viewFormData['plano_id'])
    && is_scalar($viewFormData['plano_id'])
    ? (string) $viewFormData['plano_id']
    : '';

$inicioEm =
    isset($viewFormData['inicio_em'])
    && is_string($viewFormData['inicio_em'])
    ? $viewFormData['inicio_em']
    : '';

$produtos = [];

foreach ($viewCatalogo as $item) {
    if (!is_array($item)) {
        continue;
    }

    $produtoId =
        isset($item['produto_id'])
        ? (int) $item['produto_id']
        : 0;

    if ($produtoId <= 0) {
        continue;
    }

    if (!isset($produtos[$produtoId])) {
        $produtos[$produtoId] = [
            'id' => $produtoId,
            'nome' =>
            isset($item['produto_nome'])
                && is_string($item['produto_nome'])
                ? $item['produto_nome']
                : '',
            'codigo' =>
            isset($item['produto_codigo'])
                && is_string($item['produto_codigo'])
                ? $item['produto_codigo']
                : '',
            'planos' => [],
        ];
    }

    $produtos[$produtoId]['planos'][] =
        $item;
}

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Gestão comercial
        </span>

        <h1>
            Nova assinatura
        </h1>

        <p>
            Vincule um cliente a um produto e a um plano comercial.
        </p>

    </div>

    <a
        href="<?= htmlspecialchars(
                    $viewAppUrl . '/assinaturas',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
        class="button-secondary">
        Voltar às assinaturas
    </a>

</section>


<section class="form-panel subscription-form-panel">

    <header class="form-panel-header">

        <h2>
            Dados do contrato
        </h2>

        <p>
            O contrato será criado inicialmente como pendente de ativação.
        </p>

    </header>

    <form
        method="POST"
        action="<?= htmlspecialchars(
                    $viewAppUrl . '/assinaturas',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
        class="form-content"
        novalidate>

        <input
            type="hidden"
            name="_token"
            value="<?= htmlspecialchars(
                        $viewCsrfToken,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">

        <?php if ($viewErrors !== []): ?>

            <div
                class="form-alert form-alert-error subscription-form-alert"
                role="alert">
                Revise os campos indicados antes de continuar.
            </div>

        <?php endif; ?>


        <div class="form-grid">

            <div class="field-group form-grid-full">

                <label for="empresa_id">
                    Cliente
                </label>

                <select
                    id="empresa_id"
                    name="empresa_id"
                    required>
                    <option value="">
                        Selecione o cliente
                    </option>

                    <?php foreach ($viewEmpresas as $empresa): ?>

                        <?php

                        if (!is_array($empresa)) {
                            continue;
                        }

                        $empresaId =
                            isset($empresa['id'])
                            ? (int) $empresa['id']
                            : 0;

                        if ($empresaId <= 0) {
                            continue;
                        }

                        $nomeFantasia =
                            isset($empresa['nome_fantasia'])
                            && is_string($empresa['nome_fantasia'])
                            ? trim($empresa['nome_fantasia'])
                            : '';

                        $razaoSocial =
                            isset($empresa['razao_social'])
                            && is_string($empresa['razao_social'])
                            ? trim($empresa['razao_social'])
                            : '';

                        $label =
                            $nomeFantasia !== ''
                            ? $nomeFantasia
                            : $razaoSocial;

                        ?>

                        <option
                            value="<?= $empresaId ?>"
                            <?= $empresaSelecionada === (string) $empresaId
                                ? 'selected'
                                : '' ?>>
                            <?= htmlspecialchars(
                                $label,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if (
                    isset($viewErrors['empresa_id'])
                    && is_string($viewErrors['empresa_id'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            $viewErrors['empresa_id'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group">

                <label for="produto_id">
                    Produto
                </label>

                <select
                    id="produto_id"
                    name="produto_id"
                    required>
                    <option value="">
                        Selecione o produto
                    </option>

                    <?php foreach ($produtos as $produto): ?>

                        <?php

                        $produtoId =
                            (int) $produto['id'];

                        $produtoNome =
                            is_string($produto['nome'])
                            ? $produto['nome']
                            : '';

                        $produtoCodigo =
                            is_string($produto['codigo'])
                            ? $produto['codigo']
                            : '';

                        ?>

                        <option
                            value="<?= $produtoId ?>"
                            <?= $produtoSelecionado === (string) $produtoId
                                ? 'selected'
                                : '' ?>>
                            <?= htmlspecialchars(
                                $produtoNome
                                    . (
                                        $produtoCodigo !== ''
                                        ? ' · ' . $produtoCodigo
                                        : ''
                                    ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if (
                    isset($viewErrors['produto_id'])
                    && is_string($viewErrors['produto_id'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            $viewErrors['produto_id'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group">

                <label for="plano_id">
                    Plano
                </label>

                <select
                    id="plano_id"
                    name="plano_id"
                    required>
                    <option value="">
                        Selecione o plano
                    </option>

                    <?php foreach ($produtos as $produto): ?>

                        <?php

                        $produtoNome =
                            is_string($produto['nome'])
                            ? $produto['nome']
                            : '';

                        $planos =
                            isset($produto['planos'])
                            && is_array($produto['planos'])
                            ? $produto['planos']
                            : [];

                        ?>

                        <optgroup
                            label="<?= htmlspecialchars(
                                        $produtoNome,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>">

                            <?php foreach ($planos as $plano): ?>

                                <?php

                                if (!is_array($plano)) {
                                    continue;
                                }

                                $planoId =
                                    isset($plano['plano_id'])
                                    ? (int) $plano['plano_id']
                                    : 0;

                                if ($planoId <= 0) {
                                    continue;
                                }

                                $planoNome =
                                    isset($plano['plano_nome'])
                                    && is_string($plano['plano_nome'])
                                    ? $plano['plano_nome']
                                    : '';

                                $planoCodigo =
                                    isset($plano['plano_codigo'])
                                    && is_string($plano['plano_codigo'])
                                    ? $plano['plano_codigo']
                                    : '';

                                $periodicidade =
                                    isset($plano['periodicidade'])
                                    && is_string($plano['periodicidade'])
                                    ? $plano['periodicidade']
                                    : '';

                                $valor =
                                    isset($plano['valor'])
                                    && is_numeric($plano['valor'])
                                    ? (float) $plano['valor']
                                    : 0.0;

                                $moeda =
                                    isset($plano['moeda'])
                                    && is_string($plano['moeda'])
                                    ? $plano['moeda']
                                    : 'BRL';

                                $valorLabel =
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
                                    );

                                ?>

                                <option
                                    value="<?= $planoId ?>"
                                    <?= $planoSelecionado === (string) $planoId
                                        ? 'selected'
                                        : '' ?>>
                                    <?= htmlspecialchars(
                                        $planoNome
                                            . (
                                                $planoCodigo !== ''
                                                ? ' · ' . $planoCodigo
                                                : ''
                                            )
                                            . ' · '
                                            . $valorLabel
                                            . (
                                                $periodicidade !== ''
                                                ? ' · ' . $periodicidade
                                                : ''
                                            ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </optgroup>

                    <?php endforeach; ?>

                </select>

                <span class="field-hint">
                    O backend confirmará se o plano pertence ao produto selecionado.
                </span>

                <?php if (
                    isset($viewErrors['plano_id'])
                    && is_string($viewErrors['plano_id'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            $viewErrors['plano_id'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group form-grid-full subscription-date-field">

                <label for="inicio_em">
                    Data de início
                </label>

                <input
                    type="date"
                    id="inicio_em"
                    name="inicio_em"
                    value="<?= htmlspecialchars(
                                $inicioEm,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                    required>

                <span class="field-hint">
                    A ativação e a próxima cobrança serão tratadas em uma etapa posterior.
                </span>

                <?php if (
                    isset($viewErrors['inicio_em'])
                    && is_string($viewErrors['inicio_em'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            $viewErrors['inicio_em'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>

        </div>


        <div class="subscription-contract-note">

            <strong>
                Definição automática
            </strong>

            <p>
                Status, valor contratado, moeda e periodicidade são definidos pelo backend com base no plano cadastrado.
            </p>

        </div>


        <div class="form-actions">

            <a
                href="<?= htmlspecialchars(
                            $viewAppUrl . '/assinaturas',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                class="button-secondary">
                Cancelar
            </a>

            <button
                type="submit"
                class="button-primary">
                Criar assinatura
            </button>

        </div>

    </form>

</section>