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

$e = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES,
    'UTF-8'
);

$value = static function (
    array $source,
    string $key
): string {
    $fieldValue = $source[$key] ?? null;

    return is_string($fieldValue)
        ? $fieldValue
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


<section class="form-panel">

    <header class="form-panel-header">

        <div>

            <h2>
                Dados da empresa
            </h2>

            <p>
                Altere somente as informações cadastrais necessárias.
            </p>

        </div>

        <?php if ($viewStatus !== ''): ?>

            <span class="status-badge">
                <?= $e($viewStatus) ?>
            </span>

        <?php endif; ?>

    </header>


    <form
        method="POST"
        action="<?= $e(
            $viewAppUrl
                . '/clientes/'
                . $viewClienteId
        ) ?>"
        class="entity-form"
        data-client-form
    >

        <input
            type="hidden"
            name="_token"
            value="<?= $e($viewCsrfToken) ?>"
        >


        <?php if ($viewErrors !== []): ?>

            <div
                class="form-alert form-alert-error form-grid-full"
                role="alert"
            >
                Revise os campos indicados antes de continuar.
            </div>

        <?php endif; ?>


        <div class="form-grid">

            <div class="field-group form-grid-full">

                <label for="razao_social">
                    Razão social
                </label>

                <input
                    id="razao_social"
                    type="text"
                    name="razao_social"
                    value="<?= $e(
                        $value(
                            $viewFormData,
                            'razao_social'
                        )
                    ) ?>"
                    maxlength="200"
                    autocomplete="organization"
                    required
                >

                <?php if (
                    isset($viewErrors['razao_social'])
                    && is_string($viewErrors['razao_social'])
                ): ?>

                    <span class="field-error">
                        <?= $e($viewErrors['razao_social']) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group">

                <label for="nome_fantasia">
                    Nome fantasia
                </label>

                <input
                    id="nome_fantasia"
                    type="text"
                    name="nome_fantasia"
                    value="<?= $e(
                        $value(
                            $viewFormData,
                            'nome_fantasia'
                        )
                    ) ?>"
                    maxlength="200"
                    autocomplete="organization"
                >

                <span class="field-hint">
                    Opcional.
                </span>

                <?php if (
                    isset($viewErrors['nome_fantasia'])
                    && is_string($viewErrors['nome_fantasia'])
                ): ?>

                    <span class="field-error">
                        <?= $e($viewErrors['nome_fantasia']) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group">

                <label for="cnpj">
                    CNPJ
                </label>

                <input
                    id="cnpj"
                    type="text"
                    name="cnpj"
                    value="<?= $e(
                        $value(
                            $viewFormData,
                            'cnpj'
                        )
                    ) ?>"
                    inputmode="numeric"
                    maxlength="18"
                    autocomplete="off"
                    spellcheck="false"
                    data-mask="cnpj"
                    aria-describedby="cnpj-hint"
                    required
                >

                <span
                    id="cnpj-hint"
                    class="field-hint"
                >
                    A formatação é aplicada automaticamente.
                </span>

                <?php if (
                    isset($viewErrors['cnpj'])
                    && is_string($viewErrors['cnpj'])
                ): ?>

                    <span class="field-error">
                        <?= $e($viewErrors['cnpj']) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group">

                <label for="email">
                    E-mail
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="<?= $e(
                        $value(
                            $viewFormData,
                            'email'
                        )
                    ) ?>"
                    maxlength="255"
                    autocomplete="email"
                >

                <span class="field-hint">
                    Opcional.
                </span>

                <?php if (
                    isset($viewErrors['email'])
                    && is_string($viewErrors['email'])
                ): ?>

                    <span class="field-error">
                        <?= $e($viewErrors['email']) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group">

                <label for="telefone">
                    Telefone
                </label>

                <input
                    id="telefone"
                    type="tel"
                    name="telefone"
                    value="<?= $e(
                        $value(
                            $viewFormData,
                            'telefone'
                        )
                    ) ?>"
                    maxlength="20"
                    autocomplete="tel"
                >

                <span class="field-hint">
                    Opcional.
                </span>

                <?php if (
                    isset($viewErrors['telefone'])
                    && is_string($viewErrors['telefone'])
                ): ?>

                    <span class="field-error">
                        <?= $e($viewErrors['telefone']) ?>
                    </span>

                <?php endif; ?>

            </div>

        </div>


        <div class="form-actions">

            <a
                href="<?= $e($viewAppUrl . '/clientes') ?>"
                class="button-secondary"
            >
                Cancelar
            </a>

            <button
                type="submit"
                class="button-primary"
            >
                Salvar alterações
            </button>

        </div>

    </form>

</section>
