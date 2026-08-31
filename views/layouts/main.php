<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Dados recebidos pelo layout
|--------------------------------------------------------------------------
*/

$viewTitle = isset($title) && is_string($title)
    ? $title
    : 'Init SaaS Platform';

$viewActiveMenu = isset($activeMenu) && is_string($activeMenu)
    ? $activeMenu
    : 'dashboard';

$viewContent = isset($content) && is_string($content)
    ? $content
    : '';

$viewAppUrl = isset($appUrl) && is_string($appUrl)
    ? rtrim($appUrl, '/')
    : '';

$viewCsrfToken = isset($csrfToken) && is_string($csrfToken)
    ? $csrfToken
    : '';

$viewUserName = isset($userName) && is_string($userName)
    ? $userName
    : 'Usuário';

$viewUserRole = isset($userRole) && is_string($userRole)
    ? $userRole
    : '';

$viewPageStyles = isset($pageStyles) && is_array($pageStyles)
    ? $pageStyles
    : [];

$viewPageScripts = isset($pageScripts) && is_array($pageScripts)
    ? $pageScripts
    : [];


/*
|--------------------------------------------------------------------------
| Helpers da view
|--------------------------------------------------------------------------
*/

$e = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES,
    'UTF-8'
);

$url = static function (string $path) use (
    $viewAppUrl,
    $e
): string {
    $normalizedPath = '/' . ltrim($path, '/');

    return $e(
        $viewAppUrl . $normalizedPath
    );
};

$navClass = static function (string $menu) use (
    $viewActiveMenu
): string {
    return 'navigation-item'
        . (
            $viewActiveMenu === $menu
                ? ' active'
                : ''
        );
};

$userInitial = mb_strtoupper(
    mb_substr(
        $viewUserName,
        0,
        1,
        'UTF-8'
    ),
    'UTF-8'
);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <meta
        name="robots"
        content="noindex, nofollow">

    <title>
        <?= $e($viewTitle) ?> | Init SaaS Platform
    </title>

    <link
        rel="stylesheet"
        href="<?= $url('/css/app.css') ?>">

    <?php foreach ($viewPageStyles as $stylesheet): ?>

        <?php

        $stylesheetIsValid =
            is_string($stylesheet)
            && preg_match(
                '/^[a-z0-9_-]+\.css$/',
                $stylesheet
            ) === 1;

        ?>

        <?php if ($stylesheetIsValid): ?>

            <link
                rel="stylesheet"
                href="<?= $url(
                    '/css/' . $stylesheet
                ) ?>">

        <?php endif; ?>

    <?php endforeach; ?>

</head>

