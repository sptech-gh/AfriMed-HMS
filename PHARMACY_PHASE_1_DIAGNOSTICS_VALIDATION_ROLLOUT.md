# Pharmacy Phase 1 Diagnostics Validation and Rollout

## Scope

Phase 1 is diagnostics-only. It must not block dispensing, billing, stock adjustment, expiry removal, invoice creation, invoice update, or payment workflows.

## Feature Flags

All pharmacy hardening flags are default OFF in `application/config/hms.php`:

- `PHARMACY_BILLING_PARITY_DIAGNOSTICS_ENABLED`
- `PHARMACY_OVERRIDE_AUDIT_REQUIRED`
- `PHARMACY_ENFORCE_DISPENSE_PAYMENT_MATCH`
- `PHARMACY_BLOCK_INVOICE_EDIT_WHEN_RX_VERIFIED`
- `PHARMACY_STRICT_PRICE_ON_LEGACY_INVOICE_LINES`
- `PHARMACY_STRICT_QTY_MATCH_RX_ON_LEGACY_INVOICE_LINES`
- `BILLING_ENFORCE_INVOICE_FINALIZATION_LOCK`

Only `PHARMACY_BILLING_PARITY_DIAGNOSTICS_ENABLED` should be enabled during Phase 1 observation.

## Rollout Steps

1. Confirm syntax checks pass for changed files.
2. Confirm `billing_reconciliation_log` exists.
3. Enable diagnostics in a non-production environment using an environment variable override:

   - Set `PHARMACY_BILLING_PARITY_DIAGNOSTICS_ENABLED=true` for the PHP runtime.
   - Restart the web server (Apache) so PHP picks up the environment.

   Notes:

   - The environment variable overrides the default `application/config/hms.php` value.
   - On Apache, you can typically set this via `SetEnv PHARMACY_BILLING_PARITY_DIAGNOSTICS_ENABLED true` in the vhost/config (or equivalent), then restart.
4. Run the workflow scenarios below.
5. Review `billing_reconciliation_log` entries with `department = 'PHARMACY'`.
6. Confirm no user-facing workflow is blocked or delayed.
7. If stable, enable diagnostics during a low-volume production window.
8. Monitor for 3-7 business days before considering Phase 2 enforcement flags.

## Validation Scenarios

### Prescription and Billing

- Create a verified pharmacy prescription and generate invoice lines.
- Update an invoice containing a verified prescription line.
- Confirm any diagnostics are recorded as `PHARMACY_POST_VERIFICATION_INVOICE_EDIT`, not blocked.

### Price Parity

- Generate pharmacy invoice lines using current catalog pricing.
- Confirm no `PHARMACY_INVOICE_PRICE_DRIFT` appears for valid pricing.
- Test a controlled price mismatch in non-production and confirm diagnostic logging only.

### Quantity Parity

- Create a prescription with known `total_qty`.
- Generate an invoice line with matching quantity.
- Test a controlled invoice quantity above prescription quantity in non-production.
- Confirm `PHARMACY_INVOICE_QTY_EXCEEDS_RX` appears and invoice still saves.

### Dispense Payment Parity

- Fully paid prescription: dispense full quantity and confirm no mismatch diagnostic.
- Partial payment: dispense only paid-safe quantity and confirm no mismatch diagnostic.
- Controlled over-dispense in non-production, if workflow permits, should log `PHARMACY_DISPENSE_PAYMENT_MISMATCH` or `PHARMACY_DISPENSE_EXCEEDS_RX` without changing behavior.

### Exception Modes

- Mark external purchase, unable to pay, deferred, waived, emergency, or admitted dispense mode.
- Confirm `PHARMACY_DISPENSE_EXCEPTION_MODE_USED` logs as a breadcrumb only.

### Stock Adjustments

- Admin restock.
- Admin write-off.
- Non-admin stock request approval flow.
- Confirm manual adjustments log `PHARMACY_STOCK_ADJUSTMENT_SHADOW` only when diagnostics are enabled.

### Expiry Removal

- Remove an expired batch.
- Confirm existing stock audit is preserved.
- Confirm `PHARMACY_EXPIRED_BATCH_REMOVAL_SHADOW` logs only when diagnostics are enabled.

### Extended Hospital-Grade Scenarios

1. Verified prescription -> invoice generation.
2. Partial payment -> partial dispense.
3. Attempted over-dispense.
4. Invoice edit after pharmacist verification.
5. Stock adjustment approval workflow.
6. Expiry alert generation (<90 days).
7. Concurrent dispensing attempts.
8. Waived/emergency dispense path.
9. Receipt creation + invoice mutation attempt.
10. Rollback test:

    - Remove `PHARMACY_BILLING_PARITY_DIAGNOSTICS_ENABLED`.
    - Restart Apache.
    - Verify diagnostics fully disable.

## Operational Monitoring Checkpoints

Monitor for:

- performance regressions
- excessive log volume
- duplicate reconciliation entries
- transaction latency increases
- lock contention during dispensing

## Baseline Metrics to Capture Before Phase 2

Capture baseline metrics with diagnostics enabled (observation only):

- normal dispense rate
- expected reconciliation variance
- average override frequency
- average partial-fill frequency
- average pharmacist verification turnaround time

## Additional Monitoring Dimensions

- average dispense transaction duration
- invoice save/update latency
- reconciliation query execution time
- deadlock frequency
- DB write amplification
- pharmacy queue throughput before/after diagnostics

## Retention Policy Recommendation

Diagnostics logs should be treated as operational audit data and governed explicitly:

- retain detailed diagnostics rows for 30-90 days (local policy)
- retain summarized aggregates longer if required for finance/audit governance
- if exporting logs externally, consider hashing sensitive references (patient/encounter) according to hospital policy

## Monitoring Queries

```sql
SELECT recon_date, issue_type, record_ref, patient_no, encounter_id, created_at, details
FROM billing_reconciliation_log
WHERE department = 'PHARMACY'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC;
```

```sql
SELECT issue_type, COUNT(*) AS total
FROM billing_reconciliation_log
WHERE department = 'PHARMACY'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY issue_type
ORDER BY total DESC;
```

## Rollback

Rollback is configuration-only:

1. Set `PHARMACY_BILLING_PARITY_DIAGNOSTICS_ENABLED` to `false`.
2. Clear application/config cache if applicable.
3. No database rollback is required.
4. Existing diagnostic rows may remain as audit evidence.

## Phase 2 Entry Criteria

Do not enable enforcement flags until:

- Diagnostics have run through representative pharmacy shifts.
- False positives are reviewed and explained.
- Billing, pharmacy, and inventory leads approve the issue categories.
- Rollback path is rehearsed.
- Patient safety and operational continuity sign-off is documented.
