<?php

namespace App\Services\Ccd;

use Smalot\PdfParser\Parser;
use Throwable;

class CcdPdfIdentifierExtractor
{
    private CcdDocumentDniRecognizer $dniRecognizer;

    public function __construct(
        ?CcdDocumentDniRecognizer $dniRecognizer = null,
    ) {
        $this->dniRecognizer = $dniRecognizer ?? new CcdDocumentDniRecognizer;
    }

    /** @return array{status:string,text_length:int,dnis:array<int,string>,cuentas:array<int,string>,operaciones:array<int,string>,entidades:array<int,string>,text:string,evidence:string} */
    public function extract(string $path): array
    {
        try {
            $text = (new Parser)->parseFile($path)->getText();
        } catch (Throwable) {
            return $this->failed('error', 'pdf_ilegible');
        }

        $text = trim((string) $text);

        if ($text === '') {
            return $this->failed('sin_texto', 'sin_texto');
        }

        return [
            'status' => 'ok',
            'text_length' => mb_strlen($text),
            'dnis' => $this->dniRecognizer->extract($text),
            'cuentas' => $this->matches($text, [
                '/\b(?:N[ÚU]MERO\s+DE\s+CUENTA|NRO\.?\s*CUENTA|CUENTA(?:\s+(?:DE\s+)?(?:CR[ÉE]DITO|DEUDA))?)\s*[:#º°\-]?\s*([A-Z0-9][A-Z0-9 -]{2,49})\b/ui',
            ]),
            'operaciones' => $this->operationMatches($text),
            'entidades' => $this->matches($text, [
                '/\b(?:ENTIDAD(?:\s+FINANCIERA)?|BANCO|CAJA)\s*[:#º°\-]?\s*([A-ZÁÉÍÓÚÑ0-9][A-ZÁÉÍÓÚÑ0-9 .&-]{2,79})\b/ui',
            ]),
            'text' => $text,
            'evidence' => 'texto_pdf_extraible',
        ];
    }

    /** @param array<int,string> $patterns @return array<int,string> */
    private function matches(string $text, array $patterns): array
    {
        $matches = [];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $found);

            foreach ($found[1] ?? [] as $value) {
                $value = preg_replace('/\s+/', '', strtoupper(trim((string) $value)));

                if ($value !== null && $value !== '') {
                    $matches[] = $value;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    /** @return array<int,string> */
    private function operationMatches(string $text): array
    {
        $operations = $this->matches($text, [
            '/\b(?:N[ÚU]MERO\s+DE\s+OPERACI[ÓO]N|NRO\.?\s*OPERACI[ÓO]N|OPERACI[ÓO]N|OP\.)\s*[:#º°\-]?\s*([A-Z0-9][A-Z0-9 -]{2,49})\b/ui',
        ]);

        // Algunos formatos concatenan una referencia numérica y una palabra,
        // por ejemplo "6878399DEUDAACTUAL". Se extrae sólo la secuencia
        // numérica explícita, sin unir fragmentos separados del documento.
        preg_match_all('/(?<![0-9])([0-9]{6,})(?=[A-ZÁÉÍÓÚÑ])/u', $text, $found);

        foreach ($found[1] ?? [] as $reference) {
            $operations[] = $reference;
        }

        return array_values(array_unique($operations));
    }

    /** @return array{status:string,text_length:int,dnis:array<int,string>,cuentas:array<int,string>,operaciones:array<int,string>,entidades:array<int,string>,text:string,evidence:string} */
    private function failed(string $status, ?string $evidence = null): array
    {
        return [
            'status' => $status,
            'text_length' => 0,
            'dnis' => [],
            'cuentas' => [],
            'operaciones' => [],
            'entidades' => [],
            'text' => '',
            'evidence' => $evidence ?? $status,
        ];
    }
}
