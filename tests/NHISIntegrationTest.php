<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/NHISClaimPreValidatorTest.php';
require_once __DIR__ . '/NHISClaimXMLGeneratorTest.php';

class NHISIntegrationTest extends TestCase
{
    public function test_valid_batch_passes_prevalidation_and_produces_xsd_valid_xml()
    {
        $fixture = $this->makeIntegrationFixture();

        // Step A: Pre-validator confirms the batch is valid
        $preValidator = new ValidatorWithFixture($fixture);
        $validation   = $preValidator->validate(1);

        $this->assertTrue(
            $validation['is_valid'],
            'Pre-validator found errors: ' .
            json_encode(array_map(fn($e) => $e['message'], $validation['errors']))
        );
        $this->assertTrue($validation['can_export']);
        $this->assertSame(0, $validation['summary']['error_count']);

        // Step B: Generator produces XML from the same fixture
        $generator = new GeneratorWithFixture($fixture);
        $xml       = $generator->generate(1);

        $this->assertIsString($xml);
        $this->assertNotEmpty($xml);

        // Step C: XML passes the official NHIA XSD
        $dom = new DOMDocument();
        $loaded = $dom->loadXML($xml);
        $this->assertTrue($loaded, 'DOMDocument could not parse the generated XML');

        $xsdPath = __DIR__ . '/_support/nhia_claims.xsd';
        libxml_use_internal_errors(true);
        $valid  = $dom->schemaValidate($xsdPath);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        $this->assertTrue(
            $valid,
            'XSD validation failed: ' .
            implode('; ', array_map(fn($e) => trim($e->message), $errors))
        );

        // Step D: Spot-check key values in the generated XML
        $xpath = new DOMXPath($dom);
        $this->assertSame(
            '03-05-12437',
            $xpath->evaluate('string(//ProviderAccreditationNumber)')
        );
        $this->assertSame(
            '2026',
            $xpath->evaluate('string(//ServiceYear)')
        );
        $this->assertSame(
            'OUT',
            $xpath->evaluate('string(//ServiceType)')
        );
        $this->assertSame(
            'AGYEMANG',
            $xpath->evaluate('string(//Surname)')
        );
        $this->assertSame(
            '15/06/1985',
            $xpath->evaluate('string(//DateOfBirth)')
        );
        $this->assertSame(
            'CRO',
            $xpath->evaluate('string(//AdmissionType)')
        );
        $this->assertSame(
            '01/04/2026',
            $xpath->evaluate('string(//AdmissionDate)')
        );
        $this->assertSame(
            'Yes',
            $xpath->evaluate('string(//PharmacyIncluded)')
        );
    }

    private function makeIntegrationFixture(): array
    {
        // Start from the generator's valid batch fixture so XML/XSD expectations match.
        $fixture = [
            'batch' => [
                'id'            => 1,
                'batch_number'  => 'BATCH-2026-04-001',
                'service_year'  => '2026',
                'service_month' => '04',
                'creation_date' => '2026-04-22',
                'batch_currency'=> 'GHS',
                // For the validator summary we also need counts/amounts; these will
                // be recomputed internally, but we can supply consistent values.
                'claims_count'  => 1,
                'batch_amount'  => 15.54,
            ],
            'batch_claims' => [
                [
                    'batch_id'   => 1,
                    'claim_id'   => 123,
                    'patient_no' => 'P001',
                    'total_cost' => 15.54,
                ],
            ],
            'facility_credentials' => [
                'id'                            => 1,
                'is_active'                     => 1,
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
                    // Pre-validator fixture uses surname/other_name; generator uses
                    // lastname/firstname. We provide both so both services are happy.
                    'surname'                   => 'Agyemang',
                    'other_name'                => 'Kofi',
                    'lastname'                  => 'Agyemang',
                    'firstname'                 => 'Kofi',
                    'middlename'                => '',
                    'date_of_birth'             => '1985-06-15',
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
                    'treatments_count'     => 1,
                    'medicines_count'      => 1,
                    'total_cost'           => 15.54,
                    'treatments' => [
                        [
                            // Map to validator's expectations as well
                            'type'           => 'diagnosis',
                            'treatment_type' => 'Diagnosis',
                            'treatment_date' => '2026-04-01',
                            'gdrg_code'      => 'OPDC06A',
                            'icd_code'       => 'J00',
                            'icd10_code'     => 'J00',
                            'tariff_amount'  => 10.50,
                        ],
                    ],
                    'medicines' => [
                        [
                            'medicine_code'       => 'PARAC500MG',
                            'nhis_medicine_code'  => 'PARAC500MG',
                            'quantity'            => 12.00,
                            'unit_price'          => 0.42,
                            'total'               => 5.04,
                            'medicine_total'      => 5.04,
                            'dispensed_date'      => '2026-04-01',
                        ],
                    ],
                ],
            ],
            'speciality_codes' => [
                'OPDC' => [
                    'code'      => 'OPDC',
                    'is_active' => 1,
                ],
            ],
            'ref_medicines' => [
                'PARAC500MG' => [
                    'code'               => 'PARAC500MG',
                    'is_active'          => 1,
                    'requires_lab_result'=> 0,
                ],
            ],
            'ref_gdrg'               => [],
            'ref_icd10'              => [],
            'gdrg_icd_mapping_count' => 1,
        ];

        return $fixture;
    }
}
