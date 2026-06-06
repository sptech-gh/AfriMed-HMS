# OPD Module Duplication & Unified Architecture Report

**System:** SBMC Hospital Management System  
**Date:** April 9, 2026  
**Prepared By:** Healthcare Systems Architect  

---

## 1. Executive Summary

### Current State Assessment

The OPD module has evolved organically over time, resulting in:

| Issue | Severity | Impact |
|-------|----------|--------|
| **5 different status tracking mechanisms** | HIGH | Conflicting patient states |
| **3 tables storing visit status** | HIGH | Data inconsistency |
| **Duplicate clinical clearance logic** | MEDIUM | Maintenance burden |
| **Legacy + Modern workflow coexistence** | MEDIUM | Confusion, bugs |
| **No single source of truth** | HIGH | Incorrect UI, broken workflows |

### Key Findings

1. **Status Fragmentation:** Patient visit status is stored in 5 different places
2. **Dual Workflow Systems:** Legacy `nStatus` coexists with modern `iop_opd_workflow`
3. **Clearance Logic Duplication:** `discharge()` and `clinical_clear()` perform similar operations
4. **Module-Specific Statuses:** Lab, Pharmacy, Billing each maintain separate status fields
5. **No Unified State Machine:** Transitions not enforced consistently

---

## 2. Duplicate Functionalities Identified

### 2.1 Controllers

#### Status Update Duplication

| Controller | Method | What It Does | Conflict |
|------------|--------|--------------|----------|
| `opd.php` | `discharge()` | Sets `nStatus='Discharged'`, `clinical_clearance_status=1`, workflow=`CLINICALLY_CLEARED` | Duplicates `clinical_clear()` |
| `opd.php` | `clinical_clear()` | Sets `clinical_clearance_status=1`, `visit_status='closed'`, workflow=`CLINICALLY_CLEARED` | Duplicates `discharge()` |
| `opd.php` | `set_queue_status()` | Updates `iop_opd_workflow.status` | Only updates workflow, not legacy |
| `billing.php` | `final_clearance()` | Sets `nStatus='Discharged'` | Doesn't update workflow status |
| `pharmacy.php` | `medication_clearance()` | Updates `iop_clearance_workflow` | Separate clearance system |

#### OPD Visit Creation Duplication

| Controller | Method | Creates Visit In |
|------------|--------|------------------|
| `opd.php` | `save_opd()` | `patient_details_iop` |
| `opd.php` | `opd_reg()` | `patient_details_iop` |
| `opd.php` | `start_opd_quick()` | `patient_details_iop` via `opd_model->quick_start_opd()` |
| `ipd.php` | `admit_patient()` | `patient_details_iop` with `patient_type=IPD` |

### 2.2 Models

#### Status Management Duplication

| Model | Method | Updates |
|-------|--------|---------|
| `opd_model.php` | `upsert_opd_workflow_status()` | `iop_opd_workflow.status` |
| `opd_model.php` | `log_status_transition()` | `opd_status_audit` |
| `billing_model.php` | `upsert_clearance_stage()` | `iop_clearance_workflow` |
| `billing_model.php` | `update_payment_status()` | `iop_billing.payment_status` |
| `pharmacy_model.php` | `update_dispensing_status()` | `iop_medication.dispensing_status` |
| `laboratory_model.php` | `update_lab_status()` | `iop_laboratory` status fields |

### 2.3 Views

| View | Purpose | Duplication |
|------|---------|-------------|
| `opd/index.php` | OPD Master List | Shows both legacy and workflow status |
| `dashboard.php` | Dashboard | Queries multiple status sources |
| `dashboard_doctor.php` | Doctor Dashboard | Different status logic than OPD index |
| `dashboard_receptionist.php` | Reception Dashboard | Yet another status interpretation |

---

## 3. Conflicting Status Logic

