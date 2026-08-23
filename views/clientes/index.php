<?php

declare(strict_types=1);

$viewClientes = isset($clientes)
    && is_array($clientes)
    ? $clientes
    : [];

$viewResumo = isset($resumo)
    && is_array($resumo)
    ? $resumo
    : [];

$viewAppUrl = isset($appUrl)
    && is_string($appUrl)
    ? rtrim($appUrl, '/')
    : '';

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

</section>


<section class="client-summary">

    <article class="client-summary-item">
        <span>Total de clientes</span>
        <strong>
            <?= (int) ($viewResumo['total'] ?? 0) ?>
        </strong>
    </article>

    <article class="client-summary-item">
        <span>Operação regular</span>
        <strong>
            <?= (int) ($viewResumo['ativos'] ?? 0) ?>
        </strong>
    </article>

    <article class="client-summary-item">
        <span>Exigem atenção</span>
        <strong>
            <?= (int) ($viewResumo['atencao'] ?? 0) ?>
        </strong>
    </article>

    <article class="client-summary-item">
        <span>Suspensos</span>
        <strong>
            <?= (int) ($viewResumo['suspensos'] ?? 0) ?>
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
                <?= count($viewClientes) ?>
                cliente<?= count($viewClientes) === 1 ? '' : 's' ?>
                cadastrado<?= count($viewClientes) === 1 ? '' : 's' ?>
            </p>

        </div>

    </header>


    <?php if ($viewClientes === []): ?>

        <div class="empty-state">

            <h3>
                Nenhum cliente cadastrado
            </h3>

            <p>
                As empresas contratantes aparecerão aqui.
            </p>

        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="data-table">

                <thead>

                    <tr>
                        <th>Cliente</th>
                        <th>CNPJ</th>
                        <th>Produtos</th>
                        <th>Assinaturas</th>
                        <th>Situação</th>
                        <th>Ações</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach (
                        $viewClientes as $cliente
                    ): ?>

                        <?php

                        $clienteId = isset($cliente['id'])
                            ? (int) $cliente['id']
                            : 0;

                        $razaoSocial =
                            isset($cliente['razao_social'])
                            && is_string($cliente['razao_social'])
                            ? $cliente['razao_social']
                            : '';

                        $nomeFantasia =
                            isset($cliente['nome_fantasia'])
                            && is_string($cliente['nome_fantasia'])
                            ? $cliente['nome_fantasia']
                            : '';

                        $email =
                            isset($cliente['email'])
                            && is_string($cliente['email'])
                            ? $cliente['email']
                            : '';

                        $cnpj =
                            isset($cliente['cnpj'])
                            && is_string($cliente['cnpj'])
                            ? $cliente['cnpj']
                            : '';

                        $produtos =
                            isset($cliente['produtos'])
                            && is_string($cliente['produtos'])
                            ? $cliente['produtos']
                            : '';

                        $totalProdutos =
                            (int) (
                                $cliente['total_produtos']
                                ?? 0
                            );

                        $ativas =
                            (int) (
                                $cliente['assinaturas_ativas']
                                ?? 0
                            );

                        $atrasadas =
                            (int) (
                                $cliente['assinaturas_atrasadas']
                                ?? 0
                            );

                        $suspensas =
                            (int) (
                                $cliente['assinaturas_suspensas']
                                ?? 0
                            );

                        $situacao =
                            isset(
                                $cliente['situacao_comercial']
                            )
                            && is_string(
                                $cliente['situacao_comercial']
                            )
                            ? $cliente['situacao_comercial']
                            : 'SEM_ASSINATURA';

                        ?>

                        <tr>

                            <td>

                                <div class="client-cell">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $nomeFantasia !== ''
                                                ? $nomeFantasia
                                                : $razaoSocial,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>

                                    <?php if (
                                        $nomeFantasia !== ''
                                        && $razaoSocial !== ''
                                        && $nomeFantasia !== $razaoSocial
                                    ): ?>

                                        <span>
                                            <?= htmlspecialchars(
                                                $razaoSocial,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                    <?php elseif ($email !== ''): ?>

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

                                <span class="table-muted">
                                    <?= htmlspecialchars(
                                        $cnpj,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </td>


                            <td>

                                <?php if ($totalProdutos > 0): ?>

                                    <div class="client-products">

                                        <strong>
                                            <?= $totalProdutos ?>
                                        </strong>

                                        <span>
                                            <?= htmlspecialchars(
                                                $produtos,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                    </div>

                                <?php else: ?>

                                    <span class="table-muted">
                                        Nenhum
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="subscription-summary">

                                    <?php if ($ativas > 0): ?>
                                        <span>
                                            <?= $ativas ?> ativa<?= $ativas === 1 ? '' : 's' ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($atrasadas > 0): ?>
                                        <span>
                                            <?= $atrasadas ?> atrasada<?= $atrasadas === 1 ? '' : 's' ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($suspensas > 0): ?>
                                        <span>
                                            <?= $suspensas ?> suspensa<?= $suspensas === 1 ? '' : 's' ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (
                                        $ativas === 0
                                        && $atrasadas === 0
                                        && $suspensas === 0
                                    ): ?>

                                        <span class="table-muted">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </td>


                            <td>

                                <?php

                                $statusClass = match ($situacao) {
                                    'ATIVO' =>
                                    'client-state-ok',

                                    'ATENCAO' =>
                                    'client-state-warning',

                                    'SUSPENSO' =>
                                    'client-state-danger',

                                    'TRIAL' =>
                                    'client-state-info',

                                    default =>
                                    'client-state-neutral',
                                };

                                $statusLabel = match ($situacao) {
                                    'ATIVO' =>
                                    'Regular',

                                    'ATENCAO' =>
                                    'Atenção',

                                    'SUSPENSO' =>
                                    'Suspenso',

                                    'TRIAL' =>
                                    'Trial',

                                    'PENDENTE' =>
                                    'Pendente',

                                    default =>
                                    'Sem assinatura',
                                };

                                ?>

                                <span
                                    class="client-state <?= $statusClass ?>">
                                    <?= $statusLabel ?>
                                </span>

                            </td>


                            <td>

                                <a
                                    href="<?= htmlspecialchars(
                                                $viewAppUrl
                                                    . '/clientes/'
                                                    . $clienteId,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                    class="table-action">
                                    Ver
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>