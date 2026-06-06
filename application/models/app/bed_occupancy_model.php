<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

class Bed_occupancy_model extends CI_Model
{
	private $_constraints_checked = false;

	public function __construct()
	{
		parent::__construct();
		$this->load->model('app/ipd_model');
		$this->load->model('app/billing_model');
	}

	private function lock_system_option_inpatient_no()
	{
		try {
			$this->db->query("SELECT cCode FROM system_option WHERE cCode = 'INPATIENTNO' AND InActive = 0 LIMIT 1 FOR UPDATE");
		} catch (\Throwable $e) {
			// best-effort
		}
		return true;
	}

	public function ensure_bed_occupancy_constraints()
	{
		if ($this->_constraints_checked) {
			return array('ok' => true);
		}
		if (!isset($this->ipd_model) || !method_exists($this->ipd_model, 'ensure_room_beds_invariants_schema')) {
			return array('ok' => false, 'error' => 'ipd_model_missing_ensure');
		}
		$res = $this->ipd_model->ensure_room_beds_invariants_schema();
		if (is_array($res) && isset($res['ok']) && $res['ok'] === true) {
			$this->_constraints_checked = true;
		}
		return $res;
	}

	public function create_ipd_admission_from_detention($params)
	{
		$this->ensure_bed_occupancy_constraints();
		return $this->ipd_model->create_ipd_admission_from_detention($params);
	}

