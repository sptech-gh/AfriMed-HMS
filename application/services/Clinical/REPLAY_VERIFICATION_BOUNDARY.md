# Clinical Replay Verification Boundary

## Purpose

The Clinical Replay verification commands are read-only validation tools for proving replay consistency before production activation.

They are not part of the clinical truth model and must not become write-path dependencies.

## Authoritative replay inputs

Replay reconstruction may depend only on:

- `clinical_events`
- Clinical domain tables linked by `event_id`
- Deterministic `stream_version` ordering

Replay reconstruction must not depend on:

- Idempotency records
- Outbox tables
- Shadow Governance logs
- Legacy HMS tables
- Cache state
- Session state
- Current active shift flags
- Runtime controller state

## Fingerprint rule

Replay fingerprints are verification artifacts only.

They must:

- Be computed at runtime
- Be derived from replay input/output
- Be disposable
- Be safe to regenerate

They must never:

- Be persisted as authoritative clinical truth
- Replace `stream_version` ordering
- Become primary keys or identity systems
- Be used to reconstruct clinical state
- Be used as write-path guards
- Be treated as stable across fingerprint algorithm versions

## Determinism contract

The determinism harness validates two layers:

1. Stream integrity
   - Event sequence
   - `stream_version`
   - Event identity
   - Domain mapping
   - Correction chain structure
   - Anomaly structure

2. Output integrity
   - Canonical replay payload hash
   - Effective clinical state
   - Derived balance/result structures

A replay pass requires both stream integrity and output integrity to pass.

## Activation rule

The replay engine may be used for CLI verification before schema activation.

It must not be wired into UI/controllers until:

- Phase 3 schema installer exists
- Phase 3 schema verifier passes
- Clinical write service contracts are implemented
- CTM/idempotency boundary exists for write paths
- A controlled facade/service entry point exists for read paths
