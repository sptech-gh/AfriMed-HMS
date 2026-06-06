<?php

class NursingVitalsMutationEngine
{
    const ACTION_TYPE = 'NURSING_VITALS_RECORDED';
    const MODE_DRY_RUN = 'DRY_RUN';
    const MODE_VALIDATE_ONLY = 'VALIDATE_ONLY';

    protected $db;
    protected $lockManager;
    protected $runtimeLockCoordinator;
    protected $idempotencyService;
    protected $idempotencyReservationService;
    protected $authorizationEvaluator;
    protected $preconditionEvaluator;
    protected $safetyEvaluator;
    protected $evidenceEmitter;
    protected $mode;

    public function __construct($db, NursingLockManager $lockManager = null, NursingIdempotencyService $idempotencyService = null, NursingAuthorizationEvaluator $authorizationEvaluator = null, NursingPreconditionEvaluator $preconditionEvaluator = null, NursingSafetyEvaluator $safetyEvaluator = null, $evidenceEmitter = null, $mode = self::MODE_DRY_RUN, $idempotencyReservationService = null, $runtimeLockCoordinator = null)
    {
        if (is_string($authorizationEvaluator) && $preconditionEvaluator === null && $safetyEvaluator === null && $evidenceEmitter === null && $mode === self::MODE_DRY_RUN) {
            $mode = $authorizationEvaluator;
            $authorizationEvaluator = null;
        }
        if (is_string($evidenceEmitter) && $mode === self::MODE_DRY_RUN) {
            $mode = $evidenceEmitter;
            $evidenceEmitter = null;
        }
        $this->db = $db;
        $this->mode = strtoupper(trim((string)$mode)) !== '' ? strtoupper(trim((string)$mode)) : self::MODE_DRY_RUN;
        $this->lockManager = $lockManager !== null ? $lockManager : new NursingLockManager($db, 60, null, true);
        $this->runtimeLockCoordinator = $runtimeLockCoordinator instanceof NursingRuntimeLockCoordinator ? $runtimeLockCoordinator : new NursingRuntimeLockCoordinator();
        $this->idempotencyService = $idempotencyService !== null ? $idempotencyService : new NursingIdempotencyService($db, true);
        $this->idempotencyReservationService = $idempotencyReservationService instanceof NursingIdempotencyReservationService ? $idempotencyReservationService : new NursingIdempotencyReservationService();
        $this->authorizationEvaluator = $authorizationEvaluator !== null ? $authorizationEvaluator : new NursingAuthorizationEvaluator();
        $this->preconditionEvaluator = $preconditionEvaluator !== null ? $preconditionEvaluator : new NursingPreconditionEvaluator($db);
        $this->safetyEvaluator = $safetyEvaluator !== null ? $safetyEvaluator : new NursingSafetyEvaluator();
        $this->evidenceEmitter = $evidenceEmitter instanceof NursingRuntimeEvidenceEmitter ? $evidenceEmitter : new NursingRuntimeEvidenceEmitter();
    }

    public static function loadDependencies()
    {
        $base = dirname(__FILE__) . DIRECTORY_SEPARATOR;
        $files = array(
            'ClinicalMutationRequest.php',
            'NursingAuthorizationEvaluator.php',
            'NursingPreconditionEvaluator.php',
            'NursingSafetyEvaluator.php',
            'NursingRuntimeVerdict.php',
            'NursingRuntimeAuditSink.php',
            'NursingRuntimeEvidenceEmitter.php',
            'NursingLockManager.php',
            'NursingRuntimeLockCoordinator.php',
            'NursingIdempotencyService.php',
            'NursingIdempotencyReservationService.php',
            'NursingShadowComparisonEngine.php',
            'NursingExecutionCertificationGate.php',
        );
        foreach ($files as $file) {
            $path = $base . $file;
            if (file_exists($path)) {
                require_once($path);
            }
        }
    }

    public function recordVitalsDryRun(array $data)
    {
        $data['action_type'] = isset($data['action_type']) && trim((string)$data['action_type']) !== '' ? $data['action_type'] : self::ACTION_TYPE;
        return $this->execute(ClinicalMutationRequest::fromArray($data));
    }

