<?php

declare(strict_types=1);

$viewProdutos = isset($produtos)
    && is_array($produtos)
    ? $produtos
    : [];

$viewAppUrl = isset($appUrl)
    && is_string($appUrl)
    ? rtrim($appUrl, '/')
    : '';

$viewSuccess = isset($success)
    && is_string($success)
    ? $success
    : null;

$viewError = isset($error)
    && is_string($error)
    ? $error
    : null;

$viewCsrfToken = isset($csrfToken)
    && is_string($csrfToken)
    ? $csrfToken
    : '';

$totalPlanos = 0;

foreach ($viewProdutos as $produto) {
    if (
        isset($produto['planos'])
        && is_array($produto['planos'])
    ) {
        $totalPlanos += count(
            $produto['planos']
        );
    }
}

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Estrutura comercial
        </span>

        <h1>
            Planos
        </h1>

        <p>
            Configure os planos comerciais disponíveis para cada produto da Init Sistemas.
        </p>

    </div>

</section>


<?php if (
    $viewSuccess !== null
    && $viewSuccess !== ''
): ?>

    <div
        class="form-alert form-alert-success page-alert"
        role="status">
        <?= htmlspecialchars(
            $viewSuccess,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>

<?php endif; ?>


<?php if (
    $viewError !== null
    && $viewError !== ''
): ?>

    <div
        class="form-alert form-alert-error page-alert"
        role="alert">
        <?= htmlspecialchars(
            $viewError,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>

<?php endif; ?>


<div class="plans-overview">

    <div>

        <span>
            Produtos
        </span>

        <strong>
            <?= count($viewProdutos) ?>
        </strong>

    </div>

    <div>

        <span>
            Planos configurados
        </span>

        <strong>
            <?= $totalPlanos ?>
        </strong>

    </div>

</div>


<section class="plans-catalog">

    <?php foreach ($viewProdutos as $produto): ?>

        <?php

        $produtoId = isset($produto['id'])
            ? (int) $produto['id']
            : 0;

        $nome = isset($produto['nome'])
            && is_string($produto['nome'])
            ? $produto['nome']
            : '';

        $codigo = isset($produto['codigo'])
            && is_string($produto['codigo'])
            ? $produto['codigo']
            : '';

        $descricao = isset($produto['descricao'])
            && is_string($produto['descricao'])
            ? $produto['descricao']
            : '';

        $ativo = isset($produto['ativo'])
            && $produto['ativo'] === true;

        $planosProduto =
            isset($produto['planos'])
            && is_array($produto['planos'])
            ? $produto['planos']
            : [];

        ?>

        <article class="plans-product">

            <header class="plans-product-header">

                <div class="plans-product-identity">

                    <span class="plans-product-mark">
                        <?= htmlspecialchars(
                            mb_strtoupper(
                                mb_substr(
                                    $nome,
                                    0,
                                    1,
                                    'UTF-8'
                                ),
                                'UTF-8'
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                    <div>

                        <div class="plans-product-title">

                            <h2>
                                <?= htmlspecialchars(
                                    $nome,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </h2>

                            <span>
                                <?= htmlspecialchars(
                                    $codigo,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                        </div>

                        <?php if ($descricao !== ''): ?>

                            <p>
                                <?= htmlspecialchars(
                                    $descricao,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </p>

                        <?php endif; ?>

                    </div>

                </div>


                <div class="plans-product-actions">

                    <span class="plans-count">
                        <?= count($planosProduto) ?>
                        plano<?= count($planosProduto) === 1
                                    ? ''
                                    : 's' ?>
                    </span>

                    <?php if ($ativo): ?>

                        <a
                            href="<?= htmlspecialchars(
                                        $viewAppUrl
                                            . '/planos/novo?produto='
                                            . $produtoId,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                            class="button-primary">
                            Novo plano
                        </a>

                    <?php endif; ?>

                </div>

            </header>


            <?php if ($planosProduto === []): ?>

                <div class="plans-empty">

                    <strong>
                        Nenhum plano configurado
                    </strong>

                    <span>
                        Este produto ainda não possui opções comerciais cadastradas.
                    </span>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="data-table plans-table">

                        <thead>

                            <tr>
                                <th>Plano</th>
                                <th>Valor</th>
                                <th>Periodicidade</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach (
                                $planosProduto
                                as $plano
                            ): ?>

                                <?php

                                $planoId = isset($plano['id'])
                                    ? (int) $plano['id']
                                    : 0;

                                $planoNome =
                                    isset($plano['nome'])
                                    && is_string($plano['nome'])
                                    ? $plano['nome']
                                    : '';

                                $planoCodigo =
                                    isset($plano['codigo'])
                                    && is_string($plano['codigo'])
                                    ? $plano['codigo']
                                    : '';

                                $valor =
                                    isset($plano['valor'])
                                    && is_numeric($plano['valor'])
                                    ? (float) $plano['valor']
                                    : 0;

                                $periodicidade =
                                    isset($plano['periodicidade'])
                                    && is_string(
                                        $plano['periodicidade']
                                    )
                                    ? $plano['periodicidade']
                                    : '';

                                $planoAtivo =
                                    isset($plano['ativo'])
                                    && $plano['ativo'] === true;

                                ?>

                                <tr>

                                    <td>

                                        <div class="plan-name">

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $planoNome,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </strong>

                                            <span>
                                                <?= htmlspecialchars(
                                                    $planoCodigo,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </span>

                                        </div>

                                    </td>


                                    <td>

                                        <strong class="plan-value">
                                            R$
                                            <?= number_format(
                                                $valor,
                                                2,
                                                ',',
                                                '.'
                                            ) ?>
                                        </strong>

                                    </td>


                                    <td>

                                        <span class="table-muted">
                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    mb_strtolower(
                                                        $periodicidade,
                                                        'UTF-8'
                                                    )
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                    </td>


                                    <td>

                                        <span
                                            class="status-badge <?= $planoAtivo
                                                                    ? 'status-active'
                                                                    : 'status-inactive' ?>">
                                            <?= $planoAtivo
                                                ? 'Ativo'
                                                : 'Inativo' ?>
                                        </span>

                                    </td>


                                    <td>

                                        <div class="table-actions">

                                            <a
                                                href="<?= htmlspecialchars(
                                                            $viewAppUrl
                                                                . '/planos/'
                                                                . $planoId
                                                                . '/editar',
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                class="table-action">
                                                Editar
                                            </a>

                                            <a
                                                href="<?= htmlspecialchars(
                                                            $viewAppUrl
                                                                . '/planos/'
                                                                . $planoId
                                                                . '/limites',
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                class="table-action">
                                                Limites
                                            </a>

                                            <?php if ($planoAtivo): ?>

                                                <form
                                                    method="POST"
                                                    action="<?= htmlspecialchars(
                                                                $viewAppUrl
                                                                    . '/planos/'
                                                                    . $planoId
                                                                    . '/desativar',
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>"
                                                    class="table-action-form">

                                                    <input
                                                        type="hidden"
                                                        name="_token"
                                                        value="<?= htmlspecialchars(
                                                                    $viewCsrfToken,
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ) ?>">

                                                    <button
                                                        type="submit"
                                                        class="table-action table-action-danger">
                                                        Desativar
                                                    </button>

                                                </form>

                                            <?php else: ?>

                                                <form
                                                    method="POST"
                                                    action="<?= htmlspecialchars(
                                                                $viewAppUrl
                                                                    . '/planos/'
                                                                    . $planoId
                                                                    . '/ativar',
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>"
                                                    class="table-action-form">

                                                    <input
                                                        type="hidden"
                                                        name="_token"
                                                        value="<?= htmlspecialchars(
                                                                    $viewCsrfToken,
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ) ?>">

                                                    <button
                                                        type="submit"
                                                        class="table-action table-action-activate">
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

        </article>

    <?php endforeach; ?>

</section>