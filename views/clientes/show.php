<?php

declare(strict_types=1);

$viewCliente = isset($cliente)
    && is_array($cliente)
    ? $cliente
    : [];

$viewAppUrl = isset($appUrl)
    && is_string($appUrl)
    ? rtrim($appUrl, '/')
    : '';

/**
 * Escape centralizado para saída HTML.
 */
$e = static fn(string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES,
    'UTF-8'
);

/**
 * Recupera valores textuais com segurança.
 */
$stringValue = static function (
    array $source,
    string $key
): string {
    $value = $source[$key] ?? null;

    return is_string($value)
        ? $value
        : '';
};

/**
 * Formata o CNPJ somente para apresentação.
 */
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

/**
 * Formata telefone somente para apresentação.
 */
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

/**
 * Formata timestamps vindos do PostgreSQL.
 */
$formatDateTime = static function (
    string $value
): string {
    if ($value === '') {
        return '—';
    }

    try {
        $date = new DateTimeImmutable(
            $value
        );

        return $date->format(
            'd/m/Y H:i'
        );
    } catch (Throwable) {
        return '—';
    }
};


/*
|--------------------------------------------------------------------------
| Dados do cliente
|--------------------------------------------------------------------------
*/

$clienteId = isset($viewCliente['id'])
    ? (int) $viewCliente['id']
    : 0;

$razaoSocial = $stringValue(
    $viewCliente,
    'razao_social'
);

$nomeFantasia = $stringValue(
    $viewCliente,
    'nome_fantasia'
);

$cnpj = $stringValue(
    $viewCliente,
    'cnpj'
);

$email = $stringValue(
    $viewCliente,
    'email'
);

$telefone = $stringValue(
    $viewCliente,
    'telefone'
);

$slug = $stringValue(
    $viewCliente,
    'slug'
);

$status = $stringValue(
    $viewCliente,
    'status'
);

$criadoEm = $stringValue(
    $viewCliente,
    'criado_em'
);

$atualizadoEm = $stringValue(
    $viewCliente,
    'atualizado_em'
);

$nomePrincipal =
    $nomeFantasia !== ''
    ? $nomeFantasia
    : $razaoSocial;

$statusAtivo =
    $status === 'ATIVA';

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Base comercial
        </span>

        <h1>
            <?= $e($nomePrincipal) ?>
        </h1>

        <p>
            Informações cadastrais e administrativas da empresa cliente.
        </p>

    </div>


    <div class="page-heading-actions">

        <a
            href="<?= $e(
                        $viewAppUrl
                            . '/clientes'
                    ) ?>"
            class="button-secondary">
            Voltar
        </a>


        <a
            href="<?= $e(
                        $viewAppUrl
                            . '/clientes/'
                            . $clienteId
                            . '/responsaveis'
                    ) ?>"
            class="button-secondary">
            Responsáveis
        </a>


        <a
            href="<?= $e(
                        $viewAppUrl
                            . '/clientes/'
                            . $clienteId
                            . '/editar'
                    ) ?>"
            class="button-primary">
            Editar cliente
        </a>

    </div>

</section>


<section class="client-profile-header">

    <div class="client-profile-identity">

        <span class="client-profile-label">
            Empresa
        </span>

        <h2>
            <?= $e($razaoSocial) ?>
        </h2>

        <?php if (
            $nomeFantasia !== ''
            && $nomeFantasia !== $razaoSocial
        ): ?>

            <p>
                <?= $e($nomeFantasia) ?>
            </p>

        <?php endif; ?>

    </div>


    <div class="client-profile-status">

        <span>
            Status cadastral
        </span>

        <strong
            class="client-state <?= $statusAtivo
                                    ? 'client-state-ok'
                                    : 'client-state-danger' ?>">
            <?= $statusAtivo
                ? 'Ativa'
                : 'Inativa' ?>
        </strong>

    </div>

</section>


<section class="client-detail-grid">

    <!-- =====================================================
         DADOS DA EMPRESA
    ====================================================== -->

    <article class="client-detail-panel">

        <header>

            <span>
                Cadastro
            </span>

            <h2>
                Dados da empresa
            </h2>

        </header>


        <dl class="client-detail-list">

            <div>

                <dt>
                    Razão social
                </dt>

                <dd>
                    <?= $razaoSocial !== ''
                        ? $e($razaoSocial)
                        : '—' ?>
                </dd>

            </div>


            <div>

                <dt>
                    Nome fantasia
                </dt>

                <dd>
                    <?= $nomeFantasia !== ''
                        ? $e($nomeFantasia)
                        : '—' ?>
                </dd>

            </div>


            <div>

                <dt>
                    CNPJ
                </dt>

                <dd>
                    <?= $cnpj !== ''
                        ? $e(
                            $formatCnpj(
                                $cnpj
                            )
                        )
                        : '—' ?>
                </dd>

            </div>

        </dl>

    </article>


    <!-- =====================================================
         CONTATO
    ====================================================== -->

    <article class="client-detail-panel">

        <header>

            <span>
                Contato
            </span>

            <h2>
                Informações de contato
            </h2>

        </header>


        <dl class="client-detail-list">

            <div>

                <dt>
                    E-mail
                </dt>

                <dd>
                    <?= $email !== ''
                        ? $e($email)
                        : '—' ?>
                </dd>

            </div>


            <div>

                <dt>
                    Telefone
                </dt>

                <dd>
                    <?= $telefone !== ''
                        ? $e(
                            $formatTelefone(
                                $telefone
                            )
                        )
                        : '—' ?>
                </dd>

            </div>

        </dl>

    </article>


    <!-- =====================================================
         IDENTIFICAÇÃO NA PLATAFORMA
    ====================================================== -->

    <article class="client-detail-panel">

        <header>

            <span>
                Plataforma
            </span>

            <h2>
                Identificação
            </h2>

        </header>


        <dl class="client-detail-list">

            <div>

                <dt>
                    ID interno
                </dt>

                <dd>
                    #<?= $clienteId ?>
                </dd>

            </div>


            <div>

                <dt>
                    Slug
                </dt>

                <dd>

                    <?php if ($slug !== ''): ?>

                        <code class="client-slug">
                            <?= $e($slug) ?>
                        </code>

                    <?php else: ?>

                        —

                    <?php endif; ?>

                </dd>

            </div>


            <div>

                <dt>
                    Status
                </dt>

                <dd>
                    <?= $status !== ''
                        ? $e($status)
                        : '—' ?>
                </dd>

            </div>

        </dl>

    </article>


    <!-- =====================================================
         HISTÓRICO
    ====================================================== -->

    <article class="client-detail-panel">

        <header>

            <span>
                Histórico
            </span>

            <h2>
                Registro
            </h2>

        </header>


        <dl class="client-detail-list">

            <div>

                <dt>
                    Cadastrado em
                </dt>

                <dd>
                    <?= $e(
                        $formatDateTime(
                            $criadoEm
                        )
                    ) ?>
                </dd>

            </div>


            <div>

                <dt>
                    Última atualização
                </dt>

                <dd>
                    <?= $e(
                        $formatDateTime(
                            $atualizadoEm
                        )
                    ) ?>
                </dd>

            </div>

        </dl>

    </article>

</section>