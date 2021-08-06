<?php

namespace ReactApp\Exception;

use ReactApp\Exception\ServerException;

class InvalidValue extends ServerException
{
    protected $code        = 422;
    protected $message     = 'Unprocessable Entity';
    protected $title       = '422 Unprocessable Entity';
    protected $description = 'Invalid value for %s with value %s';

    public static function throw(string $property, mixed $value): self
    {
        $error = new self();
        $error->description = sprintf($error->description, $property, $value);
        throw $error;
    }
}