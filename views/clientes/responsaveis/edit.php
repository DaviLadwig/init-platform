<?php

declare(strict_types=1);

$viewEmpresa = isset($empresa) && is_array($empresa)
    ? $empresa
    : [];

$viewErrors = isset($errors) && is_array($errors)
    ? $errors
    : [];

$viewFormData = isset($formData) && is_array($formData)
    ? $formData
    : [];

$viewAppUrl = isset($appUrl) && is_string($appUrl)
    ? rtrim($appUrl, '/')
    : '';

$viewCsrfToken = isset($csrfToken) && is_string($csrfToken)
    ? $csrfToken
    : '';

$viewEmpresaId = isset($empresaId)
    ? (int) $empresaId
    : 0;

$viewResponsavelId = isset($responsavelId)
    ? (int) $responsavelId
    : 0;

$e = static fn(string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES,
    'UTF-8'
);

$getValue = static function (
    array $data,
    string $key
): string {
    $value = $data[$key] ?? null;

    return is_string($value)
        ? $value
        : '';
};

$razaoSocial = $getValue(
    $viewEmpresa,
    'razao_social'
);

$nomeFantasia = $getValue(
    $viewEmpresa,
    'nome_fantasia'
);

$nomeEmpresa = $nomeFantasia !== ''
    ? $nomeFantasia
    : $razaoSocial;

$principalMarcado =
    $getValue(
        $viewFormData,
        'principal'
    ) === '1';

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Clientes
        </span>

        <h1>
            Editar responsável
        </h1>

        <p>
            Atualize os dados do contato vinculado a <?= $e($nomeEmpresa) ?>.
        </p>

    </div>

</section>


<section class="client-context-bar">

    <div>

        <span>
            Cliente
        </span>

        <strong>
            <?= $e($nomeEmpresa) ?>
        </strong>

    </div>

</section>


<section class="form-panel">

    <header class="form-panel-header">

        <div>

            <h2>
                Dados do responsável
            </h2>

            <p>
                Atualize somente as informações necessárias.
            </p>

        </div>

    </header>


    <form
        method="POST"
        action="<?= $e(
                    $viewAppUrl
                        . '/clientes/'
                        . $viewEmpresaId
                        . '/responsaveis/'
                        . $viewResponsavelId
                ) ?>"
        class="form-content"
        novalidate>

        <input
            type="hidden"
            name="_token"
            value="<?= $e($viewCsrfToken) ?>">


        <?php if (
            isset($viewErrors['general'])
        ): ?>

            <div
                class="form-alert form-alert-error"
                role="alert">
                <?= $e(
                    (string) $viewErrors['general']
                ) ?>
            </div>

        <?php endif; ?>


        <div class="form-grid">

            <div class="field-group form-grid-full">

                <label for="nome">
                    Nome
                </label>

                <input
                    type="text"
                    id="nome"
                    name="nome"
                    maxlength="150"
                    value="<?= $e(
                                $getValue(
                                    $viewFormData,
                                    'nome'
                                )
                            ) ?>"
                    required>

                <?php if (
                    isset($viewErrors['nome'])
                ): ?>

                    <span class="field-error">
                        <?= $e(
                            (string) $viewErrors['nome']
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group">

                <label for="cargo">
                    Cargo ou função
                </label>

                <input
                    type="text"
                    id="cargo"
                    name="cargo"
                    maxlength="100"
                    value="<?= $e(
                                $getValue(
                                    $viewFormData,
                                    'cargo'
                                )
                            ) ?>"
                    placeholder="Ex.: Diretor administrativo">

                <span class="field-hint">
                    Opcional.
                </span>

                <?php if (
                    isset($viewErrors['cargo'])
                ): ?>

                    <span class="field-error">
                        <?= $e(
                            (string) $viewErrors['cargo']
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
                    value="<?= $e(
                                $getValue(
                                    $viewFormData,
                                    'email'
                                )
                            ) ?>"
                    placeholder="contato@empresa.com.br">

                <span class="field-hint">
                    Opcional.
                </span>

                <?php if (
                    isset($viewErrors['email'])
                ): ?>

                    <span class="field-error">
                        <?= $e(
                            (string) $viewErrors['email']
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
                    value="<?= $e(
                                $getValue(
                                    $viewFormData,
                                    'telefone'
                                )
                            ) ?>"
                    placeholder="(98) 99999-9999">

                <span class="field-hint">
                    Opcional.
                </span>

                <?php if (
                    isset($viewErrors['telefone'])
                ): ?>

                    <span class="field-error">
                        <?= $e(
                            (string) $viewErrors['telefone']
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>


            <div class="field-group form-grid-full">

                <div class="responsavel-option">

                    <label
                        for="principal"
                        class="responsavel-option-control">

                        <input
                            type="checkbox"
                            id="principal"
                            name="principal"
                            value="1"
                            <?= $principalMarcado
                                ? 'checked'
                                : '' ?>>

                        <span>
                            Definir como responsável principal
                        </span>

                    </label>

                    <p>
                        Ao marcar esta opção, o responsável principal atual será substituído com segurança.
                    </p>

                </div>


                <?php if (
                    isset($viewErrors['principal'])
                ): ?>

                    <span class="field-error">
                        <?= $e(
                            (string) $viewErrors['principal']
                        ) ?>
                    </span>

                <?php endif; ?>

            </div>

        </div>


        <footer class="form-actions">

            <a
                href="<?= $e(
                            $viewAppUrl
                                . '/clientes/'
                                . $viewEmpresaId
                                . '/responsaveis'
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