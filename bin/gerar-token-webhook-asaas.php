<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    echo bin2hex(
        random_bytes(32)
    );

    echo PHP_EOL;

    exit(0);
} catch (\Throwable) {
    fwrite(
        STDERR,
        "Não foi possível gerar o token.\n"
    );

    exit(1);
}
