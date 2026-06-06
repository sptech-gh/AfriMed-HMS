<?php

class NursingRuntimeEvidenceEmitter
{
    protected $evidenceRoot;
    protected $verdictClassifier;
    protected $auditSink;

    public function __construct($evidenceRoot = null, NursingRuntimeVerdict $verdictClassifier = null, NursingRuntimeAuditSink $auditSink = null)
    {
        $this->evidenceRoot = $evidenceRoot !== null && trim((string)$evidenceRoot) !== ''
            ? rtrim((string)$evidenceRoot, DIRECTORY_SEPARATOR)
            : $this->defaultEvidenceRoot();
        $this->verdictClassifier = $verdictClassifier !== null ? $verdictClassifier : new NursingRuntimeVerdict();
        $this->auditSink = $auditSink !== null ? $auditSink : new NursingRuntimeAuditSink();
    }

    public function emitVitalsExecutionEvidence(ClinicalMutationRequest $request, array $result, array $artifacts)
    {
        $bundleDir = $this->buildBundleDirectory($request, $result);
        if (!$this->ensureDirectory($bundleDir)) {
            return array(
                'status' => 'SKIPPED',
                'decision' => 'EVIDENCE_DIRECTORY_NOT_WRITABLE',
                'evidence_root' => $this->evidenceRoot,
                'bundle_dir' => $bundleDir,
                'files' => array(),
            );
        }

        $verdict = $this->verdictClassifier->classify($result, $artifacts);
        $files = array(
            'runtime_request.json' => $this->requestSummary($request),
            'runtime_decision.json' => array('verdict' => $verdict, 'artifacts' => $this->decisionArtifacts($artifacts)),
            'runtime_outcome.json' => $this->resultSummary($result),
            'runtime_failure.json' => $this->failureSummary($result),
            'runtime_recovery.json' => $this->recoverySummary($result, $artifacts),
            'runtime_execution_trace.json' => array(
                'request' => $this->requestSummary($request),
                'result' => $this->resultSummary($result),
                'verdict' => $verdict,
                'runtime_action_trace' => isset($result['runtime_action_trace']) ? $result['runtime_action_trace'] : array(),
            ),
            'authorization_evaluation.json' => $this->artifactOrPlaceholder($artifacts, 'authorization_evaluation'),
            'precondition_evaluation.json' => $this->artifactOrPlaceholder($artifacts, 'precondition_evaluation'),
            'safety_evaluation.json' => $this->artifactOrPlaceholder($artifacts, 'safety_evaluation'),
            'idempotency_classification.json' => $this->artifactOrPlaceholder($artifacts, 'idempotency_classification'),
            'idempotency_reservation.json' => $this->artifactOrPlaceholder($artifacts, 'idempotency_reservation'),
            'lock_acquisition_plan.json' => $this->artifactOrPlaceholder($artifacts, 'lock_acquisition_plan'),
            'mutation_plan.json' => $this->artifactOrPlaceholder($artifacts, 'mutation_plan'),
            'truth_recertification_plan.json' => $this->artifactOrPlaceholder($artifacts, 'truth_recertification_plan'),
        );

        $written = array();
        foreach ($files as $filename => $payload) {
            $path = $bundleDir . DIRECTORY_SEPARATOR . $filename;
            $this->writeJson($path, $this->wrapPayload($request, $result, $filename, $payload));
            $written[] = array(
                'file' => $filename,
                'path' => $path,
                'sha256' => hash_file('sha256', $path),
            );
        }

        $audit = $this->auditSink->appendRuntimeAudit($request, $result, $verdict, array(
            'bundle_dir' => $bundleDir,
            'files' => $written,
        ));
        $auditPath = $bundleDir . DIRECTORY_SEPARATOR . 'runtime_audit_chain.json';
        $this->writeJson($auditPath, $this->wrapPayload($request, $result, 'runtime_audit_chain.json', $audit));
        $written[] = array(
            'file' => 'runtime_audit_chain.json',
            'path' => $auditPath,
            'sha256' => hash_file('sha256', $auditPath),
        );

        $manifestPath = $bundleDir . DIRECTORY_SEPARATOR . 'evidence_manifest.json';
        $manifest = array(
            'schema' => 'SPRINT2D_NURSING_RUNTIME_EXECUTION_EVIDENCE_V1',
            'status' => 'PASS',
            'decision' => 'EVIDENCE_BUNDLE_WRITTEN',
            'action_type' => $request->actionType(),
            'request_id' => $request->requestId(),
            'idempotency_key_hash' => hash('sha256', $request->idempotencyKey()),
            'patient_no' => $request->patientNo(),
            'encounter_id' => $request->encounterId(),
            'mode' => isset($result['mode']) ? $result['mode'] : null,
            'mutation_performed' => false,
            'runtime_verdict' => $verdict,
            'runtime_audit' => $audit,
            'bundle_dir' => $bundleDir,
            'files' => $written,
            'created_at_utc' => gmdate('c'),
        );
        $this->writeJson($manifestPath, $manifest);

        return array(
            'status' => 'PASS',
            'decision' => 'EVIDENCE_BUNDLE_WRITTEN',
            'evidence_root' => $this->evidenceRoot,
            'bundle_dir' => $bundleDir,
            'manifest_path' => $manifestPath,
            'manifest_sha256' => hash_file('sha256', $manifestPath),
            'runtime_verdict' => $verdict,
            'runtime_audit' => $audit,
            'files' => $written,
        );
    }

