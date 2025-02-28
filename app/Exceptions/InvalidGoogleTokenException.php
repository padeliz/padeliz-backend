<?php

namespace App\Exceptions;

class InvalidGoogleTokenException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Invalid Google token provided');
    }
}