    public function execute(ClinicalMutationRequest $request)
    {
        $startedAt = gmdate('c');
        $trace = array();
        $errors = array();
        $warnings = array();
        $artifacts = array();

        $trace[] = $this->trace('REQUEST_RECEIVED', 'PASS', array(
            'request_id' => $request->requestId(),
            'action_type' => $request->actionType(),
            'mode' => $this->mode,
        ));

        $modeDecision = $this->evaluateMode();
        $trace[] = $this->trace('EXECUTION_MODE_VALIDATED', $modeDecision['status'], $modeDecision);
        if ($modeDecision['status'] !== 'PASS') {
            $errors[] = array('field' => 'mode', 'code' => $modeDecision['code'], 'message' => $modeDecision['message']);
            return $this->blockedResult($request, $trace, $warnings, $errors, $startedAt, $modeDecision['code'], $artifacts);
        }

        $envelopeErrors = $request->validateEnvelope();
        if (count($envelopeErrors) > 0) {
            $trace[] = $this->trace('REQUEST_ENVELOPE_VALIDATED', 'BLOCK', array('errors' => $envelopeErrors));
            return $this->blockedResult($request, $trace, $warnings, $envelopeErrors, $startedAt, 'REQUEST_ENVELOPE_INVALID', $artifacts);
        }
        $trace[] = $this->trace('REQUEST_ENVELOPE_VALIDATED', 'PASS', array('operation_fingerprint' => $request->operationFingerprint()));

        $actionDecision = $this->evaluateAction($request);
        $trace[] = $this->trace('ACTION_TYPE_VALIDATED', $actionDecision['status'], $actionDecision);
        if ($actionDecision['status'] !== 'PASS') {
            $errors[] = array('field' => 'action_type', 'code' => $actionDecision['code'], 'message' => $actionDecision['message']);
            return $this->blockedResult($request, $trace, $warnings, $errors, $startedAt, $actionDecision['code'], $artifacts);
        }

        $preconditions = $this->preconditionEvaluator->evaluateVitals($request);
        $artifacts['precondition_evaluation'] = $preconditions;
        $trace[] = $this->trace('PRECONDITIONS_EVALUATED', $preconditions['status'], $preconditions);
        if ($preconditions['status'] === 'BLOCK') {
            return $this->blockedResult($request, $trace, $warnings, $preconditions['errors'], $startedAt, 'PRECONDITION_BLOCKED', $artifacts);
        }
        $warnings = array_merge($warnings, $preconditions['warnings']);

        $authorization = $this->authorizationEvaluator->evaluate($request);
        $artifacts['authorization_evaluation'] = $authorization;
        $trace[] = $this->trace('AUTHORIZATION_EVALUATED', $authorization['status'], $authorization);
        if ($authorization['status'] === 'BLOCK') {
            return $this->blockedResult($request, $trace, $warnings, $authorization['errors'], $startedAt, 'AUTHORIZATION_BLOCKED', $artifacts);
        }
        $warnings = array_merge($warnings, $authorization['warnings']);

        $truth = $this->preconditionEvaluator->evaluateTruthContext($request);
        $artifacts['truth_context_evaluation'] = $truth;
        $trace[] = $this->trace('TRUTH_CONTEXT_VALIDATED', $truth['status'], $truth);
        if ($truth['status'] === 'BLOCK') {
            return $this->blockedResult($request, $trace, $warnings, $truth['errors'], $startedAt, 'TRUTH_CONTEXT_BLOCKED', $artifacts);
        }
        $warnings = array_merge($warnings, $truth['warnings']);

        $safety = $this->safetyEvaluator->evaluateVitals($request);
        $artifacts['safety_evaluation'] = $safety;
        $trace[] = $this->trace('SAFETY_EVALUATED', $safety['status'], $safety);
        if ($safety['status'] === 'BLOCK') {
            return $this->blockedResult($request, $trace, $warnings, $safety['errors'], $startedAt, 'SAFETY_BLOCKED', $artifacts);
        }
        $warnings = array_merge($warnings, $safety['warnings']);

        $idempotency = $this->idempotencyService->classifyRequest($request);
        $artifacts['idempotency_classification'] = $idempotency;
        $trace[] = $this->trace('IDEMPOTENCY_CLASSIFIED', $this->isBlockingIdempotency($idempotency) ? 'BLOCK' : 'PASS', $idempotency);
        if ($this->isBlockingIdempotency($idempotency)) {
            $errors[] = array('field' => 'idempotency_key', 'code' => $idempotency['classification'], 'message' => $idempotency['decision']);
            return $this->blockedResult($request, $trace, $warnings, $errors, $startedAt, $idempotency['classification'], $artifacts);
        }
        if (isset($idempotency['warnings']) && is_array($idempotency['warnings'])) {
            $warnings = array_merge($warnings, $idempotency['warnings']);
        }

        if ($this->mode === self::MODE_VALIDATE_ONLY) {
            $reservation = array('status' => 'SKIPPED', 'decision' => 'VALIDATE_ONLY_RESERVATION_SKIPPED', 'shadow_reservation' => true);
        } else {
            $reservation = $this->idempotencyReservationService->reserve($request, $idempotency);
        }
        $artifacts['idempotency_reservation'] = $reservation;
        $trace[] = $this->trace('IDEMPOTENCY_RESERVED', isset($reservation['status']) ? $reservation['status'] : 'BLOCK', $reservation);
        if (isset($reservation['status']) && $reservation['status'] === 'BLOCK') {
            $reservationErrors = isset($reservation['errors']) && is_array($reservation['errors']) ? $reservation['errors'] : array(array('field' => 'idempotency_key', 'code' => 'RESERVATION_BLOCKED', 'message' => 'Idempotency reservation was blocked.'));
            return $this->blockedResult($request, $trace, $warnings, $reservationErrors, $startedAt, isset($reservation['decision']) ? $reservation['decision'] : 'RESERVATION_BLOCKED', $artifacts);
        }

        $lockPlan = $this->lockManager->buildVitalsLockPlan($request);
        $runtimeLockPlan = $this->runtimeLockCoordinator->buildOrderedVitalsLockPlan($request);
        $artifacts['lock_acquisition_plan'] = array('decision' => 'LOCK_PLAN_BUILT', 'locks' => $lockPlan, 'shadow_runtime_locks' => $runtimeLockPlan);
        $trace[] = $this->trace('LOCK_PLAN_BUILT', 'PASS', array('locks' => $lockPlan, 'shadow_runtime_locks' => $runtimeLockPlan));

        if ($this->mode === self::MODE_VALIDATE_ONLY) {
            $artifacts['lock_acquisition_plan']['acquisition'] = array('status' => 'SKIPPED', 'decision' => 'VALIDATE_ONLY_LOCK_ACQUISITION_SKIPPED');
            $artifacts['lock_acquisition_plan']['shadow_acquisition'] = array('status' => 'SKIPPED', 'decision' => 'VALIDATE_ONLY_SHADOW_LOCK_ACQUISITION_SKIPPED');
            $artifacts['mutation_plan'] = array('decision' => 'VALIDATE_ONLY_NO_MUTATION', 'mutation_performed' => false);
            $artifacts['truth_recertification_plan'] = array('decision' => 'VALIDATE_ONLY_RECERTIFICATION_NOT_REQUESTED', 'truth_recertification_required' => false);
            $trace[] = $this->trace('LOCKS_ACQUIRED', 'SKIPPED', array('decision' => 'VALIDATE_ONLY_LOCK_ACQUISITION_SKIPPED'));
            $trace[] = $this->trace('MUTATION_EXECUTED', 'SKIPPED', array('decision' => 'VALIDATE_ONLY_NO_MUTATION'));
            $trace[] = $this->trace('AUDIT_EVENT_COMMITTED', 'SKIPPED', array('decision' => 'VALIDATE_ONLY_AUDIT_NOT_COMMITTED'));
            $trace[] = $this->trace('TRUTH_RECERTIFICATION_REQUESTED', 'SKIPPED', array('decision' => 'VALIDATE_ONLY_RECERTIFICATION_NOT_REQUESTED'));
            return $this->successResult($request, $trace, $warnings, $startedAt, $lockPlan, $idempotency, 'VALIDATION_PASS_NO_COMMIT', $artifacts);
        }

        $locks = $this->runtimeLockCoordinator->acquireVitalsLocks($request, $reservation);
        $artifacts['lock_acquisition_plan']['shadow_acquisition'] = $locks;
        $trace[] = $this->trace('SHADOW_LOCKS_ACQUIRED', $this->hasLockFailure($locks) ? 'BLOCK' : 'PASS', array('locks' => $locks));
        if ($this->hasLockFailure($locks)) {
            $errors[] = array('field' => 'locks', 'code' => 'LOCK_ACQUISITION_BLOCKED', 'message' => 'Lock acquisition was blocked.');
            $reservationRelease = $this->idempotencyReservationService->release($request, $reservation);
            $artifacts['idempotency_reservation_release'] = $reservationRelease;
            $trace[] = $this->trace('IDEMPOTENCY_RESERVATION_RELEASED', isset($reservationRelease['status']) ? $reservationRelease['status'] : 'SKIPPED', $reservationRelease);
            return $this->blockedResult($request, $trace, $warnings, $errors, $startedAt, 'LOCK_ACQUISITION_BLOCKED', $artifacts);
        }

        $mutationPlan = $this->buildDryRunMutationPlan($request, $safety['normalized_values']);
        $artifacts['mutation_plan'] = $mutationPlan;
        $trace[] = $this->trace('CTM_TRANSACTION_BOUNDARY_EVALUATED', 'PASS', array('decision' => 'DRY_RUN_CTM_EXECUTION_NOT_INVOKED'));
        $trace[] = $this->trace('VITALS_MUTATION_EXECUTED', 'SKIPPED', $mutationPlan);

        $audit = $this->buildDryRunAuditEvent($request, $authorization, $safety, $idempotency, $locks);
        $trace[] = $this->trace('AUDIT_EVENT_COMMITTED', 'SKIPPED', $audit);

        $truthRecertification = $this->buildDryRunTruthRecertification($request);
        $artifacts['truth_recertification_plan'] = $truthRecertification;
        $trace[] = $this->trace('TRUTH_RECERTIFICATION_REQUESTED', 'SKIPPED', $truthRecertification);

        $release = $this->runtimeLockCoordinator->releaseVitalsLocks($request, $reservation);
        $artifacts['lock_acquisition_plan']['shadow_release'] = $release;
        $trace[] = $this->trace('SHADOW_LOCKS_RELEASED', $release['status'] === 'PASS' ? 'PASS' : 'SKIPPED', $release);

        $reservationRelease = $this->idempotencyReservationService->release($request, $reservation);
        $artifacts['idempotency_reservation_release'] = $reservationRelease;
        $trace[] = $this->trace('IDEMPOTENCY_RESERVATION_RELEASED', isset($reservationRelease['status']) ? $reservationRelease['status'] : 'SKIPPED', $reservationRelease);

        return $this->successResult($request, $trace, $warnings, $startedAt, $lockPlan, $idempotency, 'DRY_RUN_PASS_NO_COMMIT', $artifacts);
    }

