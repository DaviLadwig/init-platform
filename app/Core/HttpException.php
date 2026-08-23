<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        private readonly string $publicMessage
    ) {
        if ($statusCode < 400 || $statusCode > 599) {
            throw new InvalidArgumentException(
                'Código HTTP inválido.'
            );
        }

        parent::__construct($publicMessage);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getPublicMessage(): string
    {
        return $this->publicMessage;
    }
}