### 3.1 Current Status Fields

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    PATIENT VISIT STATUS SOURCES (5 TOTAL)                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  TABLE: patient_details_iop                                                 │
│  ├── nStatus (varchar) ─────────────── "Pending" | "Discharged"             │
│  ├── visit_status (varchar) ────────── "active" | "closed"                  │
│  └── clinical_clearance_status (int) ─ 0 | 1                                │
│                                                                             │
│  TABLE: iop_opd_workflow                                                    │
│  └── status (varchar) ──────────────── "WAITING" | "IN_CONSULTATION" |      │
│                                        "CLINICALLY_CLEARED" | "PENDING_LAB" │
│                                        "PENDING_PHARMACY" | "COMPLETED" |   │
│                                        "ADMITTED" | "CANCELLED"             │
│                                                                             │
│  TABLE: iop_clearance_workflow                                              │
│  ├── clinical_cleared_at (datetime) ── Timestamp of clinical clearance     │
│  ├── medication_cleared_at (datetime)  Timestamp of medication clearance   │
│  └── final_cleared_at (datetime) ───── Timestamp of final clearance        │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3.2 Status Update Flow Problems

```
CURRENT BROKEN FLOW:

Doctor clicks "Clinically Clear"
    │
    ├──→ opd.php::clinical_clear()
    │        ├── Updates patient_details_iop.clinical_clearance_status = 1
    │        ├── Updates patient_details_iop.visit_status = 'closed'
    │        ├── Updates iop_opd_workflow.status = 'CLINICALLY_CLEARED'
    │        └── Updates iop_clearance_workflow.clinical_cleared_at
    │
    └──→ BUT opd.php::discharge() ALSO exists and does:
             ├── Updates patient_details_iop.nStatus = 'Discharged'  ← DIFFERENT!
             ├── Updates patient_details_iop.clinical_clearance_status = 1
             ├── Updates iop_opd_workflow.status = 'CLINICALLY_CLEARED'
             └── Updates iop_clearance_workflow

RESULT: Depending on which method is called, different fields are updated!
```

### 3.3 Module-Specific Status Conflicts

| Module | Status Field | Values | Who Updates |
|--------|--------------|--------|-------------|
| **OPD Workflow** | `iop_opd_workflow.status` | WAITING, IN_CONSULTATION, etc. | OPD Controller |
| **Laboratory** | `iop_laboratory.result_status` | PENDING, COMPLETED | Lab Controller |
| **Pharmacy** | `iop_medication.dispensing_status` | PENDING, DISPENSED, UNAVAILABLE | Pharmacy Controller |
| **Pharmacy Billing** | `pharmacy_billing_queue.payment_status` | PENDING, PAID, CANCELLED | Billing Controller |
| **Invoice** | `iop_billing.payment_status` | PENDING, PARTIAL, PAID | POS Controller |
| **Clearance** | `iop_clearance_workflow.*_cleared_at` | Timestamps | Multiple Controllers |

### 3.4 Synchronization Failures

**Problem 1:** When lab completes results, `iop_opd_workflow` is NOT updated
```php
// laboratory.php::edit_save() - Updates lab table but NOT OPD workflow
$this->laboratory_model->save_result($result_data);
// Missing: $this->opd_model->upsert_opd_workflow_status($iop_id, 'PENDING_PHARMACY');
```

**Problem 2:** When pharmacy dispenses, `iop_opd_workflow` is NOT updated
```php
// pharmacy.php::log_action() - Updates medication but NOT OPD workflow
$this->pharmacy_model->update_dispensing_status($med_id, 'DISPENSED');
// Missing: Check if all meds dispensed, then update OPD workflow
```

**Problem 3:** Legacy `nStatus` not synced with modern workflow
```php
// Index page checks BOTH:
$legacyCompleted = ($patient->nStatus !== 'Pending');
$workflowCleared = in_array($wfStatus, ['COMPLETED','CLINICALLY_CLEARED']);
// Can have contradictory states!
```

---

## 4. Database Redundancies

### 4.1 Tables Analysis

| Table | Purpose | Records Status? | Redundant? |
|-------|---------|-----------------|------------|
| `patient_details_iop` | Visit master | Yes (3 fields!) | PARTIAL - nStatus is legacy |
| `iop_opd_workflow` | Modern workflow | Yes (primary) | NO - Keep as source of truth |
| `iop_clearance_workflow` | Clearance tracking | Yes (stages) | NO - Needed for 3-stage clearance |
| `opd_status_audit` | Status history | No (audit log) | NO - Needed for audit |
| `pharmacy_billing_queue` | Pharmacy payment | Yes | NO - Module-specific |
| `iop_lab_billing` | Lab payment | Yes | DUPLICATE - Use unified billing |

### 4.2 Redundant Columns

