<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH . 'controllers/general.php';

class Worklist extends General
{
	public function __construct()
	{
		parent::__construct();
	}

	public function unified()
	{
		require_role(array('admin', 'doctor', 'sonographer', 'laboratory'));

		$this->load->model('app/unified_worklist_model', 'unified_worklist');

		$status = strtoupper(trim((string)$this->input->get('status')));
		if ($status !== 'PENDING' && $status !== 'COMPLETED') {
			$status = 'PENDING';
		}

		$items = $this->unified_worklist->get_worklist(array(
			'status' => $status,
			'limit' => 300,
		));

		$data = $this->data;
		$data['title'] = 'Unified Worklist';
		$data['items'] = $items;
		$data['status'] = $status;

		$this->load->view('app/worklist/unified', $data);
	}

	public function validate_gate($item_ref = null)
	{
		require_role(array('admin'));
		header('Content-Type: application/json');

		$item_ref = urldecode(trim((string)$item_ref));
		if ($item_ref === '') {
			echo json_encode(array('ok' => false, 'message' => 'Missing item_ref'));
			return;
		}

		$module = '';
		$source_ref = '';
		if (strpos($item_ref, 'io_lab_id:') === 0) {
			$module = 'LAB';
			$source_ref = (string)(int)substr($item_ref, strlen('io_lab_id:'));
		} elseif (strpos($item_ref, 'radiology_order_id:') === 0) {
			$module = 'RADIOLOGY';
			$source_ref = (string)(int)substr($item_ref, strlen('radiology_order_id:'));
		} elseif (strpos($item_ref, 'sono_charge_id:') === 0) {
			$module = 'SONOGRAPHY';
			$source_ref = (string)(int)substr($item_ref, strlen('sono_charge_id:'));
		} elseif (strpos($item_ref, 'iop_med_id:') === 0) {
			$module = 'PHARMACY';
			$source_ref = (string)(int)substr($item_ref, strlen('iop_med_id:'));
		}

		$this->load->model('app/service_gate_model', 'service_gate');
		$backend_by_item_ref = null;
		if (isset($this->service_gate) && method_exists($this->service_gate, 'check_service_by_item_ref')) {
			$backend_by_item_ref = $this->service_gate->check_service_by_item_ref($item_ref, true);
		}

		if ($module === '' || $source_ref === '' || (int)$source_ref <= 0) {
			if (strpos($item_ref, 'walkin_order_item_id:') === 0 && $backend_by_item_ref !== null) {
				echo json_encode(array(
					'ok' => true,
					'item_ref' => $item_ref,
					'module' => null,
					'source_ref' => null,
					'sql' => null,
					'backend' => null,
					'backend_by_item_ref' => $backend_by_item_ref,
					'mismatch' => null,
					'mismatch_backend_vs_item_ref' => null,
				));
				return;
			}
			echo json_encode(array('ok' => false, 'message' => 'Unsupported item_ref format'));
			return;
		}

		$this->load->model('app/unified_worklist_model', 'unified_worklist');

		$row = $this->unified_worklist->get_item_by_ref($item_ref);
		if (!$row) {
			echo json_encode(array('ok' => false, 'message' => 'Worklist item not found'));
			return;
		}

		$iop_id = isset($row['iop_id']) ? $row['iop_id'] : null;
		$patient_no = isset($row['patient_no']) ? $row['patient_no'] : null;
		$backend = $this->service_gate->check_service($module, $source_ref, $iop_id, $patient_no);

		$backend_allowed2 = null;
		$backend_reason2 = null;
		if (is_array($backend_by_item_ref)) {
			$backend_allowed2 = array_key_exists('allowed', $backend_by_item_ref) ? (bool)$backend_by_item_ref['allowed'] : null;
			$backend_reason2 = ($backend_allowed2 === true) ? 'ALLOWED' : (isset($backend_by_item_ref['blocked_reason']) ? (string)$backend_by_item_ref['blocked_reason'] : null);
		}

		$sql_allowed = isset($row['can_proceed']) ? ((int)$row['can_proceed'] === 1 || $row['can_proceed'] === true) : false;
		$backend_allowed = isset($backend['allowed']) ? (bool)$backend['allowed'] : false;
		$sql_reason = $sql_allowed ? 'ALLOWED' : (isset($row['blocked_reason']) ? (string)$row['blocked_reason'] : null);
		$backend_reason = $backend_allowed ? 'ALLOWED' : (isset($backend['blocked_reason']) ? (string)$backend['blocked_reason'] : null);

		$mismatch = ($sql_allowed !== $backend_allowed) || ($sql_reason !== $backend_reason);
		$mismatch_backend_vs_item_ref = null;
		if ($backend_allowed2 !== null) {
			$mismatch_backend_vs_item_ref = ($backend_allowed !== $backend_allowed2) || ($backend_reason !== $backend_reason2);
			if ($mismatch_backend_vs_item_ref) {
				log_message('error', '[GATE_ITEM_REF_MISMATCH] item_ref=' . $item_ref . ' module=' . $module . ' source_ref=' . $source_ref . ' backend_allowed=' . (int)$backend_allowed . ' backend_item_ref_allowed=' . (int)$backend_allowed2 . ' backend_reason=' . (string)$backend_reason . ' backend_item_ref_reason=' . (string)$backend_reason2);
			}
		}
		if ($mismatch) {
			log_message('error', '[GATE_PARITY_MISMATCH] item_ref=' . $item_ref . ' module=' . $module . ' source_ref=' . $source_ref . ' sql_allowed=' . (int)$sql_allowed . ' backend_allowed=' . (int)$backend_allowed . ' sql_reason=' . (string)$sql_reason . ' backend_reason=' . (string)$backend_reason);
		}

		echo json_encode(array(
			'ok' => true,
			'item_ref' => $item_ref,
			'module' => $module,
			'source_ref' => $source_ref,
			'sql' => array(
				'can_proceed' => $sql_allowed,
				'blocked_reason' => $sql_reason,
			),
			'backend' => array(
				'allowed' => $backend_allowed,
				'blocked_reason' => $backend_reason,
				'status' => isset($backend['status']) ? $backend['status'] : null,
				'reason' => isset($backend['reason']) ? $backend['reason'] : null,
			),
			'backend_by_item_ref' => $backend_by_item_ref,
			'mismatch' => $mismatch,
			'mismatch_backend_vs_item_ref' => $mismatch_backend_vs_item_ref,
		));
	}
}