<body>

    <div class="app-shell">

        <!-- =====================================================
             SIDEBAR
        ====================================================== -->

        <aside
            class="app-sidebar"
            id="app-sidebar">

            <div class="sidebar-brand">

                <a
                    href="<?= $url('/') ?>"
                    class="brand-link"
                    aria-label="Init SaaS Platform">

                    <span class="brand-mark">
                        I
                    </span>

                    <span class="brand-content">

                        <strong>
                            INIT
                        </strong>

                        <small>
                            SaaS Platform
                        </small>

                    </span>

                </a>

            </div>


            <nav
                class="sidebar-navigation"
                aria-label="Navegação principal">

                <!-- VISÃO GERAL -->

                <div class="navigation-section">

                    <span class="navigation-label">
                        Visão geral
                    </span>

                    <a
                        href="<?= $url('/') ?>"
                        class="<?= $e(
                            $navClass('dashboard')
                        ) ?>">

                        <span class="navigation-icon">

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path
                                    d="M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z" />
                            </svg>

                        </span>

                        <span>
                            Dashboard
                        </span>

                    </a>

                </div>


                <!-- COMERCIAL -->

                <div class="navigation-section">

                    <span class="navigation-label">
                        Comercial
                    </span>

                    <!-- CLIENTES -->

                    <a
                        href="<?= $url('/clientes') ?>"
                        class="<?= $e(
                            $navClass('clientes')
                        ) ?>">

                        <span class="navigation-icon">

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path
                                    d="M4 4h16v16H4V4Zm2 2v3h12V6H6Zm0 5v7h12v-7H6Z" />
                            </svg>

                        </span>

                        <span>
                            Clientes
                        </span>

                    </a>


                    <!-- PRODUTOS -->

                    <a
                        href="<?= $url('/produtos') ?>"
                        class="<?= $e(
                            $navClass('produtos')
                        ) ?>">

                        <span class="navigation-icon">

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path
                                    d="M4 5h16v4H4V5zm0 6h7v8H4v-8z" />
                            </svg>

                        </span>

                        <span>
                            Produtos
                        </span>

                    </a>


                    <!-- PLANOS -->

                    <a
                        href="<?= $url('/planos') ?>"
                        class="<?= $e(
                            $navClass('planos')
                        ) ?>">

                        <span class="navigation-icon">

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path
                                    d="M3 6h18v12H3V6Zm2 2v8h14V8H5Zm2 2h6v2H7v-2Z" />
                            </svg>

                        </span>

                        <span>
                            Planos
                        </span>

                    </a>


                    <!-- ASSINATURAS -->

                    <a
                        href="<?= $url('/assinaturas') ?>"
                        class="<?= $e(
                            $navClass('assinaturas')
                        ) ?>">

                        <span class="navigation-icon">

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path
                                    d="M7 2h10v3h3v17H4V5h3V2Zm2 3h6V4H9v1Zm-3 2v13h12V7H6Zm2 3h8v2H8v-2Zm0 4h6v2H8v-2Z" />
                            </svg>

                        </span>

                        <span>
                            Assinaturas
                        </span>

                    </a>

                </div>


                <!-- OPERAÇÕES -->

                <div class="navigation-section">

                    <span class="navigation-label">
                        Operações
                    </span>

                    <!-- TENANTS - ainda não liberado -->

                    <span
                        class="navigation-item navigation-item-disabled"
                        aria-disabled="true">

                        <span class="navigation-icon">

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path
                                    d="M12 2 3 7v10l9 5 9-5V7l-9-5Zm0 2.3L18.6 8 12 11.7 5.4 8 12 4.3ZM5 9.7l6 3.3v6.3l-6-3.4V9.7Zm8 9.6V13l6-3.3v6.2l-6 3.4Z" />
                            </svg>

                        </span>

                        <span>
                            Tenants
                        </span>

                    </span>


                    <!-- LOGS - ainda não liberado -->

                    <span
                        class="navigation-item navigation-item-disabled"
                        aria-disabled="true">

                        <span class="navigation-icon">

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path
                                    d="M4 3h16v18H4V3Zm2 2v14h12V5H6Zm2 2h8v2H8V7Zm0 4h8v2H8v-2Zm0 4h5v2H8v-2Z" />
                            </svg>

                        </span>

                        <span>
                            Logs
                        </span>

                    </span>

                </div>

            </nav>


            <div class="sidebar-footer">

                <div class="sidebar-user">

                    <span class="user-avatar">
                        <?= $e($userInitial) ?>
                    </span>

                    <div class="sidebar-user-info">

                        <strong>
                            <?= $e($viewUserName) ?>
                        </strong>

                        <span>
                            <?= $e($viewUserRole) ?>
                        </span>

                    </div>

                </div>

            </div>

        </aside>


        <!-- =====================================================
             CONTEÚDO
        ====================================================== -->

        <div class="app-content">

            <!-- HEADER -->

            <header class="app-header">

                <div class="header-left">

                    <button
                        type="button"
                        class="sidebar-toggle"
                        id="sidebar-toggle"
                        aria-label="Abrir ou fechar menu"
                        aria-controls="app-sidebar">

                        <span></span>
                        <span></span>
                        <span></span>

                    </button>


                    <div class="header-context">

                        <span>
                            Init Sistemas
                        </span>

                        <strong>
                            <?= $e($viewTitle) ?>
                        </strong>

                    </div>

                </div>


                <div class="header-right">

                    <div class="header-user">

                        <span class="header-user-name">
                            <?= $e($viewUserName) ?>
                        </span>

                        <span class="header-user-role">
                            <?= $e($viewUserRole) ?>
                        </span>

                    </div>


                    <form
                        method="POST"
                        action="<?= $url('/logout') ?>"
                        class="logout-form">

                        <input
                            type="hidden"
                            name="_token"
                            value="<?= $e(
                                $viewCsrfToken
                            ) ?>">

                        <button
                            type="submit"
                            class="logout-button">
                            Sair
                        </button>

                    </form>

                </div>

            </header>


            <!-- MAIN -->

            <main class="app-main">

                <?= $viewContent ?>

            </main>

        </div>

    </div>


    <script
        src="<?= $url('/js/app.js') ?>"
        defer>
    </script>

    <?php foreach ($viewPageScripts as $script): ?>

        <?php

        $scriptIsValid =
            is_string($script)
            && preg_match(
                '/^[a-z0-9_-]+\.js$/',
                $script
            ) === 1;

        ?>

        <?php if ($scriptIsValid): ?>

            <script
                src="<?= $url('/js/' . $script) ?>"
                defer>
            </script>

        <?php endif; ?>

    <?php endforeach; ?>

</body>

</html>
