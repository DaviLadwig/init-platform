<?php

declare(strict_types=1);

$viewCliente = isset($cliente)
    && is_array($cliente)
        ? $cliente
        : [];

$viewResumo = isset($resumoComercial)
    && is_array($resumoComercial)
        ? $resumoComercial
        : [];

$viewAppUrl = isset($appUrl)
    && is_string($appUrl)
        ? rtrim($appUrl, '/')
        : '';

$clienteId = isset($viewCliente['id'])
    ? (int) $viewCliente['id']
    : 0;

$stringValue = static function (
    array $source,
    string $key
): string {
    $value = $source[$key] ?? null;

    return is_string($value)
        ? trim($value)
        : '';
};

$e = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES,
    'UTF-8'
);

$formatCnpj = static function (
    string $cnpj
): string {
    $digits = preg_replace(
        '/\D+/',
        '',
        $cnpj
    );

    if (
        !is_string($digits)
        || strlen($digits) !== 14
    ) {
        return $cnpj;
    }

    return substr($digits, 0, 2)
        . '.'
        . substr($digits, 2, 3)
        . '.'
        . substr($digits, 5, 3)
        . '/'
        . substr($digits, 8, 4)
        . '-'
        . substr($digits, 12, 2);
};

$formatTelefone = static function (
    string $telefone
): string {
    $digits = preg_replace(
        '/\D+/',
        '',
        $telefone
    );

    if (!is_string($digits)) {
        return $telefone;
    }

    if (strlen($digits) === 11) {
        return '('
            . substr($digits, 0, 2)
            . ') '
            . substr($digits, 2, 5)
            . '-'
            . substr($digits, 7, 4);
    }

    if (strlen($digits) === 10) {
        return '('
            . substr($digits, 0, 2)
            . ') '
            . substr($digits, 2, 4)
            . '-'
            . substr($digits, 6, 4);
    }

    return $telefone;
};

$razaoSocial = $stringValue(
    $viewCliente,
    'razao_social'
);

$nomeFantasia = $stringValue(
    $viewCliente,
    'nome_fantasia'
);

$cnpj = $formatCnpj(
    $stringValue(
        $viewCliente,
        'cnpj'
    )
);

$email = $stringValue(
    $viewCliente,
    'email'
);

$telefone = $formatTelefone(
    $stringValue(
        $viewCliente,
        'telefone'
    )
);

$status = $stringValue(
    $viewCliente,
    'status'
);

$produtos = $stringValue(
    $viewResumo,
    'produtos'
);

$totalProdutos = (int) (
    $viewResumo['total_produtos']
    ?? 0
);

$totalAssinaturas = (int) (
    $viewResumo['total_assinaturas']
    ?? 0
);

$situacaoComercial = $stringValue(
    $viewResumo,
    'situacao_comercial'
);

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Base comercial
        </span>

        <h1>
            <?= $e(
                $nomeFantasia !== ''
                    ? $nomeFantasia
                    : $razaoSocial
            ) ?>
        </h1>

        <p>
            Ficha cadastral e visão comercial do cliente.
        </p>

    </div>

    <div class="client-show-actions">

        <a
            href="<?= $e(
                $viewAppUrl
                    . '/clientes/'
                    . $clienteId
                    . '/responsaveis'
            ) ?>"
            class="button-secondary"
        >
            Responsáveis
        </a>

        <a
            href="<?= $e(
                $viewAppUrl
                    . '/clientes/'
                    . $clienteId
                    . '/editar'
            ) ?>"
            class="button-primary"
        >
            Editar cliente
        </a>

    </div>

</section>


<section class="data-panel">

    <header class="data-panel-header">

        <div>

            <h2>
                Dados da empresa
            </h2>

            <p>
                Informações cadastrais registradas na plataforma.
            </p>

        </div>

        <?php if ($status !== ''): ?>

            <span class="status-badge">
                <?= $e($status) ?>
            </span>

        <?php endif; ?>

    </header>


    <div class="client-show-grid">

        <div class="client-show-item">
            <span>Razão social</span>
            <strong>
                <?= $e(
                    $razaoSocial !== ''
                        ? $razaoSocial
                        : '—'
                ) ?>
            </strong>
        </div>

        <div class="client-show-item">
            <span>Nome fantasia</span>
            <strong>
                <?= $e(
                    $nomeFantasia !== ''
                        ? $nomeFantasia
                        : '—'
                ) ?>
            </strong>
        </div>

        <div class="client-show-item">
            <span>CNPJ</span>
            <strong>
                <?= $e(
                    $cnpj !== ''
                        ? $cnpj
                        : '—'
                ) ?>
            </strong>
        </div>

        <div class="client-show-item">
            <span>E-mail</span>
            <strong>
                <?= $e(
                    $email !== ''
                        ? $email
                        : '—'
                ) ?>
            </strong>
        </div>

        <div class="client-show-item">
            <span>Telefone</span>
            <strong>
                <?= $e(
                    $telefone !== ''
                        ? $telefone
                        : '—'
                ) ?>
            </strong>
        </div>

    </div>

</section>


<section class="data-panel">

    <header class="data-panel-header">

        <div>

            <h2>
                Situação comercial
            </h2>

            <p>
                Resumo das contratações vinculadas ao cliente.
            </p>

        </div>

    </header>


    <div class="client-show-grid">

        <div class="client-show-item">
            <span>Situação</span>
            <strong>
                <?= $e(
                    $situacaoComercial !== ''
                        ? $situacaoComercial
                        : 'Sem assinatura'
                ) ?>
            </strong>
        </div>

        <div class="client-show-item">
            <span>Produtos contratados</span>
            <strong>
                <?= $totalProdutos ?>
            </strong>

            <?php if ($produtos !== ''): ?>

                <small>
                    <?= $e($produtos) ?>
                </small>

            <?php endif; ?>
        </div>

        <div class="client-show-item">
            <span>Assinaturas</span>
            <strong>
                <?= $totalAssinaturas ?>
            </strong>
        </div>

    </div>

</section>


<div class="form-actions">

    <a
        href="<?= $e($viewAppUrl . '/clientes') ?>"
        class="button-secondary"
    >
        Voltar aos clientes
    </a>

</div>
