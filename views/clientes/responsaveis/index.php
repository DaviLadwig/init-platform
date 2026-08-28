<?php

declare(strict_types=1);

$viewEmpresa = isset($empresa)
    && is_array($empresa)
    ? $empresa
    : [];

$viewResponsaveis = isset($responsaveis)
    && is_array($responsaveis)
    ? $responsaveis
    : [];

$viewAppUrl = isset($appUrl)
    && is_string($appUrl)
    ? rtrim($appUrl, '/')
    : '';

$viewCsrfToken = isset($csrfToken)
    && is_string($csrfToken)
    ? $csrfToken
    : '';

$viewSuccess = isset($success)
    && is_string($success)
    ? $success
    : null;

$viewError = isset($error)
    && is_string($error)
    ? $error
    : null;

$empresaId = isset($viewEmpresa['id'])
    ? (int) $viewEmpresa['id']
    : 0;

$razaoSocial = isset($viewEmpresa['razao_social'])
    && is_string($viewEmpresa['razao_social'])
    ? $viewEmpresa['razao_social']
    : '';

$nomeFantasia = isset($viewEmpresa['nome_fantasia'])
    && is_string($viewEmpresa['nome_fantasia'])
    ? $viewEmpresa['nome_fantasia']
    : '';

$empresaNome = $nomeFantasia !== ''
    ? $nomeFantasia
    : $razaoSocial;

$boolValue = static function (mixed $value): bool {
    return $value === true
        || $value === 1
        || $value === '1'
        || $value === 't'
        || $value === 'true';
};

$formatTelefone = static function (string $telefone): string {
    $digits = preg_replace('/\D+/', '', $telefone);

    if (!is_string($digits)) {
        return $telefone;
    }

    if (strlen($digits) === 11) {
        return sprintf(
            '(%s) %s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 5),
            substr($digits, 7, 4)
        );
    }

    if (strlen($digits) === 10) {
        return sprintf(
            '(%s) %s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 4),
            substr($digits, 6, 4)
        );
    }

    return $telefone;
};

?>

<section class="page-heading responsaveis-heading">

    <div>
        <span class="page-eyebrow">
            Clientes
        </span>

        <h1>
            Responsáveis
        </h1>

        <p>
            Contatos administrativos e comerciais vinculados a
            <?= htmlspecialchars(
                $empresaNome,
                ENT_QUOTES,
                'UTF-8'
            ) ?>.
        </p>
    </div>

    <div class="responsaveis-heading-actions">
        <a
            href="<?= htmlspecialchars(
                $viewAppUrl . '/clientes/' . $empresaId,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            class="button-secondary"
        >
            Voltar ao cliente
        </a>

        <a
            href="<?= htmlspecialchars(
                $viewAppUrl
                    . '/clientes/'
                    . $empresaId
                    . '/responsaveis/novo',
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            class="button-primary"
        >
            Novo responsável
        </a>
    </div>

</section>

