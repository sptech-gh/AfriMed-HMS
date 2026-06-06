# Phase 4 — SSOT Consolidation Rollout Guide

This document is the operational checklist for safely turning on each Phase 4 feature flag.

> **Read this first:** every Phase 4 change ships **dark** by default. The code is deployed, but every flag is `FALSE`. Behavior on the wire is identical to what you had before Phase 4. You decide when each flag flips.

---

## 1. The flags at a glance

All flags live in `application/config/config.php`. Every one defaults to `FALSE`.

| # | Flag | What it does when `TRUE` | Risk |
|---|---|---|---|
| 4 | `BILLING_FACADE_RECEIPT_ROUTE` | Routes all receipt recording through `Billing_facade_model::record_payment()` (the consolidated chokepoint). | Medium |
| 5 | `BILLING_FACADE_STRICT_HEADER` | Rejects an invoice save when submitted header total ≠ SUM(line items). | Low–Medium |
| 7 | `BILLING_FACADE_NHIS_CLAIM_SSOT` | Rejects an NHIS claim whose totals disagree with `billing_transactions` (or the `iop_billing` fallback). | Low |
| 9 | `BILLING_FACADE_COMPANY_PRICING_SWEEP` | For COMPANY-payer invoices, re-prices every line through the company's `pricing_percentage` (lab, imaging, IPD, services, drugs). | **High — changes invoice totals** |
| 10 | `BILLING_FACADE_IMMUTABLE_AFTER_RECEIPT` | Blocks invoice edits once at least one active receipt exists. | Low |

Each flag also reads the corresponding environment variable (e.g. `BILLING_FACADE_STRICT_HEADER=1`) so you can flip it without editing files.

---

## 2. Enable order — the recommended sequence

Don't enable everything at once. The order below is what minimises risk.

```
Day 0:  Deploy. All flags OFF. Audit channels start populating.
Day 1:  Run the audit queries (Section 4). Confirm zero unexpected events.
Day 2:  Enable Step 5 (STRICT_HEADER).             ← cheapest win
Day 3:  Enable Step 7 (NHIS_CLAIM_SSOT).            ← prevents wrong claims
Day 4:  Enable Step 4 (RECEIPT_ROUTE) after manual  ← needs UI smoke test
        validation in Section 5.
Day 5:  Enable Step 10 (IMMUTABLE_AFTER_RECEIPT).   ← UX-visible
Week 2: Enable Step 9 (COMPANY_PRICING_SWEEP) only  ← real money change
        after running Section 6.
```

If any step's audit shows unexpected mismatches, **stop**. Investigate before flipping the next flag.

---

## 3. Pre-flight (Day 0–1) — verify the deploy

Run this from the project root once after deploying:

```powershell
php -l application\models\app\Billing_facade_model.php
php -l application\models\app\nhis_model.php
php -l application\config\config.php
```

All three must report `No syntax errors detected`.

Then check that the audit table is taking writes:

```sql
SELECT COUNT(*) AS rows_today
FROM billing_audit_log
WHERE performed_at >= CURDATE();
```

Number should grow as users save invoices. If it's stuck at zero, audit logging is broken — **do not flip any flag**.

---

## 4. Audit queries — your daily dashboard

Run these at the start of each day during rollout. Names match the action enums emitted by the facade.

### 4.1 Top-level health check

```sql
SELECT action, COUNT(*) AS n,
       MIN(performed_at) AS first_seen,
       MAX(performed_at) AS last_seen
FROM billing_audit_log
WHERE performed_at >= NOW() - INTERVAL 1 DAY
  AND action IN (
    'HEADER_LINE_CHECK',           'HEADER_LINE_MISMATCH',           'HEADER_LINE_MISMATCH_DETAIL',
    'NHIS_CLAIM_TOTAL_CHECK',      'NHIS_CLAIM_TOTAL_MISMATCH',      'NHIS_CLAIM_TOTAL_MISMATCH_DETAIL',
    'NHIS_CLAIM_NO_SSOT',
    'COMPANY_PRICING_ADJUSTMENT',  'COMPANY_PRICING_SKIPPED',
    'INVOICE_EDIT_AFTER_RECEIPT',  'INVOICE_EDIT_BLOCKED',
    'INVOICE_GENERATED_FROM_QUEUE',
    'NHIS_TEST_ENDPOINT_BLOCKED',  'NHIS_TEST_ENDPOINT_INVOKED'
  )
GROUP BY action
ORDER BY n DESC;
```

What you want to see:

