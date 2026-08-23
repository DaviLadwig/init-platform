<?php

declare(strict_types=1);

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

$codigo = isset($viewFormData['codigo'])
    && is_string($viewFormData['codigo'])
    ? $viewFormData['codigo']
    : '';

$nome = isset($viewFormData['nome'])
    && is_string($viewFormData['nome'])
    ? $viewFormData['nome']
    : '';

$slug = isset($viewFormData['slug'])
    && is_string($viewFormData['slug'])
    ? $viewFormData['slug']
    : '';

$descricao = isset($viewFormData['descricao'])
    && is_string($viewFormData['descricao'])
    ? $viewFormData['descricao']
    : '';

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Catálogo SaaS
        </span>

        <h1>
            Novo produto
        </h1>

        <p>
            Cadastre um novo produto disponibilizado pela Init Sistemas.
        </p>

    </div>

</section>


<section class="form-panel">

    <header class="form-panel-header">

        <div>

            <h2>
                Informações do produto
            </h2>

            <p>
                Defina a identificação principal do produto SaaS.
            </p>

        </div>

    </header>


    <form
        method="POST"
        action="<?= htmlspecialchars(
                    $viewAppUrl . '/produtos',
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
                    placeholder="EX: INIT_LAN"
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


            <div class="field-group form-grid-full">

                <label for="slug">
                    Slug
                </label>

                <input
                    type="text"
                    id="slug"
                    name="slug"
                    maxlength="100"
                    value="<?= htmlspecialchars(
                                $slug,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                    placeholder="ex: init-lan"
                    required>

                <span class="field-hint">
                    Identificador amigável utilizado internamente nas URLs.
                </span>

                <?php if (
                    isset($viewErrors['slug'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors['slug'],
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
                    rows="5"><?= htmlspecialchars(
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
                            $viewAppUrl . '/produtos',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                class="button-secondary">
                Cancelar
            </a>

            <button
                type="submit"
                class="button-primary">
                Cadastrar produto
            </button>

        </footer>

    </form>

</section>