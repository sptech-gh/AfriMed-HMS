# Deprecated / Quarantined Billing Layer

Quarantined: 2026-04-26 (Phase 4, Step 2 of SSOT consolidation).

## Why

These five models constitute a parallel "Enterprise Billing" layer
(eb_transactions, eb_invoices, eb_payments, eb_service_gates,
eb_adjustments, eb_events_log) that was never wired into the active
write path used by `Billing::save_invoice()`, `cashier_model`, or
`unified_billing_model`. All `eb_*` tables were verified empty
(0 rows) on the date of quarantine.

The models also contained:
- Hardcoded placeholder pricing (`get_service_price()` returns 50,
  `get_fee()` returns hardcoded defaults) which would silently
  mis-price every charge if activated.
- Auto-running `ensure_schema()` in every constructor, mutating DB
  on every request.
- A "Revenue_analytics_model" that aggregated only the empty
  `eb_*` tables and therefore returned zeroes for every metric in
  `Unified_billing` analytics pages.

## What replaces them

- Pricing: `application/models/app/Price_engine_model.php` (Step 1).
- Charge capture / invoicing / payments chokepoint:
  `application/models/app/Billing_facade_model.php` (Step 2+).
- Authoritative SSOT ledger: `billing_transactions` (managed by
  `billing_transaction_model`).

## Files

- `Billing_engine_model.php.disabled`
- `Payment_engine_model.php.disabled`
- `Reconciliation_engine_model.php.disabled`
- `Enterprise_billing_model.php.disabled`
- `Revenue_analytics_model.php.disabled`

The `.disabled` extension prevents CodeIgniter's autoloader from
finding them. Files are retained for audit / reference. Do not
re-enable without consultation.

## Affected entry points (rewired)

- `controllers/app/Ebilling.php` — replaced with redirect-only stub
  pointing to `app/unified_billing` (sidebar links continue to work).
- `controllers/app/Unified_billing.php` — `Revenue_analytics_model`
  calls replaced with empty-array safe defaults; analytics and
  department-report pages now render with zero rows until a
  legacy-table aggregation is introduced in a later step.
- Views under `application/views/app/enterprise_billing/` are
  retained but unreachable through the controller; treat as dead.