- `HEADER_LINE_CHECK` rising steadily — proves Step 5 verifier is running.
- `NHIS_CLAIM_TOTAL_CHECK` rising on claim days — proves Step 7 verifier is running.
- `HEADER_LINE_MISMATCH` ideally **zero**. If non-zero before flipping `STRICT_HEADER`, look at the detail row to see who and why.
- `NHIS_CLAIM_TOTAL_MISMATCH` ideally **zero**.
- `NHIS_TEST_ENDPOINT_BLOCKED` ideally **zero**. Anything > 0 means someone or something is probing the quarantined test endpoint.

### 4.2 Drill-down on header mismatches (Step 5 readiness)

```sql
SELECT performed_at, record_id AS invoice_no,
       old_value AS submitted_total, new_value AS computed_total,
       performed_by, patient_no
FROM billing_audit_log
WHERE action = 'HEADER_LINE_MISMATCH'
ORDER BY performed_at DESC
LIMIT 50;
```

Acceptance gate for flipping `STRICT_HEADER`: zero rows in the last 48 hours.

### 4.3 Drill-down on NHIS claim mismatches (Step 7 readiness)

```sql
SELECT performed_at, record_id AS encounter_id,
       old_value AS claim_nhis, new_value AS ssot_nhis,
       performed_by, patient_no
FROM billing_audit_log
WHERE action = 'NHIS_CLAIM_TOTAL_MISMATCH'
ORDER BY performed_at DESC
LIMIT 50;
```

Acceptance gate for flipping `NHIS_CLAIM_SSOT`: zero rows in the last 48 hours.

### 4.4 No-SSOT encounters (after Phase 4 deploy)

```sql
SELECT performed_at, record_id AS encounter_id, patient_no
FROM billing_audit_log
WHERE action = 'NHIS_CLAIM_NO_SSOT'
ORDER BY performed_at DESC
LIMIT 50;
```

Each row is an NHIS claim where neither `billing_transactions` nor the `iop_billing` fallback had data. Investigate — these visits aren't reaching the SSOT and any per-line audit you do later will miss them.

### 4.5 Receipted-invoice edits (Step 10 readiness)

```sql
SELECT performed_at, record_id AS invoice_no,
       JSON_EXTRACT(new_value, '$.receipt_count') AS receipts,
       performed_by
FROM billing_audit_log
WHERE action = 'INVOICE_EDIT_AFTER_RECEIPT'
ORDER BY performed_at DESC
LIMIT 50;
```

Acceptance gate for flipping `IMMUTABLE_AFTER_RECEIPT`: zero rows in the last 48 hours, or every row is from staff who you're confident should not have been editing receipted invoices.

### 4.6 Company-pricing dry run (Step 9 readiness)

After enabling `COMPANY_PRICING_SWEEP` for a soak day, see what changed:

```sql
SELECT performed_at, record_id AS invoice_no,
       JSON_EXTRACT(new_value, '$.count') AS lines_adjusted,
       performed_by, patient_no
FROM billing_audit_log
WHERE action = 'COMPANY_PRICING_ADJUSTMENT'
ORDER BY performed_at DESC
LIMIT 50;
```

And the lines the sweep couldn't resolve:

```sql
SELECT performed_at, record_id AS invoice_no,
       JSON_EXTRACT(new_value, '$.count') AS lines_skipped,
       performed_by, patient_no
FROM billing_audit_log
WHERE action = 'COMPANY_PRICING_SKIPPED'
ORDER BY performed_at DESC
LIMIT 50;
```

If `SKIPPED.count` is high, drill into the JSON `skipped[]` array — each element has `item_name`, `qty`, `rate`, and a `reason`. Common reasons: `no_catalog_match` (the line's `bill_name` doesn't match any active row in `bill_particular` or `medicine_drug_name`).

---

## 5. Step 4 manual smoke test — receipt route

Before flipping `BILLING_FACADE_RECEIPT_ROUTE = TRUE`:

1. **Have a known invoice with a known balance.**
   Pick a single OPD invoice whose patient details and balance you can identify in the UI.

2. **Run a normal payment in the cashier UI** (flag still OFF).
   Note the receipt number.

3. **Verify ledger lands as expected.**
   ```sql
   SELECT receipt_no, invoice_no, amount_paid, payment_method, dDate
   FROM iop_receipt
   WHERE receipt_no = 'YOUR_RECEIPT_NO';

   SELECT * FROM cashier_payment_log
   WHERE receipt_no = 'YOUR_RECEIPT_NO';
   ```
   Confirm both rows exist with the right amount.

4. **Flip the flag.**
   ```php
   $config['BILLING_FACADE_RECEIPT_ROUTE'] = TRUE;
   ```

5. **Repeat steps 1–3 with another invoice.**
   You should see **identical row shapes** in the same tables. The receipt path now goes through the facade.

