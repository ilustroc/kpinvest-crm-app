<?php

namespace App\Support;

class ExportSanitizer
{
    /**
     * Prevents CSV/Excel Formula Injection by prepending a single quote (')
     * to string values that begin with '=', '+', '-', '@', "\t", or "\r".
     *
     * @param mixed $value
     * @return mixed
     */
    public static function sanitize(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        $trimmed = ltrim($value, " \t\r\n");
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        if (in_array($value[0], ["\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
