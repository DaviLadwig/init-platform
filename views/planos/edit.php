<?php

declare(strict_types=1);

$viewPlanoId = isset($planoId)
    ? (int) $planoId
    : 0;

$viewProduto = isset($produto)
    && is_array($produto)
    ? $produto
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

$produtoNome =
    is_string($viewProduto['nome'] ?? null)
    ? $viewProduto['nome']
    : '';

$produtoCodigo =
    is_string($viewProduto['codigo'] ?? null)
    ? $viewProduto['codigo']
    : '';

$codigo =
    is_string($viewFormData['codigo'] ?? null)
    ? $viewFormData['codigo']
    : '';

$nome =
    is_string($viewFormData['nome'] ?? null)
    ? $viewFormData['nome']
    : '';

$descricao =
    is_string($viewFormData['descricao'] ?? null)
    ? $viewFormData['descricao']
    : '';

$valor =
    is_string($viewFormData['valor'] ?? null)
    ? $viewFormData['valor']
    : '';

$periodicidade =
    is_string(
        $viewFormData['periodicidade']
            ?? null
    )
    ? $viewFormData['periodicidade']
    : 'MENSAL';

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Estrutura comercial
        </span>

        <h1>
            Editar plano
        </h1>

        <p>
            Atualize as condições comerciais do plano.
        </p>

    </div>

</section>


<section class="form-panel">

    <header class="form-panel-header">

        <div>

            <h2>
                Informações do plano
            </h2>

            <p>
                O produto associado ao plano não pode ser alterado.
            </p>

        </div>

    </header>


    <form
        method="POST"
        action="<?= htmlspecialchars(
                    $viewAppUrl
                        . '/planos/'
                        . $viewPlanoId,
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


        <?php if (
            isset($viewErrors['general'])
        ): ?>

            <div
                class="form-alert form-alert-error"
                role="alert">
                <?= htmlspecialchars(
                    (string) $viewErrors['general'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>


        <div class="plan-product-reference">

            <span>
                Produto
            </span>

            <strong>
                <?= htmlspecialchars(
                    $produtoNome,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>

            <small>
                <?= htmlspecialchars(
                    $produtoCodigo,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </small>

        </div>


        <div class="form-grid">

            <div class="field-group">

                <label for="nome">
                    Nome
                </label>

                <input
                    type="text"
                    id="nome"
                    name="nome"
                    maxlength="150"
                    value="<?= htmlspecialchars(
                                $nome,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                    required>

                <?php if (
                    isset($viewErrors['nome'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors['nome'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group">

                <label for="codigo">
                    Código
                </label>

                <input
                    type="text"
                    id="codigo"
                    name="codigo"
                    maxlength="50"
                    value="<?= htmlspecialchars(
                                $codigo,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                    required>

                <?php if (
                    isset($viewErrors['codigo'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors['codigo'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group">

                <label for="valor">
                    Valor
                </label>

                <input
                    type="text"
                    id="valor"
                    name="valor"
                    inputmode="decimal"
                    maxlength="13"
                    value="<?= htmlspecialchars(
                                $valor,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                    required>

                <?php if (
                    isset($viewErrors['valor'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors['valor'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group">

                <label for="periodicidade">
                    Periodicidade
                </label>

                <select
                    id="periodicidade"
                    name="periodicidade"
                    required>

                    <?php

                    $periodicidades = [
                        'MENSAL' => 'Mensal',
                        'TRIMESTRAL' =>
                        'Trimestral',
                        'SEMESTRAL' =>
                        'Semestral',
                        'ANUAL' => 'Anual',
                    ];

                    ?>

                    <?php foreach (
                        $periodicidades
                        as $value => $label
                    ): ?>

                        <option
                            value="<?= $value ?>"
                            <?= $periodicidade === $value
                                ? 'selected'
                                : '' ?>>
                            <?= $label ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="field-group form-grid-full">

                <label for="descricao">
                    Descrição
                </label>

                <textarea
                    id="descricao"
                    name="descricao"
                    maxlength="2000"
                    rows="5"><?= htmlspecialchars(
                                    $descricao,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?></textarea>

            </div>

        </div>


        <footer class="form-actions">

            <a
                href="<?= htmlspecialchars(
                            $viewAppUrl . '/planos',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                class="button-secondary">
                Cancelar
            </a>

            <button
                type="submit"
                class="button-primary">
                Salvar alterações
            </button>

        </footer>

    </form>

</section>