6. **Compare audit:** the new path emits a different audit signature. Look for a row in `billing_audit_log` close to the receipt's timestamp tagged `RECEIPT_RECORDED` (or whatever the consolidated path emits) and confirm the JSON payload contains `receipt_no` and `invoice_no` matching what you saw in the UI.

7. **If anything looks wrong:** flip `BILLING_FACADE_RECEIPT_ROUTE = FALSE` immediately. The legacy path is unchanged and resumes on the next request.

---

## 6. Step 9 dry-run protocol — *don't skip this*

`COMPANY_PRICING_SWEEP` is the **only flag that changes invoice totals**. Treat it as a real release.

### 6.1 Choose one company patient with a clean test invoice

```sql
-- Find a recent COMPANY-payer invoice you can re-create.
SELECT invoice_no, patient_no, total_amount, dDate
FROM iop_billing
WHERE payer_type = 'COMPANY' AND InActive = 0
ORDER BY dDate DESC LIMIT 10;
```

### 6.2 Capture the "before" totals

```sql
SELECT id, bill_name, qty, rate, amount
FROM iop_billing_t
WHERE invoice_no = 'YOUR_INVOICE' AND InActive = 0
ORDER BY id;
```

Save this output to a text file. You will diff against it.

### 6.3 Calculate the expected "after"

For each line, look up the catalog entry and compute:

```
expected_rate   = catalog_unit_price * (company.pricing_percentage / 100)
expected_amount = expected_rate * qty
```

`catalog_unit_price` is from `bill_particular.particular_price` for services or `medicine_drug_name.nPrice` for drugs. `company.pricing_percentage` is on the `companies` table for the patient's company.

### 6.4 Flip the flag, edit the same invoice

1. `$config['BILLING_FACADE_COMPANY_PRICING_SWEEP'] = TRUE;`
2. In the UI, open the invoice and click **Save** (no changes — the resave triggers the sweep).
3. Re-run the SQL from 6.2.

Diff the two outputs. The sweep should have moved each `rate` and `amount` to your computed expected values, **except** for any lines that show up in the `COMPANY_PRICING_SKIPPED` audit row (those need their `bill_name` cleaned up in the source catalog).

### 6.5 Stop and investigate if

- Any line *increased* unexpectedly. Company pricing should always reduce or match (assuming `pricing_percentage <= 100`).
- The header total in `iop_billing.total_amount` doesn't equal the new SUM of line `amount`s. If this happens, Step 5 verifier should have already corrected it; check `HEADER_LINE_MISMATCH_DETAIL` for the same invoice_no.
- The `SKIPPED` count is large enough that the sweep isn't really doing its job. Fix catalog name mismatches first, then retry.

### 6.6 Roll back

If the test invoice is wrong:

1. `$config['BILLING_FACADE_COMPANY_PRICING_SWEEP'] = FALSE;`
2. Manually correct that invoice's lines via the UI, OR re-run the original `Save` action in the UI (which re-saves the user-supplied rates).

---

## 7. Per-flag rollback

| Flag | Rollback action |
|---|---|
| `RECEIPT_ROUTE` | Set `FALSE`. Existing receipts already in `iop_receipt` are unchanged. |
| `STRICT_HEADER` | Set `FALSE`. Mismatched headers go back to silent-correct (with audit). |
| `NHIS_CLAIM_SSOT` | Set `FALSE`. Mismatched claims go back to insert-and-audit. |
| `COMPANY_PRICING_SWEEP` | Set `FALSE`. **Already-adjusted invoices stay adjusted** — they're permanent edits. To unwind, manually re-edit affected invoices. The audit trail (`COMPANY_PRICING_ADJUSTMENT`) tells you exactly which lines and what values to restore. |
| `IMMUTABLE_AFTER_RECEIPT` | Set `FALSE`. Edit attempts on receipted invoices go back to audit-only. |

---

## 8. Known limitations & their compensating controls

These are the residual gaps after this rollout. They are deliberate scope decisions, not bugs.

### 8.1 Step 9: by-name catalog lookup
**What:** the company-pricing sweep matches an `iop_billing_t` line to a catalog entry by **name**. If the line's `bill_name` doesn't exactly match a row in `bill_particular.particular_name` or `medicine_drug_name.drug_name`, the line is skipped.

**Mitigation in code (now):**
- The sweep uses `billing_transactions.item_type` for the same invoice as a **type hint**, so a service named `Paracetamol Drip` no longer accidentally matches a drug named `Paracetamol`.
- Every skipped line is audited as `COMPANY_PRICING_SKIPPED` with the reason and the offending name.

