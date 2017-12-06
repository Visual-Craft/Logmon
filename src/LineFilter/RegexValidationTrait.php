<?php

namespace VisualCraft\Logmon\LineFilter;

trait RegexValidationTrait
{
    protected function validateRegex($regex)
    {
        $errorString = null;
        set_error_handler(function ($errno, $errstr) use (&$errorString) {
            $errorString = $errstr;
        });
        preg_match($regex, '');
        restore_error_handler();

        if ($errorString !== null) {
            $errorPrefix = 'preg_match(): ';

            if (strpos($errorString, $errorPrefix) === 0) {
                $errorString = substr($errorString, strlen($errorPrefix));
            }

            throw new \InvalidArgumentException(sprintf("Invalid regex string '%s'", $errorString));
        }
    }
}