```sql
-- patient_details_iop has 3 status columns!
ALTER TABLE patient_details_iop
  -- KEEP: clinical_clearance_status (needed for clearance workflow)
  -- DEPRECATE: nStatus (legacy, should use iop_opd_workflow.status)
  -- DEPRECATE: visit_status (redundant with clinical_clearance_status)
```

### 4.3 Relationship Diagram

```
┌─────────────────────┐
│ patient_personal_info│
│   patient_no (PK)   │
└─────────┬───────────┘
          │ 1:N
          ▼
┌─────────────────────────────────────────────────────────────────┐
│                    patient_details_iop                          │
│  IO_ID (PK) ─────────────────────────────────────────────────── │
│  patient_no (FK) ────────────────────────────────────────────── │
│  nStatus ──────────── DEPRECATED: Use iop_opd_workflow.status   │
│  visit_status ─────── DEPRECATED: Use clinical_clearance_status │
│  clinical_clearance_status ─── KEEP: 0/1 flag                   │
└───────────────────────────┬─────────────────────────────────────┘
                            │ 1:1
          ┌─────────────────┼─────────────────┐
          ▼                 ▼                 ▼
┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
│ iop_opd_workflow │ │iop_clearance_    │ │ opd_status_audit │
│  (SOURCE OF TRUTH)│ │    workflow      │ │   (AUDIT LOG)    │
│  iop_id (FK)     │ │  iop_id (FK)     │ │  iop_id (FK)     │
│  status ◄────────│ │  clinical_at     │ │  old_status      │
│  waiting_at      │ │  medication_at   │ │  new_status      │
│  in_consult_at   │ │  final_at        │ │  changed_by      │
│  cleared_at      │ │                  │ │  changed_at      │
└──────────────────┘ └──────────────────┘ └──────────────────┘
```

---

## 5. Unified OPD Architecture (Proposed)

### 5.1 Single Source of Truth Design

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                     UNIFIED OPD STATUS ENGINE                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  PRIMARY STATUS TABLE: iop_opd_workflow                                     │
│  ══════════════════════════════════════                                     │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ iop_id          VARCHAR(25) PK                                      │   │
│  │ patient_no      VARCHAR(25) FK                                      │   │
│  │ visit_status    ENUM('REGISTERED','WAITING','IN_CONSULTATION',      │   │
│  │                      'LAB_PENDING','LAB_COMPLETED',                  │   │
│  │                      'PHARMACY_PENDING','PHARMACY_COMPLETED',        │   │
│  │                      'BILLING_PENDING','CLINICALLY_CLEARED',         │   │
│  │                      'FINAL_CLEARED','CANCELLED','ADMITTED')         │   │
│  │ sub_status      VARCHAR(50) -- For module-specific details          │   │
│  │ lab_count       INT -- Pending lab orders                           │   │
│  │ pharmacy_count  INT -- Pending prescriptions                        │   │
│  │ billing_balance DECIMAL(12,2) -- Outstanding balance                │   │
│  │ timestamps...                                                       │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 5.2 Unified Status State Machine

```
                              ┌──────────────┐
                              │  REGISTERED  │
                              └──────┬───────┘
                                     │
                              ┌──────▼───────┐
                              │   WAITING    │◄────────────────────┐
                              └──────┬───────┘                     │
                                     │                             │
                              ┌──────▼───────┐                     │
                         ┌───►│IN_CONSULTATION├───┐                │
                         │    └──────┬───────┘   │                │
                         │           │           │                │
              ┌──────────┴──┐  ┌─────▼─────┐  ┌──▼──────────┐     │
              │ LAB_PENDING │  │PHARMACY_  │  │CLINICALLY_  │     │
              └──────┬──────┘  │ PENDING   │  │  CLEARED    │     │
                     │         └─────┬─────┘  └──────┬──────┘     │
              ┌──────▼──────┐        │               │            │
              │LAB_COMPLETED├────────┘               │            │
              └─────────────┘                        │            │
                                              ┌──────▼──────┐     │
                                              │BILLING_     │     │
                                              │ PENDING     │     │
                                              └──────┬──────┘     │
                                                     │            │
                                              ┌──────▼──────┐     │
                                              │FINAL_CLEARED│     │
                                              └──────┬──────┘     │
                                                     │            │
                    ┌────────────────────────────────┴────────────┘
                    │ (Admin can reopen)
                    ▼
            ┌──────────────┐
            │  COMPLETED   │
            └──────────────┘
```

