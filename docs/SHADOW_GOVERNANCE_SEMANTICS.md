# Shadow Governance Semantics (Canonical)

## Canonical Governance Lattice

The governance system uses a single canonical precedence lattice for all status aggregation:

UNPROVABLE
→ VIOLATION
→ ERROR
→ PASS

Meaning:

- UNPROVABLE dominates all lower states.
- VIOLATION dominates ERROR and PASS.
- ERROR dominates PASS.
- PASS indicates fully provable compliance.

## Single Source of Truth

All precedence comparisons and aggregation are centralized in:

- `application/libraries/ShadowGovernanceSemantics.php`

All layers call into this utility instead of implementing local ordering.

## Layer Semantics

- **Parity**: `ShadowParityEngine` aggregates check outcomes using `ShadowGovernanceSemantics::worstResult()`.
- **Proof**: `ShadowProofExecutor` aggregates write outcomes and invariant outcomes using `ShadowGovernanceSemantics::worstResult()`.
- **Proof PASS**: proof success uses `PASS` (historical `PROVEN` is normalized to `PASS` only for comparisons).
- **Severity**: `ShadowSeverityResolver` derives severity from the effective governance status (parity first; proof only when parity is PASS).
- **Alerting**: `ShadowAlertService` treats `shadow_alert_severity_threshold` as a minimum severity, not an equality match.

## Running Semantics Regression Tests

Run:

```bash
php scripts/test_shadow_governance_semantics.php
```

This verifies deterministic aggregation precedence for mixed-status cases.
