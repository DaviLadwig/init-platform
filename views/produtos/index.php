<?php

declare(strict_types=1);

$viewProdutos = isset($produtos) && is_array($produtos) ? $produtos : [];
$viewAppUrl = isset($appUrl) && is_string($appUrl) ? rtrim($appUrl, '/') : '';
$viewSuccess = isset($success) && is_string($success) ? $success : null;
$viewError = isset($error) && is_string($error) ? $error : null;
$viewCsrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';

$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$stringValue = static function (array $source, string $key): string {
    $value = $source[$key] ?? null;
    return is_string($value) ? $value : '';
};

$boolValue = static function (mixed $value): bool {
    return $value === true
        || $value === 1
        || $value === '1'
        || $value === 't'
        || $value === 'true';
};

$totalProdutos = count($viewProdutos);
$totalAtivos = 0;
$totalPlanos = 0;

foreach ($viewProdutos as $produtoResumo) {
    if (!is_array($produtoResumo)) {
        continue;
    }

    if ($boolValue($produtoResumo['ativo'] ?? false)) {
        $totalAtivos++;
    }

    $totalPlanos += max(0, (int) ($produtoResumo['total_planos'] ?? 0));
}
?>

<section class="page-heading products-heading">
    <div>
        <span class="page-eyebrow">Catálogo SaaS</span>
        <h1>Produtos</h1>
        <p>Gerencie os produtos comercializados pela Init e acesse seus planos.</p>
    </div>

    <a
        href="<?= $e($viewAppUrl . '/produtos/novo') ?>"
        class="button-primary">
        Novo produto
    </a>
</section>

<?php if ($viewSuccess !== null && $viewSuccess !== ''): ?>
    <div class="form-alert form-alert-success page-alert" role="status">
        <?= $e($viewSuccess) ?>
    </div>
<?php endif; ?>

<?php if ($viewError !== null && $viewError !== ''): ?>
    <div class="form-alert form-alert-error page-alert" role="alert">
        <?= $e($viewError) ?>
    </div>
<?php endif; ?>

<section class="products-overview" aria-label="Resumo do catálogo">
    <div class="products-overview-item">
        <span>Produtos cadastrados</span>
        <strong><?= $totalProdutos ?></strong>
    </div>

    <div class="products-overview-item">
        <span>Produtos ativos</span>
        <strong><?= $totalAtivos ?></strong>
    </div>

    <div class="products-overview-item">
        <span>Planos cadastrados</span>
        <strong><?= $totalPlanos ?></strong>
    </div>
</section>

<section class="products-section">
    <header class="products-section-header">
        <div>
            <h2>Catálogo de produtos</h2>
            <p>Administração comercial dos produtos SaaS disponíveis na plataforma.</p>
        </div>
    </header>

    <?php if ($viewProdutos === []): ?>
        <div class="products-empty">
            <h3>Nenhum produto cadastrado</h3>
            <p>Cadastre o primeiro produto para começar a estruturar o catálogo SaaS.</p>

            <a
                href="<?= $e($viewAppUrl . '/produtos/novo') ?>"
                class="button-primary">
                Cadastrar produto
            </a>
        </div>
    <?php else: ?>
        <div class="products-table-shell">
            <div class="table-responsive">
                <table class="data-table products-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Código</th>
                            <th>Planos</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($viewProdutos as $produto): ?>
                            <?php
                            if (!is_array($produto)) {
                                continue;
                            }

                            $produtoId = isset($produto['id']) ? (int) $produto['id'] : 0;

                            if ($produtoId <= 0) {
                                continue;
                            }

                            $nome = $stringValue($produto, 'nome');
                            $codigo = $stringValue($produto, 'codigo');
                            $descricao = $stringValue($produto, 'descricao');
                            $ativo = $boolValue($produto['ativo'] ?? false);
                            $totalPlanosProduto = max(
                                0,
                                (int) ($produto['total_planos'] ?? 0)
                            );

                            $initial = $nome !== ''
                                ? mb_strtoupper(
                                    mb_substr($nome, 0, 1, 'UTF-8'),
                                    'UTF-8'
                                )
                                : 'I';
                            ?>

                            <tr>
                                <td>
                                    <div class="product-cell">
                                        <span class="product-mark" aria-hidden="true">
                                            <?= $e($initial) ?>
                                        </span>

                                        <div class="product-info">
                                            <strong><?= $e($nome) ?></strong>

                                            <?php if ($descricao !== ''): ?>
                                                <span><?= $e($descricao) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <code class="product-code"><?= $e($codigo) ?></code>
                                </td>

                                <td>
                                    <div class="product-plan-summary">
                                        <strong><?= $totalPlanosProduto ?></strong>
                                        <span>
                                            plano<?= $totalPlanosProduto === 1 ? '' : 's' ?>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <span
                                        class="product-status <?= $ativo
                                                                    ? 'product-status-active'
                                                                    : 'product-status-inactive' ?>">
                                        <?= $ativo ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="product-actions">
                                        <?php if ($totalPlanosProduto > 0): ?>
                                            <a
                                                href="<?= $e($viewAppUrl . '/planos') ?>"
                                                class="product-action product-action-primary">
                                                Ver planos
                                            </a>
                                        <?php elseif ($ativo): ?>
                                            <a
                                                href="<?= $e(
                                                            $viewAppUrl
                                                                . '/planos/novo?produto='
                                                                . $produtoId
                                                        ) ?>"
                                                class="product-action product-action-primary">
                                                Criar plano
                                            </a>
                                        <?php endif; ?>

                                        <a
                                            href="<?= $e(
                                                        $viewAppUrl
                                                            . '/produtos/'
                                                            . $produtoId
                                                            . '/editar'
                                                    ) ?>"
                                            class="product-action">
                                            Editar
                                        </a>

                                        <?php if ($ativo): ?>
                                            <form
                                                method="POST"
                                                action="<?= $e(
                                                            $viewAppUrl
                                                                . '/produtos/'
                                                                . $produtoId
                                                                . '/desativar'
                                                        ) ?>"
                                                class="product-action-form">
                                                <input
                                                    type="hidden"
                                                    name="_token"
                                                    value="<?= $e($viewCsrfToken) ?>">

                                                <button
                                                    type="submit"
                                                    class="product-action product-action-danger">
                                                    Desativar
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form
                                                method="POST"
                                                action="<?= $e(
                                                            $viewAppUrl
                                                                . '/produtos/'
                                                                . $produtoId
                                                                . '/ativar'
                                                        ) ?>"
                                                class="product-action-form">
                                                <input
                                                    type="hidden"
                                                    name="_token"
                                                    value="<?= $e($viewCsrfToken) ?>">

                                                <button
                                                    type="submit"
                                                    class="product-action product-action-success">
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
        </div>
    <?php endif; ?>
</section>