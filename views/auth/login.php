<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Dados recebidos do AuthController
|--------------------------------------------------------------------------
|
| A view não deve assumir cegamente que todas as variáveis existem.
| Isso evita warnings de variável indefinida e torna o template
| mais defensivo caso seja chamado incorretamente no futuro.
|
*/

$viewAppUrl = isset($appUrl) && is_string($appUrl)
    ? rtrim($appUrl, '/')
    : '';

$viewCsrfToken = isset($csrfToken) && is_string($csrfToken)
    ? $csrfToken
    : '';

$viewError = isset($error) && is_string($error)
    ? $error
    : null;


/*
|--------------------------------------------------------------------------
| Escape para saída HTML
|--------------------------------------------------------------------------
*/

$safeAppUrl = htmlspecialchars(
    $viewAppUrl,
    ENT_QUOTES,
    'UTF-8'
);

$safeCsrfToken = htmlspecialchars(
    $viewCsrfToken,
    ENT_QUOTES,
    'UTF-8'
);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="robots"
        content="noindex, nofollow"
    >

    <title>
        Entrar | Init SaaS Platform
    </title>

    <link
        rel="stylesheet"
        href="<?= $safeAppUrl ?>/css/auth.css"
    >

</head>

<body>

    <main class="auth-page">

        <section
            class="auth-card"
            aria-labelledby="login-title"
        >

            <header class="auth-header">

                <span class="auth-brand">
                    INIT
                </span>

                <h1 id="login-title">
                    Init SaaS Platform
                </h1>

                <p>
                    Acesso administrativo
                </p>

            </header>

            <?php if (
                $viewError !== null
                && $viewError !== ''
            ): ?>

                <div
                    class="auth-alert"
                    role="alert"
                    aria-live="polite"
                >
                    <?= htmlspecialchars(
                        $viewError,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php endif; ?>

            <form
                method="POST"
                action="<?= $safeAppUrl ?>/login"
                autocomplete="on"
            >

                <input
                    type="hidden"
                    name="_token"
                    value="<?= $safeCsrfToken ?>"
                >

                <div class="form-group">

                    <label for="email">
                        E-mail
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="255"
                        autocomplete="username"
                        inputmode="email"
                        required
                        autofocus
                    >

                </div>

                <div class="form-group">

                    <label for="password">
                        Senha
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        maxlength="128"
                        autocomplete="current-password"
                        required
                    >

                </div>

                <button
                    type="submit"
                    class="auth-button"
                >
                    Entrar
                </button>

            </form>

        </section>

    </main>

</body>

</html>