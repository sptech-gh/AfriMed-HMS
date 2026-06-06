<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Clinical History Model
 * 
 * Comprehensive Patient Clinical History Management
 * Aligned with GHS (Ghana Health Service), NHIS, and International Medical Standards
 * 
 * Standard Order (SOAP + International Medical Documentation):
 * 1. Chief Complaint (CC)
 * 2. History of Presenting Complaint (HPC)
 * 3. Past Medical History (PMHx)
 * 4. Past Surgical History (PSHx)
 * 5. Drug History / Current Medications
 * 6. Allergy History
 * 7. Family History (FHx)
 * 8. Social History (SHx)
 * 9. Gynae/Obstetric History (females)
 * 10. On Direct Questioning (ODQ) / Review of Systems (ROS)
 * 11. Physical Examination
 * 12. Provisional Diagnosis
 */
class Clinical_history_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /* ══════════════════════════════════════════════════════════════
     * SCHEMA MANAGEMENT
     * ══════════════════════════════════════════════════════════════ */

    private function table_exists($table_name)
    {
        $q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($table_name));
        return ($q && $q->num_rows() > 0);
    }

    private function column_exists($table_name, $column_name)
    {
        if (!$this->table_exists($table_name)) return false;
        $q = $this->db->query("SHOW COLUMNS FROM `" . $table_name . "` LIKE " . $this->db->escape($column_name));
        return ($q && $q->num_rows() > 0);
    }

    /**
     * Ensure all clinical history columns exist in patient_details_iop
     * This is a safe migration that adds missing columns without affecting existing data
     */
    public function ensure_clinical_history_schema()
    {
        if (!$this->table_exists('patient_details_iop')) {
            return false;
        }

        // New columns to add for comprehensive clinical history
        $columns = array(
            'history_presenting_complaint' => "TEXT DEFAULT NULL COMMENT 'HPC - Detailed chronological symptom description'",
            'past_surgical_history' => "TEXT DEFAULT NULL COMMENT 'PSHx - Previous surgeries and procedures'",
            'drug_history' => "TEXT DEFAULT NULL COMMENT 'Current medications before visit'",
            'gynae_obstetric_history' => "TEXT DEFAULT NULL COMMENT 'LMP, parity, pregnancies (females)'",
            'on_direct_questioning' => "TEXT DEFAULT NULL COMMENT 'ODQ/Review of Systems - systematic questioning'",
            'examination_findings' => "TEXT DEFAULT NULL COMMENT 'Physical examination findings'",
            'examination_general' => "TEXT DEFAULT NULL COMMENT 'General examination (consciousness, build, etc.)'",
            'examination_cardiovascular' => "TEXT DEFAULT NULL COMMENT 'CVS examination findings'",
            'examination_respiratory' => "TEXT DEFAULT NULL COMMENT 'Respiratory system findings'",
            'examination_gastrointestinal' => "TEXT DEFAULT NULL COMMENT 'GI/Abdominal examination'",
            'examination_neurological' => "TEXT DEFAULT NULL COMMENT 'CNS examination findings'",
            'examination_musculoskeletal' => "TEXT DEFAULT NULL COMMENT 'MSK examination findings'",
            'examination_other' => "TEXT DEFAULT NULL COMMENT 'Other system examinations'",
            'clinical_history_updated_at' => "DATETIME DEFAULT NULL",
            'clinical_history_updated_by' => "VARCHAR(25) DEFAULT NULL"
        );

        foreach ($columns as $col => $def) {
            if (!$this->column_exists('patient_details_iop', $col)) {
                $this->db->query("ALTER TABLE `patient_details_iop` ADD COLUMN `{$col}` {$def}");
            }
        }

        // Also create patient-level persistent history table
        $this->ensure_patient_history_table();

        return true;
    }

    /**
     * Create patient_clinical_history table for persistent history across visits
     * This stores the "master" history that gets auto-populated on new visits
     */
    public function ensure_patient_history_table()
    {
        if ($this->table_exists('patient_clinical_history')) {
            return true;
        }

        $sql = "CREATE TABLE `patient_clinical_history` (
            `history_id` INT(11) NOT NULL AUTO_INCREMENT,
            `patient_no` VARCHAR(25) NOT NULL,
            `past_medical_history` TEXT DEFAULT NULL,
            `past_surgical_history` TEXT DEFAULT NULL,
            `drug_history` TEXT DEFAULT NULL,
            `allergies` TEXT DEFAULT NULL,
            `family_history` TEXT DEFAULT NULL,
            `social_history` TEXT DEFAULT NULL,
            `personal_history` TEXT DEFAULT NULL,
            `gynae_obstetric_history` TEXT DEFAULT NULL,
            `warnings` TEXT DEFAULT NULL,
            `blood_group` VARCHAR(10) DEFAULT NULL,
            `chronic_conditions` TEXT DEFAULT NULL COMMENT 'JSON array of chronic conditions',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME DEFAULT NULL,
            `updated_by` VARCHAR(25) DEFAULT NULL,
            `InActive` INT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`history_id`),
            UNIQUE KEY `idx_patient_unique` (`patient_no`),
            KEY `idx_patient` (`patient_no`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Persistent patient clinical history'";

        $this->db->query($sql);

        // Create audit log table
        $this->ensure_history_audit_table();

        return true;
    }

    /**
     * Create audit log for clinical history changes
     */
    public function ensure_history_audit_table()
    {
        if ($this->table_exists('patient_clinical_history_audit')) {
            return true;
        }

        $sql = "CREATE TABLE `patient_clinical_history_audit` (
            `audit_id` BIGINT(20) NOT NULL AUTO_INCREMENT,
            `patient_no` VARCHAR(25) NOT NULL,
            `iop_id` VARCHAR(25) DEFAULT NULL,
            `field_name` VARCHAR(50) NOT NULL,
            `old_value` TEXT DEFAULT NULL,
            `new_value` TEXT DEFAULT NULL,
            `changed_by` VARCHAR(25) DEFAULT NULL,
            `changed_at` DATETIME NOT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `InActive` INT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`audit_id`),
            KEY `idx_patient` (`patient_no`),
            KEY `idx_date` (`changed_at`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Clinical history change audit trail'";

        $this->db->query($sql);
        return true;
    }

    /* ══════════════════════════════════════════════════════════════
     * PATIENT-LEVEL PERSISTENT HISTORY (Cross-Visit)
     * ══════════════════════════════════════════════════════════════ */

    /**
     * Get patient's persistent clinical history (master record)
     */
    public function get_patient_history($patient_no)
    {
        $patient_no = trim((string)$patient_no);
        if ($patient_no === '' || !$this->table_exists('patient_clinical_history')) {
            return null;
        }

        $q = $this->db->get_where('patient_clinical_history', array(
            'patient_no' => $patient_no,
            'InActive' => 0
        ), 1);

        return $q ? $q->row() : null;
    }

    /**
     * Save or update patient's persistent clinical history
     */
    public function save_patient_history($patient_no, $data, $user_id = null)
    {
        $this->ensure_patient_history_table();
        $patient_no = trim((string)$patient_no);
        if ($patient_no === '') return false;

        $existing = $this->get_patient_history($patient_no);
        $now = date('Y-m-d H:i:s');
        $ip = $this->input->ip_address();

        // Fields that can be updated
        $allowed = array(
            'past_medical_history', 'past_surgical_history', 'drug_history',
            'allergies', 'family_history', 'social_history', 'personal_history',
            'gynae_obstetric_history', 'warnings', 'blood_group', 'chronic_conditions'
        );

        $save_data = array();
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $save_data[$field] = trim((string)$data[$field]);
            }
        }

        if ($existing) {
            // Log changes for audit
            foreach ($save_data as $field => $new_val) {
                $old_val = isset($existing->$field) ? $existing->$field : '';
                if ($old_val !== $new_val) {
                    $this->log_history_change($patient_no, null, $field, $old_val, $new_val, $user_id, $ip);
                }
            }

            $save_data['updated_at'] = $now;
            $save_data['updated_by'] = $user_id;

            $this->db->where('patient_no', $patient_no);
            $this->db->where('InActive', 0);
            return $this->db->update('patient_clinical_history', $save_data);
        } else {
            $save_data['patient_no'] = $patient_no;
            $save_data['created_at'] = $now;
            $save_data['updated_at'] = $now;
            $save_data['updated_by'] = $user_id;
            $save_data['InActive'] = 0;

            // Log creation
            foreach ($save_data as $field => $new_val) {
                if (in_array($field, $allowed) && !empty($new_val)) {
                    $this->log_history_change($patient_no, null, $field, '', $new_val, $user_id, $ip);
                }
            }

            return $this->db->insert('patient_clinical_history', $save_data);
        }
    }

    /**
     * Log clinical history changes for audit
     */
    private function log_history_change($patient_no, $iop_id, $field, $old_val, $new_val, $user_id, $ip)
    {
        if (!$this->table_exists('patient_clinical_history_audit')) {
            $this->ensure_history_audit_table();
        }

        $this->db->insert('patient_clinical_history_audit', array(
            'patient_no' => $patient_no,
            'iop_id' => $iop_id,
            'field_name' => $field,
            'old_value' => $old_val,
            'new_value' => $new_val,
            'changed_by' => $user_id,
            'changed_at' => date('Y-m-d H:i:s'),
            'ip_address' => $ip,
            'InActive' => 0
        ));
    }

    /* ══════════════════════════════════════════════════════════════
     * VISIT-LEVEL HISTORY (per encounter)
     * ══════════════════════════════════════════════════════════════ */

    /**
     * Get visit-specific clinical history from patient_details_iop
     */
    public function get_visit_history($iop_id)
    {
        $iop_id = trim((string)$iop_id);
        if ($iop_id === '' || !$this->table_exists('patient_details_iop')) {
            return null;
        }

        $this->db->select("
            IO_ID,
            patient_no,
            complaints,
            provisional_diagnosis,
            allergies,
            warnings,
            social_history,
            family_history,
            personal_history,
            past_medical_history,
            history_presenting_complaint,
            past_surgical_history,
            drug_history,
            gynae_obstetric_history,
            on_direct_questioning,
            examination_findings,
            examination_general,
            examination_cardiovascular,
            examination_respiratory,
            examination_gastrointestinal,
            examination_neurological,
            examination_musculoskeletal,
            examination_other,
            clinical_history_updated_at,
            clinical_history_updated_by
        ", false);
        $this->db->where('IO_ID', $iop_id);
        $this->db->where('InActive', 0);
        $q = $this->db->get('patient_details_iop');

        return $q ? $q->row() : null;
    }

    /**
     * Save visit-specific clinical history to patient_details_iop
     * Also syncs key fields to patient-level persistent history
     */
    public function save_visit_history($iop_id, $data, $user_id = null)
    {
        $this->ensure_clinical_history_schema();
        $iop_id = trim((string)$iop_id);
        if ($iop_id === '') return false;

        // Get current visit to get patient_no and for comparison
        $current = $this->get_visit_history($iop_id);
        if (!$current) return false;

        $patient_no = $current->patient_no;
        $now = date('Y-m-d H:i:s');
        $ip = $this->input->ip_address();

        // All allowed history fields for visit
        $allowed = array(
            'allergies', 'warnings', 'social_history', 'family_history',
            'personal_history', 'past_medical_history', 'history_presenting_complaint',
            'past_surgical_history', 'drug_history', 'gynae_obstetric_history',
            'on_direct_questioning', 'examination_findings', 'examination_general',
            'examination_cardiovascular', 'examination_respiratory',
            'examination_gastrointestinal', 'examination_neurological',
            'examination_musculoskeletal', 'examination_other'
        );

        $save_data = array();
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $val = trim((string)$data[$field]);
                $save_data[$field] = $val;

                // Log changes for audit
                $old_val = isset($current->$field) ? $current->$field : '';
                if ($old_val !== $val) {
                    $this->log_history_change($patient_no, $iop_id, $field, $old_val, $val, $user_id, $ip);
                }
            }
        }

        if (empty($save_data)) return true;

        $save_data['clinical_history_updated_at'] = $now;
        $save_data['clinical_history_updated_by'] = $user_id;

        $this->db->where('IO_ID', $iop_id);
        $result = $this->db->update('patient_details_iop', $save_data);

        // Sync persistent fields to patient-level history
        $persistent_fields = array(
            'past_medical_history', 'past_surgical_history', 'drug_history',
            'allergies', 'family_history', 'social_history', 'personal_history',
            'gynae_obstetric_history', 'warnings'
        );

        $sync_data = array();
        foreach ($persistent_fields as $field) {
            if (isset($save_data[$field]) && !empty($save_data[$field])) {
                $sync_data[$field] = $save_data[$field];
            }
        }

        if (!empty($sync_data)) {
            $this->save_patient_history($patient_no, $sync_data, $user_id);
        }

        return $result;
    }

    /**
     * Auto-populate visit history from patient's persistent history
     * Called when starting a new encounter
     */
    public function populate_from_patient_history($iop_id, $patient_no = null)
    {
        $this->ensure_clinical_history_schema();
        $iop_id = trim((string)$iop_id);
        if ($iop_id === '') return false;

        // Get patient_no if not provided
        if (!$patient_no) {
            $q = $this->db->select('patient_no')->where('IO_ID', $iop_id)->get('patient_details_iop');
            $r = $q ? $q->row() : null;
            if (!$r) return false;
            $patient_no = $r->patient_no;
        }

        // Get patient's persistent history
        $history = $this->get_patient_history($patient_no);
        if (!$history) return false;

        // Check if visit already has history filled
        $visit = $this->get_visit_history($iop_id);
        if (!$visit) return false;

        // Only populate if visit fields are empty
        $fields_to_populate = array(
            'past_medical_history', 'past_surgical_history', 'drug_history',
            'allergies', 'family_history', 'social_history', 'personal_history',
            'gynae_obstetric_history', 'warnings'
        );

        $update_data = array();
        foreach ($fields_to_populate as $field) {
            $visit_val = isset($visit->$field) ? trim((string)$visit->$field) : '';
            $patient_val = isset($history->$field) ? trim((string)$history->$field) : '';

            // Only populate if visit field is empty and patient history has data
            if ($visit_val === '' && $patient_val !== '') {
                $update_data[$field] = $patient_val;
            }
        }

        if (empty($update_data)) return true;

        $this->db->where('IO_ID', $iop_id);
        return $this->db->update('patient_details_iop', $update_data);
    }

    /**
     * Get comprehensive clinical history summary for a patient
     * Aggregates data from all visits
     */
    public function get_comprehensive_summary($patient_no)
    {
        $patient_no = trim((string)$patient_no);
        if ($patient_no === '') return null;

        // Get persistent patient history
        $persistent = $this->get_patient_history($patient_no);

        // Get most recent non-empty values from visits
        $this->ensure_clinical_history_schema();

        $fields = array(
            'past_medical_history', 'past_surgical_history', 'drug_history',
            'allergies', 'family_history', 'social_history', 'personal_history',
            'gynae_obstetric_history', 'warnings', 'on_direct_questioning',
            'examination_findings'
        );

        $summary = array();

        foreach ($fields as $field) {
            // Start with persistent value
            $summary[$field] = $persistent && isset($persistent->$field) ? $persistent->$field : '';

            // If empty, try to get from most recent visit
            if (empty($summary[$field]) && $this->column_exists('patient_details_iop', $field)) {
                $q = $this->db->query(
                    "SELECT `{$field}` AS val FROM patient_details_iop
                     WHERE patient_no = ? AND InActive = 0 AND IFNULL(`{$field}`,'') <> ''
                     ORDER BY date_visit DESC, IO_ID DESC LIMIT 1",
                    array($patient_no)
                );
                $r = $q ? $q->row() : null;
                if ($r && isset($r->val)) {
                    $summary[$field] = $r->val;
                }
            }
        }

        // Get chronic conditions/diagnoses
        if ($this->table_exists('iop_diagnosis')) {
            $dq = $this->db->query(
                "SELECT DISTINCT COALESCE(B.diagnosis_name, A.diagnosis_text) AS dx_name
                 FROM iop_diagnosis A
                 LEFT JOIN diagnosis B ON B.diagnosis_id = A.diagnosis_id
                 INNER JOIN patient_details_iop P ON P.IO_ID = A.iop_id AND P.InActive = 0
                 WHERE P.patient_no = ? AND A.InActive = 0 AND COALESCE(B.diagnosis_name, A.diagnosis_text) IS NOT NULL
                 ORDER BY A.dDate DESC LIMIT 10",
                array($patient_no)
            );
            $summary['recent_diagnoses'] = $dq ? $dq->result_array() : array();
        } else {
            $summary['recent_diagnoses'] = array();
        }

        // Get recent medications
        if ($this->table_exists('iop_medication')) {
            $mq = $this->db->query(
                "SELECT COALESCE(D.drug_name, M.medicine_text) AS drug_name, M.dosage, M.dDate
                 FROM iop_medication M
                 LEFT JOIN medicine_drug_name D ON D.drug_id = M.medicine_id
                 INNER JOIN patient_details_iop P ON P.IO_ID = M.iop_id AND P.InActive = 0
                 WHERE P.patient_no = ? AND M.InActive = 0
                 ORDER BY M.dDate DESC LIMIT 10",
                array($patient_no)
            );
            $summary['recent_medications'] = $mq ? $mq->result_array() : array();
        } else {
            $summary['recent_medications'] = array();
        }

        return $summary;
    }

    /**
     * Get history change audit log for a patient
     */
    public function get_history_audit($patient_no, $limit = 50)
    {
        $patient_no = trim((string)$patient_no);
        if ($patient_no === '' || !$this->table_exists('patient_clinical_history_audit')) {
            return array();
        }

        $this->db->where('patient_no', $patient_no);
        $this->db->where('InActive', 0);
        $this->db->order_by('changed_at', 'DESC');
        $this->db->limit((int)$limit);
        $q = $this->db->get('patient_clinical_history_audit');

        return $q ? $q->result_array() : array();
    }

    /**
     * Get ODQ structured data (Review of Systems)
     * Returns structured array for systematic questioning
     */
    public function get_odq_structure()
    {
        return array(
            'general' => array(
                'label' => 'General',
                'items' => array('Fever', 'Weight loss', 'Weight gain', 'Fatigue', 'Night sweats', 'Loss of appetite')
            ),
            'cardiovascular' => array(
                'label' => 'Cardiovascular',
                'items' => array('Chest pain', 'Palpitations', 'Orthopnea', 'PND', 'Leg swelling', 'Syncope')
            ),
            'respiratory' => array(
                'label' => 'Respiratory',
                'items' => array('Cough', 'Sputum', 'Hemoptysis', 'Dyspnea', 'Wheezing', 'Chest tightness')
            ),
            'gastrointestinal' => array(
                'label' => 'Gastrointestinal',
                'items' => array('Nausea', 'Vomiting', 'Diarrhea', 'Constipation', 'Abdominal pain', 'Blood in stool', 'Jaundice')
            ),
            'genitourinary' => array(
                'label' => 'Genitourinary',
                'items' => array('Dysuria', 'Frequency', 'Urgency', 'Hematuria', 'Incontinence', 'Nocturia')
            ),
            'musculoskeletal' => array(
                'label' => 'Musculoskeletal',
                'items' => array('Joint pain', 'Joint swelling', 'Back pain', 'Muscle weakness', 'Limited movement')
            ),
            'neurological' => array(
                'label' => 'Neurological',
                'items' => array('Headache', 'Dizziness', 'Numbness', 'Tingling', 'Weakness', 'Seizures', 'Memory loss')
            ),
            'skin' => array(
                'label' => 'Skin',
                'items' => array('Rash', 'Itching', 'Skin lesions', 'Hair loss', 'Nail changes')
            ),
            'psychiatric' => array(
                'label' => 'Psychiatric',
                'items' => array('Depression', 'Anxiety', 'Sleep disturbance', 'Mood changes')
            ),
            'endocrine' => array(
                'label' => 'Endocrine',
                'items' => array('Heat intolerance', 'Cold intolerance', 'Polydipsia', 'Polyuria')
            )
        );
    }

    /**
     * Get examination structure for systematic documentation
     */
    public function get_examination_structure()
    {
        return array(
            'general' => array(
                'label' => 'General Examination',
                'fields' => array(
                    'consciousness' => 'Level of Consciousness',
                    'build' => 'Build/Nutrition',
                    'pallor' => 'Pallor',
                    'jaundice' => 'Jaundice',
                    'cyanosis' => 'Cyanosis',
                    'clubbing' => 'Clubbing',
                    'lymphadenopathy' => 'Lymphadenopathy',
                    'edema' => 'Edema',
                    'dehydration' => 'Dehydration'
                )
            ),
            'cardiovascular' => array(
                'label' => 'Cardiovascular System',
                'fields' => array(
                    'pulse' => 'Pulse Character',
                    'jvp' => 'JVP',
                    'heart_sounds' => 'Heart Sounds',
                    'murmurs' => 'Murmurs',
                    'apex_beat' => 'Apex Beat'
                )
            ),
            'respiratory' => array(
                'label' => 'Respiratory System',
                'fields' => array(
                    'chest_shape' => 'Chest Shape',
                    'trachea' => 'Trachea',
                    'air_entry' => 'Air Entry',
                    'breath_sounds' => 'Breath Sounds',
                    'added_sounds' => 'Added Sounds'
                )
            ),
            'gastrointestinal' => array(
                'label' => 'Gastrointestinal/Abdomen',
                'fields' => array(
                    'inspection' => 'Inspection',
                    'tenderness' => 'Tenderness',
                    'masses' => 'Masses',
                    'organomegaly' => 'Organomegaly',
                    'ascites' => 'Ascites',
                    'bowel_sounds' => 'Bowel Sounds'
                )
            ),
            'neurological' => array(
                'label' => 'Central Nervous System',
                'fields' => array(
                    'gcs' => 'GCS',
                    'pupils' => 'Pupils',
                    'cranial_nerves' => 'Cranial Nerves',
                    'motor' => 'Motor System',
                    'sensory' => 'Sensory System',
                    'reflexes' => 'Reflexes',
                    'cerebellar' => 'Cerebellar Signs'
                )
            ),
            'musculoskeletal' => array(
                'label' => 'Musculoskeletal',
                'fields' => array(
                    'gait' => 'Gait',
                    'spine' => 'Spine',
                    'joints' => 'Joints',
                    'rom' => 'Range of Motion'
                )
            )
        );
    }
}
