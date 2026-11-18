<?php

namespace App\Services\Ccd;

class CcdDocumentDniRecognizer
{
    private const DNI_LABEL = 'D\s*\.?\s*N\s*\.?\s*I\s*\.?';

    private const DNI_NUMBER_QUALIFIER = '(?:N\s*[º°]|NRO\.?|N[ÚU]MERO)';

    private const DOCUMENT_LABEL = '(?:(?:NRO\.?|N[ÚU]MERO|N\s*[º°])\s+DE\s+DOCUMENTO|DOCUMENTO\s+(?:DE\s+IDENTIDAD|NACIONAL\s+DE\s+IDENTIDAD))';

    private const LEGACY_DOCUMENT_LABEL = 'DOC(?:UMENTO)?\.?\s*(?:'.self::DNI_NUMBER_QUALIFIER.')?';

    /** @return array<int,string> */
    public function extract(string $text): array
    {
        $value = $this->capturedValuePattern();
        $patterns = [
            $this->explicitIdentityPattern($value),
            '/\bESTIMADO\s*(?:\(\s*A\s*\)|A)?\s+(?:SR\.?\s*(?:\(\s*A\s*\)|A)?|SEÑOR\s*(?:\(\s*A\s*\)|A)?)\s*:?[^0-9\r\n]{0,140}?(?<![0-9])'.$value.'(?![0-9])/iu',
            '/\bREFERENCIA\s*:\s*(?<![0-9])'.$value.'(?![0-9])\s+(?:SEÑOR\s*(?:\(\s*A\s*\)|A)?|SR\.?\s*(?:\(\s*A\s*\)|A)?|SRA\.?)\s*:?/iu',
        ];
        $documents = [];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);

            foreach ($matches[1] ?? [] as $match) {
                $normalized = $this->normalize((string) $match);

                if ($normalized !== null) {
                    $documents[] = $normalized;
                }
            }
        }

        return array_values(array_unique($documents));
    }

    public function evidence(string $text, string $dni): ?string
    {
        $digits = $this->normalize($dni);

        if ($digits === null) {
            return null;
        }

        $bounded = $this->boundedExpectedValue($digits);

        if (preg_match($this->explicitIdentityPattern($bounded), $text) === 1) {
            return 'dni_documental_etiquetado';
        }

        $bbvaRecipient = '/\bESTIMADO\s*(?:\(\s*A\s*\)|A)?\s+(?:SR\.?\s*(?:\(\s*A\s*\)|A)?|SEÑOR\s*(?:\(\s*A\s*\)|A)?)\s*:?[^0-9\r\n]{0,140}?'.$bounded.'(?=[^0-9]|$)/iu';

        if (preg_match($bbvaRecipient, $text) === 1) {
            return 'dni_destinatario_bbva_estimado_sr';
        }

        $confianzaRecipient = '/\bREFERENCIA\s*:\s*'.$bounded.'\s+(?:SEÑOR\s*(?:\(\s*A\s*\)|A)?|SR\.?\s*(?:\(\s*A\s*\)|A)?|SRA\.?)\s*:?/iu';

        return preg_match($confianzaRecipient, $text) === 1
            ? 'dni_destinatario_confianza_referencia_senor'
            : null;
    }

    public function missingEvidenceDiagnostic(string $text, string $dni): string
    {
        $digits = $this->normalize($dni);

        if ($digits === null) {
            return 'dni_esperado_invalido';
        }

        $bounded = $this->boundedExpectedValue($digits);

        if (preg_match('/'.$bounded.'/u', $text) !== 1) {
            return 'dni_ausente_texto_extraido';
        }

        $identityVocabulary = '(?:'.self::DNI_LABEL.'|DOCUMENTO|IDENTIDAD|NACIONAL|NRO\.?|N[ÚU]MERO|REFERENCIA|IDENTIFICACI[ÓO]N|C[ÉE]DULA)';

        if (preg_match('/'.$identityVocabulary.'[^0-9\r\n]{0,80}?'.$bounded.'/iu', $text) === 1) {
            return 'dni_etiqueta_documental_no_reconocida';
        }

        return 'dni_presente_sin_etiqueta_verificable';
    }

    private function explicitIdentityPattern(string $value): string
    {
        $dniLabel = self::DNI_LABEL.'\s*(?:'.self::DNI_NUMBER_QUALIFIER.')?';

        return '/(?<![\pL0-9])(?:'.$dniLabel.'|'.self::DOCUMENT_LABEL.'|'.self::LEGACY_DOCUMENT_LABEL.')\s*(?:[:#\-]\s*)?'.$value.'/iu';
    }

    private function capturedValuePattern(): string
    {
        return '((?<![0-9])[0-9](?:[ \t.\-]?[0-9]){5,14}(?![0-9]))';
    }

    private function boundedExpectedValue(string $digits): string
    {
        return '(?<![0-9])'.implode('[ \t.\-]?', str_split($digits)).'(?![0-9])';
    }

    private function normalize(string $value): ?string
    {
        $digits = preg_replace('/[ \t.\-]+/', '', trim($value));

        return $digits !== null && preg_match('/^[0-9]{6,15}$/D', $digits) === 1
            ? $digits
            : null;
    }
}
