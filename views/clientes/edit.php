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

$viewClienteId = isset($clienteId)
    ? (int) $clienteId
    : 0;

$viewStatus = isset($status)
    && is_string($status)
    ? $status
    : '';

$getValue = static function (
    array $data,
    string $key
): string {
    $value = $data[$key] ?? null;

    return is_string($value)
        ? $value
        : '';
};

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Base comercial
        </span>

        <h1>
            Editar cliente
        </h1>

        <p>
            Atualize os dados cadastrais da empresa contratante.
        </p>

    </div>

</section>


<section class="client-edit-context">

    <span>
        Situação cadastral
    </span>

    <strong>
        <?= htmlspecialchars(
            $viewStatus,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </strong>

    <p>
        O status da empresa é administrado separadamente dos dados cadastrais.
    </p>

</section>


<section class="form-panel">

    <header class="form-panel-header">

        <div>

            <h2>
                Dados da empresa
            </h2>

            <p>
                Revise somente as informações cadastrais necessárias.
            </p>

        </div>

    </header>


    <form
        method="POST"
        action="<?= htmlspecialchars(
                    $viewAppUrl
                        . '/clientes/'
                        . $viewClienteId,
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

                <label for="razao_social">
                    Razão social
                </label>

                <input
                    type="text"
                    id="razao_social"
                    name="razao_social"
                    maxlength="200"
                    value="<?= htmlspecialchars(
                                $getValue(
                                    $viewFormData,
                                    'razao_social'
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                    required>

                <?php if (
                    isset(
                        $viewErrors['razao_social']
                    )
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors['razao_social'],
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
                                $getValue(
                                    $viewFormData,
                                    'nome_fantasia'
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">

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
                                $getValue(
                                    $viewFormData,
                                    'cnpj'
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                    required>

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
                                $getValue(
                                    $viewFormData,
                                    'email'
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">

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
                                $getValue(
                                    $viewFormData,
                                    'telefone'
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">

                <?php if (
                    isset(
                        $viewErrors['telefone']
                    )
                ): ?>

                    <span class="field-error">
                        <?= htmlspecialchars(
                            (string) $viewErrors['telefone'],
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
                                $getValue(
                                    $viewFormData,
                                    'slug'
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                    required>

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