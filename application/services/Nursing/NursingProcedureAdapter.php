<?php

class NursingProcedureAdapter
{
    protected $db;
    protected $billingTransactionModel;

    public function __construct($db, $billingTransactionModel)
    {
        $this->db = $db;
        $this->billingTransactionModel = $billingTransactionModel;
    }

    public function record($iopId, $patientNo, $actorUserId, $idempotencyKey, array $input, $transactions)
    {
        $normalized = $this->normalize($input);
        $this->validate($normalized);

        return $transactions->execute($iopId, $idempotencyKey, function () use ($iopId, $patientNo, $actorUserId, $normalized) {
            return $this->persist($iopId, $patientNo, $actorUserId, $normalized);
        });
    }

    protected function normalize(array $input)
    {
        $particularId = 0;
        foreach (array('particular_id', 'particular', 'cItem_id') as $k) {
            if (isset($input[$k]) && trim((string)$input[$k]) !== '') {
                $particularId = (int)$input[$k];
                break;
            }
        }

        $qty = 1;
        if (isset($input['qty']) && trim((string)$input['qty']) !== '') {
            $qty = (int)$input['qty'];
        } elseif (isset($input['quantity']) && trim((string)$input['quantity']) !== '') {
            $qty = (int)$input['quantity'];
        }

        $remarks = '';
        foreach (array('remarks', 'notes', 'note') as $k) {
            if (isset($input[$k]) && trim((string)$input[$k]) !== '') {
                $remarks = (string)$input[$k];
                break;
            }
        }
        $remarks = str_replace("\r\n", "\n", $remarks);
        $remarks = str_replace("\r", "\n", $remarks);
        $remarks = trim($remarks);

        $simulateFailure = '';
        if (isset($input['simulate_failure'])) {
            $simulateFailure = trim((string)$input['simulate_failure']);
        }

        return array(
            'particular_id' => $particularId,
            'qty' => $qty,
            'remarks' => $remarks,
            'simulate_failure' => $simulateFailure,
        );
    }

    protected function validate(array $data)
    {
        $particularId = isset($data['particular_id']) ? (int)$data['particular_id'] : 0;
        if ($particularId <= 0) {
            throw new InvalidArgumentException('procedure_particular_required');
        }
        $qty = isset($data['qty']) ? (int)$data['qty'] : 0;
        if ($qty <= 0) {
            throw new InvalidArgumentException('procedure_qty_invalid');
        }
        if (isset($data['remarks']) && strlen((string)$data['remarks']) > 2000) {
            throw new InvalidArgumentException('procedure_remarks_too_long');
        }

        $sf = isset($data['simulate_failure']) ? (string)$data['simulate_failure'] : '';
        if ($sf !== '' && !in_array($sf, array('after_insert', 'after_projection'), true)) {
            throw new InvalidArgumentException('simulate_failure_invalid');
        }
    }

    protected function resolve_procedure_name($particularId)
    {
        if ($particularId <= 0 || !$this->db->table_exists('bill_particular')) {
            return null;
        }
        $row = $this->db->get_where('bill_particular', array('particular_id' => (int)$particularId))->row();
        if ($row && isset($row->particular_name) && trim((string)$row->particular_name) !== '') {
            return (string)$row->particular_name;
        }
        return null;
    }

    protected function persist($iopId, $patientNo, $actorUserId, array $normalized)
    {
        if (!$this->db->table_exists('iop_bed_side_procedure')) {
            throw new RuntimeException('procedure_table_missing');
        }

        $now = date('Y-m-d H:i:s');
        $row = array(
            'iop_id' => (string)$iopId,
            'dDate' => date('Y-m-d'),
            'dDateTime' => $now,
            'cItem_id' => (int)$normalized['particular_id'],
            'qty' => (int)$normalized['qty'],
            'notes' => (string)$normalized['remarks'],
            'cPreparedBy' => (string)$actorUserId,
            'InActive' => 0,
        );

        if (!$this->db->insert('iop_bed_side_procedure', $row)) {
            throw new RuntimeException('procedure_insert_failed');
        }

        $procedureId = (int)$this->db->insert_id();

        if (isset($normalized['simulate_failure']) && $normalized['simulate_failure'] === 'after_insert') {
            throw new RuntimeException('simulated_failure_after_insert');
        }

        $projection = null;
        if ($this->billingTransactionModel && method_exists($this->billingTransactionModel, 'sync_bed_side_procedure')) {
            $projection = $this->billingTransactionModel->sync_bed_side_procedure($procedureId, $actorUserId);
        }

        if (!is_array($projection) || !isset($projection['ok']) || !$projection['ok']) {
            throw new RuntimeException('billing_projection_failed');
        }

        if (isset($normalized['simulate_failure']) && $normalized['simulate_failure'] === 'after_projection') {
            throw new RuntimeException('simulated_failure_after_projection');
        }

        $procedureName = $this->resolve_procedure_name((int)$normalized['particular_id']);
        if ($procedureName === null) {
            $procedureName = 'Bedside Procedure';
        }

        return array(
            'status' => 'success',
            'code' => 'PROCEDURE_SAVED',
            'message' => 'Procedure saved.',
            'encounter_id' => (string)$iopId,
            'patient_no' => (string)$patientNo,
            'procedure_id' => $procedureId,
            'normalized_procedure_id' => (int)$normalized['particular_id'],
            'normalized_procedure_name' => (string)$procedureName,
            'billing_projection_status' => 'ok',
            'billing_txn_id' => (isset($projection['txn_id']) ? (int)$projection['txn_id'] : null),
            'refresh_required' => true,
            'governance_observed' => true,
            'warnings' => array(),
            'errors' => array(),
        );
    }
}
