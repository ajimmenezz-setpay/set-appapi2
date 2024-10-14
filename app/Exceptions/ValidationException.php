<?php

namespace App\Exceptions;

use Exception;

class ValidationException extends Exception
{
    public function render($request)
    {
        return response($this->getMessage(), 400);
    }
}