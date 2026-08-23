<?php

declare(strict_types=1);

/*
 * Valor padrão seguro caso esta view seja chamada
 * sem uma mensagem definida pelo ExceptionHandler.
 */
$message = isset($publicMessage) && is_string($publicMessage)
    ? $publicMessage
    : 'A página solicitada não foi encontrada.';

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
        Página não encontrada | Init SaaS Platform
    </title>
</head>

<body>

    <main>

        <h1>404</h1>

        <p>
            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>

    </main>

</body>

</html>