    protected function evaluateMode()
    {
        if ($this->mode === self::MODE_DRY_RUN || $this->mode === self::MODE_VALIDATE_ONLY) {
            return array('status' => 'PASS', 'code' => 'MODE_ALLOWED', 'message' => 'Execution mode is non-mutating.', 'mode' => $this->mode);
        }
        return array('status' => 'BLOCK', 'code' => 'LIVE_MODE_NOT_ENABLED', 'message' => 'Only DRY_RUN and VALIDATE_ONLY modes are enabled for this engine.', 'mode' => $this->mode);
    }

    protected function evaluateAction(ClinicalMutationRequest $request)
    {
        if ($request->actionType() === self::ACTION_TYPE) {
            return array('status' => 'PASS', 'code' => 'ACTION_ALLOWED', 'message' => 'Action type is supported.');
        }
        return array('status' => 'BLOCK', 'code' => 'ACTION_NOT_SUPPORTED', 'message' => 'Only NURSING_VITALS_RECORDED is supported.');
    }

    protected function isBlockingIdempotency(array $idempotency)
    {
        $classification = isset($idempotency['classification']) ? (string)$idempotency['classification'] : '';
        return in_array($classification, array('INVALID_REQUEST_ENVELOPE', 'IDEMPOTENCY_TABLE_MISSING', NursingIdempotencyService::IDEMPOTENCY_COLLISION, NursingIdempotencyService::LEASE_ACTIVE), true);
    }

