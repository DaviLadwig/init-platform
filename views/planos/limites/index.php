<?php

declare(strict_types=1);

$viewClientes = isset($clientes) && is_array($clientes)
    ? $clientes
    : [];

$viewResumo = isset($resumo) && is_array($resumo)
    ? $resumo
    : [];

$viewAppUrl = isset($appUrl) && is_string($appUrl)
    ? rtrim($appUrl, '/')
    : '';

$viewSuccess = isset($success) && is_string($success)
    ? $success
    : null;

$viewError = isset($error) && is_string($error)
    ? $error
    : null;

/**
 * Escapa conteúdo dinâmico para saída HTML.
 */
$e = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES,
    'UTF-8'
);

/**
 * Retorna uma string segura de um array.
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
 * Formata CNPJ apenas para apresentação.
 *
 * O banco continua armazenando somente números.
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

$totalClientes = count($viewClientes);

$totalAtencao =
    (int) ($viewResumo['atencao'] ?? 0)
    + (int) ($viewResumo['suspensos'] ?? 0);

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Base comercial
        </span>

        <h1>
            Clientes
        </h1>

        <p>
            Empresas cadastradas e situação dos serviços contratados na plataforma Init.
        </p>

    </div>

    <a
        href="<?= $e(
            $viewAppUrl . '/clientes/novo'
        ) ?>"
        class="button-primary"
    >
        Novo cliente
    </a>

</section>


<?php if (
    $viewSuccess !== null
    && $viewSuccess !== ''
): ?>

    <div
        class="form-alert form-alert-success page-alert"
        role="status"
    >
        <?= $e($viewSuccess) ?>
    </div>

<?php endif; ?>


<?php if (
    $viewError !== null
    && $viewError !== ''
): ?>

    <div
        class="form-alert form-alert-error page-alert"
        role="alert"
    >
        <?= $e($viewError) ?>
    </div>

<?php endif; ?>


<section
    class="client-summary"
    aria-label="Resumo da base de clientes"
>

    <article class="client-summary-item">

        <span>
            Total de clientes
        </span>

        <strong>
            <?= (int) (
                $viewResumo['total']
                ?? $totalClientes
            ) ?>
        </strong>

    </article>


    <article class="client-summary-item">

        <span>
            Operação regular
        </span>

        <strong>
            <?= (int) (
                $viewResumo['ativos']
                ?? 0
            ) ?>
        </strong>

    </article>


    <article class="client-summary-item">

        <span>
            Exigem atenção
        </span>

        <strong>
            <?= $totalAtencao ?>
        </strong>

    </article>


    <article class="client-summary-item">

        <span>
            Sem assinatura
        </span>

        <strong>
            <?= (int) (
                $viewResumo['sem_assinatura']
                ?? 0
            ) ?>
        </strong>

    </article>

</section>


<section class="data-panel">

    <header class="data-panel-header">

        <div>

            <h2>
                Base de clientes
            </h2>

            <p>
                <?= $totalClientes ?>
                cliente<?= $totalClientes === 1
                    ? ''
                    : 's' ?>
                cadastrado<?= $totalClientes === 1
                    ? ''
                    : 's' ?>
            </p>

        </div>

    </header>


    <?php if ($viewClientes === []): ?>

        <div class="empty-state">

            <h3>
                Nenhum cliente cadastrado
            </h3>

            <p>
                Cadastre a primeira empresa para iniciar a estrutura comercial da plataforma.
            </p>

        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="data-table clients-table">

                <thead>

                    <tr>
                        <th>Cliente</th>
                        <th>CNPJ</th>
                        <th>Produtos</th>
                        <th>Assinaturas</th>
                        <th>Situação</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach (
                        $viewClientes
                        as $cliente
                    ): ?>

                        <?php

                        if (!is_array($cliente)) {
                            continue;
                        }

                        $razaoSocial = $stringValue(
                            $cliente,
                            'razao_social'
                        );

                        $nomeFantasia = $stringValue(
                            $cliente,
                            'nome_fantasia'
                        );

                        $cnpj = $stringValue(
                            $cliente,
                            'cnpj'
                        );

                        $email = $stringValue(
                            $cliente,
                            'email'
                        );

                        $telefone = $stringValue(
                            $cliente,
                            'telefone'
                        );

                        $produtos = $stringValue(
                            $cliente,
                            'produtos'
                        );

                        $situacao = $stringValue(
                            $cliente,
                            'situacao_comercial'
                        );

                        $totalProdutos = (int) (
                            $cliente['total_produtos']
                            ?? 0
                        );

                        $totalAssinaturas = (int) (
                            $cliente['total_assinaturas']
                            ?? 0
                        );

                        $ativas = (int) (
                            $cliente['assinaturas_ativas']
                            ?? 0
                        );

                        $trial = (int) (
                            $cliente['assinaturas_trial']
                            ?? 0
                        );

                        $atrasadas = (int) (
                            $cliente['assinaturas_atrasadas']
                            ?? 0
                        );

                        $suspensas = (int) (
                            $cliente['assinaturas_suspensas']
                            ?? 0
                        );

                        $pendentes = (int) (
                            $cliente['assinaturas_pendentes']
                            ?? 0
                        );

                        $nomePrincipal =
                            $nomeFantasia !== ''
                                ? $nomeFantasia
                                : $razaoSocial;

                        $detalheCliente = '';

                        if (
                            $nomeFantasia !== ''
                            && $razaoSocial !== ''
                            && $nomeFantasia !== $razaoSocial
                        ) {
                            $detalheCliente =
                                $razaoSocial;
                        } elseif ($email !== '') {
                            $detalheCliente =
                                $email;
                        } elseif ($telefone !== '') {
                            $detalheCliente =
                                $formatTelefone(
                                    $telefone
                                );
                        }

                        [$statusClass, $statusLabel] = match (
                            $situacao
                        ) {
                            'ATIVO' => [
                                'client-state-ok',
                                'Regular',
                            ],

                            'ATENCAO' => [
                                'client-state-warning',
                                'Atenção',
                            ],

                            'SUSPENSO' => [
                                'client-state-danger',
                                'Suspenso',
                            ],

                            'TRIAL' => [
                                'client-state-info',
                                'Trial',
                            ],

                            'PENDENTE' => [
                                'client-state-info',
                                'Pendente',
                            ],

                            'INATIVO' => [
                                'client-state-danger',
                                'Inativo',
                            ],

                            default => [
                                'client-state-neutral',
                                'Sem assinatura',
                            ],
                        };

                        $resumoAssinaturas = [];

                        if ($ativas > 0) {
                            $resumoAssinaturas[] =
                                $ativas
                                . ' ativa'
                                . ($ativas === 1
                                    ? ''
                                    : 's');
                        }

                        if ($trial > 0) {
                            $resumoAssinaturas[] =
                                $trial
                                . ' trial';
                        }

                        if ($atrasadas > 0) {
                            $resumoAssinaturas[] =
                                $atrasadas
                                . ' atrasada'
                                . ($atrasadas === 1
                                    ? ''
                                    : 's');
                        }

                        if ($suspensas > 0) {
                            $resumoAssinaturas[] =
                                $suspensas
                                . ' suspensa'
                                . ($suspensas === 1
                                    ? ''
                                    : 's');
                        }

                        if ($pendentes > 0) {
                            $resumoAssinaturas[] =
                                $pendentes
                                . ' pendente'
                                . ($pendentes === 1
                                    ? ''
                                    : 's');
                        }

                        ?>

                        <tr>

                            <td>

                                <div class="client-cell">

                                    <strong>
                                        <?= $e(
                                            $nomePrincipal
                                        ) ?>
                                    </strong>

                                    <?php if (
                                        $detalheCliente !== ''
                                    ): ?>

                                        <span>
                                            <?= $e(
                                                $detalheCliente
                                            ) ?>
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </td>


                            <td>

                                <span class="table-muted">
                                    <?= $e(
                                        $formatCnpj(
                                            $cnpj
                                        )
                                    ) ?>
                                </span>

                            </td>


                            <td>

                                <?php if (
                                    $totalProdutos > 0
                                ): ?>

                                    <div class="client-products">

                                        <strong>
                                            <?= $totalProdutos ?>
                                            produto<?= $totalProdutos === 1
                                                ? ''
                                                : 's' ?>
                                        </strong>

                                        <?php if (
                                            $produtos !== ''
                                        ): ?>

                                            <span>
                                                <?= $e(
                                                    $produtos
                                                ) ?>
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                <?php else: ?>

                                    <span class="table-muted">
                                        Nenhum
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if (
                                    $totalAssinaturas > 0
                                ): ?>

                                    <div class="subscription-summary">

                                        <?php foreach (
                                            $resumoAssinaturas
                                            as $item
                                        ): ?>

                                            <span>
                                                <?= $e(
                                                    $item
                                                ) ?>
                                            </span>

                                        <?php endforeach; ?>

                                    </div>

                                <?php else: ?>

                                    <span class="table-muted">
                                        Nenhuma
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <span
                                    class="client-state <?= $e(
                                        $statusClass
                                    ) ?>"
                                >
                                    <?= $e(
                                        $statusLabel
                                    ) ?>
                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>