<?php

namespace App\Traits;

use Carbon\Carbon;

trait CsvImportTrait
{
    protected function detectDelimiter(string $firstLine): string {
        return (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
    }

    protected function toUtf8(?string $s): ?string
    {
        if ($s === null) { return null; }
        $s = str_replace("\xEF\xBB\xBF", '', $s);
        $s = preg_replace('/^\x{FEFF}/u', '', $s);
        $s = trim($s);
        if ($s === '') { return null;}
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1, Windows-1252, UTF-8');
        }
        return trim($s);
    }

    protected function parseDate(?string $v): ?string {
        if (!$v) return null;
        $v = trim($v);
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $v, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $v)) return $v;
        return null;
    }

    protected function parseNumber(?string $v): ?float {
        if ($v === null || trim($v) === '') return null;
        $v = str_replace(' ', '', trim($v));
        if (str_contains($v, ',') && str_contains($v, '.')) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } elseif (str_contains($v, ',')) {
            $v = str_replace(',', '.', $v);
        }
        return is_numeric($v) ? (float)$v : null;
    }
}