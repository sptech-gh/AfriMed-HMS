# HMS LAB SSOT + DISPOSITION — WINDSURF IMPLEMENTATION GUARDRAILS PACK v1.0

## Purpose
This pack translates `HMS-LAB-SSOT-DISP-v1.2.1` into enforceable engineering boundaries inside the codebase:

- File-level control points
- Method-level enforcement hooks
- Anti-pattern blocking rules
- Review/CI guardrails
- Runtime safety expectations

## Enforcement layers

### Layer 1 — Model-level enforcement (primary)
All SSOT writes MUST pass through canonical chokepoints.

#### Financial SSOT
- `application/models/app/billing_transaction_model.php`
  - `sync_lab_request()`
  - `link_transactions_to_invoice()`

#### Payment flow
- `application/models/app/Billing_facade_model.php`
  - `record_payment()`
  - `save_invoice_from_post()`
  - `update_invoice_from_post()`

#### Disposition flow (mandatory single chokepoint)
- `application/models/app/billing_disposition_model.php`
  - `append_event()`
  - `validate_transition()`
  - `resolve_current_state()`

If this file does not exist, it is a mandatory creation artifact.

### Layer 2 — Controller-level restrictions (no business logic)
Controllers MUST NEVER:

- Insert into `billing_transactions`
- Insert into `billing_dispositions`
- Mutate financial fields
- Compute state transitions

Controllers may only:

- Call service/model methods
- Pass request payload
- Return response

Violations MUST be flagged in review and MUST fail guardrails scan.

### Layer 3 — Service contract layer (logic boundary)
Business logic MUST live in:

- Models OR service classes

No logic allowed in:

- Controllers
- Views
- Helpers (except pure formatting)

## File-level enforcement map

### Financial core
- `application/models/app/billing_transaction_model.php`
- `application/models/app/Billing_facade_model.php`

### Disposition core
- `application/models/app/billing_disposition_model.php`

### Clinical entry points (SSOT triggers)
- `application/libraries/Billing_automation.php`
- `application/controllers/app/laboratory.php`

Allowed ONLY:

- Ensure SSOT via canonical model call
- Request disposition append via canonical model/service

Forbidden:

- Direct DB writes to SSOT tables

### Reconciliation / audit zone
- Admin reconciliation controllers/models (read-only analysis)

Allowed:

- Read-only discrepancy reporting
- Escalation generation

Forbidden:

- Writing to SSOT tables from reporting/reconciliation paths

## Anti-pattern blacklist (hard block)

### Financial anti-patterns
- Direct SQL updates to `billing_transactions` financial fields
- Controller-level inserts/updates on SSOT tables
- Bypassing `Billing_facade_model` for invoice linking/payment routing

### Disposition anti-patterns
- `UPDATE billing_dispositions`
- `DELETE FROM billing_dispositions`
- Controller-level disposition inserts
- Missing mandatory audit payload fields

### Architectural violations
- Business logic in controllers
- State transitions outside `append_event()`
- Raw SQL “quick fixes” in production execution paths

## Required function contracts (implementation locks)

### `sync_lab_request()`
Must guarantee:

- Idempotency
- Single SSOT per `io_lab_id`
- Concurrency-safe locking

### `append_event()`
Inputs must include:

- `txn_id`
- `from_state`
- `to_state`
- `actor_user_id`
- `reason`
- `source_ref`
- `correlation_id`

Must enforce:

- FSM transition validation
- Terminal rules
- Append-only write
- Audit payload completeness

### `record_payment()`
Must enforce:

- Correction boundary rules
- Canonical routing through facade when enabled

## Guardrails scan (CI/review gate)
The repository includes a guardrails scanner:

- `scripts/guardrails_lab_billing.php`

Run locally:

```bash
php scripts/guardrails_lab_billing.php
```

This scan MUST be run in PR review and SHOULD be wired into CI.

## Runtime safety expectations
System MUST:

- Fail safely (no silent corruption)
- Escalate uncertain financial states
- Preserve audit trail
- Never auto-correct financial truth

## Execution priority order
1. Ensure SSOT (`sync_lab_request`)
2. Append disposition (`append_event`)
3. Link invoice (facade only)
4. Record payment (if any)
5. Publish UI state
6. Emit reconciliation snapshot
7. Persist audit payload
