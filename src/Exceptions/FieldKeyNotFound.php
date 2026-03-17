<?php

namespace Karabin\TranslatableRevisions\Exceptions;

use Exception;

class FieldKeyNotFound extends Exception
{
    public static function fieldKeyNotFound(string $fieldKey): FieldKeyNotFound
    {
        return new self('Field key not found for: '.$fieldKey);
    }
}