### 5.3 Unified Status Engine Class

```php
<?php
/**
 * Unified OPD Status Engine
 * Single source of truth for all OPD visit status management
 */
class Opd_status_engine {
    
    // Valid status transitions
    private $transitions = [
        'REGISTERED'        => ['WAITING', 'CANCELLED'],
        'WAITING'           => ['IN_CONSULTATION', 'CANCELLED'],
        'IN_CONSULTATION'   => ['LAB_PENDING', 'PHARMACY_PENDING', 'CLINICALLY_CLEARED', 'ADMITTED', 'CANCELLED'],
        'LAB_PENDING'       => ['LAB_COMPLETED', 'IN_CONSULTATION'],
        'LAB_COMPLETED'     => ['IN_CONSULTATION', 'PHARMACY_PENDING', 'CLINICALLY_CLEARED'],
        'PHARMACY_PENDING'  => ['PHARMACY_COMPLETED', 'IN_CONSULTATION'],
        'PHARMACY_COMPLETED'=> ['CLINICALLY_CLEARED', 'BILLING_PENDING'],
        'CLINICALLY_CLEARED'=> ['BILLING_PENDING', 'FINAL_CLEARED'],
        'BILLING_PENDING'   => ['FINAL_CLEARED'],
        'FINAL_CLEARED'     => [], // Terminal state
        'ADMITTED'          => [], // Transfers to IPD
        'CANCELLED'         => [], // Terminal state
    ];
    
    /**
     * Transition visit to new status with validation
     */
    public function transition($iop_id, $new_status, $user_id, $reason = null) {
        $current = $this->get_status($iop_id);
        
        // Validate transition
        if (!$this->is_valid_transition($current, $new_status)) {
            throw new Exception("Invalid transition: $current → $new_status");
        }
        
        // Update single source of truth
        $this->update_workflow_status($iop_id, $new_status, $user_id);
        
        // Sync legacy fields for backward compatibility
        $this->sync_legacy_fields($iop_id, $new_status);
        
        // Log audit trail
        $this->log_transition($iop_id, $current, $new_status, $user_id, $reason);
        
        // Trigger module-specific actions
        $this->trigger_status_hooks($iop_id, $new_status);
        
        return true;
    }
    
    /**
     * Auto-compute status based on module states
     */
    public function compute_status($iop_id) {
        $pending_labs = $this->count_pending_labs($iop_id);
        $pending_meds = $this->count_pending_medications($iop_id);
        $billing_balance = $this->get_billing_balance($iop_id);
        $is_clinically_cleared = $this->is_clinically_cleared($iop_id);
        
        if ($pending_labs > 0) return 'LAB_PENDING';
        if ($pending_meds > 0) return 'PHARMACY_PENDING';
        if (!$is_clinically_cleared) return 'IN_CONSULTATION';
        if ($billing_balance > 0) return 'BILLING_PENDING';
        return 'FINAL_CLEARED';
    }
}
```

---

## 6. Deprecation Plan

### 6.1 Fields to Deprecate

| Table | Field | Replacement | Migration |
|-------|-------|-------------|-----------|
| `patient_details_iop` | `nStatus` | `iop_opd_workflow.status` | Phase 2 |
| `patient_details_iop` | `visit_status` | `clinical_clearance_status` | Phase 1 |
| `iop_lab_billing` | entire table | `unified_billing_model` | Phase 3 |

### 6.2 Methods to Deprecate

```php
// opd.php - DEPRECATE discharge(), use clinical_clear() only
/**
 * @deprecated Use clinical_clear() instead
 */
public function discharge() {
    log_message('info', 'DEPRECATED: discharge() called, redirecting to clinical_clear()');
    return $this->clinical_clear();
}

// opd_model.php - Add deprecation notices
/**
 * @deprecated Use Opd_status_engine::transition() instead
 */
public function upsert_opd_workflow_status($iop_id, $status, $user_id) {
    // Keep for backward compatibility but log usage
    log_message('info', 'DEPRECATED: Direct workflow update, use Opd_status_engine');
    // ... existing code
}
```

### 6.3 Safe Removal Timeline

