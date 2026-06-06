# Phase 3: Doctor Module Analysis & Clinical Intelligence Report

**Hebrew Medical Center HMS**  
**Date:** April 6, 2026  
**Status:** Analysis Complete - Implementation Ready

---

## Executive Summary

This report presents a comprehensive analysis of the Doctor Module in the Hebrew Medical Center HMS. The audit identifies architecture gaps, missing clinical safety features, and provides a detailed implementation plan for Clinical Intelligence capabilities.

---

## 1. Current Architecture Analysis

### 1.1 Controllers Analyzed

| Controller | Lines | Purpose | Status |
|------------|-------|---------|--------|
| `opd.php` | 2,212 | OPD consultation workflow | ✅ Well-structured |
| `doctor.php` | 214 | Doctor dashboard | ⚠️ Basic - needs enhancement |
| `ipd.php` | 57,334 | IPD workflow | ✅ Comprehensive |

### 1.2 Models Analyzed

| Model | Lines | Purpose | Status |
|-------|-------|---------|--------|
| `opd_model.php` | 1,938 | OPD data operations | ✅ Good foundation |
| `doctor_model.php` | 218 | Doctor queries | ⚠️ Minimal - needs expansion |
| `clinical_workflow_model.php` | 536 | Clinical workflow | ✅ Good structure |
| `encounter_owner_model.php` | 253 | Encounter ownership | ✅ Audit-ready |

### 1.3 Key Tables Identified

| Table | Purpose | Status |
|-------|---------|--------|
| `patient_details_iop` | Visit records | ✅ Exists |
| `iop_medication` | Prescriptions | ✅ Exists |
| `iop_diagnosis` | Diagnoses | ✅ Exists |
| `iop_laboratory` | Lab orders | ✅ Exists |
| `iop_opd_workflow` | Workflow status | ✅ Exists |
| `iop_encounter_owner` | Doctor ownership | ✅ Exists |
| `opd_status_audit` | Status audit trail | ✅ Exists |

---

## 2. Architecture Gaps Identified

### 2.1 Critical Gaps (Patient Safety)

| Gap ID | Description | Risk Level | Impact |
|--------|-------------|------------|--------|
| GAP-001 | **No Drug Interaction Checking** | 🔴 HIGH | Dangerous drug combinations not detected |
| GAP-002 | **No Allergy Detection System** | 🔴 HIGH | Patient allergies not validated against prescriptions |
| GAP-003 | **No Duplicate Therapy Detection** | 🔴 HIGH | Same-class drugs can be prescribed multiple times |
| GAP-004 | **No Dose Validation** | 🔴 HIGH | No min/max dose checking |
| GAP-005 | **No High-Risk Drug Alerts** | 🔴 HIGH | Narcotics/controlled drugs not flagged |

### 2.2 Workflow Gaps

| Gap ID | Description | Risk Level | Impact |
|--------|-------------|------------|--------|
| GAP-006 | **No Consultation Locking** | 🟡 MEDIUM | Multiple doctors can edit same consultation |
| GAP-007 | **No Clinical Notes Versioning** | 🟡 MEDIUM | Notes can be overwritten without history |
| GAP-008 | **No Primary Diagnosis Enforcement** | 🟡 MEDIUM | NHIS claims may be rejected |
| GAP-009 | **Incomplete Prescription Validation** | 🟡 MEDIUM | Missing dose/frequency allowed |

### 2.3 Data Integrity Gaps

| Gap ID | Description | Risk Level | Impact |
|--------|-------------|------------|--------|
| GAP-010 | **No Diagnosis-Prescription Linkage** | 🟡 MEDIUM | NHIS claim validation issues |
| GAP-011 | **Missing Patient Risk Flags** | 🟡 MEDIUM | High-risk patients not identified |
| GAP-012 | **No Orders Workflow States** | 🟡 MEDIUM | Lab/Radiology order tracking incomplete |

---

## 3. Existing Strengths

### 3.1 What's Working Well

1. **Encounter Ownership System** ✅
   - `iop_encounter_owner` table tracks doctor assignments
   - Full audit trail in `iop_encounter_owner_audit`
   - Override mechanism with logging