**Mitigation operationally:** monitor `COMPANY_PRICING_SKIPPED.count` after enabling Step 9. Clean up name mismatches in the catalogs as they appear.

**Real fix (out of Phase 4 scope):** add `source_module` and `source_ref` columns to `iop_billing_t`, populated at line-insert time. Then resolution becomes deterministic.

### 8.2 Step 7: encounters with no SSOT data
**What:** if `billing_transactions` has no rows for an encounter (legacy data, or modules whose `sync_*` hooks weren't yet wired) and `iop_billing` for that encounter also has no NHIS columns populated, the verifier silently passes the claim — even in strict mode — because there's nothing to compare against.

**Mitigation in code (now):**
- Fallback to `iop_billing.nhis_covered_amount` + `iop_billing.patient_payable_amount` when `billing_transactions` is empty.
- Emit `NHIS_CLAIM_NO_SSOT` audit event when both sources are empty, so operators see exactly which encounters are uncovered.

**Mitigation operationally:** monitor `NHIS_CLAIM_NO_SSOT` weekly. Encounters appearing here mean the corresponding module's order-placement code is not calling its `billing_transaction_model::sync_*` method.

### 8.3 Step 10: facade-only enforcement
**What:** the immutability check only fires for invoice updates that go through `Billing_facade_model::update_invoice_from_post()`. Any other code that writes to `iop_billing` directly bypasses it.

**Mitigation in code (now):** new public method `Billing_facade_model::assert_invoice_editable($invoice_no, $user_id, $patient_no, $iop_no)`. Any controller or model that mutates an invoice can call it before writing.

**Recommended call sites (to audit and add the guard to):**
```powershell
# From the project root, list places that update iop_billing directly:
findstr /spin /c:"update('iop_billing'" application\
findstr /spin /c:"->update('iop_billing_t'" application\
```
For each hit that performs an update intended as an *edit* (not the facade's archival flag flip), wrap it with:
```php
$g = $this->billing_facade_model->assert_invoice_editable($invoice_no, $user_id);
if (!$g['ok']) {
    // surface $g['error'] to the user and bail
    return;
}
```

**Real fix (out of Phase 4 scope):** a database-level trigger on `iop_billing` that rejects UPDATE if active receipts exist and a session variable signals strict mode is on.

---

## 9. Quarantined test endpoint

`application/controllers/api/Nhis_workflow_test.php` was a hidden direct-writer of billing tables. It's now gated:

- **Default:** every URL on this controller returns HTTP 403 + JSON.
- **Unblock locally:** in Apache (Laragon), set `SetEnv NHIS_TEST_ENDPOINT_ENABLED 1` and confirm `index.php` has `define('ENVIRONMENT', 'development');`. Restart Apache.
- **Production:** the endpoint is unreachable regardless of env var, because the gate also requires `ENVIRONMENT !== 'production'`.

Every access attempt — blocked or not — is audited as `NHIS_TEST_ENDPOINT_BLOCKED` or `NHIS_TEST_ENDPOINT_INVOKED`. If you see `BLOCKED` rows on the production audit table, someone is probing the route.

---

## 10. Quick reference — file index

| File | Purpose |
|---|---|
| `application/config/config.php` | All five Phase 4 flags. |
| `application/models/app/Billing_facade_model.php` | The chokepoint. All Phase 4 helpers live here. |
| `application/models/app/billing_model.php` | Legacy model. `log_nhis_audit()` is what writes audit rows. |
| `application/models/app/nhis_model.php` | `generate_claim()` calls the Step 7 verifier. |
| `application/models/app/billing_transaction_model.php` | The `billing_transactions` SSOT and its `sync_*` writers. |
| `application/controllers/api/Nhis_workflow_test.php` | Quarantined test controller. |
| `docs/PHASE4_ROLLOUT.md` | This file. |

---

## 11. Emergency stop

If something is clearly wrong and you don't know which flag is the culprit, set them all back to `FALSE` in `config.php`:

```php
$config['BILLING_FACADE_RECEIPT_ROUTE']            = FALSE;
$config['BILLING_FACADE_STRICT_HEADER']            = FALSE;
$config['BILLING_FACADE_NHIS_CLAIM_SSOT']          = FALSE;
$config['BILLING_FACADE_COMPANY_PRICING_SWEEP']    = FALSE;
$config['BILLING_FACADE_IMMUTABLE_AFTER_RECEIPT']  = FALSE;
```

Save the file. CodeIgniter re-reads config on the next request — no restart needed. The system is now in pre-Phase-4 behavior. Look at `billing_audit_log` for the last hour to identify the trigger, then bring the flags back one at a time.