    protected function decisionArtifacts(array $artifacts)
    {
        return array(
            'authorization' => $this->artifactOrPlaceholder($artifacts, 'authorization_evaluation'),
            'preconditions' => $this->artifactOrPlaceholder($artifacts, 'precondition_evaluation'),
            'truth_context' => $this->artifactOrPlaceholder($artifacts, 'truth_context_evaluation'),
            'safety' => $this->artifactOrPlaceholder($artifacts, 'safety_evaluation'),
            'idempotency' => $this->artifactOrPlaceholder($artifacts, 'idempotency_classification'),
            'reservation' => $this->artifactOrPlaceholder($artifacts, 'idempotency_reservation'),
            'locks' => $this->artifactOrPlaceholder($artifacts, 'lock_acquisition_plan'),
        );
    }

    protected function failureSummary(array $result)
    {
        return array(
            'status' => isset($result['status']) ? $result['status'] : null,
            'code' => isset($result['code']) ? $result['code'] : null,
            'blocked' => isset($result['status']) && strtolower((string)$result['status']) !== 'success',
            'errors' => isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : array(),
            'warnings' => isset($result['warnings']) && is_array($result['warnings']) ? $result['warnings'] : array(),
        );
    }

    protected function recoverySummary(array $result, array $artifacts)
    {
        return array(
            'recovery_required' => isset($result['status']) && strtolower((string)$result['status']) !== 'success',
            'mutation_performed' => false,
            'idempotency_reservation' => $this->artifactOrPlaceholder($artifacts, 'idempotency_reservation'),
            'idempotency_reservation_release' => $this->artifactOrPlaceholder($artifacts, 'idempotency_reservation_release'),
            'lock_state' => $this->artifactOrPlaceholder($artifacts, 'lock_acquisition_plan'),
            'truth_recertification' => $this->artifactOrPlaceholder($artifacts, 'truth_recertification_plan'),
        );
    }

    protected function artifactOrPlaceholder(array $artifacts, $key)
    {
        if (isset($artifacts[$key])) {
            return $artifacts[$key];
        }
        return array(
            'status' => 'SKIPPED',
            'decision' => 'ARTIFACT_NOT_REACHED',
            'artifact' => $key,
        );
    }

    protected function wrapPayload(ClinicalMutationRequest $request, array $result, $filename, $payload)
    {
        return array(
            'schema' => 'SPRINT2D_NURSING_RUNTIME_EXECUTION_EVIDENCE_V1',
            'artifact' => $filename,
            'action_type' => $request->actionType(),
            'request_id' => $request->requestId(),
            'patient_no' => $request->patientNo(),
            'encounter_id' => $request->encounterId(),
            'mode' => isset($result['mode']) ? $result['mode'] : null,
            'mutation_performed' => false,
            'created_at_utc' => gmdate('c'),
            'payload' => $payload,
        );
    }

    protected function requestSummary(ClinicalMutationRequest $request)
    {
        return array(
            'request_id' => $request->requestId(),
            'action_type' => $request->actionType(),
            'idempotency_key_hash' => hash('sha256', $request->idempotencyKey()),
            'operation_fingerprint' => $request->operationFingerprint(),
            'patient_no' => $request->patientNo(),
            'encounter_id' => $request->encounterId(),
            'actor_user_id' => $request->actorUserId(),
            'actor_role' => $request->actorRole(),
            'payload_hash' => $request->payloadHash(),
            'truth_hash' => $request->truthHash(),
            'source_controller' => $request->sourceController(),
            'source_method' => $request->sourceMethod(),
        );
    }

    protected function resultSummary(array $result)
    {
        return array(
            'status' => isset($result['status']) ? $result['status'] : null,
            'code' => isset($result['code']) ? $result['code'] : null,
            'message' => isset($result['message']) ? $result['message'] : null,
            'mutation_performed' => false,
            'dry_run' => isset($result['dry_run']) ? $result['dry_run'] : true,
            'mode' => isset($result['mode']) ? $result['mode'] : null,
            'governance_observed' => isset($result['governance_observed']) ? $result['governance_observed'] : true,
            'warning_count' => isset($result['warnings']) && is_array($result['warnings']) ? count($result['warnings']) : 0,
            'error_count' => isset($result['errors']) && is_array($result['errors']) ? count($result['errors']) : 0,
            'started_at_utc' => isset($result['started_at_utc']) ? $result['started_at_utc'] : null,
            'completed_at_utc' => isset($result['completed_at_utc']) ? $result['completed_at_utc'] : null,
        );
    }

    protected function buildBundleDirectory(ClinicalMutationRequest $request, array $result)
    {
        $timestamp = gmdate('Ymd_His');
        $status = isset($result['code']) ? $this->safeSegment($result['code']) : 'UNKNOWN_STATUS';
        $patient = $this->safeSegment($request->patientNo());
        $encounter = $this->safeSegment($request->encounterId());
        $fingerprint = substr(hash('sha256', $request->operationFingerprint()), 0, 12);
        return $this->evidenceRoot . DIRECTORY_SEPARATOR . $timestamp . '_' . $patient . '_' . $encounter . '_' . $status . '_' . $fingerprint;
    }

    protected function defaultEvidenceRoot()
    {
        $repoRoot = dirname(dirname(dirname(dirname(__FILE__))));
        return $repoRoot . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'sprint2d' . DIRECTORY_SEPARATOR . 'evidence' . DIRECTORY_SEPARATOR . 'nursing_runtime_execution';
    }

    protected function ensureDirectory($path)
    {
        if (is_dir($path)) {
            return is_writable($path);
        }
        return mkdir($path, 0775, true);
    }

    protected function writeJson($path, array $payload)
    {
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function safeSegment($value)
    {
        $segment = preg_replace('/[^A-Za-z0-9_\-]/', '_', trim((string)$value));
        return $segment === '' ? 'NA' : $segment;
    }
}