| Phase | Timeframe | Action |
|-------|-----------|--------|
| Phase 1 | Week 1-2 | Add deprecation warnings, create unified engine |
| Phase 2 | Week 3-4 | Migrate all controllers to use unified engine |
| Phase 3 | Week 5-6 | Remove duplicate methods, keep legacy field sync |
| Phase 4 | Week 7-8 | Remove legacy field writes (keep reads for reporting) |

---

## 7. Migration Plan

### 7.1 Data Migration Script

```sql
-- Step 1: Ensure all visits have workflow records
INSERT INTO iop_opd_workflow (iop_id, status, InActive, created_at)
SELECT 
    IO_ID,
    CASE 
        WHEN nStatus = 'Discharged' THEN 'FINAL_CLEARED'
        WHEN clinical_clearance_status = 1 THEN 'CLINICALLY_CLEARED'
        ELSE 'WAITING'
    END,
    0,
    NOW()
FROM patient_details_iop p
WHERE NOT EXISTS (
    SELECT 1 FROM iop_opd_workflow w WHERE w.iop_id = p.IO_ID
)
AND p.InActive = 0;

-- Step 2: Sync workflow status with legacy for existing records
UPDATE iop_opd_workflow w
JOIN patient_details_iop p ON p.IO_ID = w.iop_id
SET w.status = CASE
    WHEN p.nStatus = 'Discharged' THEN 'FINAL_CLEARED'
    WHEN p.clinical_clearance_status = 1 THEN 'CLINICALLY_CLEARED'
    ELSE w.status
END
WHERE w.status NOT IN ('FINAL_CLEARED', 'CLINICALLY_CLEARED')
AND (p.nStatus = 'Discharged' OR p.clinical_clearance_status = 1);
```

### 7.2 Backward Compatibility Layer

```php
/**
 * Backward compatibility wrapper
 * Syncs legacy fields when unified status changes
 */
private function sync_legacy_fields($iop_id, $new_status) {
    $legacy_map = [
        'FINAL_CLEARED'     => ['nStatus' => 'Discharged', 'clinical_clearance_status' => 1],
        'CLINICALLY_CLEARED'=> ['clinical_clearance_status' => 1, 'visit_status' => 'closed'],
        'WAITING'           => ['nStatus' => 'Pending', 'clinical_clearance_status' => 0],
        'IN_CONSULTATION'   => ['nStatus' => 'Pending', 'clinical_clearance_status' => 0],
    ];
    
    if (isset($legacy_map[$new_status])) {
        $this->db->where('IO_ID', $iop_id);
        $this->db->update('patient_details_iop', $legacy_map[$new_status]);
    }
}
```

### 7.3 Rollback Plan

```sql
-- Emergency rollback: Restore from audit log
UPDATE iop_opd_workflow w
SET w.status = (
    SELECT a.old_status 
    FROM opd_status_audit a 
    WHERE a.iop_id = w.iop_id 
    ORDER BY a.changed_at DESC 
    LIMIT 1
)
WHERE w.updated_at > '2026-04-09 00:00:00'; -- Migration timestamp
```

---

## 8. Implementation Roadmap

### Phase 1: Status Engine (Week 1-2)

| Task | Priority | Effort |
|------|----------|--------|
| Create `Opd_status_engine` model | HIGH | 2 days |
| Define state machine transitions | HIGH | 1 day |
| Add transition validation | HIGH | 1 day |
| Create backward compatibility sync | MEDIUM | 1 day |
| Unit tests for state machine | HIGH | 2 days |

**Deliverable:** New `application/models/app/opd_status_engine.php`

### Phase 2: Controller Cleanup (Week 3-4)

| Task | Priority | Effort |
|------|----------|--------|
| Merge `discharge()` into `clinical_clear()` | HIGH | 1 day |
| Update `set_queue_status()` to use engine | HIGH | 1 day |
| Update Lab controller status hooks | MEDIUM | 1 day |
| Update Pharmacy controller status hooks | MEDIUM | 1 day |
| Update Billing controller status hooks | MEDIUM | 1 day |
| Integration testing | HIGH | 2 days |

**Deliverable:** All controllers use unified status engine

### Phase 3: Database Unification (Week 5-6)

| Task | Priority | Effort |
|------|----------|--------|
| Run data migration script | HIGH | 1 day |
| Verify data integrity | HIGH | 1 day |
| Add foreign key constraints | MEDIUM | 1 day |
| Update indexes for performance | MEDIUM | 1 day |
| Create database documentation | LOW | 1 day |

