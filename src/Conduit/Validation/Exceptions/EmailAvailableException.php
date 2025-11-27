<?php

namespace Conduit\Validation\Exceptions;

use \Respect\Validation\Exceptions\ValidationException;

class EmailAvailableException extends ValidationException
{
    
    public static $defaultTemplates = [
        self::MODE_DEFAULT  => [
            self::STANDARD => 'El correo electrónico ya existe..',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => 'El correo electrónico no existe.',
        ],
    ];
}
