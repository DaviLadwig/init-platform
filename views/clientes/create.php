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

$razaoSocial =
    is_string(
        $viewFormData['razao_social']
        ?? null
    )
        ? $viewFormData['razao_social']
        : '';

$nomeFantasia =
    is_string(
        $viewFormData['nome_fantasia']
        ?? null
    )
        ? $viewFormData['nome_fantasia']
        : '';

$cnpj =
    is_string(
        $viewFormData['cnpj']
        ?? null
    )
        ? $viewFormData['cnpj']
        : '';

$email =
    is_string(
        $viewFormData['email']
        ?? null
    )
        ? $viewFormData['email']
        : '';

$telefone =
    is_string(
        $viewFormData['telefone']
        ?? null
    )
        ? $viewFormData['telefone']
        : '';

$slug =
    is_string(
        $viewFormData['slug']
        ?? null
    )
        ? $viewFormData['slug']
        : '';

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Base comercial
        </span>

        <h1>
            Novo cliente
        </h1>

        <p>
            Cadastre a empresa contratante que fará parte da plataforma Init.
        </p>

    </div>

</section>


<section class="form-panel">

    <header class="form-panel-header">

        <div>

            <h2>
                Dados da empresa
            </h2>

            <p>
                Informe os dados cadastrais básicos do cliente.
            </p>

        </div>

    </header>


    <form
        method="POST"
        action="<?= htmlspecialchars(
            $viewAppUrl . '/clientes',
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
            isset($viewErrors['general'])
        ): ?>

            <div
                class="form-alert form-alert-error"
                role="alert"
            >
                <?= htmlspecialchars(
                    (string) $viewErrors['general'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>


        <div class="form-grid">

            <div class="field-group form-grid-full">

                <label for="razao_social">
                    Razão social
                </label>

                <input
                    type="text"
                    id="razao_social"
                    name="razao_social"
                    maxlength="200"
                    value="<?= htmlspecialchars(
                        $razaoSocial,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    required
                >

                <?php if (
                    isset(
                        $viewErrors[
                            'razao_social'
                        ]
                    )
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors[
                                'razao_social'
                            ],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group">

                <label for="nome_fantasia">
                    Nome fantasia
                </label>

                <input
                    type="text"
                    id="nome_fantasia"
                    name="nome_fantasia"
                    maxlength="200"
                    value="<?= htmlspecialchars(
                        $nomeFantasia,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <span class="field-hint">
                    Opcional.
                </span>

                <?php if (
                    isset(
                        $viewErrors[
                            'nome_fantasia'
                        ]
                    )
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors[
                                'nome_fantasia'
                            ],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group">

                <label for="cnpj">
                    CNPJ
                </label>

                <input
                    type="text"
                    id="cnpj"
                    name="cnpj"
                    inputmode="numeric"
                    maxlength="18"
                    value="<?= htmlspecialchars(
                        $cnpj,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    placeholder="00.000.000/0000-00"
                    required
                >

                <?php if (
                    isset($viewErrors['cnpj'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors['cnpj'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group">

                <label for="email">
                    E-mail
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="255"
                    value="<?= htmlspecialchars(
                        $email,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <span class="field-hint">
                    Opcional.
                </span>

                <?php if (
                    isset($viewErrors['email'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors['email'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group">

                <label for="telefone">
                    Telefone
                </label>

                <input
                    type="text"
                    id="telefone"
                    name="telefone"
                    inputmode="tel"
                    maxlength="20"
                    value="<?= htmlspecialchars(
                        $telefone,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    placeholder="(98) 99999-9999"
                >

                <span class="field-hint">
                    Opcional.
                </span>

                <?php if (
                    isset($viewErrors['telefone'])
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors[
                                'telefone'
                            ],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group">

                <label for="slug">
                    Slug
                </label>

                <input
                    type="text"
                    id="slug"
                    name="slug"
                    maxlength="150"
                    value="<?= htmlspecialchars(
                        $slug,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    placeholder="prefeitura-vitoria-do-mearim"
                    required
                >

                <span class="field-hint">
                    Identificador público da empresa na plataforma.
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

        </div>


        <footer class="form-actions">

            <a
                href="<?= htmlspecialchars(
                    $viewAppUrl . '/clientes',
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
                Cadastrar cliente
            </button>

        </footer>

    </form>

</section>