<?php if ($viewSuccess !== null && $viewSuccess !== ''): ?>
    <div
        class="form-alert form-alert-success page-alert"
        role="status"
    >
        <?= htmlspecialchars(
            $viewSuccess,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>
<?php endif; ?>

<?php if ($viewError !== null && $viewError !== ''): ?>
    <div
        class="form-alert form-alert-error page-alert"
        role="alert"
    >
        <?= htmlspecialchars(
            $viewError,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>
<?php endif; ?>

<section class="client-context-bar responsaveis-context-bar">
    <div>
        <span>
            Cliente
        </span>

        <strong>
            <?= htmlspecialchars(
                $empresaNome,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
    </div>

    <div>
        <span>
            Responsáveis cadastrados
        </span>

        <strong>
            <?= count($viewResponsaveis) ?>
        </strong>
    </div>
</section>

<section class="data-panel responsaveis-panel">

    <header class="data-panel-header">
        <div>
            <h2>
                Contatos da empresa
            </h2>

            <p>
                Pessoas autorizadas para contato administrativo ou comercial.
            </p>
        </div>
    </header>

    <?php if ($viewResponsaveis === []): ?>
        <div class="empty-state">
            <h3>
                Nenhum responsável cadastrado
            </h3>

            <p>
                Cadastre o primeiro contato administrativo ou comercial deste cliente.
            </p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table responsaveis-table">
                <thead>
                    <tr>
                        <th>Responsável</th>
                        <th>Cargo</th>
                        <th>Contato</th>
                        <th>Principal</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($viewResponsaveis as $responsavel): ?>
                        <?php

                        if (!is_array($responsavel)) {
                            continue;
                        }

                        $responsavelId = isset($responsavel['id'])
                            ? (int) $responsavel['id']
                            : 0;

                        $nome = isset($responsavel['nome'])
                            && is_string($responsavel['nome'])
                            ? $responsavel['nome']
                            : '';

                        $email = isset($responsavel['email'])
                            && is_string($responsavel['email'])
                            ? $responsavel['email']
                            : '';

                        $telefone = isset($responsavel['telefone'])
                            && is_string($responsavel['telefone'])
                            ? $responsavel['telefone']
                            : '';

                        $cargo = isset($responsavel['cargo'])
                            && is_string($responsavel['cargo'])
                            ? $responsavel['cargo']
                            : '';

                        $principal = $boolValue(
                            $responsavel['principal'] ?? false
                        );

                        $ativo = $boolValue(
                            $responsavel['ativo'] ?? false
                        );

                        ?>

                        <tr>
                            <td>
                                <div class="responsavel-name">
                                    <strong>
                                        <?= htmlspecialchars(
                                            $nome,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>

                                    <?php if ($email !== ''): ?>
                                        <span>
                                            <?= htmlspecialchars(
                                                $email,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <?php if ($cargo !== ''): ?>
                                    <span class="table-muted">
                                        <?= htmlspecialchars(
                                            $cargo,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="table-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($telefone !== ''): ?>
                                    <span class="table-muted">
                                        <?= htmlspecialchars(
                                            $formatTelefone($telefone),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="table-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($principal): ?>
                                    <span class="client-state client-state-info">
                                        Principal
                                    </span>
                                <?php else: ?>
                                    <span class="table-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span
                                    class="client-state <?= $ativo
                                        ? 'client-state-ok'
                                        : 'client-state-neutral' ?>"
                                >
                                    <?= $ativo ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>

                            <td>
                                <div class="responsavel-actions">
                                    <a
                                        href="<?= htmlspecialchars(
                                            $viewAppUrl
                                                . '/clientes/'
                                                . $empresaId
                                                . '/responsaveis/'
                                                . $responsavelId
                                                . '/editar',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        class="table-action"
                                    >
                                        Editar
                                    </a>

                                    <?php if ($ativo && !$principal): ?>
                                        <form
                                            method="POST"
                                            action="<?= htmlspecialchars(
                                                $viewAppUrl
                                                    . '/clientes/'
                                                    . $empresaId
                                                    . '/responsaveis/'
                                                    . $responsavelId
                                                    . '/desativar',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            class="responsavel-action-form"
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

                                            <button
                                                type="submit"
                                                class="table-action responsavel-action-deactivate"
                                            >
                                                Desativar
                                            </button>
                                        </form>
                                    <?php elseif (!$ativo): ?>
                                        <form
                                            method="POST"
                                            action="<?= htmlspecialchars(
                                                $viewAppUrl
                                                    . '/clientes/'
                                                    . $empresaId
                                                    . '/responsaveis/'
                                                    . $responsavelId
                                                    . '/ativar',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            class="responsavel-action-form"
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

                                            <button
                                                type="submit"
                                                class="table-action responsavel-action-activate"
                                            >
                                                Ativar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</section>
