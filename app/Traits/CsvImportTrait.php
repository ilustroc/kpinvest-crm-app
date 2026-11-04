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

    /**
     * Parse and strictly validate a financial amount.
     *
     * @param string|null $v
     * @param string|null $convention 'US' (thousands comma, decimal dot), 'EU' (thousands dot, decimal comma), or null (unambiguous only)
     * @return array{
     *     valid: bool,
     *     value: string|null,
     *     float_value: float|null,
     *     is_empty: bool,
     *     is_ambiguous: bool,
     *     error: string|null
     * }
     */
    public function parseFinancialAmount(?string $v, ?string $convention = null): array
    {
        if ($v === null) {
            return ['valid' => true, 'value' => null, 'float_value' => null, 'is_empty' => true, 'is_ambiguous' => false, 'error' => null];
        }

        $raw = trim($v);
        if ($raw === '' || $raw === '-' || $raw === 'N/A' || $raw === 'NULL' || $raw === 'null') {
            return ['valid' => true, 'value' => null, 'float_value' => null, 'is_empty' => true, 'is_ambiguous' => false, 'error' => null];
        }

        // Check for accounting parentheses: (1,250.50) or ($1,250.50)
        $isNegative = false;
        if (str_starts_with($raw, '(') && str_ends_with($raw, ')')) {
            $isNegative = true;
            $raw = trim(substr($raw, 1, -1));
        } elseif (str_starts_with($raw, '-')) {
            $isNegative = true;
            $raw = trim(substr($raw, 1));
        } elseif (str_starts_with($raw, '+')) {
            $raw = trim(substr($raw, 1));
        }

        // Strip legitimate currency prefixes and suffixes
        $raw = preg_replace('/^(S\/\.?|\$|USD|PEN|EUR)\s*/i', '', $raw);
        $raw = preg_replace('/\s*(S\/\.?|\$|USD|PEN|EUR)$/i', '', $raw);
        $raw = trim($raw);

        // Strict rejection: only digits, dots, and commas are allowed in a valid number
        if (!preg_match('/^[\d.,]+$/', $raw)) {
            return [
                'valid' => false,
                'value' => null,
                'float_value' => null,
                'is_empty' => false,
                'is_ambiguous' => false,
                'error' => "TEXTO_MALFORMADO: '{$v}' contiene caracteres no numéricos no permitidos",
            ];
        }

        $hasComma = str_contains($raw, ',');
        $hasDot = str_contains($raw, '.');
        $normalized = null;

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($raw, ',');
            $lastDot = strrpos($raw, '.');

            if ($lastDot > $lastComma) {
                // US format: 1,250.50
                $integerPart = substr($raw, 0, $lastDot);
                $decimalPart = substr($raw, $lastDot + 1);

                if (!preg_match('/^\d{1,3}(,\d{3})*$/', $integerPart) || !preg_match('/^\d{1,2}$/', $decimalPart)) {
                    return [
                        'valid' => false,
                        'value' => null,
                        'float_value' => null,
                        'is_empty' => false,
                        'is_ambiguous' => false,
                        'error' => "FORMATO_INVALIDO: '{$v}' tiene separadores incoherentes",
                    ];
                }
                $cleanInt = str_replace(',', '', $integerPart);
                $normalized = $cleanInt . '.' . str_pad($decimalPart, 2, '0', STR_PAD_RIGHT);
            } else {
                // EU format: 1.250,50
                $integerPart = substr($raw, 0, $lastComma);
                $decimalPart = substr($raw, $lastComma + 1);

                if (!preg_match('/^\d{1,3}(\.\d{3})*$/', $integerPart) || !preg_match('/^\d{1,2}$/', $decimalPart)) {
                    return [
                        'valid' => false,
                        'value' => null,
                        'float_value' => null,
                        'is_empty' => false,
                        'is_ambiguous' => false,
                        'error' => "FORMATO_INVALIDO: '{$v}' tiene separadores incoherentes",
                    ];
                }
                $cleanInt = str_replace('.', '', $integerPart);
                $normalized = $cleanInt . '.' . str_pad($decimalPart, 2, '0', STR_PAD_RIGHT);
            }
        } elseif ($hasComma) {
            // Only comma present
            if (substr_count($raw, ',') > 1) {
                if (preg_match('/^\d{1,3}(,\d{3})+$/', $raw)) {
                    $normalized = str_replace(',', '', $raw) . '.00';
                } else {
                    return ['valid' => false, 'value' => null, 'float_value' => null, 'is_empty' => false, 'is_ambiguous' => false, 'error' => "FORMATO_INVALIDO: '{$v}'"];
                }
            } else {
                $parts = explode(',', $raw);
                if (strlen($parts[1]) === 1 || strlen($parts[1]) === 2) {
                    $normalized = $parts[0] . '.' . str_pad($parts[1], 2, '0', STR_PAD_RIGHT);
                } elseif (strlen($parts[1]) === 3) {
                    if ($convention === 'US') {
                        $normalized = $parts[0] . $parts[1] . '.00';
                    } elseif ($convention === 'EU') {
                        return [
                            'valid' => false,
                            'value' => null,
                            'float_value' => null,
                            'is_empty' => false,
                            'is_ambiguous' => true,
                            'error' => "DECIMALES_EXCESIVOS: '{$v}' tiene 3 decimales en convención EU",
                        ];
                    } else {
                        return [
                            'valid' => false,
                            'value' => null,
                            'float_value' => null,
                            'is_empty' => false,
                            'is_ambiguous' => true,
                            'error' => "FORMATO_AMBIGUO: '{$v}' contiene separador de 3 dígitos sin convención definida",
                        ];
                    }
                } else {
                    return ['valid' => false, 'value' => null, 'float_value' => null, 'is_empty' => false, 'is_ambiguous' => false, 'error' => "FORMATO_INVALIDO: '{$v}'"];
                }
            }
        } elseif ($hasDot) {
            // Only dot present
            if (substr_count($raw, '.') > 1) {
                if (preg_match('/^\d{1,3}(\.\d{3})+$/', $raw)) {
                    $normalized = str_replace('.', '', $raw) . '.00';
                } else {
                    return ['valid' => false, 'value' => null, 'float_value' => null, 'is_empty' => false, 'is_ambiguous' => false, 'error' => "FORMATO_INVALIDO: '{$v}'"];
                }
            } else {
                $parts = explode('.', $raw);
                if (strlen($parts[1]) === 1 || strlen($parts[1]) === 2) {
                    $normalized = $parts[0] . '.' . str_pad($parts[1], 2, '0', STR_PAD_RIGHT);
                } elseif (strlen($parts[1]) === 3) {
                    if ($convention === 'EU') {
                        $normalized = $parts[0] . $parts[1] . '.00';
                    } elseif ($convention === 'US') {
                        return [
                            'valid' => false,
                            'value' => null,
                            'float_value' => null,
                            'is_empty' => false,
                            'is_ambiguous' => true,
                            'error' => "DECIMALES_EXCESIVOS: '{$v}' tiene 3 decimales en convención US",
                        ];
                    } else {
                        return [
                            'valid' => false,
                            'value' => null,
                            'float_value' => null,
                            'is_empty' => false,
                            'is_ambiguous' => true,
                            'error' => "FORMATO_AMBIGUO: '{$v}' contiene separador de 3 dígitos sin convención definida",
                        ];
                    }
                } else {
                    return ['valid' => false, 'value' => null, 'float_value' => null, 'is_empty' => false, 'is_ambiguous' => false, 'error' => "FORMATO_INVALIDO: '{$v}'"];
                }
            }
        } else {
            $normalized = $raw . '.00';
        }

        if ($normalized === null) {
            return ['valid' => false, 'value' => null, 'float_value' => null, 'is_empty' => false, 'is_ambiguous' => false, 'error' => "FORMATO_INVALIDO: '{$v}'"];
        }

        if ($isNegative) {
            $normalized = '-' . $normalized;
        }

        // Validate MariaDB DECIMAL(12,2) limits: max 10 digits before decimal point
        $parts = explode('.', ltrim($normalized, '-'));
        $intLen = strlen($parts[0]);
        if ($intLen > 10) {
            return [
                'valid' => false,
                'value' => null,
                'float_value' => null,
                'is_empty' => false,
                'is_ambiguous' => false,
                'error' => "DESBORDAMIENTO_DECIMAL: '{$v}' excede el límite DECIMAL(12,2) de MariaDB",
            ];
        }

        $floatVal = (float) $normalized;

        return [
            'valid' => true,
            'value' => $normalized,
            'float_value' => $floatVal,
            'is_empty' => false,
            'is_ambiguous' => false,
            'error' => null,
        ];
    }

    protected function parseNumber(?string $v): ?float
    {
        $res = $this->parseFinancialAmount($v);
        return $res['valid'] ? $res['float_value'] : null;
    }
}