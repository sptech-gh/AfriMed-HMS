<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../application/helpers/nhis_formatter_helper.php';
require_once __DIR__ . '/../application/services/NHIS/NHISValidationException.php';
require_once __DIR__ . '/../application/services/NHIS/NHISXMLSchemaException.php';
require_once __DIR__ . '/../application/services/NHIS/NHISClaimXMLGenerator.php';

class GeneratorWithFixture extends NHISClaimXMLGenerator
{
    private array $fixture;

    public function __construct(array $fixture)
    {
        // Do NOT call parent::__construct() to avoid get_instance()/DB.
        $this->fixture = $fixture;
        $this->skipPreValidation = true;
    }

    protected function loadContext(int $batch_id): array
    {
        return $this->fixture;
    }
}

class NHISClaimXMLGeneratorTest extends TestCase
{
    private function makeValidBatchFixture(): array
    {
        return [
            'batch' => [
                'id'            => 1,
                'batch_number'  => 'BATCH-2026-04-001',
                'service_year'  => '2026',
                'service_month' => '04',
                'creation_date' => '2026-04-22',
                'batch_currency'=> 'GHS',
            ],
            'facility_credentials' => [
                'provider_accreditation_number' => '03-05-12437',
                'eclaim_authorization_number'   => 12345,
            ],
            'version_info' => [
                'XMLFormatVersion' => [
                    'component'     => 'XMLFormatVersion',
                    'version_value' => '1.0',
                    'is_current'    => 1,
                ],
                'MedicineVersion'  => [
                    'component'     => 'MedicineVersion',
                    'version_value' => 'May 2025',
                    'is_current'    => 1,
                ],
                'GDRGVersion'      => [
                    'component'     => 'GDRGVersion',
                    'version_value' => 'Feb 2023',
                    'is_current'    => 1,
                ],
                'TariffVersion'    => [
                    'component'     => 'TariffVersion',
                    'version_value' => 'Feb 2023',
                    'is_current'    => 1,
                ],
                'ICDVersion'       => [
                    'component'     => 'ICDVersion',
                    'version_value' => 'Dec 2022',
                    'is_current'    => 1,
                ],
            ],
            'patients' => [
                'P001' => [
                    'patient_no'                => 'P001',
                    'lastname'                  => 'Agyemang',
                    'firstname'                 => 'Kofi',
                    'middlename'                => '',
                    'birthday'                  => '1985-06-15',
                    'gender'                    => 'M',
                    'nhis_member_number'        => '12345678',
                    'nhis_temporary_card_number'=> '',
                    'nhis_card_serial_number'   => 'WRSAB',
                    'is_infant'                 => 0,
                ],
            ],
            'claims' => [
                123 => [
                    'id'                   => 123,
                    'patient_no'           => 'P001',
                    'claim_number'         => 'CLM-2026-04-0001',
                    'service_type'         => 'OUT',
                    'pharmacy_included'    => 1,
                    'all_inclusive'        => 1,
                    'outcome_type'         => 'DIS',
                    'admission_type'       => 'CRO',
                    'speciality_code'      => 'OPDC',
                    'admission_date'       => '2026-04-01',
                    'discharge_date'       => null,
                    'duration_length'      => null,
                    'out_patient_code'     => 'AB123',
                    'in_patient_code'      => null,
                    'investigation_code'   => null,
                    'out_patient_tariff'   => 10.50,
                    'in_patient_tariff'    => null,
                    'referral_number'      => null,
                    'treatments' => [
                        [
                            'treatment_date' => '2026-04-01',
                            'treatment_type' => 'Diagnosis',
                            'gdrg_code'      => 'OPDC06A',
                            'icd10_code'     => 'J00',
                            'tariff_amount'  => 10.50,
                        ],
                    ],
                    'medicines' => [
                        [
                            'nhis_medicine_code' => 'PARAC500MG',
                            'quantity'           => 12.00,
                            'unit_price'         => 0.42,
                            'medicine_total'     => 5.04,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_generate_produces_xsd_valid_xml()
    {
        $fixture   = $this->makeValidBatchFixture();
        $generator = new GeneratorWithFixture($fixture);
        $xml       = $generator->generate(1);

        $this->assertIsString($xml);
        $this->assertNotEmpty($xml);

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $xsdPath = __DIR__ . '/_support/nhia_claims.xsd';
        libxml_use_internal_errors(true);
        $valid  = $dom->schemaValidate($xsdPath);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        $this->assertTrue(
            $valid,
            'Generated XML failed XSD validation: ' .
            implode('; ', array_map(fn($e) => trim($e->message), $errors))
        );
    }
}
