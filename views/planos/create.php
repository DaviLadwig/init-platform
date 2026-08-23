<?php

declare(strict_types=1);

$viewProdutos = isset($produtos)
    && is_array($produtos)
    ? $produtos
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

$produtoId =
    isset($viewFormData['produto_id'])
    ? (string) $viewFormData['produto_id']
    : '';

$codigo =
    isset($viewFormData['codigo'])
    && is_string($viewFormData['codigo'])
    ? $viewFormData['codigo']
    : '';

$nome =
    isset($viewFormData['nome'])
    && is_string($viewFormData['nome'])
    ? $viewFormData['nome']
    : '';

$descricao =
    isset($viewFormData['descricao'])
    && is_string($viewFormData['descricao'])
    ? $viewFormData['descricao']
    : '';

$valor =
    isset($viewFormData['valor'])
    && is_string($viewFormData['valor'])
    ? $viewFormData['valor']
    : '';

$periodicidade =
    isset($viewFormData['periodicidade'])
    && is_string($viewFormData['periodicidade'])
    ? $viewFormData['periodicidade']
    : 'MENSAL';

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Estrutura comercial
        </span>

        <h1>
            Novo plano
        </h1>

        <p>
            Configure um plano comercial para um dos produtos ativos da plataforma.
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
                Defina o produto, preço e periodicidade comercial.
            </p>

        </div>

    </header>


    <form
        method="POST"
        action="<?= htmlspecialchars(
                    $viewAppUrl . '/planos',
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


        <div class="form-grid">

            <div class="field-group form-grid-full">

                <label for="produto_id">
                    Produto
                </label>

                <select
                    id="produto_id"
                    name="produto_id"
                    required>

                    <option value="">
                        Selecione um produto
                    </option>

                    <?php foreach (
                        $viewProdutos as $produto
                    ): ?>

                        <?php

                        $id = isset($produto['id'])
                            ? (int) $produto['id']
                            : 0;

                        $produtoNome =
                            isset($produto['nome'])
                            && is_string($produto['nome'])
                            ? $produto['nome']
                            : '';

                        $produtoCodigo =
                            isset($produto['codigo'])
                            && is_string($produto['codigo'])
                            ? $produto['codigo']
                            : '';

                        ?>

                        <option
                            value="<?= $id ?>"
                            <?= $produtoId === (string) $id
                                ? 'selected'
                                : '' ?>>
                            <?= htmlspecialchars(
                                $produtoNome
                                    . ' — '
                                    . $produtoCodigo,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if (
                    isset($viewErrors['produto_id'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors['produto_id'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>


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
                    placeholder="Ex: Professional"
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
                    placeholder="Ex: PROFESSIONAL"
                    required>

                <span class="field-hint">
                    Letras maiúsculas, números e underline.
                </span>

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
                    placeholder="199,90"
                    required>

                <span class="field-hint">
                    Moeda padrão: BRL.
                </span>

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
                        'TRIMESTRAL' => 'Trimestral',
                        'SEMESTRAL' => 'Semestral',
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

                <?php if (
                    isset($viewErrors['periodicidade'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors['periodicidade'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group form-grid-full">

                <label for="descricao">
                    Descrição
                </label>

                <textarea
                    id="descricao"
                    name="descricao"
                    maxlength="2000"
                    rows="5"
                    placeholder="Descreva a proposta comercial deste plano."><?= htmlspecialchars(
                                                                                    $descricao,
                                                                                    ENT_QUOTES,
                                                                                    'UTF-8'
                                                                                ) ?></textarea>

                <?php if (
                    isset($viewErrors['descricao'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors['descricao'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                <?php endif; ?>

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
                Cadastrar plano
            </button>

        </footer>

    </form>

</section>