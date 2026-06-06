<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Billing_canonical_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
	}

	public function normalize_invoice_payment_status($status)
	{
		$raw = strtoupper(trim((string)$status));
		if ($raw === '' || $raw === 'PENDING') {
			return 'UNPAID';
		}
		if ($raw === 'CREDIT') {
			return 'CREDITED';
		}
		return $raw;
	}

	public function normalize_transaction_payment_status($status)
	{
		$raw = strtoupper(trim((string)$status));
		if ($raw === '') {
			return 'PENDING';
		}
		if ($raw === 'CREDIT') {
			return 'CREDITED';
		}
		return $raw;
	}

	public function is_invoice_outstanding($status)
	{
		$normalized = $this->normalize_invoice_payment_status($status);
		return in_array($normalized, array('UNPAID', 'PARTIAL'), true);
	}

	public function derive_invoice_payment_status($total, $paid, $payer_type = '')
	{
		$total = (float)$total;
		$paid = (float)$paid;
		$payer_type = strtoupper(trim((string)$payer_type));
		if ($payer_type !== '' && $payer_type !== 'CASH' && $payer_type !== 'PRIVATE') {
			return 'PAID';
		}
		if ($total > 0.0 && $paid + 0.009 >= $total) {
			return 'PAID';
		}
		if ($paid > 0.009) {
			return 'PARTIAL';
		}
		return 'UNPAID';
	}

	public function resolve_service_reference($module, $source_ref, $context = array())
	{
		$module = strtoupper(trim((string)$module));
		$source_ref = trim((string)$source_ref);
		if ($module === '' || $source_ref === '') {
			return null;
		}

		$id = $this->extract_numeric_id($source_ref);
		if ($id <= 0) {
			$this->log_compatibility('INVALID_REF', $module, $source_ref, null);
			return null;
		}

		if ($module === 'PHARMACY') {
			return $this->reference_result($module, $source_ref, 'PHARMACY', 'iop_med_id:' . $id, 'PHARMACY', 'iop_medication:' . $id, $id);
		}
		if ($module === 'LAB' || $module === 'LABORATORY') {
			return $this->reference_result($module, $source_ref, 'LABORATORY', 'io_lab_id:' . $id, 'LAB', 'iop_laboratory:' . $id, $id);
		}
		if ($module === 'SONO' || $module === 'SONOGRAPHY') {
			$charge_id = $this->resolve_sonography_charge_id($id, $context);
			if ($charge_id <= 0) {
				$this->log_compatibility('UNRESOLVED_SONOGRAPHY_REF', $module, $source_ref, null);
				return null;
			}
			if ((string)$source_ref !== 'sono_charge_id:' . $charge_id) {
				$this->log_compatibility('LEGACY_SONOGRAPHY_REF', $module, $source_ref, 'sono_charge_id:' . $charge_id);
			}
			return $this->reference_result($module, $source_ref, 'IMAGING', 'sono_charge_id:' . $charge_id, 'SONOGRAPHY', 'iop_sonography_charge:' . $charge_id, $charge_id);
		}
		if ($module === 'RAD' || $module === 'RADIOLOGY') {
			return $this->reference_result($module, $source_ref, 'IMAGING', 'radiology_order_id:' . $id, 'RADIOLOGY', 'radiology_order:' . $id, $id);
		}
		if ($module === 'IPD_ROOM') {
			return $this->reference_result($module, $source_ref, 'IPD', 'iop_room_charge_id:' . $id, 'IPD_ROOM', 'iop_room_charge:' . $id, $id);
		}
		if ($module === 'IPD_BED_SIDE') {
			return $this->reference_result($module, $source_ref, 'IPD', 'iop_bed_side_procedure_id:' . $id, 'IPD_BED_SIDE', 'iop_bed_side_procedure:' . $id, $id);
		}
		if ($module === 'IPD_OT') {
			return $this->reference_result($module, $source_ref, 'IPD', 'iop_operation_theater_id:' . $id, 'IPD_OT', 'iop_operation_theater:' . $id, $id);
		}
		if ($module === 'PROCEDURE') {
			return $this->reference_result($module, $source_ref, 'OPD', 'opd_procedure_request_id:' . $id, 'PROCEDURE', 'opd_procedure_request:' . $id, $id);
		}

		$this->log_compatibility('UNSUPPORTED_MODULE_REF', $module, $source_ref, null);
		return null;
	}

	public function item_ref_candidates($module, $source_ref, $context = array())
	{
		$resolved = $this->resolve_service_reference($module, $source_ref, $context);
		if (!$resolved || !isset($resolved['item_ref'])) {
			return array();
		}
		$candidates = array($resolved['item_ref']);
		if (isset($resolved['legacy_item_refs']) && is_array($resolved['legacy_item_refs'])) {
			foreach ($resolved['legacy_item_refs'] as $ref) {
				if ($ref !== '' && !in_array($ref, $candidates, true)) {
					$candidates[] = $ref;
				}
			}
		}
		return $candidates;
	}

	public function extract_numeric_id($ref)
	{
		$ref = trim((string)$ref);
		if ($ref === '') {
			return 0;
		}
		if (preg_match('/^[0-9]+$/', $ref)) {
			return (int)$ref;
		}
		if (strpos($ref, ':') !== false) {
			$parts = explode(':', $ref, 2);
			return isset($parts[1]) ? (int)$parts[1] : 0;
		}
		return 0;
	}

	private function resolve_sonography_charge_id($id, $context = array())
	{
		$id = (int)$id;
		if ($id <= 0 || !$this->db->table_exists('iop_sonography_charge')) {
			return 0;
		}
		$this->db->select('charge_id');
		$this->db->from('iop_sonography_charge');
		$this->db->where('InActive', 0);
		$this->db->group_start();
		$this->db->where('charge_id', $id);
		$this->db->or_where('io_lab_id', $id);
		$this->db->group_end();
		if (isset($context['iop_id']) && trim((string)$context['iop_id']) !== '') {
			$this->db->where('iop_id', (string)$context['iop_id']);
		}
		if (isset($context['patient_no']) && trim((string)$context['patient_no']) !== '') {
			$this->db->where('patient_no', (string)$context['patient_no']);
		}
		$this->db->order_by('charge_id', 'DESC');
		$this->db->limit(1);
		$row = $this->db->get()->row();
		return ($row && isset($row->charge_id)) ? (int)$row->charge_id : 0;
	}

	private function reference_result($module, $input_ref, $department, $item_ref, $source_module, $lock_source_ref, $canonical_id)
	{
		return array(
			'module' => $module,
			'input_ref' => $input_ref,
			'department' => $department,
			'item_ref' => $item_ref,
			'source_module' => $source_module,
			'lock_source_ref' => $lock_source_ref,
			'canonical_id' => (int)$canonical_id,
			'legacy_item_refs' => array()
		);
	}

	public function log_compatibility($event, $module, $input_ref, $canonical_ref = null)
	{
		$event = strtoupper(trim((string)$event));
		$module = strtoupper(trim((string)$module));
		$input_ref = trim((string)$input_ref);
		$canonical_ref = $canonical_ref === null ? '' : trim((string)$canonical_ref);
		log_message('warning', '[BILLING_CANONICAL] event=' . $event . ' module=' . $module . ' input_ref=' . $input_ref . ' canonical_ref=' . $canonical_ref);
	}
}
