<?php

declare(strict_types=1);

$viewPlano = isset($plano)
    && is_array($plano)
    ? $plano
    : [];

$viewLimite = isset($limite)
    && is_array($limite)
    ? $limite
    : [];

$viewAppUrl = isset($appUrl)
    && is_string($appUrl)
    ? rtrim($appUrl, '/')
    : '';

$viewCsrfToken = isset($csrfToken)
    && is_string($csrfToken)
    ? $csrfToken
    : '';

$planoId = isset($viewPlano['id'])
    ? (int) $viewPlano['id']
    : 0;

$limiteId = isset($viewLimite['id'])
    ? (int) $viewLimite['id']
    : 0;

$planoNome =
    is_string($viewPlano['nome'] ?? null)
    ? $viewPlano['nome']
    : '';

$produtoNome =
    is_string(
        $viewPlano['produto_nome']
            ?? null
    )
    ? $viewPlano['produto_nome']
    : '';

$chave =
    is_string($viewLimite['chave'] ?? null)
    ? $viewLimite['chave']
    : '';

$valor =
    is_numeric($viewLimite['valor'] ?? null)
    ? (string) $viewLimite['valor']
    : '0';

$unidade =
    is_string($viewLimite['unidade'] ?? null)
    ? $viewLimite['unidade']
    : '';

if (str_contains($valor, '.')) {
    $valor = rtrim(
        rtrim(
            $valor,
            '0'
        ),
        '.'
    );
}

?>

<section class="page-heading">

    <div>

        <span class="page-eyebrow">
            Configuração comercial
        </span>

        <h1>
            Remover limite
        </h1>

        <p>
            Confirme a remoção da configuração selecionada.
        </p>

    </div>

</section>


<section class="limit-delete-panel">

    <div class="limit-delete-context">

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


        <span>
            Plano
        </span>

        <strong>
            <?= htmlspecialchars(
                $planoNome,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>

    </div>


    <div class="limit-delete-target">

        <span>
            Limite selecionado
        </span>

        <strong>
            <?= htmlspecialchars(
                $chave,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>

        <p>
            Valor atual:
            <?= htmlspecialchars(
                $valor,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

            <?php if ($unidade !== ''): ?>
                <?= htmlspecialchars(
                    $unidade,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            <?php endif; ?>
        </p>

    </div>


    <div class="limit-delete-warning">

        <strong>
            Esta configuração será removida.
        </strong>

        <p>
            O registro deixará de fazer parte do plano, mas a operação permanecerá registrada na auditoria administrativa.
        </p>

    </div>


    <form
        method="POST"
        action="<?= htmlspecialchars(
                    $viewAppUrl
                        . '/planos/'
                        . $planoId
                        . '/limites/'
                        . $limiteId
                        . '/remover',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
        class="limit-delete-actions">

        <input
            type="hidden"
            name="_token"
            value="<?= htmlspecialchars(
                        $viewCsrfToken,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">

        <a
            href="<?= htmlspecialchars(
                        $viewAppUrl
                            . '/planos/'
                            . $planoId
                            . '/limites',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
            class="button-secondary">
            Cancelar
        </a>

        <button
            type="submit"
            class="button-danger">
            Remover limite
        </button>

    </form>

</section>