<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Clinical Safety Model - Phase 3.1 Critical Safety Enhancements
 * 
 * Implements:
 * 1. Weight-Based Pediatric Dosing
 * 2. Cumulative Daily Dose Validation
 * 3. Severity-Based Allergy Blocking
 * 4. Black-Box Warning Detection
 * 
 * @version 1.0.0
 * @since Phase 3.1
 */
class Clinical_safety_model extends CI_Model
{
    private $schema_initialized = false;

    public function __construct()
    {
        parent::__construct();
    }

    private function table_exists($table)
    {
        $q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape((string)$table));
        return ($q && $q->num_rows() > 0);
    }

    private function column_exists($table, $column)
    {
        if (!$this->table_exists($table)) return false;
        $q = $this->db->query("SHOW COLUMNS FROM `" . (string)$table . "` LIKE " . $this->db->escape((string)$column));
        return ($q && $q->num_rows() > 0);
    }

    public function ensure_phase31_schema()
    {
        if ($this->schema_initialized) return;
        $this->schema_initialized = true;

        $old_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
        if ($old_debug !== null) $this->db->db_debug = false;

        $this->add_pediatric_dosing_columns();
        $this->add_cumulative_dose_columns();
        $this->add_black_box_columns();
        $this->create_clinical_alert_logs_table();
        $this->ensure_patient_weight_columns();

        if ($old_debug !== null) $this->db->db_debug = $old_debug;
    }

    private function add_pediatric_dosing_columns()
    {
        if (!$this->table_exists('drug_dose_limits')) return;
        if (!$this->column_exists('drug_dose_limits', 'pediatric_min_mg_per_kg'))
            $this->db->query("ALTER TABLE `drug_dose_limits` ADD COLUMN `pediatric_min_mg_per_kg` DECIMAL(10,3) DEFAULT NULL");
        if (!$this->column_exists('drug_dose_limits', 'pediatric_max_mg_per_kg'))
            $this->db->query("ALTER TABLE `drug_dose_limits` ADD COLUMN `pediatric_max_mg_per_kg` DECIMAL(10,3) DEFAULT NULL");
        if (!$this->column_exists('drug_dose_limits', 'pediatric_max_daily_mg'))
            $this->db->query("ALTER TABLE `drug_dose_limits` ADD COLUMN `pediatric_max_daily_mg` DECIMAL(10,3) DEFAULT NULL");
        if (!$this->column_exists('drug_dose_limits', 'pediatric_age_limit_years'))
            $this->db->query("ALTER TABLE `drug_dose_limits` ADD COLUMN `pediatric_age_limit_years` INT DEFAULT 12");
        $this->seed_pediatric_data();
    }

    private function add_cumulative_dose_columns()
    {
        if (!$this->table_exists('medicine_drug_name')) return;
        if (!$this->column_exists('medicine_drug_name', 'max_daily_dose_mg'))
            $this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `max_daily_dose_mg` DECIMAL(10,3) DEFAULT NULL");
        $this->seed_max_daily_doses();
    }

    private function add_black_box_columns()
    {
        if (!$this->table_exists('medicine_drug_name')) return;
        if (!$this->column_exists('medicine_drug_name', 'has_black_box_warning'))
            $this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `has_black_box_warning` TINYINT(1) DEFAULT 0");
        if (!$this->column_exists('medicine_drug_name', 'black_box_description'))
            $this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `black_box_description` TEXT DEFAULT NULL");
        $this->seed_black_box_warnings();
    }

    private function create_clinical_alert_logs_table()
    {
        if ($this->table_exists('clinical_alert_logs')) return;
        $this->db->query("CREATE TABLE `clinical_alert_logs` (
            `log_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `user_id` VARCHAR(25) NOT NULL,
            `patient_no` VARCHAR(25) NOT NULL,
            `iop_id` VARCHAR(25) DEFAULT NULL,
            `drug_id` INT DEFAULT NULL,
            `drug_name` VARCHAR(255) DEFAULT NULL,
            `alert_type` ENUM('PEDIATRIC_DOSE','CUMULATIVE_DOSE','ALLERGY_BLOCK','BLACK_BOX','OTHER') NOT NULL,
            `severity` ENUM('INFO','WARNING','CRITICAL','BLOCKED') NOT NULL DEFAULT 'WARNING',
            `alert_message` TEXT NOT NULL,
            `was_overridden` TINYINT(1) DEFAULT 0,
            `override_reason` TEXT DEFAULT NULL,
            `override_approved_by` VARCHAR(25) DEFAULT NULL,
            `requires_supervisor` TINYINT(1) DEFAULT 0,
            `calculated_values` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_patient (patient_no), INDEX idx_type (alert_type), INDEX idx_date (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function ensure_patient_weight_columns()
    {
        if (!$this->table_exists('patient_personal_info')) return;
        if (!$this->column_exists('patient_personal_info', 'current_weight_kg'))
            $this->db->query("ALTER TABLE `patient_personal_info` ADD COLUMN `current_weight_kg` DECIMAL(5,2) DEFAULT NULL");
        if (!$this->column_exists('patient_personal_info', 'weight_recorded_date'))
            $this->db->query("ALTER TABLE `patient_personal_info` ADD COLUMN `weight_recorded_date` DATE DEFAULT NULL");
    }

    private function seed_pediatric_data()
    {
        $check = $this->db->query("SELECT COUNT(*) as cnt FROM drug_dose_limits WHERE pediatric_min_mg_per_kg IS NOT NULL")->row();
        if ($check && $check->cnt > 0) return;
        $drugs = array('PARACETAMOL'=>array(10,15,4000),'IBUPROFEN'=>array(5,10,2400),'AMOXICILLIN'=>array(20,40,3000));
        foreach ($drugs as $name => $vals) {
            $found = $this->db->query("SELECT drug_id FROM medicine_drug_name WHERE UPPER(drug_name) LIKE ? AND InActive=0", array('%'.$name.'%'))->result();
            foreach ($found as $d) {
                $this->db->insert('drug_dose_limits', array('drug_id'=>$d->drug_id,'age_group'=>'PEDIATRIC','pediatric_min_mg_per_kg'=>$vals[0],'pediatric_max_mg_per_kg'=>$vals[1],'pediatric_max_daily_mg'=>$vals[2],'weight_based'=>1,'is_active'=>1));
            }
        }
    }

    private function seed_max_daily_doses()
    {
        $check = $this->db->query("SELECT COUNT(*) as cnt FROM medicine_drug_name WHERE max_daily_dose_mg > 0")->row();
        if ($check && $check->cnt > 5) return;
        $doses = array('PARACETAMOL'=>4000,'IBUPROFEN'=>3200,'METFORMIN'=>2550,'TRAMADOL'=>400);
        foreach ($doses as $name => $max) {
            $this->db->query("UPDATE medicine_drug_name SET max_daily_dose_mg=? WHERE UPPER(drug_name) LIKE ? AND InActive=0 AND (max_daily_dose_mg IS NULL OR max_daily_dose_mg=0)", array($max,'%'.$name.'%'));
        }
    }

    private function seed_black_box_warnings()
    {
        $check = $this->db->query("SELECT COUNT(*) as cnt FROM medicine_drug_name WHERE has_black_box_warning=1")->row();
        if ($check && $check->cnt > 3) return;
        $warnings = array('WARFARIN'=>'Major bleeding risk. Monitor INR.','METHOTREXATE'=>'Hepatotoxicity, bone marrow suppression.','MORPHINE'=>'Respiratory depression, addiction risk.');
        foreach ($warnings as $name => $desc) {
            $this->db->query("UPDATE medicine_drug_name SET has_black_box_warning=1, black_box_description=? WHERE UPPER(drug_name) LIKE ? AND InActive=0", array($desc,'%'.$name.'%'));
        }
    }

    public function check_pediatric_dosing($drug_id, $dose, $frequency, $patient_no)
    {
        $this->ensure_phase31_schema();
        $alerts = array();
        $patient = $this->db->get_where('patient_personal_info', array('patient_no'=>$patient_no,'InActive'=>0))->row();
        if (!$patient) return $alerts;
        $age = $this->calc_age($patient);
        $limits = $this->db->query("SELECT * FROM drug_dose_limits WHERE drug_id=? AND is_active=1 AND pediatric_max_mg_per_kg IS NOT NULL LIMIT 1", array((int)$drug_id))->row();
        if (!$limits) return $alerts;
        $age_limit = isset($limits->pediatric_age_limit_years) ? (int)$limits->pediatric_age_limit_years : 12;
        if ($age >= $age_limit) return $alerts;
        $weight = isset($patient->current_weight_kg) ? (float)$patient->current_weight_kg : 0;
        if ($weight <= 0) {
            $alerts[] = (object)array('type'=>'PEDIATRIC_DOSE','severity'=>'WARNING','message'=>"PEDIATRIC: Weight required for safe dosing. Please record patient weight.",'requires_weight'=>true);
            return $alerts;
        }
        $drug = $this->db->get_where('medicine_drug_name', array('drug_id'=>$drug_id))->row();
        $drug_name = $drug ? $drug->drug_name : 'Drug';
        $dose_val = $this->parse_dose($dose);
        $max_mg_kg = (float)$limits->pediatric_max_mg_per_kg;
        $safe_max = $max_mg_kg * $weight;
        if ($dose_val > $safe_max) {
            $sev = (($dose_val - $safe_max) / $safe_max) > 0.5 ? 'BLOCKED' : 'CRITICAL';
            $alerts[] = (object)array('type'=>'PEDIATRIC_DOSE','severity'=>$sev,'message'=>"PEDIATRIC OVERDOSE: {$drug_name} {$dose_val}mg exceeds safe max {$safe_max}mg for {$weight}kg child ({$max_mg_kg}mg/kg).",'safe_max'=>$safe_max,'calculated'=>array('weight'=>$weight,'age'=>$age,'max_mg_kg'=>$max_mg_kg));
        }
        return $alerts;
    }

    public function check_cumulative_daily_dose($drug_id, $dose, $frequency, $patient_no, $iop_id)
    {
        $this->ensure_phase31_schema();
        $alerts = array();
        $drug = $this->db->get_where('medicine_drug_name', array('drug_id'=>(int)$drug_id,'InActive'=>0))->row();
        if (!$drug || !isset($drug->max_daily_dose_mg) || $drug->max_daily_dose_mg <= 0) return $alerts;
        $max_daily = (float)$drug->max_daily_dose_mg;
        $dose_val = $this->parse_dose($dose);
        $freq_mult = $this->get_freq_mult($frequency);
        $new_daily = $dose_val * $freq_mult;
        $existing = $this->get_existing_daily($drug_id, $patient_no, $iop_id);
        $total = $existing + $new_daily;
        if ($total > $max_daily) {
            $sev = (($total - $max_daily) / $max_daily) > 0.5 ? 'BLOCKED' : 'CRITICAL';
            $alerts[] = (object)array('type'=>'CUMULATIVE_DOSE','severity'=>$sev,'message'=>"CUMULATIVE OVERDOSE: Total {$drug->drug_name} {$total}mg/day exceeds max {$max_daily}mg. Existing:{$existing}mg + New:{$new_daily}mg.",'total'=>$total,'max'=>$max_daily);
        }
        return $alerts;
    }

    public function check_allergy_severity($drug_id, $patient_no)
    {
        $this->ensure_phase31_schema();
        $alerts = array();
        $drug = $this->db->get_where('medicine_drug_name', array('drug_id'=>(int)$drug_id))->row();
        if (!$drug) return $alerts;
        if (!$this->table_exists('patient_allergies')) return $alerts;
        $allergies = $this->db->query("SELECT * FROM patient_allergies WHERE patient_no=? AND is_active=1 AND InActive=0", array($patient_no))->result();
        foreach ($allergies as $a) {
            $allergen = strtoupper($a->allergen_name);
            if (stripos($drug->drug_name, $allergen) !== false || stripos($allergen, $drug->drug_name) !== false) {
                $rt = strtoupper($a->reaction_type);
                if ($rt === 'ANAPHYLAXIS') {
                    $alerts[] = (object)array('type'=>'ALLERGY_BLOCK','severity'=>'BLOCKED','message'=>"LIFE-THREATENING: Patient has ANAPHYLAXIS to {$a->allergen_name}. {$drug->drug_name} BLOCKED.",'requires_supervisor'=>true,'allow_override'=>false);
                } elseif ($rt === 'SEVERE') {
                    $alerts[] = (object)array('type'=>'ALLERGY_BLOCK','severity'=>'BLOCKED','message'=>"SEVERE ALLERGY: Patient allergic to {$a->allergen_name}. Requires supervisor override.",'requires_supervisor'=>true,'allow_override'=>true);
                } else {
                    $sev = $rt === 'MODERATE' ? 'CRITICAL' : 'WARNING';
                    $alerts[] = (object)array('type'=>'ALLERGY_BLOCK','severity'=>$sev,'message'=>"ALLERGY: Patient has {$rt} allergy to {$a->allergen_name}.",'allow_override'=>true);
                }
            }
        }
        return $alerts;
    }

    public function check_black_box_warning($drug_id)
    {
        $this->ensure_phase31_schema();
        $alerts = array();
        if (!$this->column_exists('medicine_drug_name', 'has_black_box_warning')) return $alerts;
        $drug = $this->db->get_where('medicine_drug_name', array('drug_id'=>(int)$drug_id,'InActive'=>0))->row();
        if (!$drug || !$drug->has_black_box_warning) return $alerts;
        $desc = isset($drug->black_box_description) ? $drug->black_box_description : 'Serious safety warnings.';
        $alerts[] = (object)array('type'=>'BLACK_BOX','severity'=>'CRITICAL','message'=>"BLACK BOX WARNING: {$drug->drug_name} - {$desc}",'requires_acknowledgement'=>true);
        return $alerts;
    }

    public function check_phase31_safety($drug_id, $dose, $frequency, $patient_no, $iop_id)
    {
        $this->ensure_phase31_schema();
        $all = array_merge(
            $this->check_pediatric_dosing($drug_id, $dose, $frequency, $patient_no),
            $this->check_cumulative_daily_dose($drug_id, $dose, $frequency, $patient_no, $iop_id),
            $this->check_allergy_severity($drug_id, $patient_no),
            $this->check_black_box_warning($drug_id)
        );
        usort($all, function($a,$b){ $o=array('BLOCKED'=>0,'CRITICAL'=>1,'WARNING'=>2,'INFO'=>3); return ($o[$a->severity]??4)-($o[$b->severity]??4); });
        $blocked = false; $supervisor = false; $ack = false;
        foreach ($all as $a) {
            if ($a->severity === 'BLOCKED' && (!isset($a->allow_override) || !$a->allow_override)) $blocked = true;
            if (isset($a->requires_supervisor) && $a->requires_supervisor) $supervisor = true;
            if (isset($a->requires_acknowledgement) && $a->requires_acknowledgement) $ack = true;
        }
        return array('success'=>true,'alerts'=>$all,'alert_count'=>count($all),'is_blocked'=>$blocked,'requires_supervisor'=>$supervisor,'requires_acknowledgement'=>$ack,'can_proceed'=>!$blocked);
    }

    public function log_clinical_alert($data)
    {
        $this->ensure_phase31_schema();
        if (!$this->table_exists('clinical_alert_logs')) return false;
        return $this->db->insert('clinical_alert_logs', array(
            'user_id'=>$data['user_id']??null,'patient_no'=>$data['patient_no']??null,'iop_id'=>$data['iop_id']??null,
            'drug_id'=>$data['drug_id']??null,'drug_name'=>$data['drug_name']??null,'alert_type'=>$data['alert_type']??'OTHER',
            'severity'=>$data['severity']??'WARNING','alert_message'=>$data['message']??'','was_overridden'=>($data['was_overridden']??0)?1:0,
            'override_reason'=>$data['override_reason']??null,'requires_supervisor'=>($data['requires_supervisor']??0)?1:0,
            'calculated_values'=>isset($data['calculated'])?json_encode($data['calculated']):null,
            'ip_address'=>$_SERVER['REMOTE_ADDR']??null,'created_at'=>date('Y-m-d H:i:s')
        ));
    }

    public function update_patient_weight($patient_no, $weight_kg)
    {
        $this->ensure_phase31_schema();
        if (!$this->column_exists('patient_personal_info', 'current_weight_kg')) return false;
        $this->db->where('patient_no', $patient_no);
        return $this->db->update('patient_personal_info', array('current_weight_kg'=>(float)$weight_kg,'weight_recorded_date'=>date('Y-m-d')));
    }

    private function calc_age($p)
    {
        if (isset($p->age) && $p->age > 0) return (int)$p->age;
        $dob = $p->birthday ?? $p->dob ?? null;
        if ($dob && $dob !== '0000-00-00') { try { return (new DateTime())->diff(new DateTime($dob))->y; } catch(Exception $e){} }
        return 30;
    }

    private function parse_dose($d) { preg_match('/[\d.]+/', (string)$d, $m); return isset($m[0]) ? (float)$m[0] : 0; }

    private function get_freq_mult($f)
    {
        $f = strtoupper((string)$f);
        $m = array('OD'=>1,'BD'=>2,'TDS'=>3,'QDS'=>4,'Q4H'=>6,'Q6H'=>4,'Q8H'=>3,'Q12H'=>2,'PRN'=>4,'STAT'=>1);
        foreach ($m as $k=>$v) if (strpos($f,$k)!==false) return $v;
        return 1;
    }

    private function get_existing_daily($drug_id, $patient_no, $iop_id)
    {
        $total = 0;
        $meds = $this->db->query("SELECT dosage, frequency FROM iop_medication WHERE iop_id=? AND medicine_id=? AND InActive=0", array($iop_id, $drug_id))->result();
        foreach ($meds as $m) $total += $this->parse_dose($m->dosage) * $this->get_freq_mult($m->frequency);
        return $total;
    }
}