    protected function hasLockFailure(array $locks)
    {
        foreach ($locks as $lock) {
            $status = isset($lock['status']) ? (string)$lock['status'] : '';
            if (in_array($status, array('BLOCKED', 'CONFLICT', 'FAILED'), true)) {
                return true;
            }
        }
        return false;
    }

    protected function buildDryRunMutationPlan(ClinicalMutationRequest $request, array $normalizedValues)
    {
        return array(
            'decision' => 'DRY_RUN_NO_COMMIT',
            'mutation_performed' => false,
            'would_insert' => array('iop_vital_parameters'),
            'would_update' => array('patient_details_iop.vitals_status', 'patient_details_iop.vitals_nurse_id', 'patient_details_iop.vitals_at'),
            'would_call_adapter' => 'NursingVitalsAdapter::record',
            'would_call_ctm' => 'CiClinicalTransactionManager::execute',
            'encounter_id' => $request->encounterId(),
            'patient_no' => $request->patientNo(),
            'normalized_values' => $normalizedValues,
        );
    }

    protected function buildDryRunAuditEvent(ClinicalMutationRequest $request, array $authorization, array $safety, array $idempotency, array $locks)
    {
        return array(
            'decision' => 'DRY_RUN_AUDIT_NOT_COMMITTED',
            'audit_event_id' => 'dry_run_' . $request->requestId(),
            'request_id' => $request->requestId(),
            'idempotency_key' => $request->idempotencyKey(),
            'action_type' => $request->actionType(),
            'mutation_finality_class' => 'IMMUTABLE_WRITE',
            'patient_no' => $request->patientNo(),
            'encounter_id' => $request->encounterId(),
            'actor_user_id' => $request->actorUserId(),
            'actor_role' => $request->actorRole(),
            'source_controller' => $request->sourceController(),
            'source_method' => $request->sourceMethod(),
            'payload_hash' => $request->payloadHash(),
            'truth_hash_before' => $request->truthHash(),
            'authorization_decision' => isset($authorization['decision']) ? $authorization['decision'] : null,
            'safety_decision' => isset($safety['decision']) ? $safety['decision'] : null,
            'idempotency_classification' => isset($idempotency['classification']) ? $idempotency['classification'] : null,
            'lock_decisions' => $locks,
            'status' => 'DRY_RUN_NOT_COMMITTED',
            'recovery_state' => 'NONE',
            'created_at_utc' => gmdate('c'),
        );
    }

