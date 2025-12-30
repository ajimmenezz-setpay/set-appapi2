<?php

namespace App\Exceptions;

use Exception;

abstract class ApiException extends Exception
{
    protected int $status = 400;
    protected string $error = 'api_error';
    protected array $meta = [];

    public function status(): int
    {
        return $this->status;
    }

    public function error(): string
    {
        return $this->error;
    }

    public function meta(): array
    {
        return $this->meta;
    }
}
