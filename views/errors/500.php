<?php

declare(strict_types=1);

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
        Erro interno | Init SaaS Platform
    </title>
</head>

<body>

    <main>

        <h1>500</h1>

        <p>
            Não foi possível concluir a operação.
        </p>

        <?php if (
            isset($errorReference)
            && is_string($errorReference)
            && $errorReference !== ''
        ): ?>

            <p>
                Referência:
                <?= htmlspecialchars(
                    $errorReference,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

        <?php endif; ?>

    </main>

</body>

</html>