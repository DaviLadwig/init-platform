<?php

declare(strict_types=1);

$viewPlano = isset($plano)
    && is_array($plano)
        ? $plano
        : [];

$viewLimites = isset($limites)
    && is_array($limites)
        ? $limites
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

$planoId = isset($viewPlano['id'])
    ? (int) $viewPlano['id']
    : 0;

$planoNome = isset($viewPlano['nome'])
    && is_string($viewPlano['nome'])
        ? $viewPlano['nome']
        : '';

$planoCodigo = isset($viewPlano['codigo'])
    && is_string($viewPlano['codigo'])
        ? $viewPlano['codigo']
        : '';

$produtoNome = isset($viewPlano['produto_nome'])
    && is_string($viewPlano['produto_nome'])
        ? $viewPlano['produto_nome']
        : '';

$produtoCodigo = isset($viewPlano['produto_codigo'])
    && is_string($viewPlano['produto_codigo'])
        ? $viewPlano['produto_codigo']
        : '';

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Configuração comercial
        </span>

        <h1>
            Limites do plano
        </h1>

        <p>
            Defina as capacidades e restrições comerciais aplicadas a este plano.
        </p>

    </div>

    <a
        href="<?= htmlspecialchars(
            $viewAppUrl . '/planos',
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
        class="button-secondary"
    >
        Voltar aos planos
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
        role="alert"
    >
        <?= htmlspecialchars(
            $viewError,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>

<?php endif; ?>


<section class="limit-plan-context">

    <div class="limit-plan-main">

        <span class="limit-plan-label">
            Plano
        </span>

        <div class="limit-plan-title">

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

    </div>


    <div class="limit-plan-product">

        <span>
            Produto
        </span>

        <strong>
            <?= htmlspecialchars(
                $produtoNome,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>

        <small>
            <?= htmlspecialchars(
                $produtoCodigo,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </small>

    </div>

</section>


<section class="data-panel">

    <header class="data-panel-header">

        <div>

            <h2>
                Limites configurados
            </h2>

            <p>
                <?= count($viewLimites) ?>
                limite<?= count($viewLimites) === 1
                    ? ''
                    : 's' ?>
                neste plano
            </p>

        </div>

        <a
            href="<?= htmlspecialchars(
                $viewAppUrl
                . '/planos/'
                . $planoId
                . '/limites/novo',
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            class="button-primary"
        >
            Novo limite
        </a>

    </header>


    <?php if ($viewLimites === []): ?>

        <div class="limits-empty">

            <strong>
                Nenhum limite configurado
            </strong>

            <p>
                Este plano ainda não possui restrições ou capacidades comerciais definidas.
            </p>

        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="data-table limits-table">

                <thead>

                    <tr>
                        <th>Limite</th>
                        <th>Valor</th>
                        <th>Unidade</th>
                        <th>Ações</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach (
                        $viewLimites as $limite
                    ): ?>

                        <?php

                        $limiteId = isset($limite['id'])
                            ? (int) $limite['id']
                            : 0;

                        $chave = isset($limite['chave'])
                            && is_string($limite['chave'])
                                ? $limite['chave']
                                : '';

                        $valor = isset($limite['valor'])
                            && is_numeric($limite['valor'])
                                ? (string) $limite['valor']
                                : '0';

                        /*
                         * Remove apenas zeros decimais desnecessários
                         * na apresentação:
                         *
                         * 10.0000 -> 10
                         * 10.5000 -> 10.5
                         * 10.2500 -> 10.25
                         */
                        if (str_contains($valor, '.')) {
                            $valor = rtrim(
                                rtrim(
                                    $valor,
                                    '0'
                                ),
                                '.'
                            );
                        }

                        if ($valor === '') {
                            $valor = '0';
                        }

                        $unidade = isset($limite['unidade'])
                            && is_string($limite['unidade'])
                                ? $limite['unidade']
                                : '';

                        ?>

                        <tr>

                            <td>

                                <span class="limit-key">
                                    <?= htmlspecialchars(
                                        $chave,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </td>


                            <td>

                                <strong class="limit-value">
                                    <?= htmlspecialchars(
                                        $valor,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <?php if ($unidade !== ''): ?>

                                    <span class="table-muted">
                                        <?= htmlspecialchars(
                                            $unidade,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                <?php else: ?>

                                    <span class="table-muted">
                                        —
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="table-actions">

                                    <a
                                        href="<?= htmlspecialchars(
                                            $viewAppUrl
                                            . '/planos/'
                                            . $planoId
                                            . '/limites/'
                                            . $limiteId
                                            . '/editar',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        class="table-action"
                                    >
                                        Editar
                                    </a>

                                    <a
                                        href="<?= htmlspecialchars(
                                            $viewAppUrl
                                            . '/planos/'
                                            . $planoId
                                            . '/limites/'
                                            . $limiteId
                                            . '/remover',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        class="table-action table-action-danger"
                                    >
                                        Remover
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>