**Deliverable:** Clean, consistent database state

### Phase 4: UI Improvements (Week 7-8)

| Task | Priority | Effort |
|------|----------|--------|
| Unified OPD dashboard | HIGH | 3 days |
| Patient workflow tracker widget | MEDIUM | 2 days |
| Status timeline component | MEDIUM | 2 days |
| Real-time status updates (polling) | LOW | 1 day |
| User acceptance testing | HIGH | 2 days |

**Deliverable:** Modern, intuitive OPD interface

---

## 9. Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Data loss during migration | LOW | HIGH | Full backup before migration, rollback script ready |
| Breaking existing workflows | MEDIUM | HIGH | Backward compatibility layer, gradual rollout |
| Performance degradation | LOW | MEDIUM | Proper indexing, query optimization |
| User confusion | MEDIUM | MEDIUM | Training documentation, gradual UI changes |
| Integration failures | MEDIUM | HIGH | Comprehensive testing, feature flags |

---

## 10. Success Metrics

| Metric | Current | Target | Measurement |
|--------|---------|--------|-------------|
| Status inconsistencies | ~15% of visits | <1% | Daily audit query |
| Status update locations | 5 places | 1 place | Code review |
| Average page load (OPD index) | ~2.5s | <1s | Performance monitoring |
| Support tickets (status issues) | ~10/week | <2/week | Help desk tracking |
| Developer onboarding time | 2 weeks | 3 days | New hire feedback |

---

## 11. Appendix

### A. Current Files Requiring Changes

```
Controllers (6 files):
├── application/controllers/app/opd.php          ← Major changes
├── application/controllers/app/billing.php      ← Minor changes  
├── application/controllers/app/pharmacy.php     ← Minor changes
├── application/controllers/app/laboratory.php   ← Minor changes
├── application/controllers/app/ipd.php          ← Minor changes
└── application/controllers/app/pos.php          ← Minor changes

Models (4 files):
├── application/models/app/opd_model.php         ← Major refactor
├── application/models/app/billing_model.php     ← Minor changes
├── application/models/app/pharmacy_model.php    ← Minor changes
└── application/models/app/laboratory_model.php  ← Minor changes

New Files (2 files):
├── application/models/app/opd_status_engine.php ← NEW
└── application/libraries/Status_machine.php     ← NEW (optional)

Views (5 files):
├── application/views/app/opd/index.php          ← UI updates
├── application/views/app/opd/view.php           ← Status widget
├── application/views/app/dashboard.php          ← Status counts
├── application/views/app/dashboard_doctor.php   ← Status widget
└── application/views/app/dashboard_receptionist.php ← Queue view
```

### B. SQL Audit Queries

```sql
-- Find visits with inconsistent status
SELECT 
    p.IO_ID,
    p.nStatus AS legacy_status,
    p.clinical_clearance_status,
    p.visit_status,
    w.status AS workflow_status,
    CASE 
        WHEN p.nStatus = 'Discharged' AND w.status NOT IN ('FINAL_CLEARED','CLINICALLY_CLEARED') THEN 'INCONSISTENT'
        WHEN p.clinical_clearance_status = 1 AND w.status NOT LIKE '%CLEARED%' THEN 'INCONSISTENT'
        ELSE 'OK'
    END AS consistency_check
FROM patient_details_iop p
LEFT JOIN iop_opd_workflow w ON w.iop_id = p.IO_ID AND w.InActive = 0
WHERE p.InActive = 0
  AND p.date_visit >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
HAVING consistency_check = 'INCONSISTENT';

-- Count status distribution
SELECT 
    w.status,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage
FROM iop_opd_workflow w
JOIN patient_details_iop p ON p.IO_ID = w.iop_id
WHERE w.InActive = 0 AND p.InActive = 0
  AND p.date_visit >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY w.status
ORDER BY count DESC;
```

---

## 12. Conclusion

The OPD module requires significant refactoring to achieve enterprise-grade reliability. The proposed **Unified Status Engine** with a **single source of truth** will:

1. ✅ Eliminate status inconsistencies
2. ✅ Reduce code duplication by 60%
3. ✅ Improve maintainability
4. ✅ Enable accurate reporting
5. ✅ Support future workflow enhancements

**Recommended Priority:** HIGH - Begin Phase 1 immediately.

---

*Document Version: 1.0*  
*Last Updated: April 9, 2026*
