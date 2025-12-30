<?php

namespace App\Exceptions\Authentication;

use App\Exceptions\ApiException;

class InvalidCredentialsException extends ApiException
{
    protected int $status = 401;
    protected string $error = 'INVALID_USER_OR_CREDENTIALS';

    public function __construct(
        string $message = 'Las credenciales proporcionadas no son válidas.',
        array $meta = []
    ) {
        parent::__construct($message);
        $this->meta = $meta;
    }
}
