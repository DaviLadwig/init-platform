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


?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Catálogo SaaS
        </span>

        <h1>
            Produtos
        </h1>

        <p>
            Produtos disponibilizados e administrados através da plataforma Init.
        </p>

    </div>

    <a
        href="<?= htmlspecialchars(
                    $viewAppUrl . '/planos/novo',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
        class="button-primary">
        Novo plano
    </a>

    <a
        href="<?= htmlspecialchars(
                    $viewAppUrl . '/produtos/novo',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
        class="button-primary">
        Novo produto
    </a>

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


<section class="data-panel">

    <header class="data-panel-header">

        <div>

            <h2>
                Produtos cadastrados
            </h2>

            <p>
                <?= count($viewProdutos) ?>
                produto<?= count($viewProdutos) === 1 ? '' : 's' ?>
                na plataforma
            </p>

        </div>

    </header>


    <?php if ($viewProdutos === []): ?>

        <div class="empty-state">

            <h3>
                Nenhum produto cadastrado
            </h3>

            <p>
                Ainda não existem produtos SaaS registrados na plataforma.
            </p>

        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="data-table">

                <thead>

                    <tr>
                        <th>Produto</th>
                        <th>Código</th>
                        <th>Slug</th>
                        <th>Planos</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($viewProdutos as $produto): ?>

                        <?php

                        $nome = isset($produto['nome'])
                            && is_string($produto['nome'])
                            ? $produto['nome']
                            : '';

                        $codigo = isset($produto['codigo'])
                            && is_string($produto['codigo'])
                            ? $produto['codigo']
                            : '';

                        $slug = isset($produto['slug'])
                            && is_string($produto['slug'])
                            ? $produto['slug']
                            : '';

                        $descricao = isset($produto['descricao'])
                            && is_string($produto['descricao'])
                            ? $produto['descricao']
                            : '';

                        $ativo = isset($produto['ativo'])
                            ? (bool) $produto['ativo']
                            : false;

                        $totalPlanos = isset($produto['total_planos'])
                            ? (int) $produto['total_planos']
                            : 0;

                        ?>

                        <tr>

                            <td>

                                <div class="product-cell">

                                    <span class="product-mark">
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

                                    <div class="product-info">

                                        <strong>
                                            <?= htmlspecialchars(
                                                $nome,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </strong>

                                        <?php if ($descricao !== ''): ?>

                                            <span>
                                                <?= htmlspecialchars(
                                                    $descricao,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <span class="code-badge">
                                    <?= htmlspecialchars(
                                        $codigo,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </td>


                            <td>

                                <span class="table-muted">
                                    <?= htmlspecialchars(
                                        $slug,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </td>


                            <td>
                                <?= $totalPlanos ?>
                            </td>


                            <td>

                                <?php if ($ativo): ?>

                                    <span class="status-badge status-active">
                                        Ativo
                                    </span>

                                <?php else: ?>

                                    <span class="status-badge status-inactive">
                                        Inativo
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <div class="table-actions">

                                    <a
                                        href="<?= htmlspecialchars(
                                                    $viewAppUrl
                                                        . '/produtos/'
                                                        . (int) $produto['id']
                                                        . '/editar',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                        class="table-action">
                                        Editar
                                    </a>


                                    <?php if ($ativo): ?>

                                        <form
                                            method="POST"
                                            action="<?= htmlspecialchars(
                                                        $viewAppUrl
                                                            . '/produtos/'
                                                            . (int) $produto['id']
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
                                                            . '/produtos/'
                                                            . (int) $produto['id']
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

</section>