<?php

namespace Conduit\Validation\Exceptions;

use \Respect\Validation\Exceptions\ValidationException;

class ExistsInTableException extends ValidationException
{

    public static $defaultTemplates = [
        self::MODE_DEFAULT  => [
            self::STANDARD => 'Existe un registro',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => 'No existe un registro',
        ],
    ];
}