	public function admit_ipd_from_post()
	{
		$iop_id = (string)$this->input->post('iopNo');
		$patient_no = (string)$this->input->post('patient_no');
		$bed_id = (int)$this->input->post('bed_no');

		if ($iop_id === '' || $patient_no === '' || $bed_id <= 0) {
			return array('ok' => false, 'error' => 'missing_required');
		}

		$this->ensure_bed_occupancy_constraints();

		$this->db->trans_begin();
		try {
			$bed = $this->ipd_model->lock_bed_row($bed_id);
			if (!$bed) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'bed_not_found');
			}
			$bed_status = strtolower(trim((string)$bed->nStatus));
			$bed_patient = trim((string)$bed->patient_no);
			if ($bed_status === 'occupied' && $bed_patient !== '' && $bed_patient !== $iop_id) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'bed_occupied');
			}

			$this->lock_system_option_inpatient_no();

			$this->ipd_model->save();
			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'admission_insert_failed');
			}

			if (!$this->ipd_model->updateBed()) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'bed_occupy_failed');
			}

			$this->ipd_model->savepatientRoom();
			$this->billing_model->generate_ipd_room_charges($iop_id, null);

			$this->db->where(array('cCode' => 'INPATIENTNO', 'InActive' => 0));
			$this->db->update('system_option', array('cValue' => $this->input->post('iopNo2')));

			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'txn_failed');
			}
			$this->db->trans_commit();
			return array('ok' => true);
		} catch (\Throwable $e) {
			$this->db->trans_rollback();
			return array('ok' => false, 'error' => 'exception:' . $e->getMessage());
		}
	}

	public function transfer_ipd_bed($iop_id, $patient_no, $new_bed_id, $transfer)
	{
		$iop_id = (string)$iop_id;
		$patient_no = (string)$patient_no;
		$new_bed_id = (int)$new_bed_id;
		$transfer = is_array($transfer) ? $transfer : array();

		if ($iop_id === '' || $new_bed_id <= 0) {
			return array('ok' => false, 'error' => 'missing_required');
		}

		$this->ensure_bed_occupancy_constraints();

		$this->db->trans_begin();
		try {
			$admission = $this->ipd_model->lock_ipd_admission_row($iop_id, null);
			$old_bed_id = ($admission && isset($admission->room_id)) ? (int)$admission->room_id : 0;
			$admission_status = ($admission && isset($admission->nStatus)) ? (string)$admission->nStatus : '';
			if (!$admission || $admission_status !== 'Pending') {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'admission_not_active');
			}

			if ($old_bed_id > 0) {
				$this->ipd_model->lock_bed_row($old_bed_id);
			}
			$bed = $this->ipd_model->lock_bed_row($new_bed_id);
			if (!$bed) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'bed_not_found');
			}
			$bed_status = strtolower(trim((string)$bed->nStatus));
			$bed_patient = trim((string)$bed->patient_no);
			if ($bed_status === 'occupied' && $bed_patient !== '' && $bed_patient !== $iop_id) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'bed_occupied');
			}

			$payload = array(
				'iop_id' => $iop_id,
				'dDate' => isset($transfer['dDate']) ? $transfer['dDate'] : null,
				'dDateTime' => isset($transfer['dDateTime']) ? $transfer['dDateTime'] : null,
				'room_category_id' => isset($transfer['room_category_id']) ? $transfer['room_category_id'] : null,
				'room_master_id' => isset($transfer['room_master_id']) ? $transfer['room_master_id'] : null,
				'bed_id' => $new_bed_id,
				'reason' => isset($transfer['reason']) ? $transfer['reason'] : null,
				'cPreparedBy' => isset($transfer['cPreparedBy']) ? $transfer['cPreparedBy'] : null,
				'InActive' => 0,
			);
			$this->db->insert('iop_room_transfer', $payload);
			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'transfer_insert_failed');
			}

			if ($old_bed_id > 0 && $old_bed_id !== $new_bed_id) {
				if (!$this->ipd_model->conditional_vacate_bed($old_bed_id, $iop_id)) {
					$this->db->trans_rollback();
					return array('ok' => false, 'error' => 'vacate_old_failed');
				}
			}
			if (!$this->ipd_model->conditional_occupy_bed($new_bed_id, $iop_id)) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'occupy_new_failed');
			}

			$this->db->where(array('IO_ID' => $iop_id, 'patient_type' => 'IPD', 'InActive' => 0));
			$this->db->update('patient_details_iop', array('room_id' => $new_bed_id));
			$this->billing_model->generate_ipd_room_charges($iop_id, null);

			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'txn_failed');
			}
			$this->db->trans_commit();
			return array('ok' => true);
		} catch (\Throwable $e) {
			$this->db->trans_rollback();
			return array('ok' => false, 'error' => 'exception:' . $e->getMessage());
		}
	}

	public function discharge_ipd($iop_id, $patient_no, $actor_id = null)
	{
		$iop_id = (string)$iop_id;
		$patient_no = (string)$patient_no;
		$actor_id = $actor_id !== null ? (string)$actor_id : null;
		if ($iop_id === '' || $patient_no === '') {
			return array('ok' => false, 'error' => 'missing_required');
		}

		$this->ensure_bed_occupancy_constraints();

		$this->db->trans_begin();
		try {
			$admission = $this->ipd_model->lock_ipd_admission_row($iop_id, $patient_no);
			if (!$admission) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'admission_not_found');
			}
			$admission_status = isset($admission->nStatus) ? (string)$admission->nStatus : '';
			if ($admission_status !== 'Pending') {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'admission_not_active');
			}

			$current_bed_id = (isset($admission->room_id) && (int)$admission->room_id > 0) ? (int)$admission->room_id : 0;
			if ($current_bed_id > 0) {
				$this->ipd_model->lock_bed_row($current_bed_id);
			}

			$this->billing_model->save_ipd_discharge_audit($iop_id, $patient_no, date('Y-m-d H:i:s'), $actor_id);
			$this->db->query("UPDATE patient_details_iop SET nStatus = 'Discharged' WHERE IO_ID = ? AND patient_no = ?", array($iop_id, $patient_no));
			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'admission_update_failed');
			}

			if ($current_bed_id > 0) {
				$this->ipd_model->conditional_vacate_bed($current_bed_id, $iop_id);
			}
			$this->ipd_model->conditional_vacate_all_beds_for_iop($iop_id);
			$this->billing_model->generate_ipd_room_charges($iop_id, null);

			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'txn_failed');
			}
			$this->db->trans_commit();
			return array('ok' => true);
		} catch (\Throwable $e) {
			$this->db->trans_rollback();
			return array('ok' => false, 'error' => 'exception:' . $e->getMessage());
		}
	}

	public function assign_detention_bed($opd_iop_id, $patient_no, $bed_id)
	{
		$opd_iop_id = trim((string)$opd_iop_id);
		$patient_no = trim((string)$patient_no);
		$bed_id = (int)$bed_id;
		if ($opd_iop_id === '' || $patient_no === '' || $bed_id <= 0) {
			return array('ok' => false, 'error' => 'missing_required');
		}

		$this->ensure_bed_occupancy_constraints();

		$this->db->trans_begin();
		try {
			$bed = $this->ipd_model->lock_bed_row($bed_id);
			if (!$bed) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'bed_not_found');
			}
			$bedStatus = isset($bed->nStatus) ? strtoupper(trim((string)$bed->nStatus)) : '';
			$bedOcc = isset($bed->patient_no) ? trim((string)$bed->patient_no) : '';
			if ($bedStatus === 'OCCUPIED' && $bedOcc !== '' && $bedOcc !== $opd_iop_id) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'bed_occupied');
			}

			$q = $this->db->query(
				"SELECT * FROM patient_details_iop WHERE IO_ID = ? AND patient_no = ? AND InActive = 0 LIMIT 1 FOR UPDATE",
				array($opd_iop_id, $patient_no)
			);
			if ($q === false) {
				$q = $this->db->query(
					"SELECT * FROM patient_details_iop WHERE IO_ID = ? AND patient_no = ? AND InActive = 0 LIMIT 1",
					array($opd_iop_id, $patient_no)
				);
			}
			$row = $q ? $q->row() : null;
			if (!$row) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'visit_not_found');
			}
			if (isset($row->patient_type) && strtoupper(trim((string)$row->patient_type)) !== 'OPD') {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'not_opd_visit');
			}
			if (!empty($row->converted_to_admission_at) && (string)$row->converted_to_admission_at !== '0000-00-00 00:00:00') {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'already_converted');
			}

			$this->db->where(array('IO_ID' => $opd_iop_id, 'patient_no' => $patient_no, 'InActive' => 0));
			$this->db->update('patient_details_iop', array('room_id' => $bed_id));

			if (!$this->ipd_model->conditional_occupy_bed($bed_id, $opd_iop_id)) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'bed_occupy_failed');
			}

			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'txn_failed');
			}
			$this->db->trans_commit();
			return array('ok' => true);
		} catch (\Throwable $e) {
			$this->db->trans_rollback();
			return array('ok' => false, 'error' => 'exception:' . $e->getMessage());
		}
	}
}
