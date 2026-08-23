<?php

declare(strict_types=1);

$message = isset($publicMessage)
    && is_string($publicMessage)
    ? $publicMessage
    : 'Muitas tentativas foram realizadas. Tente novamente mais tarde.';

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
        Muitas tentativas | Init SaaS Platform
    </title>

</head>

<body>

    <main>

        <h1>429</h1>

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