    protected function buildDryRunTruthRecertification(ClinicalMutationRequest $request)
    {
        return array(
            'decision' => 'DRY_RUN_RECERTIFICATION_NOT_REQUESTED',
            'truth_recertification_required' => true,
            'post_write_truth_status' => 'PENDING_DRY_RUN',
            'would_request' => array('structural_refresh', 'causal_validation', 'truth_recompilation', 'clinical_truth_certification'),
            'patient_no' => $request->patientNo(),
            'encounter_id' => $request->encounterId(),
        );
    }

    protected function successResult(ClinicalMutationRequest $request, array $trace, array $warnings, $startedAt, array $lockPlan, array $idempotency, $code, array $artifacts = array())
    {
        $result = array(
            'status' => 'success',
            'code' => $code,
            'message' => 'Nursing vitals mutation engine completed without committing clinical data.',
            'action_type' => $request->actionType(),
            'request_id' => $request->requestId(),
            'patient_no' => $request->patientNo(),
            'encounter_id' => $request->encounterId(),
            'mutation_performed' => false,
            'dry_run' => true,
            'mode' => $this->mode,
            'governance_observed' => true,
            'refresh_required' => false,
            'lock_plan' => $lockPlan,
            'idempotency' => $idempotency,
            'warnings' => $warnings,
            'errors' => array(),
            'runtime_action_trace' => $trace,
            'started_at_utc' => $startedAt,
            'completed_at_utc' => gmdate('c'),
        );
        return $this->withRuntimeEvidence($request, $result, $artifacts);
    }

    protected function blockedResult(ClinicalMutationRequest $request, array $trace, array $warnings, array $errors, $startedAt, $code, array $artifacts = array())
    {
        $result = array(
            'status' => 'error',
            'code' => $code,
            'message' => 'Nursing vitals mutation engine blocked before clinical mutation.',
            'action_type' => $request->actionType(),
            'request_id' => $request->requestId(),
            'patient_no' => $request->patientNo(),
            'encounter_id' => $request->encounterId(),
            'mutation_performed' => false,
            'dry_run' => true,
            'mode' => $this->mode,
            'governance_observed' => true,
            'refresh_required' => false,
            'warnings' => $warnings,
            'errors' => $errors,
            'runtime_action_trace' => $trace,
            'started_at_utc' => $startedAt,
            'completed_at_utc' => gmdate('c'),
        );
        return $this->withRuntimeEvidence($request, $result, $artifacts);
    }

    protected function withRuntimeEvidence(ClinicalMutationRequest $request, array $result, array $artifacts)
    {
        if ($this->evidenceEmitter instanceof NursingRuntimeEvidenceEmitter) {
            $result['runtime_evidence'] = $this->evidenceEmitter->emitVitalsExecutionEvidence($request, $result, $artifacts);
        } else {
            $result['runtime_evidence'] = array('status' => 'SKIPPED', 'decision' => 'EVIDENCE_EMITTER_NOT_AVAILABLE');
        }
        return $result;
    }

    protected function trace($checkpoint, $status, array $evidence = array())
    {
        return array(
            'checkpoint' => $checkpoint,
            'status' => $status,
            'evidence' => $evidence,
            'recorded_at_utc' => gmdate('c'),
        );
    }
}
