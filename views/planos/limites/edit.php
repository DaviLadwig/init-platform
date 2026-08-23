<?php

declare(strict_types=1);

$viewPlano = isset($plano)
    && is_array($plano)
    ? $plano
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

$viewLimiteId = isset($limiteId)
    ? (int) $limiteId
    : 0;

$planoId = isset($viewPlano['id'])
    ? (int) $viewPlano['id']
    : 0;

$planoNome =
    is_string($viewPlano['nome'] ?? null)
    ? $viewPlano['nome']
    : '';

$planoCodigo =
    is_string($viewPlano['codigo'] ?? null)
    ? $viewPlano['codigo']
    : '';

$produtoNome =
    is_string(
        $viewPlano['produto_nome']
            ?? null
    )
    ? $viewPlano['produto_nome']
    : '';

$chave =
    is_string($viewFormData['chave'] ?? null)
    ? $viewFormData['chave']
    : '';

$valor =
    is_string($viewFormData['valor'] ?? null)
    ? $viewFormData['valor']
    : '';

$unidade =
    is_string($viewFormData['unidade'] ?? null)
    ? $viewFormData['unidade']
    : '';

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Configuração comercial
        </span>

        <h1>
            Editar limite
        </h1>

        <p>
            Atualize a capacidade ou restrição configurada neste plano.
        </p>

    </div>

</section>


<section class="limit-form-context">

    <span>
        <?= htmlspecialchars(
            $produtoNome,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </span>

    <strong>
        <?= htmlspecialchars(
            $planoNome,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </strong>

    <small>
        <?= htmlspecialchars(
            $planoCodigo,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </small>

</section>


<section class="form-panel">

    <header class="form-panel-header">

        <div>

            <h2>
                Configuração do limite
            </h2>

            <p>
                As alterações serão aplicadas ao plano e registradas na auditoria.
            </p>

        </div>

    </header>


    <form
        method="POST"
        action="<?= htmlspecialchars(
                    $viewAppUrl
                        . '/planos/'
                        . $planoId
                        . '/limites/'
                        . $viewLimiteId,
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

                <label for="chave">
                    Chave
                </label>

                <input
                    type="text"
                    id="chave"
                    name="chave"
                    maxlength="80"
                    value="<?= htmlspecialchars(
                                $chave,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                    required>

                <span class="field-hint">
                    Identificador técnico utilizado pelos produtos SaaS.
                </span>

                <?php if (
                    isset($viewErrors['chave'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors['chave'],
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

                <label for="unidade">
                    Unidade
                </label>

                <input
                    type="text"
                    id="unidade"
                    name="unidade"
                    maxlength="50"
                    value="<?= htmlspecialchars(
                                $unidade,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">

                <?php if (
                    isset($viewErrors['unidade'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors['unidade'],
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
                            $viewAppUrl
                                . '/planos/'
                                . $planoId
                                . '/limites',
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