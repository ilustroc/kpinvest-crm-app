<?php

namespace Tests\Unit;

use App\Services\Ccd\CcdDocumentDniRecognizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CcdDocumentDniRecognizerTest extends TestCase
{
    #[DataProvider('explicitFormats')]
    public function test_it_recognizes_only_explicit_supported_identity_formats(string $text): void
    {
        $recognizer = new CcdDocumentDniRecognizer;

        $this->assertSame('dni_documental_etiquetado', $recognizer->evidence($text, '05378290'));
        $this->assertSame(['05378290'], $recognizer->extract($text));
    }

    public static function explicitFormats(): array
    {
        return [
            'D.N.I.' => ['D.N.I.: 05378290'],
            'DNI N°' => ['DNI N° 05378290'],
            'DNI Nro.' => ['DNI Nro. 05378290'],
            'Nro. de documento' => ['Nro. de documento: 05378290'],
            'Número de documento' => ['Número de documento: 05378290'],
            'Documento de identidad' => ['Documento de identidad: 05378290'],
            'Documento Nacional de Identidad' => ['Documento Nacional de Identidad: 05378290'],
            'Documento legado' => ['Documento: 05378290'],
            'Documento Nro. legado' => ['Documento Nro. 05378290'],
            'separadores y cero inicial' => ['D.N.I.: 05.378.290'],
            'saltos de línea' => ["Número de\ndocumento:\n05378290"],
        ];
    }

    public function test_it_rejects_partial_embedded_and_isolated_numbers(): void
    {
        $recognizer = new CcdDocumentDniRecognizer;

        $this->assertNull($recognizer->evidence('DNI: 5378290', '05378290'));
        $this->assertNull($recognizer->evidence('DNI: 9053782901', '05378290'));
        $this->assertNull($recognizer->evidence('Referencia interna 05378290', '05378290'));
        $this->assertSame('dni_etiqueta_documental_no_reconocida', $recognizer->missingEvidenceDiagnostic('Referencia interna 05378290', '05378290'));
        $this->assertSame('dni_presente_sin_etiqueta_verificable', $recognizer->missingEvidenceDiagnostic('Código 05378290', '05378290'));
        $this->assertSame('dni_ausente_texto_extraido', $recognizer->missingEvidenceDiagnostic('Documento sin número de identidad', '05378290'));
    }

    public function test_it_preserves_historical_bbva_and_confianza_recipient_formats(): void
    {
        $recognizer = new CcdDocumentDniRecognizer;
        $bbva = 'Estimado(a) Sr.(a): CLIENTE DE PRUEBA - 05378290';
        $confianza = 'Referencia: 00003862 Señor(a): CLIENTE DE PRUEBA';

        $this->assertSame('dni_destinatario_bbva_estimado_sr', $recognizer->evidence($bbva, '05378290'));
        $this->assertSame(['05378290'], $recognizer->extract($bbva));
        $this->assertSame('dni_destinatario_confianza_referencia_senor', $recognizer->evidence($confianza, '00003862'));
        $this->assertSame(['00003862'], $recognizer->extract($confianza));
    }

    public function test_multiple_explicit_identities_remain_visible_for_contradiction_detection(): void
    {
        $recognizer = new CcdDocumentDniRecognizer;

        $this->assertSame(
            ['05378290', '87654321'],
            $recognizer->extract("DNI: 05378290\nDocumento de identidad: 87654321"),
        );
    }

    #[DataProvider('validAqpCustomerCodeHeaders')]
    public function test_it_recognizes_contextual_aqp_customer_code_headers(string $text, string $cosecha): void
    {
        $recognizer = new CcdDocumentDniRecognizer;

        $this->assertSame(
            CcdDocumentDniRecognizer::AQP_CODE_VERIFIED,
            $recognizer->aqpCustomerCodeEvidence($text, '00123456', $cosecha),
        );
    }

    public static function validAqpCustomerCodeHeaders(): array
    {
        return [
            'AQP4 con acento' => ["CÓDIGO DEL CLIENTE: 00123456\n\nSEÑOR (A):\nCLIENTE", 'AQP4'],
            'AQP5 sin acento y espacios' => ["CODIGO   DEL CLIENTE :\n00123456\nSEÑOR(A): CLIENTE", 'AQP5'],
            'AQP4 con cero separado' => ["CÓDIGO DEL CLIENTE: 00-123-456\nSEÑOR ( A ): CLIENTE", ' aqp4 '],
        ];
    }

    public function test_it_rejects_unsafe_or_contradictory_aqp_customer_codes(): void
    {
        $recognizer = new CcdDocumentDniRecognizer;
        $recipient = static fn (string $code): string => "CÓDIGO DEL CLIENTE: {$code}\nSEÑOR (A): CLIENTE";

        $this->assertSame(
            CcdDocumentDniRecognizer::AQP_CODE_MISMATCH,
            $recognizer->aqpCustomerCodeEvidence($recipient('87654321'), '00123456', 'AQP4'),
        );
        $this->assertSame(
            CcdDocumentDniRecognizer::AQP_CODE_MISMATCH,
            $recognizer->aqpCustomerCodeEvidence($recipient('0123456'), '00123456', 'AQP4'),
        );
        $this->assertSame(
            CcdDocumentDniRecognizer::AQP_CODE_MISMATCH,
            $recognizer->aqpCustomerCodeEvidence($recipient('9001234561'), '00123456', 'AQP4'),
        );

        $multiple = $recipient('00123456')."\n".$recipient('87654321');
        $this->assertSame(
            CcdDocumentDniRecognizer::AQP_CODE_CONTRADICTORY,
            $recognizer->aqpCustomerCodeEvidence($multiple, '00123456', 'AQP5'),
        );
    }

    public function test_aqp_customer_code_requires_recipient_context_and_exact_harvest(): void
    {
        $recognizer = new CcdDocumentDniRecognizer;
        $withoutRecipient = 'Texto arbitrario CÓDIGO DEL CLIENTE: 00123456 dentro del cuerpo.';
        $validHeader = "CÓDIGO DEL CLIENTE: 00123456\nSEÑOR (A): CLIENTE";

        $this->assertSame(
            CcdDocumentDniRecognizer::AQP_CODE_INSUFFICIENT_CONTEXT,
            $recognizer->aqpCustomerCodeEvidence($withoutRecipient, '00123456', 'AQP4'),
        );
        $this->assertNull($recognizer->aqpCustomerCodeEvidence($validHeader, '00123456', 'AQP3'));
        $this->assertNull($recognizer->aqpCustomerCodeEvidence($validHeader, '00123456', 'BBVA1'));
    }
}