2. **Workflow Status Management** ✅
   - `iop_opd_workflow` tracks consultation states
   - Valid state transitions enforced
   - Status audit logging in `opd_status_audit`

3. **Clinical Clearance** ✅
   - Visits can be clinically cleared
   - Locked visits prevent further orders
   - Admin can reopen if needed

4. **Multi-Diagnosis Support** ✅
   - `iop_medication_diagnosis` table exists
   - Primary/Secondary diagnosis types
   - ICD-10 code validation

5. **NHIS Integration** ✅
   - Payment gates before orders
   - Drug coverage checking
   - Claim audit logging

---

## 4. Clinical Intelligence Implementation Plan

### 4.1 New Tables Required

```sql
-- 1. Drug Interaction Master Table
CREATE TABLE `drug_interactions` (
    `interaction_id` INT AUTO_INCREMENT PRIMARY KEY,
    `drug_id_1` INT NOT NULL,
    `drug_id_2` INT NOT NULL,
    `severity` ENUM('MILD','MODERATE','SEVERE','CONTRAINDICATED') NOT NULL,
    `description` TEXT,
    `clinical_effect` TEXT,
    `management` TEXT,
    `reference_source` VARCHAR(255),
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_drug1 (drug_id_1),
    INDEX idx_drug2 (drug_id_2),
    INDEX idx_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Drug Class Master Table
CREATE TABLE `drug_classes` (
    `class_id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_name` VARCHAR(100) NOT NULL,
    `class_code` VARCHAR(20),
    `parent_class_id` INT DEFAULT NULL,
    `description` TEXT,
    `therapeutic_category` VARCHAR(100),
    `is_active` TINYINT(1) DEFAULT 1,
    INDEX idx_name (class_name),
    INDEX idx_code (class_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Drug-Class Mapping
CREATE TABLE `drug_class_mapping` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `drug_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    UNIQUE KEY uniq_drug_class (drug_id, class_id),
    INDEX idx_drug (drug_id),
    INDEX idx_class (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Patient Allergies Table
CREATE TABLE `patient_allergies` (
    `allergy_id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_no` VARCHAR(25) NOT NULL,
    `allergen_type` ENUM('DRUG','DRUG_CLASS','FOOD','ENVIRONMENTAL','OTHER') NOT NULL,
    `allergen_id` INT DEFAULT NULL,
    `allergen_name` VARCHAR(255) NOT NULL,
    `reaction_type` ENUM('MILD','MODERATE','SEVERE','ANAPHYLAXIS') NOT NULL,
    `reaction_description` TEXT,
    `onset_date` DATE,
    `verified` TINYINT(1) DEFAULT 0,
    `verified_by` VARCHAR(25),
    `verified_at` DATETIME,
    `reported_by` VARCHAR(25),
    `reported_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `is_active` TINYINT(1) DEFAULT 1,
    `InActive` INT(1) DEFAULT 0,
    INDEX idx_patient (patient_no),
    INDEX idx_allergen (allergen_type, allergen_id),
    INDEX idx_severity (reaction_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Drug Dose Limits Table
CREATE TABLE `drug_dose_limits` (
    `limit_id` INT AUTO_INCREMENT PRIMARY KEY,
    `drug_id` INT NOT NULL,
    `min_single_dose` DECIMAL(10,3),
    `max_single_dose` DECIMAL(10,3),
    `max_daily_dose` DECIMAL(10,3),
    `dose_unit` VARCHAR(20),
    `age_group` ENUM('PEDIATRIC','ADULT','GERIATRIC','ALL') DEFAULT 'ALL',
    `min_age_years` INT,
    `max_age_years` INT,
    `weight_based` TINYINT(1) DEFAULT 0,
    `dose_per_kg` DECIMAL(10,3),
    `max_dose_per_kg` DECIMAL(10,3),
    `renal_adjustment` TINYINT(1) DEFAULT 0,
    `hepatic_adjustment` TINYINT(1) DEFAULT 0,
    `notes` TEXT,
    `reference_source` VARCHAR(255),
    `is_active` TINYINT(1) DEFAULT 1,
    INDEX idx_drug (drug_id),
    INDEX idx_age (age_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. High Risk Drug Flags
CREATE TABLE `high_risk_drugs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `drug_id` INT NOT NULL,
    `risk_category` ENUM('NARCOTIC','CONTROLLED','CHEMOTHERAPY','ANTICOAGULANT','INSULIN','HIGH_ALERT','LASA') NOT NULL,
    `requires_double_check` TINYINT(1) DEFAULT 0,
    `requires_indication` TINYINT(1) DEFAULT 0,
    `max_quantity_per_rx` INT,
    `special_instructions` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    UNIQUE KEY uniq_drug_risk (drug_id, risk_category),
    INDEX idx_drug (drug_id),
    INDEX idx_category (risk_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Patient Risk Flags
CREATE TABLE `patient_risk_flags` (
    `flag_id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_no` VARCHAR(25) NOT NULL,
    `risk_type` ENUM('DIABETES','HYPERTENSION','PREGNANCY','RENAL_IMPAIRMENT','HEPATIC_IMPAIRMENT','CARDIAC','ALLERGY_RISK','FALL_RISK','BLEEDING_RISK','OTHER') NOT NULL,
    `severity` ENUM('LOW','MODERATE','HIGH','CRITICAL') DEFAULT 'MODERATE',
    `description` TEXT,
    `onset_date` DATE,
    `diagnosed_by` VARCHAR(25),
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME,
    `InActive` INT(1) DEFAULT 0,
    INDEX idx_patient (patient_no),
    INDEX idx_risk (risk_type),
    INDEX idx_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Consultation Lock Table
CREATE TABLE `consultation_locks` (
    `lock_id` INT AUTO_INCREMENT PRIMARY KEY,
    `iop_id` VARCHAR(25) NOT NULL,
    `locked_by` VARCHAR(25) NOT NULL,
    `locked_at` DATETIME NOT NULL,
    `lock_expires_at` DATETIME NOT NULL,
    `lock_type` ENUM('EDITING','PRESCRIBING','REVIEWING') DEFAULT 'EDITING',
    `is_active` TINYINT(1) DEFAULT 1,
    UNIQUE KEY uniq_iop_lock (iop_id, lock_type),
    INDEX idx_iop (iop_id),
    INDEX idx_user (locked_by),
    INDEX idx_expires (lock_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Clinical Notes Audit Table
CREATE TABLE `clinical_notes_audit` (
    `audit_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `iop_id` VARCHAR(25) NOT NULL,
    `patient_no` VARCHAR(25) NOT NULL,
    `note_type` ENUM('COMPLAINT','HISTORY','EXAMINATION','DIAGNOSIS','PLAN','GENERAL') NOT NULL,
    `field_name` VARCHAR(100),
    `old_value` TEXT,
    `new_value` TEXT,
    `changed_by` VARCHAR(25) NOT NULL,
    `changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45),
    INDEX idx_iop (iop_id),
    INDEX idx_patient (patient_no),
    INDEX idx_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Prescription Safety Alerts Log
CREATE TABLE `prescription_safety_alerts` (
    `alert_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `iop_id` VARCHAR(25) NOT NULL,
    `patient_no` VARCHAR(25) NOT NULL,
    `iop_med_id` INT,
    `drug_id` INT,
    `alert_type` ENUM('INTERACTION','ALLERGY','DUPLICATE','DOSE_HIGH','DOSE_LOW','HIGH_RISK','CONTRAINDICATION') NOT NULL,
    `severity` ENUM('INFO','WARNING','CRITICAL','BLOCKED') NOT NULL,
    `alert_message` TEXT NOT NULL,
    `related_drug_id` INT,
    `was_overridden` TINYINT(1) DEFAULT 0,
    `override_reason` TEXT,
    `overridden_by` VARCHAR(25),
    `overridden_at` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_by` VARCHAR(25),
    INDEX idx_iop (iop_id),
    INDEX idx_patient (patient_no),
    INDEX idx_type (alert_type),
    INDEX idx_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.2 New Model: Clinical Decision Support

**File:** `application/models/app/clinical_decision_support_model.php`

```php
<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Clinical Decision Support Model
 * 
 * Provides drug interaction checking, allergy detection,
 * duplicate therapy detection, and dose validation.
 */
class Clinical_decision_support_model extends CI_Model
{
    private $schema_checked = false;

    public function __construct()
    {
        parent::__construct();
    }

    /* ================================================================== */
    /*  DRUG INTERACTION CHECKING                                          */
    /* ================================================================== */

    /**
     * Check for drug interactions between a new drug and existing prescriptions
     * @param int $drug_id - New drug being prescribed
     * @param string $patient_no - Patient number
     * @param string $iop_id - Current visit ID (to check active prescriptions)
     * @return array - List of interactions found
     */
    public function check_drug_interactions($drug_id, $patient_no, $iop_id = null)
    {
        $interactions = array();
        $drug_id = (int)$drug_id;
        
        if ($drug_id <= 0) return $interactions;
        
        // Get patient's active medications (current visit + recent history)
        $active_drugs = $this->get_patient_active_drugs($patient_no, $iop_id);
        
        if (empty($active_drugs)) return $interactions;
        
        // Check each active drug for interactions
        foreach ($active_drugs as $active_drug_id) {
            $interaction = $this->get_interaction($drug_id, $active_drug_id);
            if ($interaction) {
                $interactions[] = $interaction;
            }
        }
        
        return $interactions;
    }

    /**
     * Get interaction between two drugs
     */
    private function get_interaction($drug_id_1, $drug_id_2)
    {
        if (!$this->table_exists('drug_interactions')) return null;
        
        $sql = "SELECT di.*, 
                       d1.drug_name as drug1_name, 
                       d2.drug_name as drug2_name
                FROM drug_interactions di
                JOIN medicine_drug_name d1 ON d1.drug_id = di.drug_id_1
                JOIN medicine_drug_name d2 ON d2.drug_id = di.drug_id_2
                WHERE di.is_active = 1
                AND ((di.drug_id_1 = ? AND di.drug_id_2 = ?)
                     OR (di.drug_id_1 = ? AND di.drug_id_2 = ?))
                LIMIT 1";
        
        $q = $this->db->query($sql, array($drug_id_1, $drug_id_2, $drug_id_2, $drug_id_1));
        return $q ? $q->row() : null;
    }

    /* ================================================================== */
    /*  ALLERGY DETECTION                                                   */
    /* ================================================================== */

    /**
     * Check if patient is allergic to a drug or its class
     */
    public function check_drug_allergy($drug_id, $patient_no)
    {
        $alerts = array();
        $drug_id = (int)$drug_id;
        $patient_no = (string)$patient_no;
        
        if ($drug_id <= 0 || $patient_no === '') return $alerts;
        
        // Check direct drug allergy
        $direct = $this->check_direct_drug_allergy($drug_id, $patient_no);
        if ($direct) {
            $alerts[] = $direct;
        }
        
        // Check drug class allergy (cross-reactivity)
        $class_allergies = $this->check_drug_class_allergy($drug_id, $patient_no);
        $alerts = array_merge($alerts, $class_allergies);
        
        return $alerts;
    }

    private function check_direct_drug_allergy($drug_id, $patient_no)
    {
        if (!$this->table_exists('patient_allergies')) return null;
        
        $sql = "SELECT pa.*, d.drug_name
                FROM patient_allergies pa
                JOIN medicine_drug_name d ON d.drug_id = pa.allergen_id
                WHERE pa.patient_no = ?
                AND pa.allergen_type = 'DRUG'
                AND pa.allergen_id = ?
                AND pa.is_active = 1
                AND pa.InActive = 0
                LIMIT 1";
        
        $q = $this->db->query($sql, array($patient_no, $drug_id));
        return $q ? $q->row() : null;
    }

    private function check_drug_class_allergy($drug_id, $patient_no)
    {
        $alerts = array();
        
        if (!$this->table_exists('patient_allergies') || 
            !$this->table_exists('drug_class_mapping')) {
            return $alerts;
        }
        
        // Get drug's classes
        $drug_classes = $this->get_drug_classes($drug_id);
        
        if (empty($drug_classes)) return $alerts;
        
        // Check if patient is allergic to any of these classes
        $class_ids = array_column($drug_classes, 'class_id');
        
        $this->db->select('pa.*, dc.class_name');
        $this->db->from('patient_allergies pa');
        $this->db->join('drug_classes dc', 'dc.class_id = pa.allergen_id');
        $this->db->where('pa.patient_no', $patient_no);
        $this->db->where('pa.allergen_type', 'DRUG_CLASS');
        $this->db->where_in('pa.allergen_id', $class_ids);
        $this->db->where('pa.is_active', 1);
        $this->db->where('pa.InActive', 0);
        
        $q = $this->db->get();
        return $q ? $q->result() : array();
    }

    /* ================================================================== */
    /*  DUPLICATE THERAPY DETECTION                                         */
    /* ================================================================== */

    /**
     * Check for duplicate therapy (same drug class)
     */
    public function check_duplicate_therapy($drug_id, $patient_no, $iop_id = null)
    {
        $duplicates = array();
        $drug_id = (int)$drug_id;
        
        if ($drug_id <= 0) return $duplicates;
        
        // Get drug's therapeutic classes
        $drug_classes = $this->get_drug_classes($drug_id);
        
        if (empty($drug_classes)) return $duplicates;
        
        // Get patient's active medications
        $active_drugs = $this->get_patient_active_drugs($patient_no, $iop_id);
        
        if (empty($active_drugs)) return $duplicates;
        
        // Check each active drug's classes
        foreach ($active_drugs as $active_drug_id) {
            if ($active_drug_id == $drug_id) continue; // Skip same drug
            
            $active_classes = $this->get_drug_classes($active_drug_id);
            
            // Find overlapping classes
            foreach ($drug_classes as $dc) {
                foreach ($active_classes as $ac) {
                    if ($dc->class_id == $ac->class_id) {
                        $duplicates[] = (object) array(
                            'new_drug_id' => $drug_id,
                            'existing_drug_id' => $active_drug_id,
                            'class_id' => $dc->class_id,
                            'class_name' => $dc->class_name,
                            'message' => "Duplicate therapy: Both drugs belong to {$dc->class_name} class"
                        );
                    }
                }
            }
        }
        
        return $duplicates;
    }

    /* ================================================================== */
    /*  DOSE VALIDATION                                                     */
    /* ================================================================== */

    /**
     * Validate dose against limits
     */
    public function validate_dose($drug_id, $dose, $frequency, $patient_no)
    {
        $alerts = array();
        $drug_id = (int)$drug_id;
        
        if ($drug_id <= 0) return $alerts;
        
        // Get patient age for age-based dosing
        $patient = $this->get_patient_info($patient_no);
        $age = $patient ? (int)$patient->age : 30; // Default adult
        
        // Determine age group
        $age_group = 'ADULT';
        if ($age < 18) $age_group = 'PEDIATRIC';
        elseif ($age >= 65) $age_group = 'GERIATRIC';
        
        // Get dose limits
        $limits = $this->get_dose_limits($drug_id, $age_group);
        
        if (!$limits) return $alerts;
        
        // Parse dose value
        $dose_value = $this->parse_dose($dose);
        
        // Check single dose limits
        if ($limits->max_single_dose && $dose_value > $limits->max_single_dose) {
            $alerts[] = (object) array(
                'type' => 'DOSE_HIGH',
                'severity' => 'WARNING',
                'message' => "Dose {$dose_value} exceeds maximum single dose of {$limits->max_single_dose} {$limits->dose_unit}"
            );
        }
        
        if ($limits->min_single_dose && $dose_value < $limits->min_single_dose) {
            $alerts[] = (object) array(
                'type' => 'DOSE_LOW',
                'severity' => 'INFO',
                'message' => "Dose {$dose_value} is below minimum effective dose of {$limits->min_single_dose} {$limits->dose_unit}"
            );
        }
        
        // Calculate daily dose based on frequency
        $daily_multiplier = $this->get_frequency_multiplier($frequency);
        $daily_dose = $dose_value * $daily_multiplier;
        
        if ($limits->max_daily_dose && $daily_dose > $limits->max_daily_dose) {
            $alerts[] = (object) array(
                'type' => 'DOSE_HIGH',
                'severity' => 'CRITICAL',
                'message' => "Daily dose {$daily_dose} exceeds maximum daily dose of {$limits->max_daily_dose} {$limits->dose_unit}"
            );
        }
        
        return $alerts;
    }

    /* ================================================================== */
    /*  HIGH RISK DRUG DETECTION                                            */
    /* ================================================================== */

    /**
     * Check if drug is high-risk
     */
    public function check_high_risk_drug($drug_id)
    {
        if (!$this->table_exists('high_risk_drugs')) return null;
        
        $sql = "SELECT hrd.*, d.drug_name
                FROM high_risk_drugs hrd
                JOIN medicine_drug_name d ON d.drug_id = hrd.drug_id
                WHERE hrd.drug_id = ?
                AND hrd.is_active = 1";
        
        $q = $this->db->query($sql, array((int)$drug_id));
        return $q ? $q->result() : array();
    }

    /* ================================================================== */
    /*  PATIENT RISK FLAGS                                                  */
    /* ================================================================== */

    /**
     * Get patient risk flags
     */
    public function get_patient_risk_flags($patient_no)
    {
        if (!$this->table_exists('patient_risk_flags')) return array();
        
        $this->db->where('patient_no', $patient_no);
        $this->db->where('is_active', 1);
        $this->db->where('InActive', 0);
        $this->db->order_by('severity', 'DESC');
        
        $q = $this->db->get('patient_risk_flags');
        return $q ? $q->result() : array();
    }

    /**
     * Add patient risk flag
     */
    public function add_patient_risk_flag($patient_no, $risk_type, $severity, $description, $user_id)
    {
        $this->ensure_schema();
        
        return $this->db->insert('patient_risk_flags', array(
            'patient_no' => $patient_no,
            'risk_type' => $risk_type,
            'severity' => $severity,
            'description' => $description,
            'diagnosed_by' => $user_id,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'InActive' => 0
        ));
    }

    /* ================================================================== */
    /*  COMPREHENSIVE PRESCRIPTION CHECK                                    */
    /* ================================================================== */

    /**
     * Run all safety checks for a prescription
     * Returns array of alerts with severity levels
     */
    public function check_prescription_safety($drug_id, $dose, $frequency, $patient_no, $iop_id = null)
    {
        $all_alerts = array();
        
        // 1. Drug Interactions
        $interactions = $this->check_drug_interactions($drug_id, $patient_no, $iop_id);
        foreach ($interactions as $i) {
            $all_alerts[] = (object) array(
                'type' => 'INTERACTION',
                'severity' => $i->severity,
                'message' => "Drug interaction with {$i->drug2_name}: {$i->description}",
                'details' => $i
            );
        }
        
        // 2. Allergies
        $allergies = $this->check_drug_allergy($drug_id, $patient_no);
        foreach ($allergies as $a) {
            $severity = ($a->reaction_type === 'ANAPHYLAXIS') ? 'BLOCKED' : 'CRITICAL';
            $all_alerts[] = (object) array(
                'type' => 'ALLERGY',
                'severity' => $severity,
                'message' => "Patient allergic to {$a->allergen_name}: {$a->reaction_description}",
                'details' => $a
            );
        }
        
        // 3. Duplicate Therapy
        $duplicates = $this->check_duplicate_therapy($drug_id, $patient_no, $iop_id);
        foreach ($duplicates as $d) {
            $all_alerts[] = (object) array(
                'type' => 'DUPLICATE',
                'severity' => 'WARNING',
                'message' => $d->message,
                'details' => $d
            );
        }
        
        // 4. Dose Validation
        $dose_alerts = $this->validate_dose($drug_id, $dose, $frequency, $patient_no);
        foreach ($dose_alerts as $da) {
            $all_alerts[] = $da;
        }
        
        // 5. High Risk Drug
        $high_risk = $this->check_high_risk_drug($drug_id);
        foreach ($high_risk as $hr) {
            $all_alerts[] = (object) array(
                'type' => 'HIGH_RISK',
                'severity' => 'WARNING',
                'message' => "High-risk medication ({$hr->risk_category}): {$hr->special_instructions}",
                'details' => $hr
            );
        }
        
        // Sort by severity
        usort($all_alerts, function($a, $b) {
            $order = array('BLOCKED' => 0, 'CRITICAL' => 1, 'WARNING' => 2, 'INFO' => 3);
            return ($order[$a->severity] ?? 4) - ($order[$b->severity] ?? 4);
        });
        
        return $all_alerts;
    }

    /* ================================================================== */
    /*  HELPER METHODS                                                      */
    /* ================================================================== */

    private function get_patient_active_drugs($patient_no, $current_iop_id = null)
    {
        $drugs = array();
        
        // Get drugs from current visit
        if ($current_iop_id) {
            $q = $this->db->select('medicine_id')
                ->where('iop_id', $current_iop_id)
                ->where('InActive', 0)
                ->get('iop_medication');
            
            foreach ($q->result() as $r) {
                if ($r->medicine_id > 0) {
                    $drugs[$r->medicine_id] = $r->medicine_id;
                }
            }
        }
        
        // Get drugs from recent visits (last 30 days)
        $cutoff = date('Y-m-d', strtotime('-30 days'));
        $sql = "SELECT DISTINCT m.medicine_id
                FROM iop_medication m
                JOIN patient_details_iop p ON p.IO_ID = m.iop_id
                WHERE p.patient_no = ?
                AND p.date_visit >= ?
                AND m.InActive = 0
                AND m.medicine_id > 0";
        
        $q = $this->db->query($sql, array($patient_no, $cutoff));
        foreach ($q->result() as $r) {
            $drugs[$r->medicine_id] = $r->medicine_id;
        }
        
        return array_values($drugs);
    }

    private function get_drug_classes($drug_id)
    {
        if (!$this->table_exists('drug_class_mapping')) return array();
        
        $sql = "SELECT dc.*
                FROM drug_class_mapping dcm
                JOIN drug_classes dc ON dc.class_id = dcm.class_id
                WHERE dcm.drug_id = ?
                AND dc.is_active = 1";
        
        $q = $this->db->query($sql, array($drug_id));
        return $q ? $q->result() : array();
    }

    private function get_dose_limits($drug_id, $age_group)
    {
        if (!$this->table_exists('drug_dose_limits')) return null;
        
        $this->db->where('drug_id', $drug_id);
        $this->db->where('is_active', 1);
        $this->db->where_in('age_group', array($age_group, 'ALL'));
        $this->db->order_by("FIELD(age_group, '{$age_group}', 'ALL')");
        $this->db->limit(1);
        
        $q = $this->db->get('drug_dose_limits');
        return $q ? $q->row() : null;
    }

    private function get_patient_info($patient_no)
    {
        return $this->db->get_where('patient_personal_info', array(
            'patient_no' => $patient_no,
            'InActive' => 0
        ))->row();
    }

    private function parse_dose($dose)
    {
        // Extract numeric value from dose string
        preg_match('/[\d.]+/', (string)$dose, $matches);
        return isset($matches[0]) ? (float)$matches[0] : 0;
    }

    private function get_frequency_multiplier($frequency)
    {
        $freq = strtoupper((string)$frequency);
        $map = array(
            'OD' => 1, 'ONCE DAILY' => 1,
            'BD' => 2, 'TWICE DAILY' => 2, 'BID' => 2,
            'TDS' => 3, 'THREE TIMES DAILY' => 3, 'TID' => 3,
            'QDS' => 4, 'FOUR TIMES DAILY' => 4, 'QID' => 4,
            'Q4H' => 6, 'EVERY 4 HOURS' => 6,
            'Q6H' => 4, 'EVERY 6 HOURS' => 4,
            'Q8H' => 3, 'EVERY 8 HOURS' => 3,
            'Q12H' => 2, 'EVERY 12 HOURS' => 2,
            'STAT' => 1, 'PRN' => 1, 'AS NEEDED' => 1,
            'WEEKLY' => 0.14,
        );
        
        foreach ($map as $key => $val) {
            if (strpos($freq, $key) !== false) {
                return $val;
            }
        }
        
        return 1; // Default to once daily
    }

    private function table_exists($t)
    {
        $q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape((string)$t));
        return ($q && $q->num_rows() > 0);
    }

    public function ensure_schema()
    {
        if ($this->schema_checked) return;
        $this->schema_checked = true;
        // Schema creation handled by migration
    }
}
```

---

## 5. Implementation Phases

### Phase 3A: Foundation (Week 1)
1. Create all new database tables
2. Create `clinical_decision_support_model.php`
3. Add consultation locking mechanism
4. Add clinical notes audit trail

### Phase 3B: Drug Safety (Week 2)
1. Implement drug interaction checking
2. Implement allergy detection
3. Implement duplicate therapy detection
4. Seed initial drug interaction data

### Phase 3C: Dose Validation (Week 3)
1. Implement dose validation engine
2. Add high-risk drug detection
3. Create patient risk flags system
4. Add prescription safety alerts

### Phase 3D: UI Integration (Week 4)
1. Add safety alerts to prescription form
2. Add patient risk badges to views
3. Add allergy warnings to patient header
4. Add consultation lock indicators

---

## 6. Testing Checklist

### 6.1 Drug Interaction Tests
- [ ] Detect severe drug-drug interaction
- [ ] Detect moderate interaction
- [ ] Allow prescription with warning override
- [ ] Block contraindicated combinations

### 6.2 Allergy Tests
- [ ] Detect direct drug allergy
- [ ] Detect drug class cross-allergy
- [ ] Block anaphylaxis-risk prescriptions
- [ ] Allow with documented override

### 6.3 Duplicate Therapy Tests
- [ ] Detect same-class prescriptions
- [ ] Warn on therapeutic duplication
- [ ] Allow intentional duplicates with reason

### 6.4 Dose Validation Tests
- [ ] Flag doses above maximum
- [ ] Flag doses below minimum
- [ ] Calculate daily dose from frequency
- [ ] Apply age-based limits

### 6.5 Workflow Tests
- [ ] Consultation lock prevents concurrent editing
- [ ] Lock auto-expires after timeout
- [ ] Clinical notes changes are audited
- [ ] Primary diagnosis is enforced

---

## 7. Performance Indexes

```sql
-- Add indexes for clinical decision support queries
CREATE INDEX idx_med_patient_date ON iop_medication (iop_id, InActive);
CREATE INDEX idx_allergy_patient ON patient_allergies (patient_no, allergen_type, is_active);
CREATE INDEX idx_interaction_drugs ON drug_interactions (drug_id_1, drug_id_2, is_active);
CREATE INDEX idx_dose_drug ON drug_dose_limits (drug_id, age_group, is_active);
CREATE INDEX idx_risk_patient ON patient_risk_flags (patient_no, is_active);
CREATE INDEX idx_lock_iop ON consultation_locks (iop_id, is_active, lock_expires_at);
```

---

## 8. Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Drug interaction detection rate | 100% | All known interactions flagged |
| Allergy alert accuracy | 100% | No missed allergies |
| False positive rate | < 5% | Minimize alert fatigue |
| Prescription completion time | < 30s | With safety checks |
| System response time | < 500ms | Safety check API |

---

## 9. Conclusion

The Doctor Module has a solid foundation but lacks critical clinical safety features. The proposed Clinical Decision Support System will:

1. **Prevent medication errors** through drug interaction and allergy checking
2. **Improve NHIS claim success** through diagnosis-prescription linkage
3. **Enhance audit compliance** through comprehensive logging
4. **Support clinical workflow** through consultation locking

**Recommended Priority:** HIGH - Patient safety features should be implemented immediately.

---

**Report Prepared By:** HMS Architecture Team  
**Next Steps:** Begin Phase 3A Implementation
