<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../application/services/NHIS/NHISClaimPreValidator.php';

/**
 * Fixture-backed subclass for tests.
 * Does NOT call parent::__construct(), so no CI get_instance/db.
 */
class ValidatorWithFixture extends NHISClaimPreValidator
{
    /** @var array */
    private $fixture;

    public function __construct(array $fixture)
    {
        $this->fixture = $fixture;
    }

    protected function loadContext(int $batch_id): array
    {
        return $this->fixture;
    }

    protected function validateTreatmentReference(array $treatment, array $ctx, string $ref): array
    {
        // In tests we bypass DB-based reference validation.
        return [];
    }
}

class NHISClaimPreValidatorTest extends TestCase
{
    /** Original smoke test: invalid batch id. */
    public function test_validate_with_invalid_batch_id_yields_error()
    {
        $validator = new NHISClaimPreValidator();
        $result    = $validator->validate(0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('is_valid', $result);
        $this->assertArrayHasKey('can_export', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertArrayHasKey('summary', $result);

        $this->assertFalse($result['is_valid']);
        $this->assertFalse($result['can_export']);
        $this->assertNotEmpty($result['errors']);
    }

    // ------------------------------------------------------------------
    // Base happy-path fixture (all checks passing)
    // ------------------------------------------------------------------

    private function makeBaseFixture(): array
    {
        return [
            'batch' => [
                'id'            => 1,
                'service_year'  => '2026',
                'service_month' => '04',
                'claims_count'  => 1,
                'batch_amount'  => 100.00,
            ],
            'batch_claims' => [
                [
                    'batch_id'   => 1,
                    'claim_id'   => 123,
                    'patient_no' => 'P001',
                    'total_cost' => 100.00,
                ],
            ],
            'claims' => [
                123 => [
                    'id'              => 123,
                    'patient_no'      => 'P001',
                    'service_type'    => 'OUT',
                    'outcome_type'    => 'DIS',
                    'admission_type'  => 'CRO',
                    'speciality_code' => 'SP01',
                    'admission_date'  => '2026-04-01',
                    'discharge_date'  => '2026-04-01',
                    'duration_length' => 1,
                    'out_patient_code'=> 'ABCDE',
                    'total_cost'      => 100.00,
                    'treatments_count'=> 0,
                    'medicines_count' => 1,
                    'treatments'      => [],
                    'medicines'       => [
                        [
                            'medicine_code'  => 'MED1',
                            'quantity'       => 1,
                            'unit_price'     => 100.00,
                            'total'          => 100.00,
                            'dispensed_date' => '2026-04-01',
                        ],
                    ],
                ],
            ],
            'patients' => [
                'P001' => [
                    'patient_no'                => 'P001',
                    'surname'                   => 'Doe',
                    'other_name'                => 'John',
                    'date_of_birth'             => '1990-01-01',
                    'gender'                    => 'M',
                    'nhis_member_number'        => 'NHIS123',
                    'nhis_temporary_card_number'=> '',
                ],
            ],
            'facility_credentials' => [
                'id'                          => 1,
                'is_active'                   => 1,
                'provider_accreditation_number' => '03-05-12437',
                'eclaim_authorization_number' => '12345',
            ],
            'version_info' => [
                'XMLFormatVersion' => [
                    'component'     => 'XMLFormatVersion',
                    'version_value' => '1.0',
                    'is_current'    => 1,
                ],
            ],
            'speciality_codes' => [
                'SP01' => [
                    'code'      => 'SP01',
                    'is_active' => 1,
                ],
            ],
            // In production these are loaded from DB; in tests we supply them.
            'ref_medicines' => [
                'MED1' => [
                    'code'               => 'MED1',
                    'is_active'          => 1,
                    'requires_lab_result'=> 0,
                ],
            ],
            'ref_gdrg'               => [],
            'ref_icd10'              => [],
            'gdrg_icd_mapping_count' => 1,
        ];
    }

    // Small helper to inspect errors list
    private function hasErrorField(array $errors, string $field, string $level = null, string $severity = null): bool
    {
        foreach ($errors as $e) {
            if ($e['field'] !== $field) {
                continue;
            }
            if ($level !== null && $e['level'] !== $level) {
                continue;
            }
            if ($severity !== null && $e['severity'] !== $severity) {
                continue;
            }
            return true;
        }
        return false;
    }

    private function hasWarningField(array $warnings, string $field): bool
    {
        foreach ($warnings as $w) {
            if ($w['field'] === $field) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------------
    // Scenario 1: Empty nhis_facility_credentials → batch error
    // ------------------------------------------------------------------

    public function test_missing_facility_credentials_adds_batch_error()
    {
        $fixture = $this->makeBaseFixture();
        $fixture['facility_credentials'] = null;

        $validator = new ValidatorWithFixture($fixture);
        $result    = $validator->validate(1);

        $this->assertFalse($result['is_valid']);
        $this->assertFalse($result['can_export']);
        $this->assertTrue(
            $this->hasErrorField($result['errors'], 'FacilityCredentials', 'batch', 'error')
        );
    }

    // ------------------------------------------------------------------
    // Scenario 2: ServiceType = OUT with no OutPatientCode
    // ------------------------------------------------------------------

    public function test_out_claim_without_outpatient_code_is_claim_error()
    {
        $fixture = $this->makeBaseFixture();
        $fixture['claims'][123]['service_type']      = 'OUT';
        $fixture['claims'][123]['out_patient_code']  = ''; // missing

        $validator = new ValidatorWithFixture($fixture);
        $result    = $validator->validate(1);

        $this->assertFalse($result['is_valid']);
        $this->assertTrue(
            $this->hasErrorField($result['errors'], 'OutPatientCode', 'claim', 'error')
        );
    }

    // ------------------------------------------------------------------
    // Scenario 3: AdmissionType missing on OUT claim
    // ------------------------------------------------------------------

    public function test_out_claim_without_admission_type_is_claim_error()
    {
        $fixture = $this->makeBaseFixture();
        $fixture['claims'][123]['service_type']     = 'OUT';
        $fixture['claims'][123]['admission_type']   = ''; // invalid

        $validator = new ValidatorWithFixture($fixture);
        $result    = $validator->validate(1);

        $this->assertFalse($result['is_valid']);
        $this->assertTrue(
            $this->hasErrorField($result['errors'], 'AdmissionType', 'claim', 'error')
        );
    }

    // ------------------------------------------------------------------
    // Scenario 4: TotalCost mismatch
    // ------------------------------------------------------------------

    public function test_total_cost_mismatch_adds_claim_error()
    {
        $fixture = $this->makeBaseFixture();
        // Claim total does not equal items sum
        $fixture['claims'][123]['total_cost']  = 200.00;
        $fixture['batch']['batch_amount']      = 200.00;
        $fixture['batch_claims'][0]['total_cost'] = 200.00;

        $validator = new ValidatorWithFixture($fixture);
        $result    = $validator->validate(1);

        $this->assertFalse($result['is_valid']);
        $this->assertTrue(
            $this->hasErrorField($result['errors'], 'TotalCost', 'claim', 'error')
        );
    }

    // ------------------------------------------------------------------
    // Scenario 5: Empty nhis_gdrg_icd_mappings → WARNING not error
    // ------------------------------------------------------------------

    public function test_empty_gdrg_icd_mapping_table_produces_warning_not_error()
    {
        $fixture = $this->makeBaseFixture();
        $fixture['gdrg_icd_mapping_count'] = 0;

        // Add one diagnosis-type treatment fully inside dates
        $fixture['claims'][123]['treatments_count'] = 1;
        $fixture['claims'][123]['treatments'] = [
            [
                'type'          => 'diagnosis',
                'gdrg_code'     => 'G001',
                'icd_code'      => 'J45',
                'treatment_date'=> '2026-04-01',
            ],
        ];
        // No medicines for this scenario
        $fixture['claims'][123]['medicines_count'] = 0;
        $fixture['claims'][123]['medicines']       = [];
        $fixture['claims'][123]['total_cost']      = 100.00;

        $validator = new ValidatorWithFixture($fixture);
        $result    = $validator->validate(1);

        // No errors strictly from empty mapping; base fixture passes others
        $this->assertTrue($result['is_valid']);
        $this->assertTrue($result['can_export']);
        $this->assertTrue(
            $this->hasWarningField($result['warnings'], 'GDRG-ICD pairing')
        );
    }

    // ------------------------------------------------------------------
    // Scenario 6: requires_lab_result medicine → warning + attachment
    // ------------------------------------------------------------------

    public function test_requires_lab_result_medicine_adds_warning_and_attachment()
    {
        $fixture = $this->makeBaseFixture();
        // Mark medicine as requires_lab_result
        $fixture['ref_medicines']['MED1']['requires_lab_result'] = 1;

        $validator = new ValidatorWithFixture($fixture);
        $result    = $validator->validate(1);

        $this->assertTrue($result['is_valid']);
        $this->assertTrue($result['can_export']);
        $this->assertTrue(
            $this->hasWarningField($result['warnings'], 'requiresLabResult')
        );
        $this->assertContains('claim:123', $result['summary']['claims_requiring_attachments']);
    }
}