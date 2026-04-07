<?php

declare(strict_types=1);

namespace ShopSavvy\Laravel\Exceptions;

use RuntimeException;

class ShopSavvyException extends RuntimeException
{
    public function __construct(string $message, int $statusCode = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->getCode();
    }
}
