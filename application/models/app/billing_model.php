<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Billing_model extends CI_Model{
	private $_table_exists_cache = array();
	private $_column_exists_cache = array();
	
	public function __construct(){
		parent::__construct();	
	}

	public function getInvoice($invoice_id){
		if (!$this->table_exists('iop_billing')) {
			return null;
		}
		$invoice_id_str = (string)$invoice_id;
		$this->db->where('InActive', 0);
		if ($invoice_id_str !== '' && ctype_digit($invoice_id_str)) {
			$iid = (int)$invoice_id_str;
			if ($this->column_exists('iop_billing', 'id')) {
				$this->db->where('id', $iid);
			} elseif ($this->column_exists('iop_billing', 'invoice_id')) {
				$this->db->where('invoice_id', $iid);
			} else {
				$this->db->where('invoice_no', $invoice_id_str);
			}
		} else {
			$this->db->where('invoice_no', $invoice_id_str);
		}
		$this->db->limit(1);
		$q = $this->db->get('iop_billing');
		return $q ? $q->row() : null;
	}

	public function getInvoiceItems($invoice_id){
		$invoice = $this->getInvoice($invoice_id);
		if (!$invoice || !$this->table_exists('iop_billing_t')) {
			return array();
		}
		$invoice_no = null;
		if (isset($invoice->invoice_no) && trim((string)$invoice->invoice_no) !== '') {
			$invoice_no = (string)$invoice->invoice_no;
		} elseif (isset($invoice->invoiceno) && trim((string)$invoice->invoiceno) !== '') {
			$invoice_no = (string)$invoice->invoiceno;
		}
		if ($invoice_no === null) {
			return array();
		}

		$this->install_billing_meta_tables();
		$this->db->from('iop_billing_t T');
		if ($this->table_exists('iop_billing_line_meta')) {
			$this->db->select('T.*, M.source_module, M.source_ref', false);
			$this->db->join('iop_billing_line_meta M', 'M.invoice_no = T.invoice_no AND M.detail_id = T.id AND M.InActive = 0', 'left');
		} else {
			$this->db->select('T.*, NULL AS source_module, NULL AS source_ref', false);
		}
		$this->db->where(array('T.invoice_no' => $invoice_no, 'T.InActive' => 0));
		$this->db->order_by('T.id', 'ASC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	public function resolve_nhis_code_from_source($source_module, $source_ref){
		$out = array('code' => null, 'name' => null, 'is_drug' => 0);
		$srcMod = strtoupper(trim((string)$source_module));
		$srcRef = trim((string)$source_ref);
		if ($srcMod === '' || $srcRef === '') {
			return $out;
		}

		if ($srcMod === 'PHARMACY' && $this->table_exists('iop_medication') && $this->table_exists('medicine_drug_name') && strpos($srcRef, 'iop_medication:') === 0) {
			$iopMedId = (int)substr($srcRef, strlen('iop_medication:'));
			if ($iopMedId > 0) {
				$row = $this->db->query(
					"SELECT D.nhis_drug_code AS code, D.nhis_drug_name AS name FROM iop_medication M JOIN medicine_drug_name D ON D.drug_id = M.medicine_id WHERE M.iop_med_id = ? LIMIT 1",
					array($iopMedId)
				)->row();
				if ($row && isset($row->code) && trim((string)$row->code) !== '') {
					$out['code'] = trim((string)$row->code);
					$out['name'] = isset($row->name) ? (string)$row->name : null;
					$out['is_drug'] = 1;
					return $out;
				}
			}
		}

		if ($srcMod === 'LAB' && $this->table_exists('iop_laboratory') && $this->table_exists('bill_particular') && strpos($srcRef, 'iop_laboratory:') === 0) {
			$ioLabId = (int)substr($srcRef, strlen('iop_laboratory:'));
			if ($ioLabId > 0) {
				$row = $this->db->query(
					"SELECT BP.nhis_code AS code, BP.particular_name AS name FROM iop_laboratory L JOIN bill_particular BP ON BP.particular_id = L.laboratory_id WHERE L.io_lab_id = ? LIMIT 1",
					array($ioLabId)
				)->row();
				if ($row && isset($row->code) && trim((string)$row->code) !== '') {
					$out['code'] = trim((string)$row->code);
					$out['name'] = isset($row->name) ? (string)$row->name : null;
					return $out;
				}
			}
		}

		if ($srcMod === 'SONOGRAPHY' && $this->table_exists('iop_sonography_charge') && strpos($srcRef, 'iop_sonography_charge:') === 0) {
			$chargeId = (int)substr($srcRef, strlen('iop_sonography_charge:'));
			if ($chargeId > 0) {
				$charge = $this->db->get_where('iop_sonography_charge', array('charge_id' => $chargeId, 'InActive' => 0))->row();
				if ($charge) {
					if (isset($charge->nhis_code) && trim((string)$charge->nhis_code) !== '') {
						$out['code'] = trim((string)$charge->nhis_code);
						$out['name'] = isset($charge->item_name) ? (string)$charge->item_name : null;
						return $out;
					}
					if (isset($charge->bill_particular_id) && (int)$charge->bill_particular_id > 0 && $this->table_exists('bill_particular')) {
						$bp = $this->db->get_where('bill_particular', array('particular_id' => (int)$charge->bill_particular_id))->row();
						if ($bp && isset($bp->nhis_code) && trim((string)$bp->nhis_code) !== '') {
							$out['code'] = trim((string)$bp->nhis_code);
							$out['name'] = isset($bp->particular_name) ? (string)$bp->particular_name : null;
							return $out;
						}
					}
				}
			}
		}

		return $out;
	}

	public function get_nhis_service_tariff_by_code($service_code){
		if (!$this->table_exists('nhis_tariffs')) {
			return null;
		}
		$this->db->where(array('service_code' => (string)$service_code, 'is_active' => 1));
		$this->db->limit(1);
		$q = $this->db->get('nhis_tariffs');
		return $q ? $q->row() : null;
	}

	public function get_nhis_drug_tariff_by_code($nhis_code){
		if (!$this->table_exists('nhis_drug_tariffs')) {
			return null;
		}
		$this->db->where(array('nhis_code' => (string)$nhis_code, 'is_active' => 1));
		$this->db->limit(1);
		$q = $this->db->get('nhis_drug_tariffs');
		return $q ? $q->row() : null;
	}

	public function install_clearance_workflow_tables(){
		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_clearance_workflow` (\n".
			"  `wf_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `iop_id` varchar(25) NOT NULL,\n".
			"  `patient_no` varchar(25) DEFAULT NULL,\n".
			"  `clinical_cleared_at` datetime DEFAULT NULL,\n".
			"  `clinical_cleared_by` varchar(25) DEFAULT NULL,\n".
			"  `medication_cleared_at` datetime DEFAULT NULL,\n".
			"  `medication_cleared_by` varchar(25) DEFAULT NULL,\n".
			"  `final_cleared_at` datetime DEFAULT NULL,\n".
			"  `final_cleared_by` varchar(25) DEFAULT NULL,\n".
			"  `updated_at` datetime NOT NULL,\n".
			"  `updated_by` varchar(25) DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`wf_id`),\n".
			"  UNIQUE KEY `uq_iop` (`iop_id`),\n".
			"  KEY `idx_iop` (`iop_id`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");
		return true;
	}

	public function get_clearance_workflow($iop_id){
		$iop_id = (string)$iop_id;
		if (!$this->table_exists('iop_clearance_workflow')) {
			return null;
		}
		$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
		$this->db->limit(1);
		$q = $this->db->get('iop_clearance_workflow');
		return $q ? $q->row() : null;
	}

	public function upsert_clearance_stage($iop_id, $stage, $patient_no = null, $updated_by = null){
		$this->install_clearance_workflow_tables();
		$iop_id = (string)$iop_id;
		$stage = strtoupper(trim((string)$stage));
		$now = date('Y-m-d H:i:s');
		$existing = $this->get_clearance_workflow($iop_id);
		$data = array(
			'updated_at' => $now,
			'updated_by' => $updated_by !== null ? (string)$updated_by : null
		);
		if ($patient_no !== null && trim((string)$patient_no) !== '') {
			$data['patient_no'] = (string)$patient_no;
		}
		if ($stage === 'CLINICAL') {
			if (!$existing || !isset($existing->clinical_cleared_at) || trim((string)$existing->clinical_cleared_at) === '' || (string)$existing->clinical_cleared_at === '0000-00-00 00:00:00') {
				$data['clinical_cleared_at'] = $now;
				$data['clinical_cleared_by'] = $updated_by !== null ? (string)$updated_by : null;
			}
		}
		if ($stage === 'MEDICATION') {
			if (!$existing || !isset($existing->medication_cleared_at) || trim((string)$existing->medication_cleared_at) === '' || (string)$existing->medication_cleared_at === '0000-00-00 00:00:00') {
				$data['medication_cleared_at'] = $now;
				$data['medication_cleared_by'] = $updated_by !== null ? (string)$updated_by : null;
			}
		}
		if ($stage === 'FINAL') {
			if (!$existing || !isset($existing->final_cleared_at) || trim((string)$existing->final_cleared_at) === '' || (string)$existing->final_cleared_at === '0000-00-00 00:00:00') {
				$data['final_cleared_at'] = $now;
				$data['final_cleared_by'] = $updated_by !== null ? (string)$updated_by : null;
			}
		}
		if ($existing) {
			$this->db->where('iop_id', $iop_id);
			$this->db->update('iop_clearance_workflow', $data);
			return true;
		}
		$data['iop_id'] = $iop_id;
		$data['InActive'] = 0;
		$this->db->insert('iop_clearance_workflow', $data);
		return true;
	}

	public function medication_clearance_requirements($iop_id){
		$this->install_billing_meta_tables();
		$iop_id = (string)$iop_id;
		$res = array(
			'ok' => false,
			'total_meds' => 0,
			'missing_billing' => 0,
			'missing_dispense' => 0,
			'external_count' => 0,
			'cleared_count' => 0
		);
		if ($iop_id === '' || !$this->table_exists('iop_medication')) {
			$res['ok'] = true;
			return $res;
		}

		// Select medication with extended_status and dispensing_status for proper clearance check
		$selectCols = 'iop_med_id, total_qty';
		if ($this->column_exists('iop_medication', 'extended_status')) {
			$selectCols .= ', extended_status';
		}
		if ($this->column_exists('iop_medication', 'dispensing_status')) {
			$selectCols .= ', dispensing_status';
		}
		
		$this->db->select($selectCols);
		$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
		$q = $this->db->get('iop_medication');
		$meds = $q ? $q->result() : array();
		if (!$meds || count($meds) === 0) {
			$res['ok'] = true;
			return $res;
		}
		$res['total_meds'] = count($meds);

		$ids = array();
		foreach ($meds as $m) {
			$ids[] = (int)$m->iop_med_id;
		}

		// Get dispensed quantities from administration records (SOURCE OF TRUTH)
		$dispMap = array();
		if ($this->table_exists('iop_medication_administration') && count($ids) > 0) {
			$this->db->select('iop_med_id, SUM(dose_given) AS dispensed_qty', false);
			$this->db->from('iop_medication_administration');
			$this->db->where('InActive', 0);
			$this->db->where_in('iop_med_id', $ids);
			$this->db->where_in('status', array('DISPENSED', 'PARTIAL', 'RETURN'));
			$this->db->group_by('iop_med_id');
			$dq = $this->db->get();
			$drows = $dq ? $dq->result() : array();
			foreach ($drows as $dr) {
				$dispMap[(string)$dr->iop_med_id] = (float)$dr->dispensed_qty;
			}
		}

		// Get extended_status from pharmacy_billing_queue (SINGLE SOURCE OF TRUTH)
		$extStatusMap = array();
		if ($this->table_exists('pharmacy_billing_queue') && count($ids) > 0) {
			$this->db->select('iop_med_id, extended_status, payment_status, dispense_status');
			$this->db->from('pharmacy_billing_queue');
			$this->db->where('InActive', 0);
			$this->db->where_in('iop_med_id', $ids);
			$eq = $this->db->get();
			$erows = $eq ? $eq->result() : array();
			foreach ($erows as $er) {
				$extStatusMap[(string)$er->iop_med_id] = array(
					'extended_status' => isset($er->extended_status) ? strtoupper(trim((string)$er->extended_status)) : '',
					'payment_status' => isset($er->payment_status) ? strtoupper(trim((string)$er->payment_status)) : '',
					'dispense_status' => isset($er->dispense_status) ? strtoupper(trim((string)$er->dispense_status)) : ''
				);
			}
		}

		// Statuses that count as "cleared" for dispensing (patient handled outside normal flow)
		$clearedStatuses = array('EXTERNAL_PURCHASE', 'EXTERNAL', 'UNABLE_TO_PAY', 'CANCELLED', 'WAIVED', 'WAIVER_APPROVED');

		foreach ($meds as $m) {
			$mid = (int)$m->iop_med_id;
			$midStr = (string)$mid;
			
			// Get extended status from billing queue (single source of truth) or fallback to iop_medication
			$extStatus = '';
			if (isset($extStatusMap[$midStr]) && $extStatusMap[$midStr]['extended_status'] !== '') {
				$extStatus = $extStatusMap[$midStr]['extended_status'];
			} elseif (isset($m->extended_status)) {
				$extStatus = strtoupper(trim((string)$m->extended_status));
			}
			
			// Get dispense status from billing queue
			$dispenseStatus = '';
			if (isset($extStatusMap[$midStr]) && $extStatusMap[$midStr]['dispense_status'] !== '') {
				$dispenseStatus = $extStatusMap[$midStr]['dispense_status'];
			} elseif (isset($m->dispensing_status)) {
				$dispenseStatus = strtoupper(trim((string)$m->dispensing_status));
			}

			// Check if this medication is "cleared" via exception status
			$isCleared = in_array($extStatus, $clearedStatuses) || $dispenseStatus === 'EXTERNAL';
			if ($isCleared) {
				$res['external_count']++;
				$res['cleared_count']++;
				continue; // Skip billing and dispense checks for cleared items
			}

			// Check billing lock (only for non-cleared items)
			$this->db->where(array(
				'source_module' => 'PHARMACY',
				'source_ref' => 'iop_medication:' . $mid,
				'InActive' => 0
			));
			$this->db->limit(1);
			$lock = $this->db->get('iop_billable_item_lock')->row();
			if (!$lock) {
				$res['missing_billing']++;
			}
			
			// Check dispensing (only for non-cleared items)
			$total = isset($m->total_qty) ? (float)$m->total_qty : 0.0;
			$dispensed = isset($dispMap[$midStr]) ? (float)$dispMap[$midStr] : 0.0;
			if ($total > 0 && $dispensed + 0.0001 < $total) {
				$res['missing_dispense']++;
			} else {
				$res['cleared_count']++;
			}
		}

		$res['ok'] = ($res['missing_billing'] === 0 && $res['missing_dispense'] === 0);
		return $res;
	}

	public function get_invoice_settlement($invoice_no){
		$invoice_no = (string)$invoice_no;
		$out = array(
			'invoice_no' => $invoice_no,
			'total' => 0.0,
			'paid' => 0.0,
			'payment_type' => '',
			'is_settled' => false
		);
		if ($invoice_no === '' || !$this->table_exists('iop_billing')) {
			$out['is_settled'] = true;
			return $out;
		}
		$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
		$this->db->limit(1);
		$hdr = $this->db->get('iop_billing')->row();
		if ($hdr) {
			$out['payment_type'] = isset($hdr->payment_type) ? (string)$hdr->payment_type : '';
			$out['total'] = isset($hdr->total_amount) ? (float)$hdr->total_amount : 0.0;
		}
		$paid = 0.0;
		if ($this->table_exists('iop_receipt')) {
			$this->db->select('SUM(amountPaid) AS paid_amount', false);
			$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
			$r = $this->db->get('iop_receipt')->row();
			$paid = ($r && isset($r->paid_amount)) ? (float)$r->paid_amount : 0.0;
		}
		$out['paid'] = $paid;
		$ptype = strtolower(trim((string)$out['payment_type']));
		$payerCol = ($hdr && isset($hdr->payer_type)) ? strtoupper(trim((string)$hdr->payer_type)) : '';
		if ($ptype === 'insurance' || $payerCol === 'NHIS') {
			$out['is_settled'] = true;
		} else {
			$out['is_settled'] = ($out['paid'] + 0.0001 >= $out['total']);
		}
		return $out;
	}

	public function has_unpaid_billable_locks_for_iop($iop_id){
		$this->install_billing_meta_tables();
		$iop_id = (string)$iop_id;
		if ($iop_id === '' || !$this->table_exists('iop_billable_item_lock')) {
			return false;
		}
		$this->db->select('COUNT(*) AS c', false);
		$this->db->from('iop_billable_item_lock L');
		$this->db->where(array('L.iop_id' => $iop_id, 'L.InActive' => 0));
		$this->db->where("L.status != 'PAID'", null, false);
		if ($this->table_exists('iop_billing')) {
			$this->db->join('iop_billing B', "B.invoice_no = L.invoice_no AND B.InActive = 0", 'left');
			$this->db->where("(B.invoice_no IS NULL OR LOWER(B.payment_type) != 'insurance')", null, false);
		}
		$q = $this->db->get();
		$r = $q ? $q->row() : null;
		return ($r && isset($r->c)) ? ((int)$r->c > 0) : false;
	}

	public function has_unpaid_invoices($iop_id){
		$iop_id = trim((string)$iop_id);
		if ($iop_id === '' || !$this->table_exists('iop_billing')) {
			return false;
		}

		$this->ensure_billing_enhancements();
		$this->db->select('invoice_no, payment_status, balance_due, payer_type, payment_type');
		$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
		$rows = $this->db->get('iop_billing')->result();
		if (empty($rows)) {
			return false;
		}

		foreach ($rows as $row) {
			$invoice_no = isset($row->invoice_no) ? trim((string)$row->invoice_no) : '';
			if ($invoice_no === '') {
				continue;
			}

			$settlement = $this->get_invoice_settlement($invoice_no);
			if ($settlement && isset($settlement['is_settled']) && $settlement['is_settled']) {
				continue;
			}

			$status = isset($row->payment_status) ? strtoupper(trim((string)$row->payment_status)) : '';
			$balance = isset($row->balance_due) ? (float)$row->balance_due : 0.0;
			if (in_array($status, array('UNPAID', 'PENDING', 'PARTIAL'), true) || $balance > 0.009) {
				return true;
			}

			return true;
		}

		return false;
	}

	public function final_clearance_requirements($iop_id, $patient_no = null){
		$this->install_clearance_workflow_tables();
		$this->install_billing_meta_tables();
		$iop_id = (string)$iop_id;
		$encounter_type = 'OPD';
		if ($iop_id !== '' && $this->table_exists('patient_details_iop')) {
			$this->db->select('patient_type, patient_no');
			$this->db->where(array('IO_ID' => $iop_id, 'InActive' => 0));
			$this->db->limit(1);
			$enc = $this->db->get('patient_details_iop')->row();
			if ($enc) {
				$encounter_type = isset($enc->patient_type) ? strtoupper(trim((string)$enc->patient_type)) : 'OPD';
				if (($patient_no === null || trim((string)$patient_no) === '') && isset($enc->patient_no)) {
					$patient_no = (string)$enc->patient_no;
				}
			}
		}
		if ($encounter_type === 'IPD') {
			$this->generate_ipd_room_charges($iop_id, null);
		}
		$res = array(
			'ok' => false,
			'encounter_type' => $encounter_type,
			'clinical_ok' => false,
			'medication_ok' => false,
			'billing_ok' => false,
			'pharmacy_billing_ok' => true,
			'unpaid_locks' => 0,
			'unsettled_invoices' => 0,
			'unpaid_pharmacy_bills' => 0,
		);
		$wf = $this->get_clearance_workflow($iop_id);
		$res['clinical_ok'] = ($wf && isset($wf->clinical_cleared_at) && trim((string)$wf->clinical_cleared_at) !== '' && (string)$wf->clinical_cleared_at !== '0000-00-00 00:00:00');
		$res['medication_ok'] = ($wf && isset($wf->medication_cleared_at) && trim((string)$wf->medication_cleared_at) !== '' && (string)$wf->medication_cleared_at !== '0000-00-00 00:00:00');
		if ($encounter_type === 'IPD' && $this->table_exists('iop_discharge_summary')) {
			$this->db->select('COUNT(*) AS c', false);
			$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
			$ds = $this->db->get('iop_discharge_summary')->row();
			$res['clinical_ok'] = ($ds && isset($ds->c) && (int)$ds->c > 0);
			if ($res['clinical_ok']) {
				$this->upsert_clearance_stage($iop_id, 'CLINICAL', $patient_no, null);
			}
		}
		if (!$res['medication_ok']) {
			$medReq = $this->medication_clearance_requirements($iop_id);
			if ($medReq && isset($medReq['ok']) && $medReq['ok']) {
				$res['medication_ok'] = true;
				$this->upsert_clearance_stage($iop_id, 'MEDICATION', $patient_no, null);
			}
		}

		if ($this->table_exists('iop_billable_item_lock')) {
			$this->db->select('COUNT(*) AS c', false);
			$this->db->from('iop_billable_item_lock L');
			$this->db->where(array('L.iop_id' => $iop_id, 'L.InActive' => 0));
			$this->db->where("L.status != 'PAID'", null, false);
			if ($this->table_exists('iop_billing')) {
				$this->db->join('iop_billing B', "B.invoice_no = L.invoice_no AND B.InActive = 0", 'left');
				$this->db->where("(B.invoice_no IS NULL OR LOWER(B.payment_type) != 'insurance')", null, false);
			}
			$q = $this->db->get();
			$r = $q ? $q->row() : null;
			$res['unpaid_locks'] = ($r && isset($r->c)) ? (int)$r->c : 0;
		}

		$unsettled = 0;
		if ($this->table_exists('iop_billing')) {
			$this->db->select('invoice_no');
			$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
			$hq = $this->db->get('iop_billing');
			$rows = $hq ? $hq->result() : array();
			foreach ($rows as $r) {
				$inv = isset($r->invoice_no) ? (string)$r->invoice_no : '';
				if ($inv === '') {
					continue;
				}
				$settle = $this->get_invoice_settlement($inv);
				if (!$settle || !isset($settle['is_settled']) || !$settle['is_settled']) {
					$unsettled++;
				}
			}
		}
		$res['unsettled_invoices'] = $unsettled;
		$res['billing_ok'] = ($res['unpaid_locks'] === 0 && $res['unsettled_invoices'] === 0);

		if ($this->table_exists('pharmacy_billing_queue')) {
			$this->db->select('COUNT(*) AS c', false);
			$this->db->from('pharmacy_billing_queue');
			$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
			/* All exception statuses are treated as cleared for final discharge */
			$this->db->where("payment_status NOT IN ('PAID','CANCELLED','EXTERNAL','EXTERNAL_PURCHASE','UNABLE_TO_PAY','DEFERRED','WAIVED','EMERGENCY','ADMITTED')", null, false);
			/* Also respect extended_status if column exists */
			if ($this->column_exists('pharmacy_billing_queue', 'extended_status')) {
				$this->db->where("extended_status NOT IN ('EXTERNAL_PURCHASE','UNABLE_TO_PAY','DEFERRED','WAIVED','EMERGENCY','ADMITTED','WAIVER_REQUESTED')", null, false);
			}
			$pbq = $this->db->get()->row();
			$res['unpaid_pharmacy_bills'] = ($pbq && isset($pbq->c)) ? (int)$pbq->c : 0;
			$res['pharmacy_billing_ok'] = ($res['unpaid_pharmacy_bills'] === 0);
		}

		$res['ok'] = ($res['clinical_ok'] && $res['medication_ok'] && $res['billing_ok'] && $res['pharmacy_billing_ok']);
		return $res;
	}

	public function table_exists($table_name){
		$table_name = (string) $table_name;
		if (array_key_exists($table_name, $this->_table_exists_cache)) {
			return $this->_table_exists_cache[$table_name];
		}
		$q = $this->db->query("SHOW TABLES LIKE ".$this->db->escape($table_name));
		$this->_table_exists_cache[$table_name] = ($q && $q->num_rows() > 0);
		return $this->_table_exists_cache[$table_name];
	}

	public function column_exists($table_name, $column_name){
		$table_name = (string) $table_name;
		$column_name = (string) $column_name;
		$cache_key = $table_name . '.' . $column_name;
		if (array_key_exists($cache_key, $this->_column_exists_cache)) {
			return $this->_column_exists_cache[$cache_key];
		}
		if (!$this->table_exists($table_name)) {
			$this->_column_exists_cache[$cache_key] = false;
			return false;
		}
		$q = $this->db->query("SHOW COLUMNS FROM `".$table_name."` LIKE ".$this->db->escape($column_name));
		$this->_column_exists_cache[$cache_key] = ($q && $q->num_rows() > 0);
		return $this->_column_exists_cache[$cache_key];
	}

	public function install_billing_meta_tables(){
		if (!$this->table_exists('iop_billing')) {
			return false;
		}
		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_billing_line_meta` (\n".
			"  `meta_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `invoice_no` varchar(50) NOT NULL,\n".
			"  `detail_id` int(11) NOT NULL,\n".
			"  `source_module` varchar(30) DEFAULT NULL,\n".
			"  `source_ref` varchar(100) DEFAULT NULL,\n".
			"  `created_at` datetime NOT NULL,\n".
			"  `created_by` varchar(25) DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`meta_id`),\n".
			"  KEY `idx_invoice` (`invoice_no`),\n".
			"  KEY `idx_detail` (`detail_id`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");
		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_billable_item_lock` (\n".
			"  `lock_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `source_module` varchar(30) NOT NULL,\n".
			"  `source_ref` varchar(120) NOT NULL,\n".
			"  `invoice_no` varchar(50) NOT NULL,\n".
			"  `detail_id` int(11) DEFAULT NULL,\n".
			"  `iop_id` varchar(25) DEFAULT NULL,\n".
			"  `patient_no` varchar(25) DEFAULT NULL,\n".
			"  `status` varchar(20) NOT NULL DEFAULT 'INVOICED',\n".
			"  `locked_at` datetime NOT NULL,\n".
			"  `locked_by` varchar(25) DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`lock_id`),\n".
			"  KEY `idx_source` (`source_module`,`source_ref`),\n".
			"  KEY `idx_invoice` (`invoice_no`),\n".
			"  KEY `idx_source_invoice` (`source_module`,`source_ref`,`invoice_no`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");
		if ($this->table_exists('iop_billable_item_lock')) {
			$prevDebug = isset($this->db->db_debug) ? $this->db->db_debug : null;
			if ($prevDebug !== null) { $this->db->db_debug = false; }

			$idxOld = $this->db->query("SHOW INDEX FROM `iop_billable_item_lock` WHERE Key_name = 'uq_src_inv'");
			if ($idxOld && $idxOld->num_rows() > 0) {
				$this->db->query("ALTER TABLE `iop_billable_item_lock` DROP INDEX `uq_src_inv`");
			}
			$idxOld2 = $this->db->query("SHOW INDEX FROM `iop_billable_item_lock` WHERE Key_name = 'uq_src_inv_active'");
			if ($idxOld2 && $idxOld2->num_rows() > 0) {
				$this->db->query("ALTER TABLE `iop_billable_item_lock` DROP INDEX `uq_src_inv_active`");
			}
			$idxOld3 = $this->db->query("SHOW INDEX FROM `iop_billable_item_lock` WHERE Key_name = 'uq_lock'");
			if ($idxOld3 && $idxOld3->num_rows() > 0) {
				$this->db->query("ALTER TABLE `iop_billable_item_lock` DROP INDEX `uq_lock`");
			}
			$idxNew = $this->db->query("SHOW INDEX FROM `iop_billable_item_lock` WHERE Key_name = 'uq_src_active'");
			if (!$idxNew || $idxNew->num_rows() === 0) {
				$this->db->query("ALTER TABLE `iop_billable_item_lock` ADD UNIQUE KEY `uq_src_active` (`source_module`,`source_ref`,`InActive`)");
			}

			if ($prevDebug !== null) { $this->db->db_debug = $prevDebug; }
		}
		return true;
	}

	public function reconcile_ipd_room_charges($iop_id){
		$this->install_ipd_room_charge_tables();
		$iop_id = (string)$iop_id;
		if ($iop_id === '') {
			return null;
		}
		if (!$this->table_exists('iop_room_charge')) {
			return null;
		}

		$q1 = $this->db->query(
			"SELECT " .
			"  SUM(CASE WHEN status = 'PENDING' THEN rate_amount * quantity ELSE 0 END) AS pending_amount, " .
			"  SUM(CASE WHEN status = 'INVOICED' THEN rate_amount * quantity ELSE 0 END) AS invoiced_amount, " .
			"  SUM(CASE WHEN status = 'PAID' THEN rate_amount * quantity ELSE 0 END) AS paid_amount, " .
			"  COUNT(*) AS total_rows " .
			"FROM iop_room_charge WHERE iop_id = " . $this->db->escape($iop_id) . " AND InActive = 0"
		);
		$totals = $q1 ? $q1->row() : null;

		$q2 = null;
		$invoiceTotals = null;
		if ($this->table_exists('iop_billing_t') && $this->table_exists('iop_billing_line_meta')) {
			$q2 = $this->db->query(
				"SELECT " .
				"  SUM(T.amount) AS billed_amount, " .
				"  COUNT(*) AS billed_rows " .
				"FROM iop_billing_t T " .
				"JOIN iop_billing_line_meta M ON M.invoice_no = T.invoice_no AND M.detail_id = T.id AND M.InActive = 0 " .
				"WHERE T.InActive = 0 AND M.source_module = 'IPD_ROOM' AND T.iop_id = " . $this->db->escape($iop_id)
			);
			$invoiceTotals = $q2 ? $q2->row() : null;
		}

		return array(
			'ledger' => $totals,
			'invoiced_lines' => $invoiceTotals
		);
	}

	public function install_ipd_room_charge_tables(){
		$this->install_billing_meta_tables();
		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_room_charge` (\n".
			"  `charge_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `iop_id` varchar(25) NOT NULL,\n".
			"  `patient_no` varchar(25) DEFAULT NULL,\n".
			"  `room_master_id` int(11) DEFAULT NULL,\n".
			"  `bed_id` int(11) DEFAULT NULL,\n".
			"  `billing_category` varchar(30) NOT NULL DEFAULT 'IPD_ROOM',\n".
			"  `rate_type` varchar(30) NOT NULL DEFAULT 'daily_24h',\n".
			"  `rate_amount` decimal(18,2) NOT NULL DEFAULT 0,\n".
			"  `quantity` decimal(18,2) NOT NULL DEFAULT 1,\n".
			"  `charge_start_at` datetime NOT NULL,\n".
			"  `charge_end_at` datetime NOT NULL,\n".
			"  `source` varchar(20) NOT NULL DEFAULT 'admission',\n".
			"  `source_ref` varchar(120) DEFAULT NULL,\n".
			"  `status` varchar(20) NOT NULL DEFAULT 'PENDING',\n".
			"  `invoice_no` varchar(50) DEFAULT NULL,\n".
			"  `detail_id` int(11) DEFAULT NULL,\n".
			"  `created_at` datetime NOT NULL,\n".
			"  `created_by` varchar(25) DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`charge_id`),\n".
			"  UNIQUE KEY `uq_iop_start_bed` (`iop_id`,`charge_start_at`,`bed_id`),\n".
			"  KEY `idx_iop_status` (`iop_id`,`status`),\n".
			"  KEY `idx_invoice` (`invoice_no`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");
		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_discharge_audit` (\n".
			"  `audit_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `iop_id` varchar(25) NOT NULL,\n".
			"  `patient_no` varchar(25) DEFAULT NULL,\n".
			"  `discharged_at` datetime NOT NULL,\n".
			"  `discharged_by` varchar(25) DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`audit_id`),\n".
			"  UNIQUE KEY `uq_iop` (`iop_id`),\n".
			"  KEY `idx_dt` (`discharged_at`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");
		return true;
	}

	public function get_ipd_discharge_datetime($iop_id){
		if (!$this->table_exists('iop_discharge_audit')) {
			return null;
		}
		$iop_id = (string)$iop_id;
		$q = $this->db->get_where('iop_discharge_audit', array('iop_id' => $iop_id, 'InActive' => 0), 1);
		$r = $q ? $q->row() : null;
		if ($r && isset($r->discharged_at) && trim((string)$r->discharged_at) !== '') {
			return (string)$r->discharged_at;
		}
		return null;
	}

	public function save_ipd_discharge_audit($iop_id, $patient_no, $discharged_at, $discharged_by = null){
		$this->install_ipd_room_charge_tables();
		$iop_id = (string)$iop_id;
		$discharged_at = (string)$discharged_at;
		$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
		$this->db->limit(1);
		$exists = $this->db->get('iop_discharge_audit')->row();
		if ($exists) {
			return true;
		}
		$this->db->insert('iop_discharge_audit', array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no !== null ? (string)$patient_no : null,
			'discharged_at' => $discharged_at,
			'discharged_by' => $discharged_by !== null ? (string)$discharged_by : null,
			'InActive' => 0
		));
		return true;
	}

	private function lock_billable_item($source_module, $source_ref, $invoice_no, $detail_id = null, $iop_id = null, $patient_no = null, $status = 'INVOICED'){
		$this->install_billing_meta_tables();
		$source_module = trim((string)$source_module);
		$source_ref = trim((string)$source_ref);
		$invoice_no = (string)$invoice_no;
		if ($source_module === '' || $source_ref === '' || $invoice_no === '') {
			return false;
		}
		$this->db->where(array(
			'source_module' => $source_module,
			'source_ref' => $source_ref,
			'InActive' => 0
		));
		$this->db->limit(1);
		$exists = $this->db->get('iop_billable_item_lock')->row();
		if ($exists) {
			return ((isset($exists->invoice_no) ? (string)$exists->invoice_no : '') === (string)$invoice_no);
		}
		$lockedBy = null;
		if (isset($this->session) && is_object($this->session) && method_exists($this->session, 'userdata')) {
			$lockedBy = (string)$this->session->userdata('user_id');
			if (trim($lockedBy) === '') {
				$lockedBy = null;
			}
		}
		$this->db->insert('iop_billable_item_lock', array(
			'source_module' => $source_module,
			'source_ref' => $source_ref,
			'invoice_no' => $invoice_no,
			'detail_id' => $detail_id !== null ? (int)$detail_id : null,
			'iop_id' => $iop_id !== null ? (string)$iop_id : null,
			'patient_no' => $patient_no !== null ? (string)$patient_no : null,
			'status' => (string)$status,
			'locked_at' => date('Y-m-d H:i:s'),
			'locked_by' => $lockedBy,
			'InActive' => 0
		));
		return true;
	}

	/**
	 * Audit-preserving migration of legacy IPD_BED_SIDE / IPD_OT locks.
	 * - Identifies locks whose source_ref id is NOT a valid event-row PK.
	 * - Marks them InActive=1 (audit-preserving, no rewrite).
	 * - Annotates them with legacy_migrated_at if the column exists (best-effort).
	 * - Returns counts for finance reconciliation reporting.
	 */
	public function migrate_legacy_procedure_locks($user_id = null){
		$this->install_billing_meta_tables();
		$result = array(
			'bedside_deactivated' => 0,
			'ot_deactivated' => 0,
			'errors' => array()
		);
		if (!$this->table_exists('iop_billable_item_lock')) {
			$result['errors'][] = 'iop_billable_item_lock table not found';
			return $result;
		}

		// Best-effort: add legacy_migrated_at + legacy_migrated_by columns once
		$prevDebug = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prevDebug !== null) { $this->db->db_debug = false; }
		if (!$this->column_exists('iop_billable_item_lock', 'legacy_migrated_at')) {
			$this->db->query("ALTER TABLE `iop_billable_item_lock` ADD COLUMN `legacy_migrated_at` datetime DEFAULT NULL");
		}
		if (!$this->column_exists('iop_billable_item_lock', 'legacy_migrated_by')) {
			$this->db->query("ALTER TABLE `iop_billable_item_lock` ADD COLUMN `legacy_migrated_by` varchar(25) DEFAULT NULL");
		}
		if ($prevDebug !== null) { $this->db->db_debug = $prevDebug; }

		$now = date('Y-m-d H:i:s');
		$user = $user_id !== null ? (string)$user_id : null;

		// IPD_BED_SIDE legacy detection: source_ref id should match an existing bed_pro_id in iop_bed_side_procedure (active or inactive)
		if ($this->table_exists('iop_bed_side_procedure')) {
			$this->db->select('lock_id, source_ref');
			$this->db->from('iop_billable_item_lock');
			$this->db->where(array('source_module' => 'IPD_BED_SIDE', 'InActive' => 0));
			$rows = $this->db->get()->result();
			foreach ($rows as $r) {
				$src = isset($r->source_ref) ? trim((string)$r->source_ref) : '';
				if (strpos($src, 'iop_bed_side_procedure:') !== 0) { continue; }
				$id = (int)substr($src, strlen('iop_bed_side_procedure:'));
				if ($id <= 0) { continue; }
				$exists = $this->db->get_where('iop_bed_side_procedure', array('bed_pro_id' => $id))->row();
				if (!$exists) {
					$update = array('InActive' => 1);
					if ($this->column_exists('iop_billable_item_lock', 'legacy_migrated_at')) {
						$update['legacy_migrated_at'] = $now;
					}
					if ($this->column_exists('iop_billable_item_lock', 'legacy_migrated_by') && $user !== null) {
						$update['legacy_migrated_by'] = $user;
					}
					$this->db->where('lock_id', (int)$r->lock_id);
					$this->db->update('iop_billable_item_lock', $update);
					$result['bedside_deactivated']++;
				}
			}
		}

		// IPD_OT legacy detection
		if ($this->table_exists('iop_operation_theater')) {
			$this->db->select('lock_id, source_ref');
			$this->db->from('iop_billable_item_lock');
			$this->db->where(array('source_module' => 'IPD_OT', 'InActive' => 0));
			$rows = $this->db->get()->result();
			foreach ($rows as $r) {
				$src = isset($r->source_ref) ? trim((string)$r->source_ref) : '';
				if (strpos($src, 'iop_operation_theater:') !== 0) { continue; }
				$id = (int)substr($src, strlen('iop_operation_theater:'));
				if ($id <= 0) { continue; }
				$exists = $this->db->get_where('iop_operation_theater', array('operation_id' => $id))->row();
				if (!$exists) {
					$update = array('InActive' => 1);
					if ($this->column_exists('iop_billable_item_lock', 'legacy_migrated_at')) {
						$update['legacy_migrated_at'] = $now;
					}
					if ($this->column_exists('iop_billable_item_lock', 'legacy_migrated_by') && $user !== null) {
						$update['legacy_migrated_by'] = $user;
					}
					$this->db->where('lock_id', (int)$r->lock_id);
					$this->db->update('iop_billable_item_lock', $update);
					$result['ot_deactivated']++;
				}
			}
		}

		return $result;
	}

	/**
	 * Reconciliation listing of deactivated legacy procedure locks for finance review.
	 */
	public function get_legacy_procedure_locks($limit = 200){
		$out = array();
		if (!$this->table_exists('iop_billable_item_lock')) { return $out; }
		$limit = (int)$limit;
		if ($limit <= 0) { $limit = 200; }
		$this->db->select('lock_id, source_module, source_ref, invoice_no, iop_id, patient_no, status, locked_at' . ($this->column_exists('iop_billable_item_lock', 'legacy_migrated_at') ? ', legacy_migrated_at' : '') . ($this->column_exists('iop_billable_item_lock', 'legacy_migrated_by') ? ', legacy_migrated_by' : ''));
		$this->db->from('iop_billable_item_lock');
		$this->db->where_in('source_module', array('IPD_BED_SIDE', 'IPD_OT'));
		$this->db->where('InActive', 1);
		$this->db->order_by('lock_id', 'DESC');
		$this->db->limit($limit);
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	public function mark_invoice_billable_items_paid($invoice_no){
		$this->install_billing_meta_tables();
		$invoice_no = (string)$invoice_no;
		if ($invoice_no === '') {
			return false;
		}
		$this->load->model('app/laboratory_model');
		$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
		$this->db->update('iop_billable_item_lock', array('status' => 'PAID'));
		if ($this->table_exists('iop_room_charge')) {
			$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
			$this->db->update('iop_room_charge', array('status' => 'PAID'));
		}
		if ($this->table_exists('iop_sonography_charge')) {
			// Re-link sonography charges using invoice line meta (covers cases where charge.invoice_no
			// was never stamped during invoicing, which can cause "paid but gate says pending" drift).
			if ($this->table_exists('iop_billing_line_meta')) {
				$this->db->select('source_ref');
				$this->db->from('iop_billing_line_meta');
				$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
				$this->db->where('source_module', 'SONOGRAPHY');
				$metaRows = $this->db->get()->result();
				if (!empty($metaRows)) {
					foreach ($metaRows as $m) {
						$src = $m && isset($m->source_ref) ? trim((string)$m->source_ref) : '';
						if ($src === '') { continue; }
						// Newer flow: iop_sonography_charge:{charge_id}
						if (strpos($src, 'iop_sonography_charge:') === 0) {
							$cid = (int)substr($src, strlen('iop_sonography_charge:'));
							if ($cid > 0) {
								$this->db->where(array('charge_id' => $cid, 'InActive' => 0));
								$this->db->update('iop_sonography_charge', array('invoice_no' => $invoice_no, 'status' => 'PAID'));
							}
							continue;
						}
						// Legacy flow: iop_sonography_request:{io_lab_id}
						if (strpos($src, 'iop_sonography_request:') === 0) {
							$io = (int)substr($src, strlen('iop_sonography_request:'));
							if ($io > 0) {
								$this->db->where(array('io_lab_id' => $io, 'InActive' => 0));
								$this->db->update('iop_sonography_charge', array('invoice_no' => $invoice_no, 'status' => 'PAID'));
							}
							continue;
						}
					}
				}
			}

			$this->db->select('charge_id');
			$this->db->from('iop_sonography_charge');
			$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
			$charges = $this->db->get()->result();
			$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
			$this->db->update('iop_sonography_charge', array('status' => 'PAID'));
			if (!empty($charges) && isset($this->laboratory_model) && method_exists($this->laboratory_model, 'sync_order_master_from_sonography_charge')) {
				foreach ($charges as $c) {
					$cid = isset($c->charge_id) ? (int)$c->charge_id : 0;
					if ($cid > 0) {
						try {
							$this->laboratory_model->sync_order_master_from_sonography_charge($cid, null, date('Y-m-d H:i:s'), false);
						} catch (\Throwable $e) {
							log_message('error', 'SONOGRAPHY finance->SSOT sync failed (PAID) invoice_no=' . $invoice_no . ' charge_id=' . $cid . ': ' . $e->getMessage());
						}
					}
				}
			}
		}
		return true;
	}

	public function reconcile_billable_item_locks_for_invoice($invoice_no, $invoice_status){
		$this->install_billing_meta_tables();
		$invoice_no = (string)$invoice_no;
		$invoice_status = strtoupper(trim((string)$invoice_status));
		if ($invoice_no === '') {
			return false;
		}
		if ($invoice_status === 'PAID') {
			return $this->mark_invoice_billable_items_paid($invoice_no);
		}
		$this->load->model('app/laboratory_model');
		if ($this->table_exists('iop_billable_item_lock')) {
			$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
			$this->db->where('status', 'PAID');
			$this->db->update('iop_billable_item_lock', array('status' => 'INVOICED'));
		}
		if ($this->table_exists('iop_room_charge')) {
			$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
			$this->db->where('status', 'PAID');
			$this->db->update('iop_room_charge', array('status' => 'INVOICED'));
		}
		if ($this->table_exists('iop_sonography_charge')) {
			$this->db->select('charge_id');
			$this->db->from('iop_sonography_charge');
			$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
			$this->db->where('status', 'PAID');
			$charges = $this->db->get()->result();
			$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
			$this->db->where('status', 'PAID');
			$this->db->update('iop_sonography_charge', array('status' => 'INVOICED'));
			if (!empty($charges) && isset($this->laboratory_model) && method_exists($this->laboratory_model, 'sync_order_master_from_sonography_charge')) {
				foreach ($charges as $c) {
					$cid = isset($c->charge_id) ? (int)$c->charge_id : 0;
					if ($cid > 0) {
						try {
							$this->laboratory_model->sync_order_master_from_sonography_charge($cid, null, date('Y-m-d H:i:s'), false);
						} catch (\Throwable $e) {
							log_message('error', 'SONOGRAPHY finance->SSOT sync failed (INVOICED) invoice_no=' . $invoice_no . ' charge_id=' . $cid . ': ' . $e->getMessage());
						}
					}
				}
			}
		}
		return true;
	}

	public function generate_ipd_room_charges($iop_id, $until_datetime = null){
		$this->install_ipd_room_charge_tables();
		$iop_id = (string)$iop_id;
		if ($iop_id === '') {
			return false;
		}

		$until = $until_datetime !== null ? (string)$until_datetime : null;
		if ($until === null || trim($until) === '') {
			$disc = $this->get_ipd_discharge_datetime($iop_id);
			$until = $disc !== null ? $disc : date('Y-m-d H:i:s');
		}
		$untilTs = strtotime($until);
		if ($untilTs === false) {
			$untilTs = time();
			$until = date('Y-m-d H:i:s', $untilTs);
		}

		if (!$this->table_exists('iop_room_transfer')) {
			return true;
		}

		$this->db->order_by('dDateTime', 'ASC');
		$events = $this->db->get_where('iop_room_transfer', array('iop_id' => $iop_id, 'InActive' => 0))->result();
		if (!$events || count($events) === 0) {
			return true;
		}

		$this->db->select('patient_no');
		$this->db->where(array('IO_ID' => $iop_id, 'InActive' => 0));
		$this->db->limit(1);
		$adm = $this->db->get('patient_details_iop')->row();
		$patient_no = ($adm && isset($adm->patient_no)) ? (string)$adm->patient_no : null;

		for ($idx = 0; $idx < count($events); $idx++) {
			$ev = $events[$idx];
			$segStart = isset($ev->dDateTime) ? (string)$ev->dDateTime : null;
			if ($segStart === null || trim($segStart) === '') {
				continue;
			}
			$segStartTs = strtotime($segStart);
			if ($segStartTs === false) {
				continue;
			}
			$segEnd = $until;
			if ($idx + 1 < count($events)) {
				$nextDt = isset($events[$idx + 1]->dDateTime) ? (string)$events[$idx + 1]->dDateTime : '';
				$nextTs = strtotime($nextDt);
				if ($nextTs !== false && $nextTs < $untilTs) {
					$segEnd = $nextDt;
				}
			}
			$segEndTs = strtotime($segEnd);
			if ($segEndTs === false || $segEndTs <= $segStartTs) {
				continue;
			}

			$roomMasterId = isset($ev->room_master_id) ? (int)$ev->room_master_id : null;
			$bedId = isset($ev->bed_id) ? (int)$ev->bed_id : null;
			$transferId = isset($ev->transfer_id) ? (int)$ev->transfer_id : null;
			$source = 'transfer';
			if (isset($ev->reason) && stripos((string)$ev->reason, 'admitted') !== false) {
				$source = 'admission';
			}
			$sourceRef = $transferId !== null ? ('iop_room_transfer:' . (string)$transferId) : null;

			$rateAmt = 0.0;
			if ($roomMasterId !== null) {
				$this->db->select('room_rates');
				$this->db->where(array('room_master_id' => $roomMasterId));
				$this->db->limit(1);
				$rm = $this->db->get('room_master')->row();
				$rateAmt = ($rm && isset($rm->room_rates)) ? (float)$rm->room_rates : 0.0;
			}

			$blockStartTs = $segStartTs;
			while ($blockStartTs < $segEndTs) {
				$blockEndTs = $blockStartTs + (24 * 60 * 60);
				if ($blockEndTs > $segEndTs) {
					$blockEndTs = $segEndTs;
				}
				$blockStart = date('Y-m-d H:i:s', $blockStartTs);
				$blockEnd = date('Y-m-d H:i:s', $blockEndTs);

				$this->db->where(array('iop_id' => $iop_id, 'charge_start_at' => $blockStart, 'bed_id' => $bedId, 'InActive' => 0));
				$this->db->limit(1);
				$exists = $this->db->get('iop_room_charge')->row();
				if (!$exists) {
					$createdBy = null;
					if (isset($this->session) && is_object($this->session) && method_exists($this->session, 'userdata')) {
						$createdBy = (string)$this->session->userdata('user_id');
						if (trim($createdBy) === '') {
							$createdBy = null;
						}
					}
					$this->db->insert('iop_room_charge', array(
						'iop_id' => $iop_id,
						'patient_no' => $patient_no,
						'room_master_id' => $roomMasterId,
						'bed_id' => $bedId,
						'billing_category' => 'IPD_ROOM',
						'rate_type' => 'daily_24h',
						'rate_amount' => $rateAmt,
						'quantity' => 1,
						'charge_start_at' => $blockStart,
						'charge_end_at' => $blockEnd,
						'source' => $source,
						'source_ref' => $sourceRef,
						'status' => 'PENDING',
						'invoice_no' => null,
						'detail_id' => null,
						'created_at' => date('Y-m-d H:i:s'),
						'created_by' => $createdBy,
						'InActive' => 0
					));
				}
				$blockStartTs = $blockEndTs;
			}
		}

		$this->load->model('app/billing_transaction_model');
		if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'ensure_billing_transaction_schema')) {
			$this->billing_transaction_model->ensure_billing_transaction_schema();
		}
		if ($this->table_exists('billing_transactions')) {
			$this->db->select('charge_id');
			$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
			$this->db->group_start();
			$this->db->where('invoice_no IS NULL', null, false);
			$this->db->or_where('invoice_no', '');
			$this->db->group_end();
			$charges = $this->db->get('iop_room_charge')->result();
			if (!empty($charges) && isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'sync_ipd_room_charge')) {
				$user_id = null;
				if (isset($this->session) && is_object($this->session) && method_exists($this->session, 'userdata')) {
					$user_id = (string)$this->session->userdata('user_id');
					if (trim((string)$user_id) === '') {
						$user_id = null;
					}
				}
				foreach ($charges as $c) {
					$cid = isset($c->charge_id) ? (int)$c->charge_id : 0;
					if ($cid > 0) {
						try {
							$this->billing_transaction_model->sync_ipd_room_charge($cid, $user_id);
						} catch (\Throwable $e) {
							log_message('error', 'IPD room charge SSOT sync failed iop_id=' . $iop_id . ' charge_id=' . $cid . ': ' . $e->getMessage());
						}
					}
				}
			}
		}

		return true;
	}

	public function install_pharmacy_dispense_audit_tables(){
		$this->install_billing_meta_tables();
		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_pharmacy_dispense_audit` (\n".
			"  `audit_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `receipt_no` varchar(50) DEFAULT NULL,\n".
			"  `invoice_no` varchar(50) NOT NULL,\n".
			"  `detail_id` int(11) NOT NULL,\n".
			"  `iop_id` varchar(25) DEFAULT NULL,\n".
			"  `patient_no` varchar(25) DEFAULT NULL,\n".
			"  `drug_id` int(11) DEFAULT NULL,\n".
			"  `drug_name` varchar(255) DEFAULT NULL,\n".
			"  `qty` decimal(18,2) DEFAULT NULL,\n".
			"  `rate` decimal(18,2) DEFAULT NULL,\n".
			"  `amount` decimal(18,2) DEFAULT NULL,\n".
			"  `source_ref` varchar(100) DEFAULT NULL,\n".
			"  `dispensed_by` varchar(25) DEFAULT NULL,\n".
			"  `dispensed_at` datetime NOT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`audit_id`),\n".
			"  KEY `idx_inv` (`invoice_no`),\n".
			"  KEY `idx_receipt` (`receipt_no`),\n".
			"  KEY `idx_drug` (`drug_id`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");
		return true;
	}

	public function record_pharmacy_dispense_audit_from_invoice($invoice_no, $receipt_no = null, $iop_id = null, $patient_no = null, $dispensed_by = null){
		$invoice_no = (string)$invoice_no;
		if (!$this->table_exists('iop_pharmacy_dispense_audit')) {
			return false;
		}
		if (!$this->table_exists('iop_billing_t')) {
			return false;
		}
		if (!$this->table_exists('iop_billing_line_meta')) {
			return false;
		}

		$this->db->select('T.id, T.bill_name, T.qty, T.rate, T.amount, M.source_ref');
		$this->db->from('iop_billing_t T');
		$this->db->join('iop_billing_line_meta M', "M.invoice_no = T.invoice_no AND M.detail_id = T.id AND M.InActive = 0 AND M.source_module = 'PHARMACY'", 'inner');
		$this->db->where('T.invoice_no', $invoice_no);
		$this->db->where('T.InActive', 0);
		$q = $this->db->get();
		$rows = $q ? $q->result() : array();
		if (!$rows || count($rows) === 0) {
			return true;
		}
		$medDrugCache = array();
		foreach ($rows as $r) {
			$detailId = isset($r->id) ? (int)$r->id : 0;
			if ($detailId <= 0) {
				continue;
			}
			$this->db->where(array('invoice_no' => $invoice_no, 'detail_id' => $detailId, 'InActive' => 0));
			$this->db->limit(1);
			$exists = $this->db->get('iop_pharmacy_dispense_audit')->row();
			if ($exists) {
				continue;
			}
			$drugId = null;
			$srcRef = isset($r->source_ref) ? (string)$r->source_ref : '';
			if ($srcRef !== '') {
				$parts = explode('|', $srcRef);
				foreach ($parts as $p) {
					$p = trim((string)$p);
					if (strpos($p, 'medicine_drug_name:') === 0) {
						$drugId = (int) substr($p, strlen('medicine_drug_name:'));
						if ($drugId <= 0) {
							$drugId = null;
						}
						break;
					}
					if (strpos($p, 'iop_medication:') === 0 && $drugId === null) {
						$iopMedId = (int) substr($p, strlen('iop_medication:'));
						if ($iopMedId > 0) {
							if (!array_key_exists($iopMedId, $medDrugCache)) {
								$this->db->select('medicine_id');
								$this->db->where(array('iop_med_id' => $iopMedId, 'InActive' => 0));
								$this->db->limit(1);
								$medRow = $this->db->get('iop_medication')->row();
								$medDrugCache[$iopMedId] = ($medRow && isset($medRow->medicine_id)) ? (int)$medRow->medicine_id : null;
							}
							$drugId = $medDrugCache[$iopMedId];
							if ($drugId !== null && (int)$drugId <= 0) {
								$drugId = null;
							}
						}
					}
				}
			}
			$this->db->insert('iop_pharmacy_dispense_audit', array(
				'receipt_no' => $receipt_no !== null ? (string)$receipt_no : null,
				'invoice_no' => $invoice_no,
				'detail_id' => $detailId,
				'iop_id' => $iop_id !== null ? (string)$iop_id : null,
				'patient_no' => $patient_no !== null ? (string)$patient_no : null,
				'drug_id' => $drugId,
				'drug_name' => isset($r->bill_name) ? (string)$r->bill_name : null,
				'qty' => isset($r->qty) ? (float)$r->qty : null,
				'rate' => isset($r->rate) ? (float)$r->rate : null,
				'amount' => isset($r->amount) ? (float)$r->amount : null,
				'source_ref' => $srcRef !== '' ? $srcRef : null,
				'dispensed_by' => $dispensed_by !== null ? (string)$dispensed_by : null,
				'dispensed_at' => date('Y-m-d H:i:s'),
				'InActive' => 0
			));
		}
		return true;
	}

	public function normalize_insurance_card_status($status){
		$status = strtoupper(trim((string)$status));
		if ($status === '0') {
			$status = 'INACTIVE';
		}
		if ($status === '1') {
			$status = 'ACTIVE';
		}
		// N/A is valid for Self Pay patients
		if ($status !== 'ACTIVE' && $status !== 'INACTIVE' && $status !== 'N/A') {
			$status = 'ACTIVE';
		}
		return $status;
	}

	public function get_patient_insurance_card_status($patient_no){
		$patient_no = (string)$patient_no;
		if (!$this->table_exists('patient_personal_info')) {
			return 'ACTIVE';
		}
		if (!$this->column_exists('patient_personal_info', 'insurance_card_status')) {
			return 'ACTIVE';
		}
		$this->db->select('insurance_card_status');
		$q = $this->db->get_where('patient_personal_info', array('patient_no' => $patient_no, 'InActive' => 0));
		$r = $q ? $q->row() : null;
		$st = ($r && isset($r->insurance_card_status)) ? (string)$r->insurance_card_status : 'ACTIVE';
		return $this->normalize_insurance_card_status($st);
	}
	
	public function getOPDPatient(){
		$this->db->select("
			A.IO_ID,
			concat(B.firstname,' ',B.lastname) as patient
		",false);
		$this->db->order_by("B.lastname","ASC");
		$this->db->where(array(
			'A.nStatus'		=>		'Pending',
			'A.InActive'	=>		0
		));
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$query = $this->db->get("patient_details_iop A");
		return $query->result();	
	}
	
	/**
	 * Search patients for billing - optimized AJAX search
	 * Searches by patient name, patient_no, or IO_ID
	 * Only returns patients with pending visits
	 */
	public function searchPatientForBilling($query = '', $limit = 20){
		$query = trim((string)$query);
		
		// Build select - check if phone column exists
		$hasPhone = $this->column_exists('patient_personal_info', 'phone');
		$selectFields = "A.IO_ID, A.patient_no, A.patient_type, A.date_visit, CONCAT(B.firstname, ' ', B.lastname) as patient_name";
		if ($hasPhone) {
			$selectFields .= ", B.phone";
		}
		
		$this->db->select($selectFields, false);
		$this->db->from('patient_details_iop A');
		$this->db->join('patient_personal_info B', 'B.patient_no = A.patient_no', 'left');
		$this->db->where('A.nStatus', 'Pending');
		$this->db->where('A.InActive', 0);
		
		if ($query !== '') {
			$this->db->group_start();
			$this->db->like('B.firstname', $query);
			$this->db->or_like('B.lastname', $query);
			$this->db->or_like('A.patient_no', $query);
			$this->db->or_like('A.IO_ID', $query);
			if ($hasPhone) {
				$this->db->or_like('B.phone', $query);
			}
			$this->db->group_end();
		}
		
		$this->db->order_by('A.date_visit', 'DESC');
		$this->db->limit($limit);
		
		$results = $this->db->get()->result();
		
		// Format for autocomplete
		$formatted = array();
		if ($results) {
			foreach ($results as $row) {
				$formatted[] = array(
					'id' => $row->IO_ID,
					'patient_no' => $row->patient_no,
					'name' => isset($row->patient_name) ? $row->patient_name : '',
					'phone' => isset($row->phone) ? $row->phone : '',
					'type' => isset($row->patient_type) ? $row->patient_type : '',
					'date' => isset($row->date_visit) ? $row->date_visit : '',
					'label' => (isset($row->patient_name) ? $row->patient_name : '') . ' (' . $row->patient_no . ')'
				);
			}
		}
		
		return $formatted;
	}
	
	
	public function loadPatientInfo($IO_ID){
		$insStatusSelect = "'ACTIVE'";
		if ($this->column_exists('patient_personal_info', 'insurance_card_status')) {
			$insStatusSelect = 'B.insurance_card_status';
		}
		$this->db->select("
				A.IO_ID,
				A.patient_no,
				A.patient_type,
				B.picture,
				A.date_visit,
				A.time_visit,
				B.age,
				concat(B.firstname,' ',B.lastname) as patient,
				B.Insurance_comp,
				B.insurance_no,
				".$insStatusSelect." as insurance_card_status
		",false);
		$this->db->where("A.IO_ID", $IO_ID);
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$query = $this->db->get("patient_details_iop A");
		return $query->row();
	}
	
	
	public function particular_cat(){
		$this->db->order_by("group_name","ASC");
		$this->db->where("InActive","0");	
		$query = $this->db->get("bill_group_name");
		return $query->result();
	}

	public function nurse_ward_service_categories(){
		if (!$this->table_exists('bill_group_name') || !$this->table_exists('bill_particular')) {
			return array();
		}
		$this->db->select('G.group_id, G.group_name, G.group_desc');
		$this->db->from('bill_group_name G');
		$this->db->join('bill_particular B', 'B.group_id = G.group_id AND B.InActive = 0', 'inner');
		$this->db->where("(UPPER(G.group_name) LIKE '%WARD%' OR UPPER(G.group_name) LIKE '%DRESS%' OR UPPER(G.group_name) LIKE '%INJECTION%' OR UPPER(G.group_name) LIKE '%OXYGEN%' OR UPPER(G.group_name) LIKE '%PROCEDURE%' OR UPPER(G.group_name) LIKE '%BED%')", null, false);
		$this->db->group_by('G.group_id, G.group_name, G.group_desc');
		$this->db->order_by('G.group_name', 'ASC');
		$query = $this->db->get();
		return $query ? $query->result() : array();
	}

	public function nurse_ward_service_itemList($id){
		$id = (int)$id;
		if ($id <= 0 || !$this->is_nurse_ward_service_group($id)) {
			return array();
		}
		return $this->itemList($id);
	}

	public function nurse_ward_service_particularName($id){
		$id = (int)$id;
		if ($id <= 0 || !$this->is_nurse_ward_service_group($id)) {
			return null;
		}
		return $this->particularName($id);
	}

	private function is_nurse_ward_service_group($id){
		if (!$this->table_exists('bill_group_name')) {
			return false;
		}
		$this->db->select('group_id');
		$this->db->where('group_id', (int)$id);
		$this->db->where("(UPPER(group_name) LIKE '%WARD%' OR UPPER(group_name) LIKE '%DRESS%' OR UPPER(group_name) LIKE '%INJECTION%' OR UPPER(group_name) LIKE '%OXYGEN%' OR UPPER(group_name) LIKE '%PROCEDURE%' OR UPPER(group_name) LIKE '%BED%')", null, false);
		$query = $this->db->get('bill_group_name');
		return ($query && $query->num_rows() > 0);
	}

	/**
	 * Get only lab test categories (groups 8-15)
	 * Excludes: Consultant, Miscellaneous, Admission, Injections, Dressing, Surgical Assistant
	 */
	public function lab_categories(){
		$lab_group_ids = $this->config->item('lab_group_ids');
		if (!is_array($lab_group_ids) || empty($lab_group_ids)) {
			$lab_group_ids = array(8, 9, 10, 11, 12, 13, 14, 15);
		}
		$this->db->order_by("group_name","ASC");
		$this->db->where("InActive","0");
		$this->db->where_in("group_id", $lab_group_ids);
		$query = $this->db->get("bill_group_name");
		return $query->result();
	}
	
	public function itemList($id){
		$this->db->order_by("particular_name","ASC");
		$this->db->where(array(
			'InActive'		=>		0,
			'group_id'		=>		$id
		));	
		$query = $this->db->get("bill_particular");
		return $query->result();
	}
	
	public function particularName($id){
		$this->db->select("group_name");
		$this->db->where('group_id',$id);	
		$query = $this->db->get("bill_group_name");
		return $query->row();
	}
	
	public function getRate($id){
		$this->db->select("charge_amount,particular_name");
		$query = $this->db->get_where("bill_particular",array('particular_id' => $id));	
		return $query->row();
	}
	
	public function insurance_company(){
		$this->db->order_by("company_name","ASC");	
		$query = $this->db->get_where("insurance_comp", array('InActive' => '0'));
		return $query->result();
	}
	
	public function payment_type(){
		$this->db->order_by("cValue","ASC");	
		$query = $this->db->get_where("system_parameters", array('cCode' => 'payment_type', 'InActive' => '0'));
		return $query->result();
	}
	
	public function invoice_no(){
		$this->db->select("(cValue + 1) as invoice_no");
		$this->db->where("cCode","invoice_no");
		$query = $this->db->get("system_option");	
		return $query->row();	
	}
	
	public function receipt_no(){
		$this->db->select("(cValue + 1) as receipt_no");
		$this->db->where("cCode","receipt_no");
		$query = $this->db->get("system_option");	
		return $query->row();	
	}
	
	public function saveHeader(){
		$this->ensure_nhis_billing_columns();
		$patientNo = (string)$this->input->post('patient_no');
		$payerType = strtoupper(trim((string)$this->input->post('payer_type')));
		if ($payerType !== 'NHIS' && $payerType !== 'CASH' && $payerType !== 'PRIVATE') {
			$payerType = $this->determine_payer_type($patientNo);
		}
		$paymentType = strtolower(trim((string)$this->input->post('paymentType')));
		$totalAmount = $this->input->post('total_amount') == 'NaN' ? 0 : ($this->input->post('total_amount') == '' ? 0 : (float)$this->input->post('total_amount'));
		$subTotal = $this->input->post('nGross') == 'NaN' ? 0 : ($this->input->post('nGross') == '' ? 0 : (float)$this->input->post('nGross'));
		$nhisAmounts = $this->compute_nhis_line_amounts($subTotal, $payerType);

		$this->data = array(
			'iop_id'				=>		$this->input->post('opd_no'),
			'patient_no'			=>		$patientNo,
			'payment_type'			=>		$this->input->post('paymentType'),
			'invoice_no'			=>		$this->input->post('invoiceno'),
			'dDate'					=>		date("Y-m-d h:i:s"),
			'discount'				=>		$this->input->post('discount'),
			'reason_discount'		=>		$this->input->post('reason_dicount'),
			'sub_total'				=>		$subTotal,
			'total_amount'			=>		$totalAmount,
			'total_purchased'		=>		$this->input->post('hdnrowcnt') == 'NaN' ? 0 : ($this->input->post('hdnrowcnt') == '' ? 0 : $this->input->post('hdnrowcnt')),
			'creditCardNo'			=>		$this->input->post('creditCardNo'),
			'creditCardHolder'		=>		'',
			'insurance_company'		=>		$this->input->post('insurance_company'),
			'remarks'				=>		$this->input->post('remarks'),
			'InActive'				=>		0
		);
		if ($this->column_exists('iop_billing', 'payer_type')) {
			$this->data['payer_type'] = $payerType;
		}
		if ($this->column_exists('iop_billing', 'payment_status')) {
			$this->data['payment_status'] = ($payerType !== 'CASH' && $payerType !== 'PRIVATE') ? 'PAID' : 'UNPAID';
		}
		if ($this->column_exists('iop_billing', 'balance_due')) {
			$this->data['balance_due'] = round((float)$nhisAmounts['patient_payable'], 2);
		}
		if ($this->column_exists('iop_billing', 'nhis_covered_amount')) {
			$this->data['nhis_covered_amount'] = $nhisAmounts['nhis_covered'];
		}
		if ($this->column_exists('iop_billing', 'patient_payable_amount')) {
			$this->data['patient_payable_amount'] = $nhisAmounts['patient_payable'];
		}
		if ($this->column_exists('iop_billing', 'created_by')) {
			$cb = $this->input->post('created_by');
			if ($cb !== null && $cb !== '') { $this->data['created_by'] = (string)$cb; }
		}
		if ($this->column_exists('iop_billing', 'updated_by')) {
			$ub = $this->input->post('updated_by');
			if ($ub !== null && $ub !== '') { $this->data['updated_by'] = (string)$ub; }
		}
		$this->db->insert('iop_billing',$this->data);
	}
	
	public function saveDetails($i){
				$this->load->helper('quantity_semantics');
		$this->load->library('Quantity_semantics', null, 'qty_semantics');
		$qtyRaw = $this->input->post('qty'.$i);
		$rateRaw = $this->input->post('rate'.$i);
		$qtyRes = $this->qty_semantics->validate_qty_input($qtyRaw, 3);
		$rateRes = $this->qty_semantics->validate_qty_input($rateRaw, 2);
		$qty = (!empty($qtyRes['ok']) && $qtyRes['value'] !== null) ? (float)$qtyRes['value'] : 0.0;
		$rate = (!empty($rateRes['ok']) && $rateRes['value'] !== null) ? (float)$rateRes['value'] : 0.0;
		$useDecimalQty = qs_flag_enabled('ENABLE_DECIMAL_INVOICE_QTY', false);
		$qty = $useDecimalQty ? round($qty, 2) : (float)((int)round($qty));
		$rate = round($rate, 2);
$billName = (string)$this->input->post('bill_name'.$i);
		$srcModForCheck = (string)$this->input->post('source_module'.$i);
		$srcRefForCheck = (string)$this->input->post('source_ref'.$i);
		$patientNo = (string)$this->input->post('patient_no');
		$d2PayerType = strtoupper(trim((string)$this->input->post('payer_type')));
		if ($d2PayerType !== 'NHIS' && $d2PayerType !== 'CASH' && $d2PayerType !== 'PRIVATE') {
			$d2PayerType = $this->determine_payer_type($patientNo);
		}

		$srcModForCheck = trim((string)$srcModForCheck);
		$srcRefForCheck = trim((string)$srcRefForCheck);
		if ($srcModForCheck !== '' && $srcRefForCheck !== '' && $this->table_exists('iop_billable_item_lock')) {
			$this->db->where(array('source_module' => $srcModForCheck, 'source_ref' => $srcRefForCheck, 'InActive' => 0));
			$this->db->limit(1);
			$lk = $this->db->get('iop_billable_item_lock')->row();
			if ($lk) {
				$existingInv = isset($lk->invoice_no) ? (string)$lk->invoice_no : '';
				throw new Exception('Service already invoiced: source_module=' . $srcModForCheck . ' source_ref=' . $srcRefForCheck . ' existing_invoice=' . $existingInv);
			}
		}

		$catItemId   = 0;
		$catItemType = null;
		if ($srcRefForCheck !== '') {
			if (strpos($srcRefForCheck, 'iop_medication:') === 0) {
				$medRowId = (int)substr($srcRefForCheck, strlen('iop_medication:'));
				if ($medRowId > 0 && $this->table_exists('iop_medication')) {
					$mr = $this->db->get_where('iop_medication', array('iop_med_id' => $medRowId))->row();
					if ($mr && isset($mr->medicine_id)) { $catItemId = (int)$mr->medicine_id; $catItemType = 'DRUG'; }
				}
			} elseif (strpos($srcRefForCheck, 'iop_laboratory:') === 0) {
				$labRowId = (int)substr($srcRefForCheck, strlen('iop_laboratory:'));
				if ($labRowId > 0 && $this->table_exists('iop_laboratory')) {
					$lr = $this->db->get_where('iop_laboratory', array('io_lab_id' => $labRowId))->row();
					if ($lr && isset($lr->laboratory_id)) { $catItemId = (int)$lr->laboratory_id; $catItemType = 'SERVICE'; }
				}
			} elseif (strpos($srcRefForCheck, 'iop_bed_side_procedure:') === 0) {
				$bpId = (int)substr($srcRefForCheck, strlen('iop_bed_side_procedure:'));
				if ($bpId > 0 && $this->table_exists('iop_bed_side_procedure')) {
					$br = $this->db->get_where('iop_bed_side_procedure', array('bed_pro_id' => $bpId))->row();
					if ($br && isset($br->particular_id)) { $catItemId = (int)$br->particular_id; $catItemType = 'SERVICE'; }
				}
			}
		}

		if ($catItemId > 0 && $catItemType !== null) {
			try {
				$this->load->model('app/Price_engine_model', 'price_engine_model');
				$res = $this->price_engine_model->resolve(array(
					'item_type'            => $catItemType,
					'item_id'              => $catItemId,
					'patient_no'           => $patientNo,
					'payer_type'           => $d2PayerType,
					'quantity'             => (float)$qty,
					'submitted_unit_price' => (float)$rate,
					'encounter_id'         => (string)$this->input->post('opd_no'),
					'user_id'              => (string)$this->session->userdata('user_id'),
					'context'              => array(
						'source_module' => $srcModForCheck,
						'source_ref'    => $srcRefForCheck,
						'invoice_no'    => (string)$this->input->post('invoiceno'),
					),
				));
				if (is_array($res) && !empty($res['ok'])) {
					$rate = round((float)$res['unit_price'], 2);
					$billName = (string)$res['item_name'];
				}
			} catch (Exception $e) {
				// ignore
			}
		}

		$amount = round(((float)$qty * (float)$rate), 2);

		$this->data = array(
			'invoice_no'	=>		$this->input->post('invoiceno'),
			'iop_id'		=>		$this->input->post('opd_no'),
			'bill_name'		=>		$billName,
			'qty'			=>		$qty,
			'rate'			=>		$rate,
			'amount'		=>		$amount,
			'note'			=>		$this->input->post('note'.$i),
			'isPackage'		=>		$this->input->post('isPackage'.$i),
			'InActive'		=>		0
		);	
		
		$this->ensure_nhis_billing_columns();
		$lineAmt = (float)$this->data['amount'];
		$d2Split = $this->compute_nhis_line_amounts($lineAmt, $d2PayerType);
		if ($this->column_exists('iop_billing_t', 'payer_type')) {
			$this->data['payer_type'] = $d2PayerType;
		}
		if ($this->column_exists('iop_billing_t', 'nhis_covered_amount')) {
			$this->data['nhis_covered_amount'] = $d2Split['nhis_covered'];
		}
		if ($this->column_exists('iop_billing_t', 'patient_payable_amount')) {
			$this->data['patient_payable_amount'] = $d2Split['patient_payable'];
		}
		if ($this->column_exists('iop_billing_t', 'sub_total')) {
			$this->data['sub_total'] = (float)$this->data['amount'];
		}
		if ($this->column_exists('iop_billing_t', 'quantity_semantics_version')) {
		$this->data['quantity_semantics_version'] = $useDecimalQty ? (int)qs_decimal_semantics_version() : (int)qs_default_semantics_version();
		}
		$this->db->insert('iop_billing_t',$this->data);
		$detailId = (int)$this->db->insert_id();
		// SSOT pricing tamper detection (non-blocking, Step 1 of SSOT enforcement).
		// If source_ref encodes a catalog id, ask Price_engine_model for the
		// authoritative unit price and log any divergence. Does NOT override values.
		try {
			if ($srcRefForCheck !== '') {
				if (strpos($srcRefForCheck, 'iop_medication:') === 0) {
					// Pharmacy event — resolve drug_id from iop_medication
					$medRowId = (int)substr($srcRefForCheck, strlen('iop_medication:'));
					if ($medRowId > 0 && $this->table_exists('iop_medication')) {
						$mr = $this->db->get_where('iop_medication', array('iop_med_id' => $medRowId))->row();
						if ($mr && isset($mr->medicine_id)) { $catItemId = (int)$mr->medicine_id; $catItemType = 'DRUG'; }
					}
				} elseif (strpos($srcRefForCheck, 'iop_laboratory:') === 0) {
					$labRowId = (int)substr($srcRefForCheck, strlen('iop_laboratory:'));
					if ($labRowId > 0 && $this->table_exists('iop_laboratory')) {
						$lr = $this->db->get_where('iop_laboratory', array('io_lab_id' => $labRowId))->row();
						if ($lr && isset($lr->laboratory_id)) { $catItemId = (int)$lr->laboratory_id; $catItemType = 'SERVICE'; }
					}
				} elseif (strpos($srcRefForCheck, 'iop_bed_side_procedure:') === 0) {
					$bpId = (int)substr($srcRefForCheck, strlen('iop_bed_side_procedure:'));
					if ($bpId > 0 && $this->table_exists('iop_bed_side_procedure')) {
						$br = $this->db->get_where('iop_bed_side_procedure', array('bed_pro_id' => $bpId))->row();
						if ($br && isset($br->particular_id)) { $catItemId = (int)$br->particular_id; $catItemType = 'SERVICE'; }
					}
				} elseif (strpos($srcRefForCheck, 'iop_room_charge:') === 0) {
					$rcId = (int)substr($srcRefForCheck, strlen('iop_room_charge:'));
					if ($rcId > 0 && $this->table_exists('iop_room_charge')) {
						$rr = $this->db->get_where('iop_room_charge', array('charge_id' => $rcId))->row();
						if ($rr && isset($rr->particular_id)) { $catItemId = (int)$rr->particular_id; $catItemType = 'SERVICE'; }
					}
				}
			}
			if ($catItemId > 0 && $catItemType !== null) {
				$this->load->model('app/Price_engine_model', 'price_engine_model');
				$res = $this->price_engine_model->resolve(array(
					'item_type'            => $catItemType,
					'item_id'              => $catItemId,
					'patient_no'           => $patientNo,
					'payer_type'           => $d2PayerType,
					'quantity'             => (float)$this->data['qty'],
					'submitted_unit_price' => (float)$this->data['rate'],
					'encounter_id'         => (string)$this->input->post('opd_no'),
					'user_id'              => (string)$this->session->userdata('user_id'),
					'context'              => array(
						'source_module' => $srcModForCheck,
						'source_ref'    => $srcRefForCheck,
						'invoice_no'    => (string)$this->input->post('invoiceno'),
					),
				));
				// Best-effort backfill of audit columns on the just-inserted line.
				if (is_array($res) && !empty($res['ok']) && $detailId > 0) {
					$update = array();
					if ($this->column_exists('iop_billing_t', 'price_source'))        { $update['price_source'] = $res['price_source']; }
					if ($this->column_exists('iop_billing_t', 'pricing_pct'))         { $update['pricing_pct'] = $res['pricing_pct']; }
					if ($this->column_exists('iop_billing_t', 'original_unit_price')) { $update['original_unit_price'] = $res['original_unit_price']; }
					if (!empty($update)) {
						$this->db->where('id', $detailId);
						$this->db->update('iop_billing_t', $update);
					}
				}
			}
		} catch (Exception $e) {
			// Never break the save flow on audit failure.
		}

		if ($detailId > 0 && $this->table_exists('iop_billing_line_meta')) {
			$srcMod = $this->input->post('source_module'.$i);
			$srcRef = $this->input->post('source_ref'.$i);
			$srcMod = $srcMod !== null ? trim((string)$srcMod) : '';
			$srcRef = $srcRef !== null ? trim((string)$srcRef) : '';
			if ($srcMod !== '' || $srcRef !== '') {
				$createdBy = null;
				if (isset($this->session) && is_object($this->session) && method_exists($this->session, 'userdata')) {
					$createdBy = (string)$this->session->userdata('user_id');
					if (trim($createdBy) === '') {
						$createdBy = null;
					}
				}
				$this->db->insert('iop_billing_line_meta', array(
					'invoice_no' => (string)$this->input->post('invoiceno'),
					'detail_id' => $detailId,
					'source_module' => ($srcMod !== '') ? $srcMod : null,
					'source_ref' => ($srcRef !== '') ? $srcRef : null,
					'created_at' => date('Y-m-d H:i:s'),
					'created_by' => $createdBy,
					'InActive' => 0
				));
				$this->lock_billable_item(
					$srcMod,
					$srcRef,
					(string)$this->input->post('invoiceno'),
					$detailId,
					(string)$this->input->post('opd_no'),
					(string)$this->input->post('patient_no'),
					'INVOICED'
				);
				try {
					if (strtoupper($srcMod) === 'PHARMACY' && $srcRef !== '') {
						$matches = array();
						if (preg_match_all('/(?:iop_medication_id|iop_medication|iop_med_id)\s*:\s*(\d+)/i', $srcRef, $matches) && !empty($matches[1])) {
							$mid = (int)$matches[1][count($matches[1]) - 1];
							if ($mid > 0) {
								$this->load->model('app/billing_transaction_model');
								if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'sync_pharmacy_medication')) {
									$this->billing_transaction_model->sync_pharmacy_medication(
										$mid,
										$createdBy,
										(string)$this->input->post('invoiceno'),
										$detailId
									);
								}
							}
						}
					}
				} catch (Throwable $e) {
				}
				if ($srcMod === 'IPD_ROOM' && $this->table_exists('iop_room_charge') && strpos($srcRef, 'iop_room_charge:') === 0) {
					$chargeId = (int)substr($srcRef, strlen('iop_room_charge:'));
					if ($chargeId > 0) {
						$this->db->where(array('charge_id' => $chargeId, 'InActive' => 0));
						$this->db->update('iop_room_charge', array(
							'status' => 'INVOICED',
							'invoice_no' => (string)$this->input->post('invoiceno'),
							'detail_id' => $detailId
						));
					}
				}
				if ($srcMod === 'SONOGRAPHY' && $this->table_exists('iop_sonography_charge') && strpos($srcRef, 'iop_sonography_charge:') === 0) {
					$chargeId = (int)substr($srcRef, strlen('iop_sonography_charge:'));
					if ($chargeId > 0) {
						$this->db->where(array('charge_id' => $chargeId, 'InActive' => 0));
						$this->db->update('iop_sonography_charge', array(
							'status' => 'INVOICED',
							'invoice_no' => (string)$this->input->post('invoiceno'),
							'detail_id' => $detailId
						));
					}
				}
			}
		}
	}
	
	public function updateInvoiceNo(){
		$this->db->query("UPDATE system_option SET cValue = ? WHERE cCode = 'invoice_no'", array($this->input->post('invoiceno2')));	
	}
	
	public function checkInvoice($iop_no){
		$this->db->where(array(
			'iop_id'		=>		$iop_no,
			'InActive'		=>		0
		));
		$query = $this->db->get("iop_billing");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function headerInv($iop_no,$invoiceno){
		$this->db->where(array(
			'iop_id'		=>		$iop_no,
			'invoice_no'	=>		$invoiceno,
			'InActive'	=>		0
		));
		$query = $this->db->get('iop_billing');
		return $query->row();
	}
	
	public function headerInv2($InvNo){
		$InvNo = trim((string)$InvNo);
		if ($InvNo === '' || !$this->table_exists('iop_billing')) {
			return null;
		}
		$this->db->where(array(
			'invoice_no'	=>		$InvNo,
			'InActive'	=>		0
		));
		$this->db->order_by('bill_id', 'DESC');
		$this->db->limit(1);
		$query = $this->db->get('iop_billing');
		return $query->row();
	}
	
	public function detailsInv2($InvNo){
		$this->install_billing_meta_tables();
		if ($this->table_exists('iop_billing_line_meta')) {
			$this->db->select('COUNT(*) AS c', false);
			$this->db->where(array('invoice_no' => (string)$InvNo, 'InActive' => 0));
			$row = $this->db->get('iop_billing_line_meta')->row();
			$c = ($row && isset($row->c)) ? (int)$row->c : 0;
			if ($c === 0) {
				$this->backfill_invoice_line_meta((string)$InvNo);
			}
		}
		$this->db->from('iop_billing_t T');
		$this->db->join('iop_billing H', 'H.invoice_no = T.invoice_no AND H.InActive = 0', 'left');
		if ($this->table_exists('iop_billing_line_meta')) {
			$this->db->join('iop_billing_line_meta M', 'M.invoice_no = T.invoice_no AND M.detail_id = T.id AND M.InActive = 0', 'left');
		} else {
			// Dummy join alias for consistent SELECT
			$this->db->join('(SELECT NULL AS invoice_no, NULL AS detail_id, NULL AS source_module, NULL AS source_ref) M', '1=0', 'left', false);
		}
		if ($this->table_exists('pharmacy_billing_queue')) {
			$this->db->join('pharmacy_billing_queue PBQ', "PBQ.InActive = 0 AND PBQ.iop_id = H.iop_id AND PBQ.patient_no = H.patient_no AND LOWER(CONVERT(TRIM(PBQ.drug_name) USING utf8mb4)) = LOWER(CONVERT(TRIM(T.bill_name) USING utf8mb4))", 'left', false);
		} else {
			$this->db->join('(SELECT NULL AS iop_med_id) PBQ', '1=0', 'left', false);
		}
		if ($this->table_exists('iop_lab_billing')) {
			$this->db->join('iop_lab_billing LB', "LB.InActive = 0 AND LB.invoice_no = T.invoice_no AND LOWER(CONVERT(TRIM(LB.item_name) USING utf8mb4)) = LOWER(CONVERT(TRIM(T.bill_name) USING utf8mb4))", 'left', false);
		} else {
			$this->db->join('(SELECT NULL AS io_lab_id) LB', '1=0', 'left', false);
		}
		$this->db->select(
			"T.*, " .
			"COALESCE(CONVERT(M.source_module USING utf8mb4), " .
			"  CASE " .
			"    WHEN PBQ.iop_med_id IS NOT NULL THEN 'PHARMACY' " .
			"    WHEN LB.io_lab_id IS NOT NULL THEN 'LABORATORY' " .
			"    ELSE NULL " .
			"  END" .
			") AS source_module, " .
			"COALESCE(CONVERT(M.source_ref USING utf8mb4), " .
			"  CASE " .
			"    WHEN PBQ.iop_med_id IS NOT NULL THEN CONCAT('iop_medication:', PBQ.iop_med_id) " .
			"    WHEN LB.io_lab_id IS NOT NULL THEN CONCAT('iop_laboratory:', LB.io_lab_id) " .
			"    ELSE NULL " .
			"  END" .
			") AS source_ref",
			false
		);
		$this->db->where(array(
			'T.invoice_no'	=>		$InvNo,
			'T.InActive'	=>		0
		));
		$this->db->order_by('T.id', 'ASC');
		$query = $this->db->get();
		return $query ? $query->result() : array();
	}

	public function backfill_invoice_line_meta($invoice_no, $user_id = null)
	{
		$invoice_no = trim((string)$invoice_no);
		if ($invoice_no === '' || !$this->table_exists('iop_billing_line_meta') || !$this->table_exists('iop_billing_t') || !$this->table_exists('iop_billing')) {
			return false;
		}
		$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
		$hdr = $this->db->get('iop_billing')->row();
		if (!$hdr) return false;
		$iop_id = isset($hdr->iop_id) ? (string)$hdr->iop_id : '';
		$patient_no = isset($hdr->patient_no) ? (string)$hdr->patient_no : '';
		$bill_id = isset($hdr->bill_id) ? (int)$hdr->bill_id : 0;

		$pbqMap = array();
		$pbqMapBill = array();
		if ($iop_id !== '' && $patient_no !== '' && $this->table_exists('pharmacy_billing_queue')) {
			$this->db->select('iop_med_id, drug_name, quantity, bill_id', false);
			$this->db->from('pharmacy_billing_queue');
			$this->db->where(array('iop_id' => $iop_id, 'patient_no' => $patient_no, 'InActive' => 0));
			$rows = $this->db->get()->result();
			foreach ($rows as $r) {
				$name = isset($r->drug_name) ? strtolower(trim((string)$r->drug_name)) : '';
				if ($name === '') continue;
				$pbqMap[$name][] = $r;
				$rb = isset($r->bill_id) ? (int)$r->bill_id : 0;
				if ($rb > 0) {
					$pbqMapBill[$rb][$name][] = $r;
				}
			}
		}

		$labMap = array();
		if ($this->table_exists('iop_lab_billing')) {
			$this->db->select('io_lab_id, item_name, quantity', false);
			$this->db->from('iop_lab_billing');
			$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
			$rows = $this->db->get()->result();
			foreach ($rows as $r) {
				$name = isset($r->item_name) ? strtolower(trim((string)$r->item_name)) : '';
				if ($name === '') continue;
				$labMap[$name][] = $r;
			}
		}

		$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
		$this->db->order_by('id', 'ASC');
		$lines = $this->db->get('iop_billing_t')->result();
		if (empty($lines)) return false;

		$now = date('Y-m-d H:i:s');
		foreach ($lines as $ln) {
			$detail_id = isset($ln->id) ? (int)$ln->id : 0;
			if ($detail_id <= 0) continue;

			$this->db->where(array('invoice_no' => $invoice_no, 'detail_id' => $detail_id, 'InActive' => 0));
			$exists = $this->db->get('iop_billing_line_meta')->row();
			if ($exists) continue;

			$billName = isset($ln->bill_name) ? strtolower(trim((string)$ln->bill_name)) : '';
			$qty = isset($ln->qty) ? (float)$ln->qty : 0;
			$srcModule = null;
			$srcRef = null;

			$pbqCandidates = array();
			if ($bill_id > 0 && isset($pbqMapBill[$bill_id]) && isset($pbqMapBill[$bill_id][$billName])) {
				$pbqCandidates = $pbqMapBill[$bill_id][$billName];
			} elseif ($billName !== '' && isset($pbqMap[$billName])) {
				$pbqCandidates = $pbqMap[$billName];
			}
			if (!empty($pbqCandidates)) {
				// Prefer qty match if possible; otherwise first match by name.
				$chosen = null;
				foreach ($pbqCandidates as $r) {
					$rQty = isset($r->quantity) ? (float)$r->quantity : 0;
					if ($qty > 0 && $rQty > 0 && abs($qty - $rQty) < 0.0001) { $chosen = $r; break; }
				}
				if ($chosen === null) { $chosen = $pbqCandidates[0]; }
				$srcModule = 'PHARMACY';
				$srcRef = 'iop_medication:' . (int)$chosen->iop_med_id;
			}
			if ($srcModule === null && $billName !== '' && isset($labMap[$billName])) {
				// Prefer qty match if possible; otherwise first match by name.
				$chosen = null;
				foreach ($labMap[$billName] as $r) {
					$rQty = isset($r->quantity) ? (float)$r->quantity : 0;
					if ($qty > 0 && $rQty > 0 && abs($qty - $rQty) < 0.0001) { $chosen = $r; break; }
				}
				if ($chosen === null) { $chosen = $labMap[$billName][0]; }
				$srcModule = 'LABORATORY';
				$srcRef = 'iop_laboratory:' . (int)$chosen->io_lab_id;
			}

			if ($srcModule === null && $srcRef === null) {
				continue;
			}

			$this->db->insert('iop_billing_line_meta', array(
				'invoice_no' => $invoice_no,
				'detail_id' => $detail_id,
				'source_module' => $srcModule,
				'source_ref' => $srcRef,
				'created_at' => $now,
				'created_by' => ($user_id !== null && trim((string)$user_id) !== '') ? (string)$user_id : null,
				'InActive' => 0,
			));
		}
		return true;
	}

	public function get_latest_invoice_no_for_visit($patient_no, $iop_id)
	{
		$patient_no = trim((string)$patient_no);
		$iop_id = trim((string)$iop_id);
		if ($patient_no === '' || $iop_id === '' || !$this->table_exists('iop_billing')) {
			return null;
		}
		$this->db->select('invoice_no');
		$this->db->from('iop_billing');
		$this->db->where(array('patient_no' => $patient_no, 'iop_id' => $iop_id, 'InActive' => 0));
		$this->db->order_by('bill_id', 'DESC');
		$this->db->limit(1);
		$row = $this->db->get()->row();
		if ($row && isset($row->invoice_no) && trim((string)$row->invoice_no) !== '') {
			return (string)$row->invoice_no;
		}
		return null;
	}
	
	public function showPatients($val){
		$this->db->select("
				A.IO_ID,
				A.patient_no,
				A.patient_type,
				B.picture,
				A.date_visit,
				A.time_visit,
				B.age,
				concat(B.firstname,' ',B.lastname) as patient
		",false);
		$valEsc = $this->db->escape_like_str($val);
		$where = "A.nStatus = 'Pending' and 
			(
				A.IO_ID like '%".$valEsc."%' or 
				A.patient_no like '%".$valEsc."%' or 
				A.patient_type like '%".$valEsc."%' or 
				B.firstname like '%".$valEsc."%' or 
				B.lastname like '%".$valEsc."%'
			)
			";
		$this->db->where($where);
		$this->db->order_by("A.IO_ID","ASC");
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$query = $this->db->get("patient_details_iop A");
		return $query->result();
	}
	
	public function getOR($invoiceno){
		$query = $this->db->get_where('iop_receipt',array('invoice_no' => $invoiceno));
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function OR_num($invoiceno){
		$this->db->where(array('invoice_no' => $invoiceno, 'InActive' => 0));
		$this->db->order_by('receipt_id', 'DESC');
		$this->db->limit(1);
		$query = $this->db->get('iop_receipt');	
		return $query ? $query->row() : null;
	}

	public function build_receipt_print_payload($invoice_no, $receipt_no = null)
	{
		$invoice_no = (string)$invoice_no;
		$receipt_no = $receipt_no !== null ? trim((string)$receipt_no) : '';
		$payload = array(
			'headerInv' => null,
			'detailsInv' => array(),
			'getOR' => null,
			'receipt_prev_balance' => 0.0,
			'receipt_payment' => 0.0,
			'receipt_outstanding_balance' => 0.0,
			'receipt_total_paid' => 0.0,
			'receipt_amount_tendered' => 0.0,
			'receipt_payment_method_label' => '',
			'receipt_cashier_id' => '',
			'receipt_cashier_name' => '',
		);
		if ($invoice_no === '') {
			return $payload;
		}
		$payload['headerInv'] = $this->headerInv2($invoice_no);
		$payload['detailsInv'] = $this->detailsInv2($invoice_no);
		$receipt = null;
		if ($receipt_no !== '' && $this->table_exists('iop_receipt')) {
			$this->db->where(array('receipt_no' => $receipt_no, 'InActive' => 0));
			$this->db->limit(1);
			$rq = $this->db->get('iop_receipt');
			$receipt = $rq ? $rq->row() : null;
		}
		if (!$receipt) {
			$receipt = $this->OR_num($invoice_no);
		}
		$payload['getOR'] = $receipt;

		$settle = $this->get_invoice_settlement($invoice_no);
		$total_due = isset($settle['total']) ? (float)$settle['total'] : 0.0;
		$paid_to_date = isset($settle['paid']) ? (float)$settle['paid'] : 0.0;
		$pay_this = ($receipt && isset($receipt->amountPaid)) ? (float)$receipt->amountPaid : 0.0;
		$change = ($receipt && isset($receipt->change)) ? max(0.0, (float)$receipt->change) : 0.0;
		$prev_paid = max(0.0, $paid_to_date - $pay_this);
		$payload['receipt_prev_balance'] = round(max(0.0, $total_due - $prev_paid), 2);
		$payload['receipt_payment'] = round($pay_this, 2);
		$payload['receipt_outstanding_balance'] = round(max(0.0, $total_due - $paid_to_date), 2);
		$payload['receipt_total_paid'] = round($paid_to_date, 2);
		$payload['receipt_amount_tendered'] = round($pay_this + $change, 2);

		$pm = '';
		if ($receipt) {
			if (isset($receipt->payment_method) && trim((string)$receipt->payment_method) !== '') {
				$pm = (string)$receipt->payment_method;
			} elseif (isset($receipt->payment_type) && trim((string)$receipt->payment_type) !== '') {
				$pm = (string)$receipt->payment_type;
			}
		}
		$payload['receipt_payment_method_label'] = strtoupper(trim($pm));

		$cashier_id = ($receipt && isset($receipt->cashier_id)) ? trim((string)$receipt->cashier_id) : '';
		$payload['receipt_cashier_id'] = $cashier_id;
		if ($cashier_id !== '') {
			$CI =& get_instance();
			try {
				if (!isset($CI->user_model)) {
					$CI->load->model('app/user_model');
				}
				if (isset($CI->user_model) && method_exists($CI->user_model, 'getUser')) {
					$u = $CI->user_model->getUser($cashier_id);
					if ($u) {
						$nm = trim((string)(isset($u->firstname) ? $u->firstname : '') . ' ' . (string)(isset($u->lastname) ? $u->lastname : ''));
						$payload['receipt_cashier_name'] = ($nm !== '' ? $nm : $cashier_id);
					} else {
						$payload['receipt_cashier_name'] = $cashier_id;
					}
				}
			} catch (Exception $e) {
				$payload['receipt_cashier_name'] = $cashier_id;
			}
		}

		return $payload;
	}
	
	public function medicine_cat(){
		$query = $this->db->get_where("medicine_category", array('InActive' => 0));	
		return $query->result();
	}
	
	public function drug_list($id){
		$id = (int)$id;
		$hasExpiry = $this->column_exists('medicine_drug_name', 'expiry_date');
		$select = 'drug_id,drug_name,nPrice,nStock,re_order_level';
		if ($hasExpiry) {
			$select .= ',expiry_date';
		}
		$this->db->select($select, false);
		$this->db->where(array(
			'InActive' => 0,
			'med_cat_id' => $id
		));
		$this->db->order_by('drug_name', 'ASC');
		$q = $this->db->get('medicine_drug_name');
		return $q ? $q->result() : array();
	}
	
	public function medicineName($id){
		$query = $this->db->get_where("medicine_category", array('cat_id' => $id));	
		return $query->row();
	}
	
	public function getDrugRate($id){
		$id = (int)$id;
		$hasExpiry = $this->column_exists('medicine_drug_name', 'expiry_date');
		$select = 'nPrice,drug_name,nStock,re_order_level';
		if ($hasExpiry) {
			$select .= ',expiry_date';
		}
		$this->db->select($select, false);
		$query = $this->db->get_where("medicine_drug_name",array('drug_id' => $id));	
		return $query->row();
	}
	
	
	public function patientMedication($patientNo,$iopNo){
		$this->install_billing_meta_tables();
		$this->install_ipd_room_charge_tables();
		$this->generate_ipd_room_charges((string)$iopNo, null);

		//table medication - include all prescription fields (dosage, frequency, days, instruction, advice)
		// Use COALESCE to get price from nPrice or cash_price
		$priceExpr = "COALESCE(NULLIF(B.nPrice,0), NULLIF(B.cash_price,0), 0)";
		$medSelect = "A.medicine_id,B.drug_name,(" . $priceExpr . ") as nPrice,A.total_qty,'0' as isPackage,IFNULL(B.medicine_text,'') as medicine_text,IFNULL(A.dosage,'') as dosage,IFNULL(A.instruction,'') as instruction,IFNULL(A.advice,'') as advice,'PHARMACY' as source_module,concat('iop_medication:',A.iop_med_id) as source_ref,IFNULL(A.days,0) as days";
		if ($this->column_exists('iop_medication', 'frequency')) {
			$medSelect .= ",IFNULL(A.frequency,'') as frequency";
		} else {
			$medSelect .= ",'' as frequency";
		}
		$this->db->select($medSelect, false);
		$this->db->where(array(
			'A.iop_id'		=>		$iopNo,
			'A.InActive'	=>		0
		));
		if ($this->column_exists('iop_medication', 'prescription_status')) {
			$this->db->where('A.prescription_status', 'VERIFIED');
		}
		if ($this->column_exists('iop_medication', 'dispensing_status')) {
			$this->db->where("A.dispensing_status != 'UNAVAILABLE'", null, false);
		}
		$this->db->join("medicine_drug_name B","B.drug_id = A.medicine_id","left outer");
		$this->db->join(
			"billing_transactions BT",
			"BT.InActive = 0 AND BT.encounter_id = A.iop_id AND BT.item_ref = concat('iop_med_id:',A.iop_med_id) AND (BT.invoice_no IS NULL OR BT.invoice_no = '')",
			"inner"
		);
		$this->db->join("iop_billable_item_lock L","L.source_module='PHARMACY' AND L.source_ref = concat('iop_medication:',A.iop_med_id) AND L.InActive = 0","left");
		$this->db->where('L.lock_id IS NULL', null, false);
		$this->db->get("iop_medication A");
		$query1 = $this->db->last_query();

		//table room charges (ledger)
		$this->db->select("A.charge_id as medicine_id,concat('Room Charge - ',IFNULL(R.room_name,'')) as drug_name,A.rate_amount as nPrice,A.quantity as total_qty,'0' as isPackage,concat('Room Charge - ',IFNULL(R.room_name,'')) as medicine_text,'' as dosage,'' as instruction,'' as advice,'IPD_ROOM' as source_module,concat('iop_room_charge:',A.charge_id) as source_ref,0 as days,'' as frequency",false);
		$this->db->where(array(
			'A.iop_id'		=>		$iopNo,
			'A.InActive'	=>		0,
			'A.status'		=>		'PENDING'
		));
		$this->db->join("room_master R","R.room_master_id = A.room_master_id","left outer");
		$this->db->join(
			"billing_transactions BT",
			"BT.InActive = 0 AND BT.encounter_id = A.iop_id AND BT.item_ref = concat('iop_room_charge_id:',A.charge_id) AND (BT.invoice_no IS NULL OR BT.invoice_no = '')",
			"inner"
		);
		$this->db->join("iop_billable_item_lock L","L.source_module='IPD_ROOM' AND L.source_ref = concat('iop_room_charge:',A.charge_id) AND L.InActive = 0","left");
		$this->db->where('L.lock_id IS NULL', null, false);
		$this->db->get("iop_room_charge A");
		$query2 = $this->db->last_query();

		//table bed side procedures (per-event source_ref to prevent collisions)
		$this->db->select("B.particular_id,B.particular_name, B.charge_amount,A.qty,'0' as isPackage,'' as medicine_text,'' as dosage,'' as instruction,'' as advice,'IPD_BED_SIDE' as source_module,concat('iop_bed_side_procedure:',A.bed_pro_id) as source_ref,0 as days,'' as frequency",false);
		$this->db->where(array(
			'A.iop_id'		=>		$iopNo,
			'A.InActive'	=>		0
		));
		$this->db->join("bill_particular B","B.particular_id = A.cItem_id","left outer");
		$this->db->join(
			"billing_transactions BT",
			"BT.InActive = 0 AND BT.encounter_id = A.iop_id AND BT.item_ref = concat('iop_bed_side_procedure_id:',A.bed_pro_id) AND (BT.invoice_no IS NULL OR BT.invoice_no = '')",
			"inner"
		);
		$this->db->join("iop_billable_item_lock L","L.source_module='IPD_BED_SIDE' AND L.source_ref = concat('iop_bed_side_procedure:',A.bed_pro_id) AND L.InActive = 0","left");
		$this->db->where('L.lock_id IS NULL', null, false);
		$this->db->get("iop_bed_side_procedure A");
		$query3 = $this->db->last_query();

		//table operation theater (per-event source_ref to prevent collisions)
		$this->db->select("B.surgery_id,B.surgery_name, B.total_costs,'1' as qty,'1' as isPackage,'' as medicine_text,'' as dosage,'' as instruction,'' as advice,'IPD_OT' as source_module,concat('iop_operation_theater:',A.operation_id) as source_ref,0 as days,'' as frequency",false);
		$this->db->where(array(
			'A.iop_id'		=>		$iopNo,
			'A.InActive'	=>		0
		));
		$this->db->join("surgical_package B","B.surgery_id = A.operation_name","left outer");
		$this->db->join(
			"billing_transactions BT",
			"BT.InActive = 0 AND BT.encounter_id = A.iop_id AND BT.item_ref = concat('iop_operation_theater_id:',A.operation_id) AND (BT.invoice_no IS NULL OR BT.invoice_no = '')",
			"inner"
		);
		$this->db->join("iop_billable_item_lock L","L.source_module='IPD_OT' AND L.source_ref = concat('iop_operation_theater:',A.operation_id) AND L.InActive = 0","left");
		$this->db->where('L.lock_id IS NULL', null, false);
		$this->db->get("iop_operation_theater A");
		$query4 = $this->db->last_query();

		//table lab (exclude sonography category 18, unified query to prevent duplicates)
		$this->db->select("A.io_lab_id,
			COALESCE(NULLIF(CONVERT(IFNULL(D.particular_name,'') USING utf8mb4) COLLATE utf8mb4_unicode_ci,''), NULLIF(CONVERT(IFNULL(A.laboratory_text,'') USING utf8mb4) COLLATE utf8mb4_unicode_ci,''), 'Unknown Test') AS particular_name,
			COALESCE(D.charge_amount, 0) as nPrice,
			'1' as total_qty, '0' as isPackage,
			COALESCE(CONVERT(IFNULL(D.particular_name,'') USING utf8mb4) COLLATE utf8mb4_unicode_ci, CONVERT(IFNULL(A.laboratory_text,'') USING utf8mb4) COLLATE utf8mb4_unicode_ci) as medicine_text,
			'' as dosage,'' as instruction,'' as advice,'LAB' as source_module,
			concat('iop_laboratory:',A.io_lab_id) as source_ref,0 as days,'' as frequency",false);
		$this->db->where(array(
			'A.iop_id'		=>		$iopNo,
			'A.InActive'	=>		0
		));
		$this->db->where("(A.category_id IS NULL OR A.category_id != '18')", null, false);
		$this->db->join("patient_details_iop B","B.IO_ID = A.iop_id","left outer");
		$this->db->join("patient_personal_info C","C.patient_no = B.patient_no","left outer");
		$this->db->join("bill_particular D","D.particular_id = A.laboratory_id","left outer");
		$this->db->join(
			"billing_transactions BT",
			"BT.InActive = 0 AND BT.encounter_id = A.iop_id AND BT.item_ref = concat('io_lab_id:',A.io_lab_id) AND (BT.invoice_no IS NULL OR BT.invoice_no = '')",
			"inner"
		);
		$this->db->join("iop_billable_item_lock L","L.source_module='LAB' AND L.source_ref = concat('iop_laboratory:',A.io_lab_id) AND L.InActive = 0","left");
		$this->db->where('L.lock_id IS NULL', null, false);
		$this->db->get("iop_laboratory A");
		$query5 = $this->db->last_query();

		// Query6 removed - unified into query5 to prevent duplicate lab entries

		//table sonography raw requests (OPD + legacy IPD). Pricing:
		// - If meta exists, use sonography_items.bill_particular_id
		// - If no meta, allow legacy bill_particular on iop_laboratory.laboratory_id
		$hasSonoMeta = $this->table_exists('iop_sonography_request_meta');
		$hasSonoItems = $this->table_exists('sonography_items');
		$hasSonoCharge = $this->table_exists('iop_sonography_charge');
		$metaJoin = $hasSonoMeta ? " LEFT JOIN iop_sonography_request_meta M ON M.io_lab_id = A.io_lab_id AND M.InActive = 0 " : "";
		$itemJoin = $hasSonoItems ? " LEFT JOIN sonography_items SI ON SI.item_id = M.scan_item_id AND SI.InActive = 0 " : "";
		$chargeJoin = $hasSonoCharge ? " LEFT JOIN iop_sonography_charge SC ON SC.io_lab_id = A.io_lab_id AND SC.InActive = 0 " : "";
		$btJoinSono = $hasSonoCharge
			? " JOIN billing_transactions BT ON BT.InActive = 0 AND BT.encounter_id = A.iop_id AND ( (SC.charge_id IS NOT NULL AND BT.item_ref = CONCAT('sono_charge_id:',SC.charge_id)) OR (SC.charge_id IS NULL AND BT.item_ref = CONCAT('sono_req_io_lab_id:',A.io_lab_id)) ) AND (BT.invoice_no IS NULL OR BT.invoice_no = '') "
			: " JOIN billing_transactions BT ON BT.InActive = 0 AND BT.encounter_id = A.iop_id AND BT.item_ref = CONCAT('sono_req_io_lab_id:',A.io_lab_id) AND (BT.invoice_no IS NULL OR BT.invoice_no = '') ";
		$sqlSono = "SELECT ".
			"A.io_lab_id AS medicine_id, ".
			"(CASE ".
			" WHEN COALESCE(BPM.charge_amount,0) > 0 THEN CONCAT('Sonography - ', IFNULL(SI.item_name, IFNULL(A.laboratory_text,''))) ".
			" WHEN (M.meta_id IS NULL AND COALESCE(BPL.charge_amount,0) > 0) THEN CONCAT('Sonography - ', IFNULL(A.laboratory_text, IFNULL(BPL.particular_name,''))) ".
			" ELSE CONCAT('Sonography - ', ".
			"   (CASE ".
			"     WHEN IFNULL(SI.item_name,'') != '' THEN SI.item_name ".
			"     WHEN IFNULL(A.laboratory_text,'') != '' THEN A.laboratory_text ".
			"     WHEN IFNULL(BPL.particular_name,'') != '' THEN BPL.particular_name ".
			"     ELSE 'Custom Sonography' ".
			"   END), ".
			"   (CASE WHEN IFNULL(M.clinical_question,'') != '' THEN CONCAT(' - ', LEFT(M.clinical_question, 60)) ELSE '' END), ".
			"   ' [MANUAL PRICING REQUIRED]') ".
			" END) AS drug_name, ".
			"(CASE ".
			" WHEN COALESCE(BPM.charge_amount,0) > 0 THEN BPM.charge_amount ".
			" WHEN (M.meta_id IS NULL AND COALESCE(BPL.charge_amount,0) > 0) THEN BPL.charge_amount ".
			" ELSE 0 ".
			" END) AS nPrice, ".
			"'1' AS total_qty, '0' AS isPackage, ".
			"IFNULL(SI.item_name, IFNULL(A.laboratory_text, IFNULL(BPL.particular_name,''))) AS medicine_text, ".
			"'' AS dosage, '' AS instruction, '' AS advice, ".
			"'SONOGRAPHY' AS source_module, CONCAT('iop_sonography_request:',A.io_lab_id) AS source_ref, ".
			"0 AS days, '' AS frequency ".
			"FROM iop_laboratory A ".
			$chargeJoin.
			$btJoinSono.
			$metaJoin.
			$itemJoin.
			" LEFT JOIN bill_particular BPM ON BPM.particular_id = SI.bill_particular_id ".
			" LEFT JOIN bill_particular BPL ON BPL.particular_id = A.laboratory_id ".
			" LEFT JOIN iop_billable_item_lock L ON L.source_module='SONOGRAPHY' AND L.source_ref = CONCAT('iop_sonography_request:',A.io_lab_id) AND L.InActive = 0 ".
			" LEFT JOIN iop_billable_item_lock LLAB ON LLAB.source_module='LAB' AND LLAB.source_ref = CONCAT('iop_laboratory:',A.io_lab_id) AND LLAB.InActive = 0 ".
			"WHERE A.iop_id = ".$this->db->escape($iopNo)." AND A.InActive = 0 AND A.category_id = '18' AND L.lock_id IS NULL AND LLAB.lock_id IS NULL ".
			($hasSonoCharge ? " AND (SC.charge_id IS NULL OR IFNULL(SC.status,'') != 'PENDING') AND (SC.charge_id IS NULL OR BT.item_ref = CONCAT('sono_charge_id:',SC.charge_id)) " : "");
		$query7 = $sqlSono;

		//table sonography IPD ledger (posted charges)
		$query8 = '';
		if ($this->table_exists('iop_sonography_charge')) {
			$query8 = "SELECT ".
				"A.charge_id AS medicine_id, ".
				"(CASE WHEN COALESCE(A.rate_amount,0) > 0 THEN CONCAT('Sonography - ', IFNULL(A.item_name,'')) ELSE CONCAT('Sonography - ', IFNULL(A.item_name,''), ' [MANUAL PRICING REQUIRED]') END) AS drug_name, ".
				"A.rate_amount AS nPrice, ".
				"A.quantity AS total_qty, '0' AS isPackage, ".
				"IFNULL(A.item_name,'') AS medicine_text, ".
				"'' AS dosage, '' AS instruction, '' AS advice, ".
				"'SONOGRAPHY' AS source_module, CONCAT('iop_sonography_charge:',A.charge_id) AS source_ref, ".
				"0 AS days, '' AS frequency ".
				"FROM iop_sonography_charge A ".
				" JOIN billing_transactions BT ON BT.InActive = 0 AND BT.encounter_id = A.iop_id AND BT.item_ref = CONCAT('sono_charge_id:',A.charge_id) AND (BT.invoice_no IS NULL OR BT.invoice_no = '') ".
				"LEFT JOIN iop_billable_item_lock L ON L.source_module='SONOGRAPHY' AND L.source_ref = CONCAT('iop_sonography_charge:',A.charge_id) AND L.InActive = 0 ".
				"WHERE A.iop_id = ".$this->db->escape($iopNo)." AND A.InActive = 0 AND A.status = 'PENDING' AND L.lock_id IS NULL";
		}

		$unionSql = $query1." UNION ALL ".$query2." UNION ALL ".$query3." UNION ALL ".$query4." UNION ALL ".$query5." UNION ALL ".$query7;
		if ($query8 !== '') {
			$unionSql .= " UNION ALL ".$query8;
		}
		$query = $this->db->query($unionSql);
		$rows = $query ? $query->result() : array();

		// Add registration and consultation fees if not already billed
		$visitFees = $this->get_visit_fees_for_billing($iopNo, $patientNo);
		if (!empty($visitFees)) {
			$rows = array_merge($visitFees, $rows);
		}

		// Deduplicate by source_ref to prevent duplicate items
		$seen = array();
		$uniqueRows = array();
		foreach ($rows as $row) {
			$ref = isset($row->source_ref) ? (string)$row->source_ref : '';
			if ($ref === '' || !isset($seen[$ref])) {
				$uniqueRows[] = $row;
				if ($ref !== '') {
					$seen[$ref] = true;
				}
			}
		}
		$rows = $uniqueRows;

		if (!empty($rows)) {
			foreach ($rows as $row) {
				$srcMod = isset($row->source_module) ? strtoupper(trim((string)$row->source_module)) : '';
				if ($srcMod !== 'SONOGRAPHY') {
					continue;
				}
				$srcRef = isset($row->source_ref) ? (string)$row->source_ref : '';
				if (strpos($srcRef, 'iop_sonography_charge:') === 0) {
					$row->charge_id = (int)substr($srcRef, strlen('iop_sonography_charge:'));
					$row->io_lab_id = null;
					$row->item_ref = 'sono_charge_id:' . (int)$row->charge_id;
				} elseif (strpos($srcRef, 'iop_sonography_request:') === 0) {
					$row->charge_id = null;
					$row->io_lab_id = (int)substr($srcRef, strlen('iop_sonography_request:'));
					$row->item_ref = 'sono_req_io_lab_id:' . (int)$row->io_lab_id;
				}
				if (empty($row->charge_id) && isset($row->item_ref) && strpos((string)$row->item_ref, 'sono_req_io_lab_id:') === 0) {
					log_message('debug', '[SONO_FALLBACK_USED] io_lab_id=' . (int)$row->io_lab_id);
				}
			}
		}

		$payer = $this->determine_payer_type((string)$patientNo);
		if ($payer === 'NHIS' && count($rows) > 0 && $this->column_exists('medicine_drug_name', 'is_nhis_covered')) {
			foreach ($rows as &$row) {
				$srcMod = isset($row->source_module) ? strtoupper(trim((string)$row->source_module)) : '';
				if ($srcMod === 'PHARMACY') {
					$drugId = isset($row->medicine_id) ? (int)$row->medicine_id : 0;
					if ($drugId > 0) {
						$cov = $this->check_drug_nhis_coverage($drugId);
						if ($cov['found'] && $cov['is_nhis_covered'] && $cov['nhis_price'] > 0) {
							$row->nPrice = $cov['nhis_price'];
							$row->nhis_covered = true;
						} else {
							$row->nhis_covered = false;
						}
					}
				} elseif ($srcMod === 'LAB' || $srcMod === 'SONOGRAPHY') {
					$svcId = isset($row->medicine_id) ? (int)$row->medicine_id : 0;
					if ($svcId > 0 && $this->column_exists('bill_particular', 'is_nhis_covered')) {
						$svc = $this->getNhisServiceRate($svcId, (string)$patientNo);
						if ($svc && $svc->nhis_covered) {
							$row->nPrice = $svc->effective_rate;
							$row->nhis_covered = true;
						}
					}
				}
			}
			unset($row);
		}

		// Apply company pricing for company-covered patients
		if (count($rows) > 0) {
			// Load pharmacy model for company pricing methods
			$this->load->model('app/pharmacy_model');
			$company_id = $this->pharmacy_model->should_use_company_pricing((string)$patientNo);
			
			if ($company_id) {
				foreach ($rows as &$row) {
					$srcMod = isset($row->source_module) ? strtoupper(trim((string)$row->source_module)) : '';
					
					// Apply company pricing to pharmacy items
					if ($srcMod === 'PHARMACY') {
						$medicine_id = isset($row->medicine_id) ? (int)$row->medicine_id : 0;
						if ($medicine_id > 0) {
							$pricing = $this->pharmacy_model->get_company_pharmacy_price($medicine_id, $company_id);
							$row->base_price = $pricing['base_price'];
							$row->nPrice = $pricing['adjusted_price'];
							$row->company_id = $company_id;
							$row->adjustment_percentage = $pricing['percentage_applied'];
							$row->adjustment_amount = $pricing['difference'];
							$row->company_pricing_applied = true;
						}
					}
					// Note: Other service types (LAB, SONOGRAPHY, etc.) use the centralized
					// pricing in Billing_master_model, so they don't need special handling here
				}
				unset($row);
			}
		}

		return $rows;
	}

	/**
	 * Get registration and consultation fees for a visit if not already billed
	 */
	public function get_visit_fees_for_billing($iopNo, $patientNo) {
		$fees = array();
		
		// Get visit details
		$this->db->select('IO_ID, patient_no, patient_type, date_visit');
		$this->db->where('IO_ID', $iopNo);
		$this->db->where('InActive', 0);
		$visit = $this->db->get('patient_details_iop')->row();
		if (!$visit) return $fees;

		$payerType = 'CASH';
		try {
			if (method_exists($this, 'determine_payer_type')) {
				$payerType = strtoupper(trim((string)$this->determine_payer_type((string)$patientNo)));
			}
		} catch (Exception $e) {
			$payerType = 'CASH';
		}

		$refs = array(
			'registration' => 'visit_registration:' . $iopNo,
			'consultation' => 'visit_consultation:' . $iopNo,
		);
		if ($this->table_exists('billing_transactions')) {
			$q = $this->db->query(
				"SELECT txn_id, item_ref, item_name, quantity, unit_price, net_amount
				 FROM billing_transactions
				 WHERE InActive = 0
				   AND encounter_id = ?
				   AND patient_no = ?
				   AND item_ref IN (?, ?)
				   AND (invoice_no IS NULL OR invoice_no = '')
				   AND UPPER(COALESCE(payment_status,'')) NOT IN ('CANCELLED','PAID')
				 ORDER BY txn_id ASC",
				array((string)$iopNo, (string)$patientNo, $refs['registration'], $refs['consultation'])
			);
			$txRows = $q ? $q->result() : array();
			foreach ($txRows as $tx) {
				$ref = isset($tx->item_ref) ? (string)$tx->item_ref : '';
				$qty = isset($tx->quantity) ? (float)$tx->quantity : 1.0;
				if ($qty <= 0) { $qty = 1.0; }
				$unit = isset($tx->unit_price) ? (float)$tx->unit_price : 0.0;
				if ($unit <= 0.009 && isset($tx->net_amount)) {
					$unit = ((float)$tx->net_amount) / $qty;
				}
				$fees[] = (object)array(
					'medicine_id' => isset($tx->txn_id) ? (int)$tx->txn_id : 0,
					'drug_name' => isset($tx->item_name) ? (string)$tx->item_name : (($ref === $refs['registration']) ? 'Registration Fee' : 'Consultation Fee'),
					'nPrice' => $unit,
					'total_qty' => $qty,
					'isPackage' => '0',
					'medicine_text' => isset($tx->item_name) ? (string)$tx->item_name : '',
					'dosage' => '',
					'instruction' => '',
					'advice' => '',
					'source_module' => ($ref === $refs['registration']) ? 'REGISTRATION' : 'CONSULTATION',
					'source_ref' => $ref,
					'days' => 0,
					'frequency' => ''
				);
			}
			if (!empty($fees)) {
				return $fees;
			}
		}
		
		// Check if registration fee already billed for this visit
		$regRef = $refs['registration'];
		$this->db->where('source_ref', $regRef);
		$this->db->where('InActive', 0);
		$regLocked = $this->db->get('iop_billable_item_lock')->row();

		$preview = null;
		try {
			$ci =& get_instance();
			$ci->load->model('app/visit_billing_resolver_model');
			if (isset($ci->visit_billing_resolver_model) && method_exists($ci->visit_billing_resolver_model, 'preview_visit_fee_decisions')) {
				$preview = $ci->visit_billing_resolver_model->preview_visit_fee_decisions((string)$patientNo, (string)$iopNo, isset($visit->date_visit) ? (string)$visit->date_visit : date('Y-m-d'));
			}
		} catch (Exception $e) {
			$preview = null;
		}

		if (is_array($preview) && !empty($preview['ok']) && !$regLocked) {
			$reg = isset($preview['registration']) && is_array($preview['registration']) ? $preview['registration'] : null;
			$regAmt = ($reg && isset($reg['amount'])) ? (float)$reg['amount'] : 0.0;
			if ($reg && isset($reg['decision']) && $reg['decision'] === 'APPLY' && $regAmt > 0) {
				$fees[] = (object)array(
					'medicine_id' => isset($reg['item_id']) ? (int)$reg['item_id'] : 0,
					'drug_name' => !empty($reg['item_name']) ? (string)$reg['item_name'] : 'Registration Fee',
					'nPrice' => $regAmt,
					'total_qty' => 1,
					'isPackage' => '0',
					'medicine_text' => !empty($reg['item_name']) ? (string)$reg['item_name'] : 'Registration Fee',
					'dosage' => '',
					'instruction' => '',
					'advice' => '',
					'source_module' => 'REGISTRATION',
					'source_ref' => $regRef,
					'days' => 0,
					'frequency' => ''
				);
			}
		}
		
		// Check if consultation fee already billed for this visit
		$consRef = $refs['consultation'];
		$this->db->where('source_ref', $consRef);
		$this->db->where('InActive', 0);
		$consLocked = $this->db->get('iop_billable_item_lock')->row();
		
		if (is_array($preview) && !empty($preview['ok']) && !$consLocked) {
			$con = isset($preview['consultation']) && is_array($preview['consultation']) ? $preview['consultation'] : null;
			$conAmt = ($con && isset($con['amount'])) ? (float)$con['amount'] : 0.0;
			if ($con && isset($con['decision']) && $con['decision'] === 'APPLY' && $conAmt > 0) {
				$fees[] = (object)array(
					'medicine_id' => isset($con['item_id']) ? (int)$con['item_id'] : 0,
					'drug_name' => !empty($con['item_name']) ? (string)$con['item_name'] : 'Consultation Fee',
					'nPrice' => $conAmt,
					'total_qty' => 1,
					'isPackage' => '0',
					'medicine_text' => !empty($con['item_name']) ? (string)$con['item_name'] : 'Consultation Fee',
					'dosage' => '',
					'instruction' => '',
					'advice' => '',
					'source_module' => 'CONSULTATION',
					'source_ref' => $consRef,
					'days' => 0,
					'frequency' => ''
				);
			}
		}
		
		return $fees;
	}
	
	/* ── NHIS Billing Infrastructure ──────────────────── */

	public function install_nhis_config_table(){
		$this->db->query("CREATE TABLE IF NOT EXISTS `nhis_billing_config` (
			`config_id` int(11) NOT NULL AUTO_INCREMENT,
			`config_key` varchar(60) NOT NULL,
			`config_value` varchar(255) NOT NULL,
			`description` varchar(255) DEFAULT NULL,
			`updated_at` datetime NOT NULL,
			`updated_by` varchar(25) DEFAULT NULL,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`config_id`),
			UNIQUE KEY `uq_config_key` (`config_key`,`InActive`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1");
		$this->db->where(array('config_key' => 'registration_fee_nhis', 'InActive' => 0));
		if (!$this->db->get('nhis_billing_config')->row()) {
			$now = date('Y-m-d H:i:s');
			$seeds = array(
				array('registration_fee_nhis', '0.00', 'OPD registration fee for NHIS patients (0 = free)'),
				array('consultation_fee_nhis', '0.00', 'Consultation fee for NHIS patients (0 = free)'),
				array('nhis_subsidy_percent', '100', 'NHIS subsidy percentage (100 = fully covered, 80 = patient pays 20%)'),
				array('nhis_review_days', '14', 'Days within which a follow-up visit is treated as free review'),
				array('nhis_covers_lab', '1', 'Whether NHIS covers laboratory requests (1=yes, 0=no)'),
				array('nhis_covers_pharmacy', '1', 'Whether NHIS covers pharmacy dispensing (1=yes, 0=no)')
			);
			foreach ($seeds as $s) {
				$this->db->insert('nhis_billing_config', array(
					'config_key' => $s[0], 'config_value' => $s[1], 'description' => $s[2],
					'updated_at' => $now, 'updated_by' => null, 'InActive' => 0
				));
			}
		}
		return true;
	}

	public function get_nhis_config($key, $default = null){
		$this->install_nhis_config_table();
		$this->db->select('config_value');
		$q = $this->db->get_where('nhis_billing_config', array('config_key' => (string)$key, 'InActive' => 0), 1);
		$r = $q ? $q->row() : null;
		return ($r && isset($r->config_value)) ? (string)$r->config_value : $default;
	}

	public function set_nhis_config($key, $value, $updated_by = null){
		$this->install_nhis_config_table();
		$now = date('Y-m-d H:i:s');
		$this->db->where(array('config_key' => (string)$key, 'InActive' => 0));
		$existing = $this->db->get('nhis_billing_config')->row();
		if ($existing) {
			$this->db->where(array('config_key' => (string)$key, 'InActive' => 0));
			$this->db->update('nhis_billing_config', array(
				'config_value' => (string)$value, 'updated_at' => $now,
				'updated_by' => $updated_by !== null ? (string)$updated_by : null
			));
		} else {
			$this->db->insert('nhis_billing_config', array(
				'config_key' => (string)$key, 'config_value' => (string)$value,
				'updated_at' => $now, 'updated_by' => $updated_by !== null ? (string)$updated_by : null, 'InActive' => 0
			));
		}
		return true;
	}

	public function ensure_nhis_billing_columns(){
		if (!$this->table_exists('iop_billing')) { return false; }
		$old_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old_debug !== null) { $this->db->db_debug = false; }

		if (!$this->column_exists('iop_billing', 'payer_type')) {
			$this->db->query("ALTER TABLE `iop_billing` ADD COLUMN `payer_type` varchar(20) DEFAULT 'CASH'");
			$this->db->query("ALTER TABLE `iop_billing` ADD KEY `idx_payer_type` (`payer_type`)");
		}
		if (!$this->column_exists('iop_billing', 'nhis_covered_amount')) {
			$this->db->query("ALTER TABLE `iop_billing` ADD COLUMN `nhis_covered_amount` decimal(18,2) DEFAULT 0");
		}
		if (!$this->column_exists('iop_billing', 'patient_payable_amount')) {
			$this->db->query("ALTER TABLE `iop_billing` ADD COLUMN `patient_payable_amount` decimal(18,2) DEFAULT 0");
		}

		if ($this->table_exists('iop_billing_t')) {
			if (!$this->column_exists('iop_billing_t', 'payer_type')) {
				$this->db->query("ALTER TABLE `iop_billing_t` ADD COLUMN `payer_type` varchar(20) DEFAULT 'CASH'");
			}
			if (!$this->column_exists('iop_billing_t', 'nhis_covered_amount')) {
				$this->db->query("ALTER TABLE `iop_billing_t` ADD COLUMN `nhis_covered_amount` decimal(18,2) DEFAULT 0");
			}
			if (!$this->column_exists('iop_billing_t', 'patient_payable_amount')) {
				$this->db->query("ALTER TABLE `iop_billing_t` ADD COLUMN `patient_payable_amount` decimal(18,2) DEFAULT 0");
			}
		}

		if ($old_debug !== null) { $this->db->db_debug = $old_debug; }
		return true;
	}

	public function determine_payer_type($patient_no){
		$patient_no = (string)$patient_no;
		$this->load->model('app/patient_model');
		$this->patient_model->ensure_nhis_schema();
		$nhis = $this->patient_model->get_nhis_info($patient_no);
		$nhisNum = isset($nhis->nhis_number) ? trim((string)$nhis->nhis_number) : '';
		if ($nhisNum === '') {
			return 'CASH';
		}
		$live = $this->patient_model->compute_nhis_status($nhis->nhis_number, $nhis->nhis_expiry_date);
		return ($live === 'ACTIVE') ? 'NHIS' : 'CASH';
	}

	public function compute_nhis_line_amounts($total_amount, $payer_type){
		$total = (float)$total_amount;
		$payer_type = strtoupper(trim((string)$payer_type));
		if ($payer_type !== 'NHIS' || $total <= 0) {
			return array('nhis_covered' => 0.00, 'patient_payable' => $total);
		}
		$pct = (float)$this->get_nhis_config('nhis_subsidy_percent', '100');
		if ($pct > 100) { $pct = 100; }
		if ($pct < 0) { $pct = 0; }
		$covered = round($total * ($pct / 100.0), 2);
		$payable = round($total - $covered, 2);
		return array('nhis_covered' => $covered, 'patient_payable' => $payable);
	}

	public function is_nhis_review_visit($patient_no, $visit_date = null){
		$patient_no = (string)$patient_no;
		if ($visit_date === null) { $visit_date = date('Y-m-d'); }
		$reviewDays = (int)$this->get_nhis_config('nhis_review_days', '14');
		if ($reviewDays <= 0) { return false; }
		$cutoff = date('Y-m-d', strtotime($visit_date . ' -' . $reviewDays . ' days'));
		$this->db->select('IO_ID');
		$this->db->where(array('patient_no' => $patient_no, 'InActive' => 0));
		$this->db->where("date_visit >= '".$cutoff."'", null, false);
		$this->db->where("date_visit < '".$visit_date."'", null, false);
		$this->db->limit(1);
		$q = $this->db->get('patient_details_iop');
		return ($q && $q->num_rows() > 0);
	}

	public function check_nhis_payment_gate($patient_no, $iop_id, $service_type){
		$patient_no = (string)$patient_no;
		$iop_id = (string)$iop_id;
		$service_type = strtoupper(trim((string)$service_type));
		$payer = $this->determine_payer_type($patient_no);
		if ($payer === 'NHIS') {
			if ($service_type === 'LAB' || $service_type === 'LABORATORY') {
				$covers = (int)$this->get_nhis_config('nhis_covers_lab', '1');
				if ($covers === 1) {
					return array('allowed' => true, 'reason' => 'NHIS covers laboratory', 'payer_type' => 'NHIS');
				}
			}
			if ($service_type === 'PHARMACY') {
				$covers = (int)$this->get_nhis_config('nhis_covers_pharmacy', '1');
				if ($covers === 1) {
					return array('allowed' => true, 'reason' => 'NHIS covers pharmacy', 'payer_type' => 'NHIS');
				}
			}
		}
		$hasUnpaid = $this->has_unpaid_billable_locks_for_iop($iop_id);
		if ($hasUnpaid) {
			return array('allowed' => false, 'reason' => 'Unpaid billable items exist. Payment required before proceeding.', 'payer_type' => $payer);
		}
		return array('allowed' => true, 'reason' => '', 'payer_type' => $payer);
	}

	/* ── Day 3: Drug, Lab & Imaging NHIS Columns ─────── */

	/**
	 * Add is_nhis_covered, nhis_price, cash_price to medicine_drug_name.
	 * cash_price defaults to existing nPrice so nothing changes for cash patients.
	 */
	public function ensure_nhis_drug_columns(){
		if (!$this->table_exists('medicine_drug_name')) { return false; }
		$old = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old !== null) { $this->db->db_debug = false; }

		if (!$this->column_exists('medicine_drug_name', 'is_nhis_covered')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `is_nhis_covered` tinyint(1) NOT NULL DEFAULT 0");
		}
		if (!$this->column_exists('medicine_drug_name', 'nhis_price')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `nhis_price` decimal(18,2) DEFAULT 0");
		}
		if (!$this->column_exists('medicine_drug_name', 'cash_price')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `cash_price` decimal(18,2) DEFAULT 0");
			$this->db->query("UPDATE `medicine_drug_name` SET `cash_price` = `nPrice` WHERE `cash_price` = 0 AND `nPrice` > 0");
		}

		if ($old !== null) { $this->db->db_debug = $old; }
		return true;
	}

	/**
	 * Add is_nhis_covered, nhis_charge_amount to bill_particular (lab/imaging services).
	 */
	public function ensure_nhis_service_columns(){
		if (!$this->table_exists('bill_particular')) { return false; }
		$old = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old !== null) { $this->db->db_debug = false; }

		if (!$this->column_exists('bill_particular', 'is_nhis_covered')) {
			$this->db->query("ALTER TABLE `bill_particular` ADD COLUMN `is_nhis_covered` tinyint(1) NOT NULL DEFAULT 0");
		}
		if (!$this->column_exists('bill_particular', 'nhis_charge_amount')) {
			$this->db->query("ALTER TABLE `bill_particular` ADD COLUMN `nhis_charge_amount` decimal(18,2) DEFAULT 0");
		}

		if ($old !== null) { $this->db->db_debug = $old; }
		return true;
	}

	/**
	 * Add is_nhis_covered, nhis_rate to sonography_items.
	 */
	public function ensure_nhis_sonography_columns(){
		if (!$this->table_exists('sonography_items')) { return false; }
		$old = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old !== null) { $this->db->db_debug = false; }

		if (!$this->column_exists('sonography_items', 'is_nhis_covered')) {
			$this->db->query("ALTER TABLE `sonography_items` ADD COLUMN `is_nhis_covered` tinyint(1) NOT NULL DEFAULT 0");
		}
		if (!$this->column_exists('sonography_items', 'nhis_rate')) {
			$this->db->query("ALTER TABLE `sonography_items` ADD COLUMN `nhis_rate` decimal(18,2) DEFAULT 0");
		}

		if ($old !== null) { $this->db->db_debug = $old; }
		return true;
	}

	/**
	 * Run all Day-3 NHIS schema migrations in one call.
	 */
	public function ensure_nhis_day3_schema(){
		$this->ensure_nhis_drug_columns();
		$this->ensure_nhis_service_columns();
		$this->ensure_nhis_sonography_columns();
	}

	/* ── NHIS-aware rate helpers ──────────────────────── */

	/**
	 * Return effective drug rate for a patient.
	 * NHIS patient with a covered drug → nhis_price;  else → nPrice (cash_price).
	 */
	public function getNhisDrugRate($drug_id, $patient_no = null){
		// Delegate to Price_engine_model::resolve_legacy_rate (Single Source of Truth).
		// Behavior preserved 1:1 — NHIS-or-catalog only; company pricing not applied here
		// (Phase 4, Step 9 will switch this call site to full resolve()).
		$this->load->model('app/Price_engine_model', 'price_engine_model');
		$res = $this->price_engine_model->resolve_legacy_rate(
			'DRUG', (int)$drug_id, $patient_no
		);
		if (empty($res['ok']) || empty($res['raw'])) { return null; }
		$row = $res['raw'];
		$row->effective_price = (float)$res['effective'];
		$row->nhis_covered    = !empty($res['nhis_covered']);
		return $row;
	}

	/**
	 * Return effective service rate for a patient.
	 * NHIS patient with a covered service → nhis_charge_amount; else → charge_amount.
	 */
	public function getNhisServiceRate($particular_id, $patient_no = null){
		// Delegate to Price_engine_model::resolve_legacy_rate (Single Source of Truth).
		// Behavior preserved 1:1 — NHIS-or-catalog only; company pricing not applied here
		// (Phase 4, Step 9 will switch this call site to full resolve()).
		$this->load->model('app/Price_engine_model', 'price_engine_model');
		$res = $this->price_engine_model->resolve_legacy_rate(
			'SERVICE', (int)$particular_id, $patient_no
		);
		if (empty($res['ok']) || empty($res['raw'])) { return null; }
		$row = $res['raw'];
		$row->effective_rate = (float)$res['effective'];
		$row->nhis_covered   = !empty($res['nhis_covered']);
		return $row;
	}

	/**
	 * Get all active sonography items for dropdown.
	 */
	public function get_sonography_items(){
		if (!$this->table_exists('sonography_items')) { return array(); }
		$this->db->select('item_id, item_name');
		$this->db->where('InActive', 0);
		$this->db->order_by('item_name', 'ASC');
		return $this->db->get('sonography_items')->result();
	}

	/**
	 * Get all lab categories for multi-entry modal.
	 */
	public function get_lab_categories(){
		if (!$this->table_exists('bill_group_name')) { return array(); }
		// Lab-related groups (typically HAEMATOLOGY, BIOCHEMISTRY, MICROBIOLOGY, etc.)
		$lab_groups = array('HAEMATOLOGY', 'BIOCHEMISTRY', 'CLINICAL PATHOLOGY', 'MICROBIOLOGY', 
			'SEROLOGY', 'SPECIAL TESTS', 'HISTOPATHOLOGY', 'TRANSFUSION MEDICINE');
		$this->db->select('group_id AS category_id, group_name AS category_name');
		$this->db->where('InActive', 0);
		$this->db->where_in('group_name', $lab_groups);
		$this->db->order_by('group_name', 'ASC');
		return $this->db->get('bill_group_name')->result();
	}

	/**
	 * Get all lab tests for multi-entry modal.
	 */
	public function get_lab_tests(){
		if (!$this->table_exists('bill_particular')) { return array(); }
		// Get all particulars from lab-related categories
		$lab_groups = array('HAEMATOLOGY', 'BIOCHEMISTRY', 'CLINICAL PATHOLOGY', 'MICROBIOLOGY', 
			'SEROLOGY', 'SPECIAL TESTS', 'HISTOPATHOLOGY', 'TRANSFUSION MEDICINE');
		$nhisCols = '';
		if ($this->column_exists('bill_particular', 'is_nhis_covered')) {
			$nhisCols .= ', bp.is_nhis_covered';
		}
		if ($this->column_exists('bill_particular', 'nhis_price')) {
			$nhisCols .= ', bp.nhis_price';
		}
		if ($this->column_exists('bill_particular', 'charge_amount')) {
			$nhisCols .= ', bp.charge_amount';
		}
		$this->db->select('bp.particular_id, bp.particular_name, bp.group_id AS category_id' . $nhisCols, false);
		$this->db->from('bill_particular bp');
		$this->db->join('bill_group_name bg', 'bg.group_id = bp.group_id', 'inner');
		$this->db->where('bp.InActive', 0);
		$this->db->where('bg.InActive', 0);
		$this->db->where_in('bg.group_name', $lab_groups);
		$this->db->order_by('bp.particular_name', 'ASC');
		return $this->db->get()->result();
	}

	public function search_walkin_lab_tests($term = '', $limit = 20)
	{
		if (!$this->table_exists('bill_particular') || !$this->table_exists('bill_group_name')) { return array(); }
		$term = trim((string)$term);
		$limit = max(1, min(50, (int)$limit));
		$lab_groups = array('HAEMATOLOGY', 'BIOCHEMISTRY', 'CLINICAL PATHOLOGY', 'MICROBIOLOGY',
			'SEROLOGY', 'SPECIAL TESTS', 'HISTOPATHOLOGY', 'TRANSFUSION MEDICINE');
		$priceCol = $this->column_exists('bill_particular', 'charge_amount') ? 'bp.charge_amount' : '0';
		$this->db->select('bp.particular_id AS item_id, bp.particular_name, ' . $priceCol . ' AS unit_price', false);
		$this->db->from('bill_particular bp');
		$this->db->join('bill_group_name bg', 'bg.group_id = bp.group_id', 'inner');
		$this->db->where('bp.InActive', 0);
		$this->db->where('bg.InActive', 0);
		$this->db->where_in('bg.group_name', $lab_groups);
		if ($term !== '') {
			$t = $this->db->escape_like_str($term);
			$this->db->where("bp.particular_name LIKE '%" . $t . "%'", null, false);
		}
		$this->db->order_by('bp.particular_name', 'ASC');
		$this->db->limit($limit);
		$rows = $this->db->get()->result();
		$out = array();
		foreach ($rows as $r) {
			$out[] = array(
				'item_id' => isset($r->item_id) ? (int)$r->item_id : 0,
				'label' => isset($r->particular_name) ? (string)$r->particular_name : '',
				'value' => isset($r->particular_name) ? (string)$r->particular_name : '',
				'unit_price' => isset($r->unit_price) ? (float)$r->unit_price : 0.0,
			);
		}
		return $out;
	}

	public function search_walkin_procedures($term = '', $limit = 20)
	{
		if (!$this->table_exists('bill_particular') || !$this->table_exists('bill_group_name')) { return array(); }
		$term = trim((string)$term);
		$limit = max(1, min(50, (int)$limit));
		$priceCol = $this->column_exists('bill_particular', 'charge_amount') ? 'bp.charge_amount' : '0';
		$this->db->select('bp.particular_id AS item_id, bp.particular_name, ' . $priceCol . ' AS unit_price', false);
		$this->db->from('bill_particular bp');
		$this->db->join('bill_group_name bg', 'bg.group_id = bp.group_id', 'inner');
		$this->db->where('bp.InActive', 0);
		$this->db->where('bg.InActive', 0);
		$this->db->where('bg.group_name', 'PROCEDURES');
		if ($term !== '') {
			$t = $this->db->escape_like_str($term);
			$this->db->where("bp.particular_name LIKE '%" . $t . "%'", null, false);
		}
		$this->db->order_by('bp.particular_name', 'ASC');
		$this->db->limit($limit);
		$rows = $this->db->get()->result();
		$out = array();
		foreach ($rows as $r) {
			$out[] = array(
				'item_id' => isset($r->item_id) ? (int)$r->item_id : 0,
				'label' => isset($r->particular_name) ? (string)$r->particular_name : '',
				'value' => isset($r->particular_name) ? (string)$r->particular_name : '',
				'unit_price' => isset($r->unit_price) ? (float)$r->unit_price : 0.0,
			);
		}
		return $out;
	}

	public function search_walkin_particulars($term = '', $limit = 20)
	{
		if (!$this->table_exists('bill_particular')) { return array(); }
		$term = trim((string)$term);
		$limit = max(1, min(50, (int)$limit));
		$priceCol = $this->column_exists('bill_particular', 'charge_amount') ? 'charge_amount' : '0';
		$this->db->select('particular_id AS item_id, particular_name, ' . $priceCol . ' AS unit_price', false);
		$this->db->from('bill_particular');
		$this->db->where('InActive', 0);
		if ($term !== '') {
			$t = $this->db->escape_like_str($term);
			$this->db->where("particular_name LIKE '%" . $t . "%'", null, false);
		}
		$this->db->order_by('particular_name', 'ASC');
		$this->db->limit($limit);
		$rows = $this->db->get()->result();
		$out = array();
		foreach ($rows as $r) {
			$out[] = array(
				'item_id' => isset($r->item_id) ? (int)$r->item_id : 0,
				'label' => isset($r->particular_name) ? (string)$r->particular_name : '',
				'value' => isset($r->particular_name) ? (string)$r->particular_name : '',
				'unit_price' => isset($r->unit_price) ? (float)$r->unit_price : 0.0,
			);
		}
		return $out;
	}

	/**
	 * Return effective sonography rate for a patient.
	 */
	public function getNhisSonographyRate($item_id, $patient_no = null){
		$this->ensure_nhis_sonography_columns();
		$item_id = (int)$item_id;
		$select = 'item_id,item_name,is_nhis_covered,nhis_rate';
		if ($this->column_exists('sonography_items', 'bill_particular_id')) {
			$select .= ',bill_particular_id';
		}
		$this->db->select($select, false);
		$row = $this->db->get_where('sonography_items', array('item_id' => $item_id, 'InActive' => 0))->row();
		if (!$row) { return null; }

		$cashRate = 0.0;
		$bpId = isset($row->bill_particular_id) ? (int)$row->bill_particular_id : 0;
		if ($bpId > 0 && $this->table_exists('bill_particular')) {
			$this->db->select('charge_amount');
			$bp = $this->db->get_where('bill_particular', array('particular_id' => $bpId))->row();
			$cashRate = ($bp && isset($bp->charge_amount)) ? (float)$bp->charge_amount : 0.0;
		}
		$row->cash_rate = $cashRate;
		$row->effective_rate = $cashRate;
		$row->nhis_covered_flag = false;

		if ($patient_no !== null) {
			$payer = $this->determine_payer_type((string)$patient_no);
			if ($payer === 'NHIS' && (int)$row->is_nhis_covered === 1) {
				$nhisR = (float)$row->nhis_rate;
				$row->effective_rate = ($nhisR > 0) ? $nhisR : $cashRate;
				$row->nhis_covered_flag = true;
			}
		}
		return $row;
	}

	/**
	 * Check if a drug is NHIS-covered.
	 * Returns array with coverage boolean and prices.
	 */
	public function check_drug_nhis_coverage($drug_id){
		$this->ensure_nhis_drug_columns();
		$drug_id = (int)$drug_id;
		$this->db->select('drug_id,drug_name,is_nhis_covered,nhis_price,nPrice,cash_price', false);
		$row = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
		if (!$row) { return array('found' => false, 'is_nhis_covered' => false); }
		return array(
			'found' => true,
			'drug_name' => (string)$row->drug_name,
			'is_nhis_covered' => ((int)$row->is_nhis_covered === 1),
			'nhis_price' => (float)$row->nhis_price,
			'cash_price' => (float)$row->cash_price,
			'nPrice' => (float)$row->nPrice
		);
	}

	/* ── End Day 3 NHIS ──────────────────────────────── */

	/* ── Day 4: NHIS Claims Module ───────────────────── */

	public function install_nhis_claims_table(){
		$this->db->query("CREATE TABLE IF NOT EXISTS `nhis_claims` (
			`claim_id` bigint(11) NOT NULL AUTO_INCREMENT,
			`claim_ref` varchar(30) NOT NULL,
			`patient_no` varchar(25) NOT NULL,
			`iop_id` varchar(25) NOT NULL,
			`invoice_no` varchar(25) DEFAULT NULL,
			`nhis_number` varchar(30) DEFAULT NULL,
			`total_amount` decimal(18,2) NOT NULL DEFAULT 0,
			`nhis_amount` decimal(18,2) NOT NULL DEFAULT 0,
			`patient_amount` decimal(18,2) NOT NULL DEFAULT 0,
			`status` varchar(20) NOT NULL DEFAULT 'PENDING',
			`submitted_at` datetime DEFAULT NULL,
			`approved_at` datetime DEFAULT NULL,
			`rejected_at` datetime DEFAULT NULL,
			`rejection_reason` text DEFAULT NULL,
			`created_at` datetime NOT NULL,
			`created_by` varchar(25) DEFAULT NULL,
			`updated_at` datetime DEFAULT NULL,
			`updated_by` varchar(25) DEFAULT NULL,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`claim_id`),
			UNIQUE KEY `uq_claim_ref` (`claim_ref`),
			KEY `idx_patient` (`patient_no`),
			KEY `idx_iop` (`iop_id`),
			KEY `idx_status` (`status`),
			KEY `idx_invoice` (`invoice_no`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1");

		$this->db->query("CREATE TABLE IF NOT EXISTS `nhis_claim_lines` (
			`line_id` bigint(11) NOT NULL AUTO_INCREMENT,
			`claim_id` bigint(11) NOT NULL,
			`service_type` varchar(30) NOT NULL,
			`service_name` varchar(255) DEFAULT NULL,
			`quantity` decimal(18,2) NOT NULL DEFAULT 1,
			`unit_price` decimal(18,2) NOT NULL DEFAULT 0,
			`total_price` decimal(18,2) NOT NULL DEFAULT 0,
			`nhis_covered` decimal(18,2) NOT NULL DEFAULT 0,
			`patient_pays` decimal(18,2) NOT NULL DEFAULT 0,
			`is_nhis_covered` tinyint(1) NOT NULL DEFAULT 0,
			`source_ref` varchar(100) DEFAULT NULL,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`line_id`),
			KEY `idx_claim` (`claim_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1");
		return true;
	}

	/**
	 * Generate a unique claim reference like CLM-20260321-0001
	 */
	private function generate_claim_ref(){
		$prefix = 'CLM-' . date('Ymd') . '-';
		$this->db->select('claim_ref');
		$this->db->like('claim_ref', $prefix, 'after');
		$this->db->order_by('claim_id', 'DESC');
		$this->db->limit(1);
		$last = $this->db->get('nhis_claims')->row();
		$seq = 1;
		if ($last && isset($last->claim_ref)) {
			$parts = explode('-', $last->claim_ref);
			$seq = (int)end($parts) + 1;
		}
		return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
	}

	/**
	 * Generate a unique claim number using the modern nhis_model pattern.
	 */
	private function generate_nhis_claim_number(){
		$prefix = 'CLM';
		$date = date('Ymd');
		$max_attempts = 10;

		for ($i = 0; $i < $max_attempts; $i++) {
			$random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
			$base = "{$prefix}-{$date}-{$random}";
			for ($suffix = 1; $suffix <= $max_attempts; $suffix++) {
				$claim_number = ($suffix === 1) ? $base : $base . '-' . $suffix;
				$exists = $this->db->where('claim_number', $claim_number)
					->count_all_results('nhis_claims');
				if ($exists == 0) {
					return $claim_number;
				}
			}
		}

		$base = "{$prefix}-{$date}-" . time();
		for ($suffix = 1; $suffix <= $max_attempts; $suffix++) {
			$claim_number = ($suffix === 1) ? $base : $base . '-' . $suffix;
			$exists = $this->db->where('claim_number', $claim_number)
				->count_all_results('nhis_claims');
			if ($exists == 0) {
				return $claim_number;
			}
		}

		return $base . '-' . uniqid();
	}

	/**
	 * Auto-generate an NHIS claim for a completed visit.
	 * Collects all invoice lines for the iop_id and builds claim + claim_lines.
	 * Returns claim_id or false.
	 */
	public function generate_nhis_claim($iop_id, $patient_no, $created_by = null){
		$CI = get_instance();
		$router_class = isset($CI->router->class) ? $CI->router->class : null;
		$router_method = isset($CI->router->method) ? $CI->router->method : null;
		$uri = function_exists('uri_string') ? uri_string() : null;
		log_message('error', 'NHIS_CLAIM_CALL_TRACE: ' . json_encode(array(
			'method' => __METHOD__,
			'file' => __FILE__,
			'uri' => $uri,
			'router_class' => $router_class,
			'router_method' => $router_method,
			'patient_no' => isset($patient_no) ? $patient_no : null,
			'iop_id' => isset($iop_id) ? $iop_id : null
		)));

		$this->install_nhis_claims_table();
		$iop_id = (string)$iop_id;
		$patient_no = (string)$patient_no;

		$payer = $this->determine_payer_type($patient_no);
		if ($payer !== 'NHIS') { return false; }

		$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0, 'status' => 'PENDING'));
		$existing = $this->db->get('nhis_claims')->row();
		if ($existing) { return (int)$existing->claim_id; }

		$this->load->model('app/patient_model');
		$nhisInfo = $this->patient_model->get_nhis_info($patient_no);
		$nhisNum = (isset($nhisInfo->nhis_number)) ? trim((string)$nhisInfo->nhis_number) : '';

		$invoiceNo = null;
		$this->db->select('invoice_no, total_amount, nhis_covered_amount, patient_payable_amount');
		$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0, 'payer_type' => 'NHIS'));
		$this->db->order_by('invoice_no', 'DESC');
		$this->db->limit(1);
		$inv = $this->db->get('iop_billing')->row();

		$totalAmt = 0.0;
		$nhisAmt = 0.0;
		$patientAmt = 0.0;
		if ($inv) {
			$invoiceNo = (string)$inv->invoice_no;
			$totalAmt = (float)$inv->total_amount;
			$nhisAmt = (float)$inv->nhis_covered_amount;
			$patientAmt = (float)$inv->patient_payable_amount;
		}

		$now = date('Y-m-d H:i:s');
		$claimRef = $this->generate_claim_ref();
		$claimNumber = $this->generate_nhis_claim_number();
		$trace = array(
			'method' => __METHOD__,
			'file' => __FILE__,
			'uri' => function_exists('uri_string') ? uri_string() : null,
			'patient_no' => isset($patient_no) ? $patient_no : null,
			'iop_id' => isset($iop_id) ? $iop_id : null,
			'claim_number' => $claimNumber
		);
		log_message('error', 'NHIS_CLAIM_INSERT_TRACE: ' . json_encode($trace));
		$this->db->insert('nhis_claims', array(
			'claim_number' => $claimNumber,
			'claim_ref' => $claimRef,
			'patient_no' => $patient_no,
			'iop_id' => $iop_id,
			'invoice_no' => $invoiceNo,
			'nhis_number' => $nhisNum,
			'total_amount' => $totalAmt,
			'nhis_amount' => $nhisAmt,
			'patient_amount' => $patientAmt,
			'status' => 'PENDING',
			'created_at' => $now,
			'created_by' => $created_by !== null ? (string)$created_by : null,
			'InActive' => 0
		));
		$claimId = (int)$this->db->insert_id();

		if ($invoiceNo !== null) {
			$this->db->select('detail_id, particular_name, amount, quantity, payer_type, nhis_covered_amount, patient_payable_amount');
			$this->db->where(array('invoice_no' => $invoiceNo, 'InActive' => 0));
			$lines = $this->db->get('iop_billing_t')->result();
			foreach ($lines as $ln) {
				$svcType = 'SERVICE';
				$svcName = isset($ln->particular_name) ? (string)$ln->particular_name : '';
				$qty = isset($ln->quantity) ? (float)$ln->quantity : 1;
				$unitP = ($qty > 0) ? round((float)$ln->amount / $qty, 2) : (float)$ln->amount;
				$totalP = (float)$ln->amount;
				$nhisCov = isset($ln->nhis_covered_amount) ? (float)$ln->nhis_covered_amount : 0;
				$patPays = isset($ln->patient_payable_amount) ? (float)$ln->patient_payable_amount : $totalP;
				$isCov = ($nhisCov > 0) ? 1 : 0;

				$this->db->insert('nhis_claim_lines', array(
					'claim_id' => $claimId,
					'service_type' => $svcType,
					'service_name' => $svcName,
					'quantity' => $qty,
					'unit_price' => $unitP,
					'total_price' => $totalP,
					'nhis_covered' => $nhisCov,
					'patient_pays' => $patPays,
					'is_nhis_covered' => $isCov,
					'source_ref' => 'iop_billing_t:' . (isset($ln->detail_id) ? $ln->detail_id : ''),
					'InActive' => 0
				));
			}
		}

		$this->log_nhis_audit('CLAIM_GENERATED', 'nhis_claims', $claimId, null,
			json_encode(array('claim_ref' => $claimRef, 'total' => $totalAmt, 'nhis' => $nhisAmt)),
			$created_by, $patient_no, $iop_id);

		return $claimId;
	}

	public function get_claim($claim_id){
		$this->install_nhis_claims_table();
		$this->db->where(array('claim_id' => (int)$claim_id, 'InActive' => 0));
		return $this->db->get('nhis_claims')->row();
	}

	public function get_claim_by_iop($iop_id){
		$this->install_nhis_claims_table();
		$this->db->where(array('iop_id' => (string)$iop_id, 'InActive' => 0));
		$this->db->order_by('claim_id', 'DESC');
		$this->db->limit(1);
		return $this->db->get('nhis_claims')->row();
	}

	public function get_claim_lines($claim_id){
		$this->install_nhis_claims_table();
		$this->db->where(array('claim_id' => (int)$claim_id, 'InActive' => 0));
		return $this->db->get('nhis_claim_lines')->result();
	}

	public function update_claim_status($claim_id, $status, $updated_by = null, $reason = null){
		$this->install_nhis_claims_table();
		$claim_id = (int)$claim_id;
		$status = strtoupper(trim((string)$status));
		$allowed = array('PENDING', 'SUBMITTED', 'APPROVED', 'REJECTED');
		if (!in_array($status, $allowed)) { return false; }

		$old = $this->get_claim($claim_id);
		$oldStatus = ($old && isset($old->status)) ? (string)$old->status : '';

		$data = array('status' => $status, 'updated_at' => date('Y-m-d H:i:s'),
			'updated_by' => $updated_by !== null ? (string)$updated_by : null);
		if ($status === 'SUBMITTED') { $data['submitted_at'] = date('Y-m-d H:i:s'); }
		if ($status === 'APPROVED') { $data['approved_at'] = date('Y-m-d H:i:s'); }
		if ($status === 'REJECTED') {
			$data['rejected_at'] = date('Y-m-d H:i:s');
			$data['rejection_reason'] = $reason !== null ? (string)$reason : null;
		}
		$this->db->where(array('claim_id' => $claim_id, 'InActive' => 0));
		$this->db->update('nhis_claims', $data);

		$this->log_nhis_audit('CLAIM_STATUS_CHANGE', 'nhis_claims', $claim_id, $oldStatus, $status,
			$updated_by, ($old ? $old->patient_no : null), ($old ? $old->iop_id : null));
		return true;
	}

	public function get_claims_list($filters = array()){
		$this->install_nhis_claims_table();
		$this->db->select("A.*, CONCAT_WS(' ', P.firstname, P.lastname) as patient_name", false);
		$this->db->from('nhis_claims A');
		$this->db->join('patient_personal_info P', 'P.patient_no = A.patient_no', 'left');
		$this->db->where('A.InActive', 0);
		if (isset($filters['status']) && $filters['status'] !== '') {
			$this->db->where('A.status', strtoupper(trim((string)$filters['status'])));
		}
		if (isset($filters['date_from']) && $filters['date_from'] !== '') {
			$this->db->where('DATE(A.created_at) >=', $filters['date_from']);
		}
		if (isset($filters['date_to']) && $filters['date_to'] !== '') {
			$this->db->where('DATE(A.created_at) <=', $filters['date_to']);
		}
		if (isset($filters['search']) && trim((string)$filters['search']) !== '') {
			$s = trim((string)$filters['search']);
			$this->db->group_start();
			$this->db->like('A.claim_ref', $s);
			$this->db->or_like('A.patient_no', $s);
			$this->db->or_like('A.nhis_number', $s);
			$this->db->or_like('P.firstname', $s);
			$this->db->or_like('P.lastname', $s);
			$this->db->group_end();
		}
		$this->db->order_by('A.created_at', 'DESC');
		$limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
		$this->db->limit($limit);
		return $this->db->get()->result();
	}

	/* ── Day 4: NHIS Audit Trail ─────────────────────── */

	public function install_nhis_audit_log(){
		$this->db->query("CREATE TABLE IF NOT EXISTS `nhis_audit_log` (
			`audit_id` bigint(11) NOT NULL AUTO_INCREMENT,
			`event_type` varchar(50) NOT NULL,
			`table_name` varchar(60) DEFAULT NULL,
			`record_id` varchar(50) DEFAULT NULL,
			`old_value` text DEFAULT NULL,
			`new_value` text DEFAULT NULL,
			`patient_no` varchar(25) DEFAULT NULL,
			`iop_id` varchar(25) DEFAULT NULL,
			`user_id` varchar(25) DEFAULT NULL,
			`ip_address` varchar(45) DEFAULT NULL,
			`created_at` datetime NOT NULL,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`audit_id`),
			KEY `idx_event` (`event_type`),
			KEY `idx_patient` (`patient_no`),
			KEY `idx_iop` (`iop_id`),
			KEY `idx_date` (`created_at`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1");
		return true;
	}

	/**
	 * Central NHIS audit logger. All NHIS-related events go through here.
	 */
	public function log_nhis_audit($event_type, $table_name = null, $record_id = null, $old_value = null, $new_value = null, $user_id = null, $patient_no = null, $iop_id = null){
		$this->install_nhis_audit_log();
		$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
		if ($user_id === null) {
			$ci =& get_instance();
			$user_id = $ci->session->userdata('user_id');
		}
		$this->db->insert('nhis_audit_log', array(
			'event_type' => (string)$event_type,
			'table_name' => $table_name !== null ? (string)$table_name : null,
			'record_id' => $record_id !== null ? (string)$record_id : null,
			'old_value' => $old_value !== null ? (string)$old_value : null,
			'new_value' => $new_value !== null ? (string)$new_value : null,
			'patient_no' => $patient_no !== null ? (string)$patient_no : null,
			'iop_id' => $iop_id !== null ? (string)$iop_id : null,
			'user_id' => $user_id !== null ? (string)$user_id : null,
			'ip_address' => $ip,
			'created_at' => date('Y-m-d H:i:s'),
			'InActive' => 0
		));
		return true;
	}

	public function get_nhis_audit_log($filters = array()){
		$this->install_nhis_audit_log();
		$this->db->where('InActive', 0);
		if (isset($filters['event_type']) && $filters['event_type'] !== '') {
			$this->db->where('event_type', (string)$filters['event_type']);
		}
		if (isset($filters['patient_no']) && $filters['patient_no'] !== '') {
			$this->db->where('patient_no', (string)$filters['patient_no']);
		}
		if (isset($filters['iop_id']) && $filters['iop_id'] !== '') {
			$this->db->where('iop_id', (string)$filters['iop_id']);
		}
		if (isset($filters['date_from']) && $filters['date_from'] !== '') {
			$this->db->where('DATE(created_at) >=', $filters['date_from']);
		}
		if (isset($filters['date_to']) && $filters['date_to'] !== '') {
			$this->db->where('DATE(created_at) <=', $filters['date_to']);
		}
		$this->db->order_by('created_at', 'DESC');
		$limit = isset($filters['limit']) ? (int)$filters['limit'] : 200;
		$this->db->limit($limit);
		return $this->db->get('nhis_audit_log')->result();
	}

	/* ── Day 4: Performance Indexes ──────────────────── */

	public function ensure_nhis_performance_indexes(){
		$old = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old !== null) { $this->db->db_debug = false; }

		$indexes = array(
			array('patient_details_iop', 'idx_nhis_patient', 'patient_no, date_visit'),
			array('iop_billing', 'idx_nhis_iop_payer', 'iop_id, payer_type, InActive'),
			array('iop_billing_t', 'idx_nhis_inv', 'invoice_no, InActive'),
			array('iop_medication', 'idx_nhis_med_iop', 'iop_id, InActive'),
			array('iop_laboratory', 'idx_nhis_lab_iop', 'iop_id, InActive'),
			array('medicine_drug_name', 'idx_nhis_drug_cov', 'is_nhis_covered'),
			array('bill_particular', 'idx_bp_name_active', 'particular_name, InActive')
		);
		foreach ($indexes as $idx) {
			$tbl = $idx[0]; $name = $idx[1]; $cols = $idx[2];
			if (!$this->table_exists($tbl)) { continue; }
			$chk = $this->db->query("SHOW INDEX FROM `{$tbl}` WHERE Key_name = ".$this->db->escape($name));
			if ($chk && $chk->num_rows() > 0) { continue; }
			$allExist = true;
			foreach (explode(',', $cols) as $c) {
				if (!$this->column_exists($tbl, trim($c))) { $allExist = false; break; }
			}
			if ($allExist) {
				$this->db->query("ALTER TABLE `{$tbl}` ADD KEY `{$name}` ({$cols})");
			}
		}

		if ($old !== null) { $this->db->db_debug = $old; }
		return true;
	}

	/* ── Day 4: Doctor Flexibility Tracking ──────────── */

	public function ensure_doctor_tracking_columns(){
		$tables = array('iop_billing', 'iop_billing_t');
		$old = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old !== null) { $this->db->db_debug = false; }

		foreach ($tables as $tbl) {
			if (!$this->table_exists($tbl)) { continue; }
			if (!$this->column_exists($tbl, 'created_by')) {
				$this->db->query("ALTER TABLE `{$tbl}` ADD COLUMN `created_by` varchar(25) DEFAULT NULL");
			}
			if (!$this->column_exists($tbl, 'updated_by')) {
				$this->db->query("ALTER TABLE `{$tbl}` ADD COLUMN `updated_by` varchar(25) DEFAULT NULL");
			}
		}

		if ($old !== null) { $this->db->db_debug = $old; }
		return true;
	}

	/**
	 * Run all Day-4 schema installations in one call.
	 */
	public function ensure_nhis_day4_schema(){
		$this->install_nhis_claims_table();
		$this->install_nhis_audit_log();
		$this->ensure_doctor_tracking_columns();
		$this->ensure_nhis_performance_indexes();
	}

	/* ── End Day 4 NHIS ──────────────────────────────── */

	/* ══════════════════════════════════════════════════
	   Day 5: Mock NHIS API + Reconciliation Engine
	   ══════════════════════════════════════════════════ */

	/**
	 * Ensure Day-5 schema additions (reconciliation columns on nhis_claims).
	 */
	public function ensure_nhis_day5_schema(){
		$this->load->helper('schema_guard');
		if (schema_already_run('nhis_day5_schema')) {
			return;
		}
		$this->install_nhis_claims_table();
		$cols = array(
			'approved_amount'    => "ALTER TABLE nhis_claims ADD COLUMN approved_amount DECIMAL(18,2) DEFAULT NULL",
			'recon_status'       => "ALTER TABLE nhis_claims ADD COLUMN recon_status VARCHAR(20) DEFAULT NULL",
			'recon_notes'        => "ALTER TABLE nhis_claims ADD COLUMN recon_notes TEXT DEFAULT NULL",
			'recon_at'           => "ALTER TABLE nhis_claims ADD COLUMN recon_at DATETIME DEFAULT NULL",
			'api_mode'           => "ALTER TABLE nhis_claims ADD COLUMN api_mode VARCHAR(10) NOT NULL DEFAULT 'MOCK'",
			'api_response'       => "ALTER TABLE nhis_claims ADD COLUMN api_response TEXT DEFAULT NULL",
			'api_ref'            => "ALTER TABLE nhis_claims ADD COLUMN api_ref VARCHAR(60) DEFAULT NULL"
		);
		$old = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old !== null) { $this->db->db_debug = false; }
		foreach ($cols as $field => $sql) {
			if (!$this->column_exists('nhis_claims', $field)) {
				$this->db->query($sql);
			}
		}
		if ($old !== null) { $this->db->db_debug = $old; }

		$this->install_nhis_api_config();
		mark_schema_run('nhis_day5_schema');
	}

	/**
	 * Install nhis_api_config table for MOCK/LIVE mode switching.
	 */
	public function install_nhis_api_config(){
		$this->db->query("CREATE TABLE IF NOT EXISTS `nhis_api_config` (
			`config_key` VARCHAR(50) NOT NULL,
			`config_value` TEXT DEFAULT NULL,
			`updated_at` DATETIME DEFAULT NULL,
			PRIMARY KEY (`config_key`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1");

		$defaults = array(
			'api_mode'             => 'MOCK',
			'api_base_url'         => 'https://api.nhis.gov.gh/v1',
			'api_key'              => '',
			'facility_code'        => '',
			'provider_code'        => '',
			'otac_expiry_hours'    => '72',
			'mock_approval_rate'   => '70',
			'mock_underpay_rate'   => '15',
			'mock_reject_rate'     => '15',
			'mock_delay_ms'        => '500'
		);
		foreach ($defaults as $k => $v) {
			$this->db->where('config_key', $k);
			$exists = $this->db->get('nhis_api_config')->row();
			if (!$exists) {
				$this->db->insert('nhis_api_config', array(
					'config_key' => $k, 'config_value' => $v, 'updated_at' => date('Y-m-d H:i:s')
				));
			}
		}
	}

	public function get_nhis_api_config($key = null){
		$this->install_nhis_api_config();
		if ($key !== null) {
			$this->db->where('config_key', (string)$key);
			$row = $this->db->get('nhis_api_config')->row();
			return $row ? $row->config_value : null;
		}
		$rows = $this->db->get('nhis_api_config')->result();
		$cfg = array();
		foreach ($rows as $r) { $cfg[$r->config_key] = $r->config_value; }
		return $cfg;
	}

	public function set_nhis_api_config($key, $value){
		$this->install_nhis_api_config();
		$this->db->where('config_key', (string)$key);
		$exists = $this->db->get('nhis_api_config')->row();
		if ($exists) {
			$this->db->where('config_key', (string)$key);
			$this->db->update('nhis_api_config', array(
				'config_value' => (string)$value, 'updated_at' => date('Y-m-d H:i:s')
			));
		} else {
			$this->db->insert('nhis_api_config', array(
				'config_key' => (string)$key, 'config_value' => (string)$value, 'updated_at' => date('Y-m-d H:i:s')
			));
		}
	}

	/* ── Mock NHIS API: Eligibility Check ────────────── */

	/**
	 * Mock eligibility verification. Simulates real-world NHIS API behavior.
	 * Returns: array('eligible' => bool, 'nhis_number' => string, 'member_name' => string,
	 *                'expiry_date' => string, 'status' => string, 'message' => string)
	 */
	public function nhis_api_check_eligibility($nhis_number, $patient_no = null){
		$mode = $this->get_nhis_api_config('api_mode');

		if ($mode === 'LIVE') {
			return $this->nhis_live_check_eligibility($nhis_number);
		}

		// MOCK mode
		$this->load->model('app/patient_model');
		$patient = null;
		if ($patient_no) {
			$patient = $this->patient_model->getPatientInfo($patient_no);
		}
		$nhisInfo = $patient_no ? $this->patient_model->get_nhis_info($patient_no) : null;

		$memberName = ($patient && isset($patient->firstname)) ?
			trim($patient->firstname . ' ' . (isset($patient->lastname) ? $patient->lastname : '')) : 'Unknown';
		$expiryDate = ($nhisInfo && isset($nhisInfo->nhis_expiry_date) && $nhisInfo->nhis_expiry_date) ?
			$nhisInfo->nhis_expiry_date : date('Y-12-31');
		$isExpired = (strtotime($expiryDate) < time());

		// Simulate random API failure (5% chance)
		if (mt_rand(1, 100) <= 5) {
			return array(
				'eligible' => false, 'nhis_number' => $nhis_number, 'member_name' => $memberName,
				'expiry_date' => $expiryDate, 'status' => 'API_ERROR',
				'message' => 'Mock: NHIS API temporarily unavailable. Please try again.'
			);
		}

		if (empty($nhis_number) || strlen($nhis_number) < 5) {
			return array(
				'eligible' => false, 'nhis_number' => $nhis_number, 'member_name' => $memberName,
				'expiry_date' => $expiryDate, 'status' => 'INVALID',
				'message' => 'Invalid NHIS number format.'
			);
		}

		if ($isExpired) {
			return array(
				'eligible' => false, 'nhis_number' => $nhis_number, 'member_name' => $memberName,
				'expiry_date' => $expiryDate, 'status' => 'EXPIRED',
				'message' => 'NHIS membership expired on ' . date('M d, Y', strtotime($expiryDate)) . '.'
			);
		}

		return array(
			'eligible' => true, 'nhis_number' => $nhis_number, 'member_name' => $memberName,
			'expiry_date' => $expiryDate, 'status' => 'ACTIVE',
			'message' => 'Member is eligible for NHIS benefits.'
		);
	}

	/**
	 * Placeholder for LIVE NHIS eligibility API call.
	 */
	private function nhis_live_check_eligibility($nhis_number){
		// TODO: Implement actual NHIS API call when credentials are available
		$baseUrl = $this->get_nhis_api_config('api_base_url');
		$apiKey  = $this->get_nhis_api_config('api_key');

		return array(
			'eligible' => false, 'nhis_number' => $nhis_number, 'member_name' => '',
			'expiry_date' => '', 'status' => 'NOT_CONFIGURED',
			'message' => 'LIVE NHIS API not yet configured. Set API key in NHIS Settings.'
		);
	}

	/* ── Mock NHIS API: Claim Submission ─────────────── */

	/**
	 * Submit a claim to NHIS (mock or live).
	 * Returns: array('success' => bool, 'api_ref' => string, 'status' => string,
	 *                'approved_amount' => float, 'message' => string, 'response' => array)
	 */
	public function nhis_api_submit_claim($claim_id){
		$this->ensure_nhis_day5_schema();
		$ci =& get_instance();
		try {
			if (!isset($ci->nhis_model)) {
				$ci->load->model('app/nhis_model');
			}
			if (isset($ci->nhis_model) && method_exists($ci->nhis_model, 'is_claim_quarantined')) {
				if ($ci->nhis_model->is_claim_quarantined($claim_id)) {
					log_message('error', '[NHIS_EXPORT_BLOCKED] claim_id=' . (int)$claim_id);
					return array(
						'success' => false,
						'api_ref' => '',
						'status' => 'ERROR',
						'approved_amount' => 0,
						'message' => 'Claim is quarantined.',
						'response' => array()
					);
				}
			}
		} catch (Exception $e) {
			// If model loading/check fails, do not block submission here.
		}

		$claim = $this->get_claim($claim_id);
		if (!$claim) {
			return array('success' => false, 'api_ref' => '', 'status' => 'ERROR',
				'approved_amount' => 0, 'message' => 'Claim not found.', 'response' => array());
		}

		if ($claim->status !== 'PENDING' && $claim->status !== 'SUBMITTED') {
			return array('success' => false, 'api_ref' => '', 'status' => 'ERROR',
				'approved_amount' => 0,
				'message' => 'Claim already processed (status: ' . $claim->status . ').',
				'response' => array());
		}

		$mode = $this->get_nhis_api_config('api_mode');
		$userId = null;
		$userId = $ci->session->userdata('user_id');

		if ($mode === 'LIVE') {
			$result = $this->nhis_live_submit_claim($claim);
		} else {
			$result = $this->nhis_mock_submit_claim($claim);
		}

		// Update claim with API response
		$updateData = array(
			'api_mode'    => $mode,
			'api_ref'     => isset($result['api_ref']) ? $result['api_ref'] : null,
			'api_response'=> json_encode($result['response']),
			'updated_at'  => date('Y-m-d H:i:s'),
			'updated_by'  => $userId
		);

		if ($result['success']) {
			$updateData['status'] = $result['status'];
			$updateData['submitted_at'] = date('Y-m-d H:i:s');

			if ($result['status'] === 'APPROVED') {
				$updateData['approved_amount'] = $result['approved_amount'];
				$updateData['approved_at'] = date('Y-m-d H:i:s');
			}
			if ($result['status'] === 'REJECTED') {
				$updateData['rejected_at'] = date('Y-m-d H:i:s');
				$updateData['rejection_reason'] = isset($result['message']) ? $result['message'] : 'Rejected by NHIS';
				$updateData['approved_amount'] = 0;
			}
		} else {
			$updateData['status'] = 'SUBMITTED';
			$updateData['submitted_at'] = date('Y-m-d H:i:s');
		}

		$this->db->where('claim_id', (int)$claim_id);
		$this->db->update('nhis_claims', $updateData);

		$this->log_nhis_audit('CLAIM_SUBMITTED', 'nhis_claims', $claim_id, $claim->status,
			json_encode(array('api_mode' => $mode, 'result_status' => $result['status'],
				'approved' => isset($result['approved_amount']) ? $result['approved_amount'] : 0)),
			$userId, $claim->patient_no, $claim->iop_id);

		return $result;
	}

	/**
	 * Mock claim submission with configurable outcome distribution.
	 */
	private function nhis_mock_submit_claim($claim){
		$approvalRate  = (int)$this->get_nhis_api_config('mock_approval_rate');
		$underpayRate  = (int)$this->get_nhis_api_config('mock_underpay_rate');
		// Remaining = rejection rate

		$apiRef = 'NHIS-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));
		$nhisAmount = (float)$claim->nhis_amount;
		$roll = mt_rand(1, 100);

		if ($roll <= $approvalRate) {
			// Full approval
			return array(
				'success' => true, 'api_ref' => $apiRef, 'status' => 'APPROVED',
				'approved_amount' => $nhisAmount,
				'message' => 'Claim approved for full amount.',
				'response' => array('code' => 200, 'outcome' => 'APPROVED', 'amount' => $nhisAmount, 'ref' => $apiRef)
			);
		} elseif ($roll <= ($approvalRate + $underpayRate)) {
			// Underpayment (50%-90% of claimed amount)
			$pct = mt_rand(50, 90) / 100.0;
			$approvedAmt = round($nhisAmount * $pct, 2);
			return array(
				'success' => true, 'api_ref' => $apiRef, 'status' => 'APPROVED',
				'approved_amount' => $approvedAmt,
				'message' => 'Claim partially approved. NHIS approved GHS ' . number_format($approvedAmt, 2) . ' of GHS ' . number_format($nhisAmount, 2) . '.',
				'response' => array('code' => 200, 'outcome' => 'PARTIAL', 'amount' => $approvedAmt, 'ref' => $apiRef)
			);
		} else {
			// Rejection
			$reasons = array(
				'Patient not found in NHIS registry.',
				'Duplicate claim for the same visit.',
				'Service not covered under patient plan.',
				'Claim exceeds allowable limits.',
				'NHIS membership suspended.',
				'Incomplete documentation submitted.'
			);
			$reason = $reasons[array_rand($reasons)];
			return array(
				'success' => true, 'api_ref' => $apiRef, 'status' => 'REJECTED',
				'approved_amount' => 0,
				'message' => $reason,
				'response' => array('code' => 200, 'outcome' => 'REJECTED', 'amount' => 0, 'reason' => $reason, 'ref' => $apiRef)
			);
		}
	}

	/**
	 * Placeholder for LIVE NHIS claim submission.
	 */
	private function nhis_live_submit_claim($claim){
		return array(
			'success' => false, 'api_ref' => '', 'status' => 'ERROR',
			'approved_amount' => 0,
			'message' => 'LIVE NHIS API not yet configured.',
			'response' => array('code' => 503, 'error' => 'NOT_CONFIGURED')
		);
	}

	/* ── Reconciliation Engine ───────────────────────── */

	/**
	 * Reconcile a single claim: compare nhis_amount vs approved_amount.
	 * Sets recon_status: MATCHED | UNDERPAID | REJECTED | PENDING
	 */
	public function reconcile_claim($claim_id){
		$this->ensure_nhis_day5_schema();
		$claim = $this->get_claim($claim_id);
		if (!$claim) { return false; }

		$userId = null;
		$ci =& get_instance();
		$userId = $ci->session->userdata('user_id');

		$reconStatus = 'PENDING';
		$reconNotes  = '';
		$nhisAmount  = (float)$claim->nhis_amount;
		$approved    = isset($claim->approved_amount) ? (float)$claim->approved_amount : null;

		if ($claim->status === 'REJECTED') {
			$reconStatus = 'REJECTED';
			$reconNotes  = 'Claim rejected by NHIS. Reason: ' . (isset($claim->rejection_reason) ? $claim->rejection_reason : 'N/A');
		} elseif ($claim->status === 'APPROVED' && $approved !== null) {
			$diff = abs($nhisAmount - $approved);
			$tolerance = 0.01;
			if ($diff <= $tolerance) {
				$reconStatus = 'MATCHED';
				$reconNotes  = 'Full payment: GHS ' . number_format($approved, 2) . ' matches claimed GHS ' . number_format($nhisAmount, 2) . '.';
			} else if ($approved < $nhisAmount) {
				$shortfall = $nhisAmount - $approved;
				$reconStatus = 'UNDERPAID';
				$reconNotes  = 'Underpayment of GHS ' . number_format($shortfall, 2) . '. Claimed: GHS ' . number_format($nhisAmount, 2) . ', Approved: GHS ' . number_format($approved, 2) . '.';
			} else {
				$reconStatus = 'OVERPAID';
				$reconNotes  = 'Overpayment of GHS ' . number_format($approved - $nhisAmount, 2) . '.';
			}
		} else {
			$reconStatus = 'PENDING';
			$reconNotes  = 'Claim not yet processed by NHIS.';
		}

		$this->db->where('claim_id', (int)$claim_id);
		$this->db->update('nhis_claims', array(
			'recon_status' => $reconStatus,
			'recon_notes'  => $reconNotes,
			'recon_at'     => date('Y-m-d H:i:s'),
			'updated_at'   => date('Y-m-d H:i:s'),
			'updated_by'   => $userId
		));

		$this->log_nhis_audit('CLAIM_RECONCILED', 'nhis_claims', $claim_id,
			isset($claim->recon_status) ? $claim->recon_status : null, $reconStatus,
			$userId, $claim->patient_no, $claim->iop_id);

		return $reconStatus;
	}

	/**
	 * Bulk reconcile all approved/rejected claims that haven't been reconciled yet.
	 * Returns array of results.
	 */
	public function reconcile_all_pending(){
		$this->ensure_nhis_day5_schema();
		$this->db->where('InActive', 0);
		$this->db->where_in('status', array('APPROVED', 'REJECTED'));
		$this->db->group_start();
		$this->db->where('recon_status IS NULL');
		$this->db->or_where('recon_status', '');
		$this->db->group_end();
		$claims = $this->db->get('nhis_claims')->result();

		$results = array();
		foreach ($claims as $c) {
			$results[$c->claim_id] = $this->reconcile_claim($c->claim_id);
		}
		return $results;
	}

	/* ── Dashboard Statistics ────────────────────────── */

	/**
	 * Get NHIS claims dashboard stats.
	 */
	public function get_nhis_dashboard_stats(){
		$this->ensure_nhis_day5_schema();
		$stats = array(
			'total_claims'   => 0, 'total_amount' => 0, 'total_nhis_amount' => 0,
			'pending'        => 0, 'submitted' => 0, 'approved' => 0, 'rejected' => 0,
			'total_approved_amount' => 0, 'total_shortfall' => 0,
			'matched' => 0, 'underpaid' => 0, 'recon_rejected' => 0, 'recon_pending' => 0
		);

		$this->db->select("
			COUNT(*) as total_claims,
			COALESCE(SUM(total_amount),0) as total_amount,
			COALESCE(SUM(nhis_amount),0) as total_nhis_amount,
			COALESCE(SUM(CASE WHEN status='PENDING' THEN 1 ELSE 0 END),0) as pending,
			COALESCE(SUM(CASE WHEN status='SUBMITTED' THEN 1 ELSE 0 END),0) as submitted,
			COALESCE(SUM(CASE WHEN status='APPROVED' THEN 1 ELSE 0 END),0) as approved,
			COALESCE(SUM(CASE WHEN status='REJECTED' THEN 1 ELSE 0 END),0) as rejected,
			COALESCE(SUM(CASE WHEN approved_amount IS NOT NULL THEN approved_amount ELSE 0 END),0) as total_approved_amount,
			COALESCE(SUM(CASE WHEN recon_status='MATCHED' THEN 1 ELSE 0 END),0) as matched,
			COALESCE(SUM(CASE WHEN recon_status='UNDERPAID' THEN 1 ELSE 0 END),0) as underpaid,
			COALESCE(SUM(CASE WHEN recon_status='REJECTED' THEN 1 ELSE 0 END),0) as recon_rejected
		", false);
		$this->db->where('InActive', 0);
		$row = $this->db->get('nhis_claims')->row();

		if ($row) {
			$stats['total_claims']         = (int)$row->total_claims;
			$stats['total_amount']         = (float)$row->total_amount;
			$stats['total_nhis_amount']    = (float)$row->total_nhis_amount;
			$stats['pending']              = (int)$row->pending;
			$stats['submitted']            = (int)$row->submitted;
			$stats['approved']             = (int)$row->approved;
			$stats['rejected']             = (int)$row->rejected;
			$stats['total_approved_amount']= (float)$row->total_approved_amount;
			$stats['total_shortfall']      = $stats['total_nhis_amount'] - $stats['total_approved_amount'];
			$stats['matched']              = (int)$row->matched;
			$stats['underpaid']            = (int)$row->underpaid;
			$stats['recon_rejected']       = (int)$row->recon_rejected;
			$stats['recon_pending']        = $stats['total_claims'] - $stats['matched'] - $stats['underpaid'] - $stats['recon_rejected'];
		}
		return $stats;
	}

	/**
	 * Get claims-over-time data for line chart.
	 * Returns array of arrays with keys: date, count, amount
	 */
	public function get_claims_timeline($days = 30){
		$this->ensure_nhis_day5_schema();
		$from = date('Y-m-d', strtotime("-{$days} days"));
		$this->db->select("DATE(created_at) as claim_date, COUNT(*) as claim_count, COALESCE(SUM(nhis_amount),0) as claim_amount", false);
		$this->db->where('InActive', 0);
		$this->db->where("DATE(created_at) >=", $from);
		$this->db->group_by('DATE(created_at)');
		$this->db->order_by('claim_date', 'ASC');
		return $this->db->get('nhis_claims')->result();
	}

	/**
	 * Get status distribution for pie chart.
	 */
	public function get_claims_status_distribution(){
		$this->ensure_nhis_day5_schema();
		$this->db->select("status, COUNT(*) as cnt, COALESCE(SUM(nhis_amount),0) as amt", false);
		$this->db->where('InActive', 0);
		$this->db->group_by('status');
		return $this->db->get('nhis_claims')->result();
	}

	/**
	 * Enhanced claims list with reconciliation columns + amount range filter.
	 */
	public function get_claims_list_v2($filters = array()){
		$this->ensure_nhis_day5_schema();
		$this->db->select("A.*, CONCAT_WS(' ', P.firstname, P.lastname) as patient_name", false);
		$this->db->from('nhis_claims A');
		$this->db->join('patient_personal_info P', 'P.patient_no = A.patient_no', 'left');
		$this->db->where('A.InActive', 0);

		if (isset($filters['status']) && $filters['status'] !== '') {
			$this->db->where('A.status', strtoupper(trim((string)$filters['status'])));
		}
		if (isset($filters['recon_status']) && $filters['recon_status'] !== '') {
			$this->db->where('A.recon_status', strtoupper(trim((string)$filters['recon_status'])));
		}
		if (isset($filters['date_from']) && $filters['date_from'] !== '') {
			$this->db->where('DATE(A.created_at) >=', $filters['date_from']);
		}
		if (isset($filters['date_to']) && $filters['date_to'] !== '') {
			$this->db->where('DATE(A.created_at) <=', $filters['date_to']);
		}
		if (isset($filters['patient_no']) && $filters['patient_no'] !== '') {
			$this->db->where('A.patient_no', (string)$filters['patient_no']);
		}
		if (isset($filters['amount_min']) && $filters['amount_min'] !== '') {
			$this->db->where('A.nhis_amount >=', (float)$filters['amount_min']);
		}
		if (isset($filters['amount_max']) && $filters['amount_max'] !== '') {
			$this->db->where('A.nhis_amount <=', (float)$filters['amount_max']);
		}
		if (isset($filters['search']) && trim((string)$filters['search']) !== '') {
			$s = trim((string)$filters['search']);
			$this->db->group_start();
			$this->db->like('A.claim_ref', $s);
			$this->db->or_like('A.patient_no', $s);
			$this->db->or_like('A.nhis_number', $s);
			$this->db->or_like('P.firstname', $s);
			$this->db->or_like('P.lastname', $s);
			$this->db->group_end();
		}

		$this->db->order_by('A.created_at', 'DESC');
		$limit = isset($filters['limit']) ? (int)$filters['limit'] : 200;
		$this->db->limit($limit);
		return $this->db->get()->result();
	}

	/**
	 * Get alert counts for dashboard badge.
	 */
	public function get_nhis_alert_counts(){
		$this->ensure_nhis_day5_schema();
		$counts = array('underpaid' => 0, 'rejected' => 0, 'total_alerts' => 0);

		$this->db->select("
			COALESCE(SUM(CASE WHEN recon_status='UNDERPAID' THEN 1 ELSE 0 END),0) as underpaid,
			COALESCE(SUM(CASE WHEN status='REJECTED' THEN 1 ELSE 0 END),0) as rejected
		", false);
		$this->db->where('InActive', 0);
		$row = $this->db->get('nhis_claims')->row();
		if ($row) {
			$counts['underpaid'] = (int)$row->underpaid;
			$counts['rejected'] = (int)$row->rejected;
			$counts['total_alerts'] = $counts['underpaid'] + $counts['rejected'];
		}
		return $counts;
	}

	/* ── End Day 5 NHIS ──────────────────────────────── */

	/* ══════════════════════════════════════════════════
	   BILLING ENHANCEMENTS — payment status tracking,
	   partial payments, payment history, service gate
	   ══════════════════════════════════════════════════ */

	/**
	 * Safe schema migration — adds payment_status, paid_at, paid_by, balance_due
	 * columns to iop_billing if they don't exist. Also adds payment_method and
	 * cashier_id to iop_receipt.
	 */
	public function ensure_billing_enhancements(){
		if (!$this->table_exists('iop_billing')) {
			return false;
		}
		// iop_billing columns
		if (!$this->column_exists('iop_billing', 'payment_status')) {
			$this->db->query("ALTER TABLE `iop_billing` ADD COLUMN `payment_status` varchar(20) NOT NULL DEFAULT 'UNPAID'");
		}
		if (!$this->column_exists('iop_billing', 'paid_at')) {
			$this->db->query("ALTER TABLE `iop_billing` ADD COLUMN `paid_at` datetime DEFAULT NULL");
		}
		if (!$this->column_exists('iop_billing', 'paid_by')) {
			$this->db->query("ALTER TABLE `iop_billing` ADD COLUMN `paid_by` varchar(25) DEFAULT NULL");
		}
		if (!$this->column_exists('iop_billing', 'balance_due')) {
			$this->db->query("ALTER TABLE `iop_billing` ADD COLUMN `balance_due` decimal(18,2) DEFAULT 0");
		}
		// iop_receipt columns
		if ($this->table_exists('iop_receipt')) {
			if (!$this->column_exists('iop_receipt', 'payment_method')) {
				$this->db->query("ALTER TABLE `iop_receipt` ADD COLUMN `payment_method` varchar(30) DEFAULT NULL");
			}
			if (!$this->column_exists('iop_receipt', 'cashier_id')) {
				$this->db->query("ALTER TABLE `iop_receipt` ADD COLUMN `cashier_id` varchar(25) DEFAULT NULL");
			}
			if (!$this->column_exists('iop_receipt', 'notes')) {
				$this->db->query("ALTER TABLE `iop_receipt` ADD COLUMN `notes` text DEFAULT NULL");
			}
		}
		// Backfill payment_status for invoices that already have receipts
		$this->_backfill_payment_status();
		return true;
	}

	/**
	 * One-time backfill: set payment_status on existing invoices
	 * that already have receipt_no but still show 'UNPAID'.
	 */
	private function _backfill_payment_status(){
		if (!$this->column_exists('iop_billing', 'payment_status')) {
			return;
		}
		// Mark fully paid invoices that have a receipt_no
		$this->db->query(
			"UPDATE `iop_billing` SET `payment_status` = 'PAID' ".
			"WHERE `payment_status` = 'UNPAID' AND `receipt_no` IS NOT NULL AND TRIM(`receipt_no`) != '' AND `InActive` = 0"
		);
		// Mark insurance invoices as PAID (auto-settle)
		$this->db->query(
			"UPDATE `iop_billing` SET `payment_status` = 'PAID' ".
			"WHERE `payment_status` = 'UNPAID' AND LOWER(`payment_type`) = 'insurance' AND `InActive` = 0"
		);
		// Mark NHIS invoices as PAID (auto-settle)
		if ($this->column_exists('iop_billing', 'payer_type')) {
			$this->db->query(
				"UPDATE `iop_billing` SET `payment_status` = 'PAID' ".
				"WHERE `payment_status` = 'UNPAID' AND UPPER(`payer_type`) = 'NHIS' AND `InActive` = 0"
			);
		}
	}

	/**
	 * Compute the payment status for a given invoice.
	 * Returns: PAID, PARTIAL, UNPAID
	 */
	public function compute_payment_status($invoice_no){
		$invoice_no = (string)$invoice_no;
		if ($invoice_no === '') {
			return 'UNPAID';
		}
		$settle = $this->get_invoice_settlement($invoice_no);
		if ($settle['is_settled']) {
			return 'PAID';
		}
		$eps = 0.009;
		if ($settle['paid'] > $eps && $settle['paid'] + $eps < $settle['total']) {
			return 'PARTIAL';
		}
		return 'UNPAID';
	}

	/**
	 * Recompute and persist payment_status + balance_due on iop_billing
	 * after a payment or invoice change.
	 */
	public function update_payment_status($invoice_no, $paid_by = null){
		$invoice_no = (string)$invoice_no;
		if ($invoice_no === '' || !$this->table_exists('iop_billing')) {
			return false;
		}
		$this->ensure_billing_enhancements();
		$status = $this->compute_payment_status($invoice_no);
		$settle = $this->get_invoice_settlement($invoice_no);
		$balance = max(0, $settle['total'] - $settle['paid']);
		$data = array(
			'payment_status' => $status,
			'balance_due' => round($balance, 2)
		);
		if ($status === 'PAID') {
			$data['paid_at'] = date('Y-m-d H:i:s');
			if ($paid_by !== null && trim((string)$paid_by) !== '') {
				$data['paid_by'] = (string)$paid_by;
			}
		}
		$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
		$this->db->update('iop_billing', $data);
		return $status;
	}

	public function apply_post_payment_side_effects($invoice_no, $receipt_no, $cashier_id = null, $iop_id = null, $patient_no = null){
		$invoice_no = trim((string)$invoice_no);
		$receipt_no = trim((string)$receipt_no);
		if ($invoice_no === '' || $receipt_no === '') {
			return false;
		}

		$iop_id = $iop_id !== null ? trim((string)$iop_id) : '';
		$patient_no = $patient_no !== null ? trim((string)$patient_no) : '';
		if (($iop_id === '' || $patient_no === '') && $this->table_exists('iop_billing')) {
			$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
			$this->db->limit(1);
			$hdr = $this->db->get('iop_billing')->row();
			if ($hdr) {
				if ($iop_id === '' && isset($hdr->iop_id)) {
					$iop_id = trim((string)$hdr->iop_id);
				}
				if ($patient_no === '' && isset($hdr->patient_no)) {
					$patient_no = trim((string)$hdr->patient_no);
				}
			}
		}

		$this->install_pharmacy_dispense_audit_tables();
		$this->record_pharmacy_dispense_audit_from_invoice($invoice_no, $receipt_no, $iop_id !== '' ? $iop_id : null, $patient_no !== '' ? $patient_no : null, $cashier_id);
		$newStatus = $this->update_payment_status($invoice_no, $cashier_id);
		$this->reconcile_invoice_operational_side_effects($invoice_no, $newStatus, $cashier_id);
		return $newStatus;
	}

	public function reconcile_invoice_operational_side_effects($invoice_no, $invoice_status = null, $user_id = null){
		$invoice_no = trim((string)$invoice_no);
		if ($invoice_no === '' || !$this->table_exists('iop_billing')) {
			return false;
		}
		$invoice_status = $invoice_status !== null ? strtoupper(trim((string)$invoice_status)) : null;
		if ($invoice_status === null || $invoice_status === '') {
			$invoice_status = $this->update_payment_status($invoice_no, $user_id);
			if (!$invoice_status) {
				return false;
			}
			$invoice_status = strtoupper(trim((string)$invoice_status));
		}

		$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
		$this->db->limit(1);
		$hdr = $this->db->get('iop_billing')->row();
		$payer = $hdr && isset($hdr->payer_type) ? strtoupper(trim((string)$hdr->payer_type)) : 'CASH';
		$iop_id = $hdr && isset($hdr->iop_id) ? trim((string)$hdr->iop_id) : '';
		$covered = ($payer !== '' && $payer !== 'CASH');

		if (method_exists($this, 'reconcile_pharmacy_queue_for_invoice')) {
			$this->reconcile_pharmacy_queue_for_invoice($invoice_no, $invoice_status, $user_id);
		}
		if (method_exists($this, 'reconcile_billable_item_locks_for_invoice')) {
			$this->reconcile_billable_item_locks_for_invoice($invoice_no, $invoice_status);
		} elseif ($invoice_status === 'PAID') {
			$this->mark_invoice_billable_items_paid($invoice_no);
		}

		$CI =& get_instance();
		$isCleared = ($invoice_status === 'PAID' || $invoice_status === 'WAIVED' || $covered);
		$allowService = ($invoice_status === 'PAID' || $invoice_status === 'PARTIAL' || $invoice_status === 'WAIVED' || $covered);
		if ($isCleared) {
			if ($iop_id !== '') {
				try {
					if (!isset($CI->laboratory_model)) {
						$CI->load->model('app/laboratory_model');
					}
					if (isset($CI->laboratory_model) && method_exists($CI->laboratory_model, 'mark_lab_bills_paid_by_iop')) {
						$CI->laboratory_model->mark_lab_bills_paid_by_iop((string)$iop_id, (string)$invoice_no);
					}
				} catch (Exception $e) {
					// ignore
				}
			}
		}
		if ($allowService) {
			try {
				if (!isset($CI->unified_billing_model)) {
					$CI->load->model('app/unified_billing_model');
				}
				if (isset($CI->unified_billing_model) && method_exists($CI->unified_billing_model, 'auto_release_gates_for_invoice')) {
					$CI->unified_billing_model->auto_release_gates_for_invoice($invoice_no);
				}
			} catch (Exception $e) {
				// ignore
			}
		} else {
			if ($this->table_exists('iop_lab_billing') && $this->column_exists('iop_lab_billing', 'payment_status')) {
				$this->db->where(array('invoice_no' => $invoice_no, 'payment_status' => 'PAID', 'InActive' => 0));
				$upd = array('payment_status' => 'PENDING');
				if ($this->column_exists('iop_lab_billing', 'updated_at')) {
					$upd['updated_at'] = date('Y-m-d H:i:s');
				}
				$this->db->update('iop_lab_billing', $upd);
				if ($this->table_exists('iop_laboratory_workflow') && $this->column_exists('iop_laboratory_workflow', 'status')) {
					$this->db->query(
						"UPDATE iop_laboratory_workflow w\n" .
						"JOIN iop_lab_billing b ON b.io_lab_id = w.io_lab_id AND b.InActive = 0\n" .
						"SET w.status = 'BILLED', w.updated_at = ?, w.updated_by = ?\n" .
						"WHERE b.invoice_no = ? AND b.InActive = 0 AND w.InActive = 0 AND w.status = 'PAID'",
						array(date('Y-m-d H:i:s'), $user_id !== null ? (string)$user_id : 'system', $invoice_no)
					);
				}
			}
			if ($this->table_exists('billing_queue') && $this->column_exists('billing_queue', 'service_gate_status')) {
				$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
				$this->db->where('service_gate_status', 'RELEASED');
				$this->db->update('billing_queue', array(
					'service_gate_status' => 'BLOCKED',
					'released_at' => null,
					'released_by' => null,
				));
			}
		}

		return $invoice_status;
	}

	/**
	 * Record a partial or full payment for an invoice.
	 * Returns array with 'ok', 'status', 'message'.
	 */
	public function record_payment($invoice_no, $amount_paid, $payment_method = 'cash', $receipt_no = null, $cashier_id = null, $notes = null, $iop_id = null, $patient_no = null){
		$invoice_no = (string)$invoice_no;
		$amount_paid = (float)$amount_paid;
		if ($invoice_no === '' || $amount_paid <= 0) {
			return array('ok' => false, 'status' => '', 'message' => 'Invalid invoice or amount.');
		}
		// Phase 4 / Step 4 — optional facade route. Default OFF.
		$this->load->model('app/Billing_facade_model', 'billing_facade_model');
		if ($this->billing_facade_model->is_receipt_route_enabled()) {
			$res = $this->billing_facade_model->record_payment(array(
				'invoice_no'     => $invoice_no,
				'amount'         => $amount_paid,
				'payment_method' => $payment_method,
				'receipt_no'     => $receipt_no,
				'cashier_id'     => $cashier_id,
				'notes'          => $notes,
				'source'         => 'BILLING',
			));
			if (!empty($res['ok'])) {
				$this->apply_post_payment_side_effects($invoice_no, $res['receipt_no'], $cashier_id, $iop_id, $patient_no);
				return array(
					'ok'         => true,
					'status'     => $res['payment_status'],
					'message'    => 'Payment recorded.',
					'receipt_no' => $res['receipt_no'],
					'balance'    => $res['balance_after'],
				);
			}
			return array('ok' => false, 'status' => '', 'message' => $res['error']);
		}
		// Check invoice exists
		$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
		$this->db->limit(1);
		$hdr = $this->db->get('iop_billing')->row();
		if (!$hdr) {
			return array('ok' => false, 'status' => '', 'message' => 'Invoice not found.');
		}
		// Check not already fully paid
		$currentStatus = $this->compute_payment_status($invoice_no);
		if ($currentStatus === 'PAID') {
			return array('ok' => false, 'status' => 'PAID', 'message' => 'Invoice is already fully paid.');
		}
		// Determine remaining balance
		$settle = $this->get_invoice_settlement($invoice_no);
		$remaining = round(max(0, $settle['total'] - $settle['paid']), 2);
		$amount_paid = round($amount_paid, 2);
		$actualPaid = min($amount_paid, $remaining);
		$change = max(0, $amount_paid - $remaining);
		// Auto-generate receipt_no if not provided
		if ($receipt_no === null || trim((string)$receipt_no) === '') {
			$rn = $this->receipt_no();
			$rnVal = isset($rn->receipt_no) ? (int)$rn->receipt_no : 1;
			$receipt_no = 'OR-' . str_pad($rnVal, 6, '0', STR_PAD_LEFT);
			// Update receipt series
			$this->db->query("UPDATE system_option SET cValue = '".(int)$rnVal."' WHERE cCode = 'receipt_no'");
		}
		// Determine iop_id and patient_no from invoice if not provided
		if (($iop_id === null || trim((string)$iop_id) === '') && isset($hdr->iop_id)) {
			$iop_id = (string)$hdr->iop_id;
		}
		if (($patient_no === null || trim((string)$patient_no) === '') && isset($hdr->patient_no)) {
			$patient_no = (string)$hdr->patient_no;
		}
		// Insert receipt
		$receiptData = array(
			'receipt_no'		=> $receipt_no,
			'invoice_no'		=> $invoice_no,
			'dDate'				=> date("Y-m-d H:i:s"),
			'iop_id'			=> $iop_id,
			'patient_no'		=> $patient_no,
			'payment_type'		=> $payment_method,
			'total_amount'		=> $settle['total'],
			'change'			=> round(max(0, $change), 2),
			'amountPaid'		=> round($actualPaid, 2),
			'total_purchased'	=> isset($hdr->total_purchased) ? $hdr->total_purchased : 0,
			'discount'			=> isset($hdr->discount) ? $hdr->discount : 0,
			'subtotal'			=> isset($hdr->sub_total) ? $hdr->sub_total : 0,
			'creditCardNo'		=> '',
			'creditCardHolder'	=> '',
			'insurance_company'	=> '',
			'remarks'			=> '',
			'InActive'			=> 0
		);
		if ($this->column_exists('iop_receipt', 'payment_method')) {
			$receiptData['payment_method'] = $payment_method;
		}
		if ($this->column_exists('iop_receipt', 'cashier_id') && $cashier_id !== null) {
			$receiptData['cashier_id'] = (string)$cashier_id;
		}
		if ($this->column_exists('iop_receipt', 'notes') && $notes !== null) {
			$receiptData['notes'] = (string)$notes;
		}
		$this->db->insert('iop_receipt', $receiptData);
		
		// Best-effort sync: reflect receipt payment into billing_transactions SSOT
		$ssot_sync = null;
		if (method_exists($this, 'sync_receipt_to_unified')) {
			$ssot_sync = $this->sync_receipt_to_unified($receipt_no, $cashier_id);
		}
		
		// Also log to cashier_payment_log for unified audit trail
		if ($this->table_exists('cashier_payment_log')) {
			$this->db->insert('cashier_payment_log', array(
				'receipt_no' => $receipt_no,
				'invoice_no' => $invoice_no,
				'patient_no' => $patient_no,
				'amount' => $actualPaid,
				'payment_method' => strtoupper($payment_method),
				'cashier_id' => $cashier_id,
				'payment_date' => date('Y-m-d H:i:s'),
				'notes' => $notes
			));
		}

		// Record to financial ledger via unified billing model if available
		$CI =& get_instance();
		if (isset($CI->unified_billing_model) && method_exists($CI->unified_billing_model, 'record_payment_to_ledger')) {
			$CI->unified_billing_model->record_payment_to_ledger(
				$receipt_no, $invoice_no, $patient_no, $actualPaid, strtoupper($payment_method), $cashier_id
			);
		}
		
		// Update iop_billing receipt_no (set to latest receipt)
		$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
		$this->db->update('iop_billing', array('receipt_no' => $receipt_no));
		// Mark billable items paid if fully paid now
		$newStatus = $this->update_payment_status($invoice_no, $cashier_id);
		// Reconcile pharmacy billing queue against invoice lines after any payment
		$this->reconcile_pharmacy_queue_for_invoice($invoice_no, $newStatus, $cashier_id);
		if ($newStatus === 'PAID') {
			$this->mark_invoice_billable_items_paid($invoice_no);
			// Pharmacy dispense audit
			if ($this->table_exists('iop_pharmacy_dispense_audit')) {
				$this->install_pharmacy_dispense_audit_tables();
				$this->record_pharmacy_dispense_audit_from_invoice($invoice_no, $receipt_no, $iop_id, $patient_no, $cashier_id);
			}
			// Auto-complete OPD visit when all invoices for this visit are fully paid
			$this->_auto_complete_visit_if_all_paid($iop_id, $cashier_id);
		}
		return array(
			'ok' => true,
			'status' => $newStatus,
			'message' => $newStatus === 'PAID' ? 'Invoice fully paid.' : 'Partial payment recorded. Balance remaining.',
			'receipt_no' => $receipt_no,
			'ssot_sync' => $ssot_sync,
			'change' => round(max(0, $change), 2),
			'balance' => round(max(0, $settle['total'] - $settle['paid'] - $actualPaid), 2)
		);
	}

	public function reconcile_pharmacy_queue_for_invoice($invoice_no, $invoice_status = null, $user_id = null)
	{
		$invoice_no = trim((string)$invoice_no);
		if ($invoice_no === '' || !$this->table_exists('pharmacy_billing_queue') || !$this->table_exists('iop_billing_t')) {
			return false;
		}
		$lines = array();
		if ($this->table_exists('billing_transactions')) {
			$this->db->select('item_ref, quantity, unit_price, net_amount, payment_status');
			$this->db->from('billing_transactions');
			$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0, 'department' => 'PHARMACY'));
			$txns = $this->db->get()->result();
			if (!empty($txns)) {
				foreach ($txns as $t) {
					$ref = isset($t->item_ref) ? (string)$t->item_ref : '';
					if (strpos($ref, 'iop_med_id:') !== 0) continue;
					$mid = (int)substr($ref, strlen('iop_med_id:'));
					if ($mid <= 0) continue;
					$st = isset($t->payment_status) ? strtoupper(trim((string)$t->payment_status)) : '';
					if ($st === '') { $st = null; }
					$lines[] = (object)array(
						'iop_med_id' => $mid,
						'qty' => isset($t->quantity) ? (float)$t->quantity : 0.0,
						'rate' => isset($t->unit_price) ? (float)$t->unit_price : 0.0,
						'amount' => isset($t->net_amount) ? (float)$t->net_amount : 0.0,
						'payment_status' => $st,
					);
				}
			}
		}

		$seenMedIds = array();
		foreach ($lines as $ln0) {
			if (isset($ln0->iop_med_id)) {
				$mid0 = (int)$ln0->iop_med_id;
				if ($mid0 > 0) {
					$seenMedIds[$mid0] = true;
				}
			}
		}
		$extractMedId = function ($srcRef) {
			$srcRef = trim((string)$srcRef);
			if ($srcRef === '') return 0;
			$matches = array();
			if (preg_match_all('/(?:iop_medication_id|iop_medication|iop_med_id)\s*:\s*(\d+)/i', $srcRef, $matches) && !empty($matches[1])) {
				return (int)$matches[1][count($matches[1]) - 1];
			}
			return 0;
		};
		if (empty($lines)) {
			try {
				$this->install_billing_meta_tables();
			} catch (Throwable $e) {
				
			}
			if (!$this->table_exists('iop_billing_line_meta') && !$this->table_exists('iop_billable_item_lock')) {
				return false;
			}
		}
		$hasDispenseStatus = $this->column_exists('pharmacy_billing_queue', 'dispense_status');
		$hasPaidBy = $this->column_exists('pharmacy_billing_queue', 'paid_by');
		$hasPaidAt = $this->column_exists('pharmacy_billing_queue', 'paid_at');
		$hasMedicationPaymentStatus = $this->table_exists('iop_medication') && $this->column_exists('iop_medication', 'payment_status');

		$invoice_status = $invoice_status !== null ? strtoupper(trim((string)$invoice_status)) : null;
		$payStatus = 'PENDING';
		if ($invoice_status === 'PAID') {
			$payStatus = 'PAID';
		} elseif ($invoice_status === 'PARTIAL') {
			$payStatus = 'PARTIAL';
		}

		if ($this->table_exists('iop_billable_item_lock')) {
			$this->db->select('L.source_ref, T.qty, T.rate, T.amount');
			$this->db->from('iop_billable_item_lock L');
			$this->db->join('iop_billing_t T', 'T.invoice_no = L.invoice_no AND T.id = L.detail_id AND T.InActive = 0', 'left', false);
			$this->db->where(array('L.invoice_no' => $invoice_no, 'L.InActive' => 0, 'L.source_module' => 'PHARMACY'));
			$this->db->where("(L.source_ref LIKE '%iop_medication:%' OR L.source_ref LIKE '%iop_med_id:%')", null, false);
			$lockRows = $this->db->get()->result();
			if (!empty($lockRows)) {
				foreach ($lockRows as $lr) {
					$midL = $extractMedId(isset($lr->source_ref) ? $lr->source_ref : '');
					if ($midL > 0 && empty($seenMedIds[$midL])) {
						$lines[] = $lr;
						$seenMedIds[$midL] = true;
					}
				}
			}
		}


		if ($this->table_exists('iop_billing_line_meta')) {
			try {
				if (method_exists($this, 'backfill_invoice_line_meta')) {
					$this->backfill_invoice_line_meta($invoice_no, $user_id);
				}
			} catch (Throwable $e) {
				
			}

			$this->db->select('M.source_ref, T.qty, T.rate, T.amount');
			$this->db->from('iop_billing_t T');
			$this->db->join('iop_billing_line_meta M', "M.invoice_no = T.invoice_no AND M.detail_id = T.id AND M.InActive = 0 AND M.source_module = 'PHARMACY'", 'inner');
			$this->db->where('T.invoice_no', $invoice_no);
			$this->db->where('T.InActive', 0);
			$metaRows = $this->db->get()->result();
			if (!empty($metaRows)) {
				foreach ($metaRows as $mr) {
					$midM = $extractMedId(isset($mr->source_ref) ? $mr->source_ref : '');
					if ($midM > 0 && empty($seenMedIds[$midM])) {
						$lines[] = $mr;
						$seenMedIds[$midM] = true;
					}
				}
			}
		}

		if (empty($lines)) {
			// Fallback for legacy invoices without meta: map by bill_name <-> drug_name
			if ($this->table_exists('iop_billing') && $this->table_exists('pharmacy_billing_queue')) {
				$this->db->select('T.bill_name, T.qty, T.rate, T.amount, PBQ.iop_med_id');
				$this->db->from('iop_billing_t T');
				$this->db->join('iop_billing H', 'H.invoice_no = T.invoice_no AND H.InActive = 0', 'inner');
				$this->db->join('pharmacy_billing_queue PBQ', "PBQ.InActive = 0 AND PBQ.iop_id = H.iop_id AND PBQ.patient_no = H.patient_no AND LOWER(CONVERT(TRIM(PBQ.drug_name) USING utf8mb4)) = LOWER(CONVERT(TRIM(T.bill_name) USING utf8mb4))", 'inner', false);
				$this->db->where('T.invoice_no', $invoice_no);
				$this->db->where('T.InActive', 0);
				$lines = $this->db->get()->result();
			}
			if (empty($lines)) {
				return true;
			}
		}


		$now = date('Y-m-d H:i:s');
		$updatedRows = 0;
		foreach ($lines as $ln) {
			$iop_med_id = 0;
			if (isset($ln->iop_med_id)) {
				$iop_med_id = (int)$ln->iop_med_id;
			} else {
				$srcRef = isset($ln->source_ref) ? (string)$ln->source_ref : '';
				$srcRef = trim((string)$srcRef);
				if ($srcRef === '') continue;
				$matches = array();
				if (preg_match_all('/(?:iop_medication_id|iop_medication|iop_med_id)\s*:\s*(\d+)/i', $srcRef, $matches) && !empty($matches[1])) {
					$iop_med_id = (int)$matches[1][count($matches[1]) - 1];
				} else {
					continue;
				}
			}
			if ($iop_med_id <= 0) continue;

			$linePayStatus = $payStatus;
			if (isset($ln->payment_status) && trim((string)$ln->payment_status) !== '') {
				$linePayStatus = strtoupper(trim((string)$ln->payment_status));
			}
			$qty = isset($ln->qty) ? (float)$ln->qty : 0;
			$rate = isset($ln->rate) ? (float)$ln->rate : 0;
			$amount = isset($ln->amount) ? (float)$ln->amount : 0;

			$this->db->where(array('iop_med_id' => $iop_med_id, 'InActive' => 0));
			$upd = array(
				'unit_price'     => round($rate, 2),
				'total_amount'   => round($amount, 2),
				'total'          => round($amount, 2),
				'quantity'       => $qty,
				'payment_status' => $linePayStatus,
				'updated_at'     => $now,
			);
			$this->db->set($upd);
			if ($linePayStatus === 'PAID') {
				if ($hasDispenseStatus) { $this->db->set('dispense_status', 'READY'); }
				if ($hasPaidBy) { $this->db->set('paid_by', $user_id !== null ? (string)$user_id : null); }
				if ($hasPaidAt) { $this->db->set('paid_at', $now); }
			} else {
				if ($hasPaidBy) {
					$this->db->set('paid_by', "IF(dispense_status='READY',NULL,paid_by)", false);
				}
				if ($hasPaidAt) {
					$this->db->set('paid_at', "IF(dispense_status='READY',NULL,paid_at)", false);
				}
				if ($hasDispenseStatus) {
					$this->db->set('dispense_status', "IF(dispense_status='READY','WAITING',dispense_status)", false);
				}
			}
			$this->db->update('pharmacy_billing_queue');
			$updatedRows += max(0, (int)$this->db->affected_rows());
			if ($hasMedicationPaymentStatus) {
				$this->db->where(array('iop_med_id' => $iop_med_id, 'InActive' => 0));
				$this->db->update('iop_medication', array('payment_status' => $linePayStatus));
			}
		}
		return true;
	}

	/**
	 * Auto-complete an OPD visit when all invoices for that visit are fully paid.
	 * Called after a successful payment — if no outstanding balance remains on
	 * any invoice tied to this iop_id, the workflow is set to COMPLETED.
	 */
	private function _auto_complete_visit_if_all_paid($iop_id, $cashier_id = null){
		$iop_id = trim((string)$iop_id);
		if ($iop_id === '') return;
		if (!$this->table_exists('iop_billing') || !$this->table_exists('iop_opd_workflow')) return;

		// Only auto-complete visits that are in a clearable state
		$CI =& get_instance();
		if (!isset($CI->opd_status_engine)) {
			$CI->load->model('app/opd_status_engine');
		}
		$currentWf = $CI->opd_status_engine->get_status($iop_id);
		$clearable = array('CLINICALLY_CLEARED', 'PENDING_LAB', 'PENDING_PHARMACY', 'LAB_PENDING', 'PHARMACY_PENDING', 'BILLING_PENDING');
		if ($currentWf === null || !in_array($currentWf, $clearable, true)) {
			return; // Not in a state that should auto-complete
		}

		// Check if ALL invoices for this visit are fully paid
		$invoices = $this->db->get_where('iop_billing', array('iop_id' => $iop_id, 'InActive' => 0))->result();
		if (empty($invoices)) return;

		foreach ($invoices as $inv) {
			$status = $this->compute_payment_status($inv->invoice_no);
			if ($status !== 'PAID') {
				return; // At least one invoice still unpaid — do not auto-complete
			}
		}

		// All invoices paid — auto-complete the visit
		$result = $CI->opd_status_engine->transition($iop_id, 'COMPLETED', $cashier_id, 'All OPD invoices paid', 'billing::auto_complete');
		if (empty($result['success'])) {
			log_message('warning', 'OPD auto-complete blocked for ' . $iop_id . ': ' . (isset($result['message']) ? $result['message'] : 'Status transition denied'));
			return;
		}

		// Also set legacy nStatus to match
		$this->db->where(array('IO_ID' => $iop_id, 'InActive' => 0));
		$this->db->update('patient_details_iop', array('nStatus' => 'Completed'));
	}

	/**
	 * Get all payment receipts for an invoice, ordered by date.
	 */
	public function get_payment_history($invoice_no){
		$invoice_no = (string)$invoice_no;
		if ($invoice_no === '' || !$this->table_exists('iop_receipt')) {
			return array();
		}
		$select = 'R.receipt_no, R.dDate, R.amountPaid, R.payment_type, R.change';
		if ($this->column_exists('iop_receipt', 'payment_method')) {
			$select .= ', R.payment_method';
		}
		if ($this->column_exists('iop_receipt', 'cashier_id')) {
			$select .= ', R.cashier_id';
		}
		if ($this->column_exists('iop_receipt', 'notes')) {
			$select .= ', R.notes';
		}
		$this->db->select($select, false);
		$this->db->from('iop_receipt R');
		$this->db->where(array('R.invoice_no' => $invoice_no, 'R.InActive' => 0));
		$this->db->order_by('R.dDate', 'ASC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Billing dashboard summary stats.
	 * Returns counts and totals for today's activity.
	 */
	public function get_billing_summary($date_from = null, $date_to = null){
		if ($date_from === null) { $date_from = date('Y-m-d'); }
		if ($date_to === null) { $date_to = date('Y-m-d'); }
		$summary = array(
			'total_invoices' => 0,
			'total_amount' => 0.0,
			'paid_count' => 0,
			'paid_amount' => 0.0,
			'partial_count' => 0,
			'partial_amount' => 0.0,
			'unpaid_count' => 0,
			'unpaid_amount' => 0.0,
			'total_received' => 0.0
		);
		if (!$this->table_exists('iop_billing')) {
			return $summary;
		}
		$hasStatus = $this->column_exists('iop_billing', 'payment_status');
		// Total invoices
		$this->db->select("COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total_amt", false);
		$this->db->where("dDate BETWEEN ".$this->db->escape($date_from)." AND ".$this->db->escape($date_to.' 23:59:59'), null, false);
		$this->db->where('InActive', 0);
		$q = $this->db->get('iop_billing');
		$r = $q ? $q->row() : null;
		if ($r) {
			$summary['total_invoices'] = (int)$r->cnt;
			$summary['total_amount'] = (float)$r->total_amt;
		}
		if ($hasStatus) {
			// Paid
			$this->db->select("COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS amt", false);
			$this->db->where("dDate BETWEEN ".$this->db->escape($date_from)." AND ".$this->db->escape($date_to.' 23:59:59'), null, false);
			$this->db->where(array('InActive' => 0, 'payment_status' => 'PAID'));
			$q2 = $this->db->get('iop_billing');
			$r2 = $q2 ? $q2->row() : null;
			if ($r2) {
				$summary['paid_count'] = (int)$r2->cnt;
				$summary['paid_amount'] = (float)$r2->amt;
			}
			// Partial
			$this->db->select("COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS amt", false);
			$this->db->where("dDate BETWEEN ".$this->db->escape($date_from)." AND ".$this->db->escape($date_to.' 23:59:59'), null, false);
			$this->db->where(array('InActive' => 0, 'payment_status' => 'PARTIAL'));
			$q3 = $this->db->get('iop_billing');
			$r3 = $q3 ? $q3->row() : null;
			if ($r3) {
				$summary['partial_count'] = (int)$r3->cnt;
				$summary['partial_amount'] = (float)$r3->amt;
			}
			// Unpaid
			$this->db->select("COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS amt", false);
			$this->db->where("dDate BETWEEN ".$this->db->escape($date_from)." AND ".$this->db->escape($date_to.' 23:59:59'), null, false);
			$this->db->where('InActive', 0);
			$this->db->where_in('payment_status', array('UNPAID', 'PENDING'));
			$q4 = $this->db->get('iop_billing');
			$r4 = $q4 ? $q4->row() : null;
			if ($r4) {
				$summary['unpaid_count'] = (int)$r4->cnt;
				$summary['unpaid_amount'] = (float)$r4->amt;
			}
		}
		// Total received today
		if ($this->table_exists('iop_receipt')) {
			$this->db->select("COALESCE(SUM(amountPaid),0) AS received", false);
			$this->db->where("dDate BETWEEN ".$this->db->escape($date_from)." AND ".$this->db->escape($date_to.' 23:59:59'), null, false);
			$this->db->where('InActive', 0);
			$q5 = $this->db->get('iop_receipt');
			$r5 = $q5 ? $q5->row() : null;
			if ($r5) {
				$summary['total_received'] = (float)$r5->received;
			}
		}
		return $summary;
	}

	/**
	 * Get list of outstanding (unpaid/partial) invoices.
	 */
	public function get_outstanding_invoices($limit = 50, $offset = 0){
		if (!$this->table_exists('iop_billing')) {
			return array();
		}
		$this->ensure_billing_enhancements();
		$this->db->select("
			A.invoice_no, A.iop_id, A.patient_no, A.dDate, A.total_amount,
			A.payment_status, A.balance_due, A.payment_type,
			CONCAT(B.firstname,' ',B.lastname) AS patient
		", false);
		$this->db->from('iop_billing A');
		$this->db->join('patient_personal_info B', 'B.patient_no = A.patient_no', 'left');
		$this->db->where('A.InActive', 0);
		$this->db->where_in('A.payment_status', array('UNPAID', 'PENDING', 'PARTIAL'));
		$this->db->order_by('A.dDate', 'DESC');
		$q = $this->db->get('', $limit, $offset);
		return $q ? $q->result() : array();
	}

	/**
	 * Count outstanding invoices.
	 */
	public function count_outstanding_invoices(){
		if (!$this->table_exists('iop_billing') || !$this->column_exists('iop_billing', 'payment_status')) {
			return 0;
		}
		$this->db->select("COUNT(*) AS cnt", false);
		$this->db->where('InActive', 0);
		$this->db->where_in('payment_status', array('UNPAID', 'PENDING', 'PARTIAL'));
		$q = $this->db->get('iop_billing');
		$r = $q ? $q->row() : null;
		return ($r && isset($r->cnt)) ? (int)$r->cnt : 0;
	}

	/**
	 * Unified service payment gate enforcement.
	 * Checks if a patient's IOP has outstanding invoices that block services.
	 * Returns: array('allowed' => bool, 'reason' => string)
	 *
	 * Rules:
	 *  - NHIS patients: auto-allowed (NHIS covers services)
	 *  - Insurance patients: auto-allowed
	 *  - Cash patients: must have no unpaid invoices for this IOP
	 */
	public function enforce_service_payment_gate($iop_id, $patient_no = null, $service_type = 'GENERAL'){
		$iop_id = (string)$iop_id;
		$result = array('allowed' => true, 'reason' => '');
		if ($iop_id === '') {
			return $result;
		}
		// Determine payer type
		$payer = 'CASH';
		if ($patient_no !== null && trim((string)$patient_no) !== '') {
			$payer = $this->determine_payer_type((string)$patient_no);
		}
		// NHIS and Insurance patients: auto-allowed
		if ($payer === 'NHIS') {
			return $result;
		}
		// Check for existing unpaid invoices on this IOP
		if (!$this->table_exists('iop_billing')) {
			return $result;
		}
		$this->ensure_billing_enhancements();
		$this->db->select("COUNT(*) AS cnt, COALESCE(SUM(balance_due),0) AS total_bal", false);
		$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
		$this->db->where_in('payment_status', array('UNPAID', 'PENDING', 'PARTIAL'));
		$q = $this->db->get('iop_billing');
		$r = $q ? $q->row() : null;
		$unpaidCount = ($r && isset($r->cnt)) ? (int)$r->cnt : 0;
		$totalBal = ($r && isset($r->total_bal)) ? (float)$r->total_bal : 0.0;
		if ($unpaidCount > 0) {
			$result['allowed'] = false;
			$result['reason'] = 'Payment required. '.$unpaidCount.' outstanding invoice(s) with balance of '.number_format($totalBal, 2).'. Please settle before proceeding with '.$service_type.' services.';
		}
		return $result;
	}

	public function check_patient_outstanding_balance_for_registration($patient_no, $max_invoices = 5)
	{
		$patient_no = trim((string)$patient_no);
		$result = array(
			'blocked' => false,
			'count' => 0,
			'balance' => 0.0,
			'invoices' => array(),
		);
		if ($patient_no === '' || !$this->table_exists('iop_billing')) {
			return $result;
		}
		$this->ensure_billing_enhancements();
		if (!$this->column_exists('iop_billing', 'payer_type')) {
			return $result;
		}
		$eps = 0.009;

		$statuses = array('PENDING', 'UNPAID', 'PARTIAL');
		$this->db->select('COUNT(*) AS cnt, COALESCE(SUM(balance_due),0) AS total_bal', false);
		$this->db->where(array('patient_no' => $patient_no, 'InActive' => 0));
		$this->db->where('payer_type', 'CASH');
		if ($this->column_exists('iop_billing', 'payment_status')) {
			$this->db->where_in('payment_status', $statuses);
		}
		if ($this->column_exists('iop_billing', 'balance_due')) {
			$this->db->where('balance_due >', $eps);
		}
		$row = $this->db->get('iop_billing')->row();
		$result['count'] = ($row && isset($row->cnt)) ? (int)$row->cnt : 0;
		$result['balance'] = ($row && isset($row->total_bal)) ? (float)$row->total_bal : 0.0;
		$result['blocked'] = ($result['count'] > 0 && $result['balance'] > $eps);

		if ($result['blocked']) {
			$this->db->select('invoice_no, iop_id, balance_due, dDate');
			$this->db->where(array('patient_no' => $patient_no, 'InActive' => 0));
			$this->db->where('payer_type', 'CASH');
			if ($this->column_exists('iop_billing', 'payment_status')) {
				$this->db->where_in('payment_status', $statuses);
			}
			if ($this->column_exists('iop_billing', 'balance_due')) {
				$this->db->where('balance_due >', $eps);
			}
			$this->db->order_by('dDate', 'DESC');
			$this->db->limit((int)$max_invoices);
			$rows = $this->db->get('iop_billing')->result();
			foreach ($rows as $r) {
				$result['invoices'][] = array(
					'invoice_no' => isset($r->invoice_no) ? (string)$r->invoice_no : '',
					'iop_id' => isset($r->iop_id) ? (string)$r->iop_id : '',
					'balance_due' => isset($r->balance_due) ? (float)$r->balance_due : 0.0,
					'date' => isset($r->dDate) ? (string)$r->dDate : '',
				);
			}
		}

		return $result;
	}

	/**
	 * Quick check: is an invoice fully paid?
	 */
	public function is_invoice_paid($invoice_no){
		$status = $this->compute_payment_status($invoice_no);
		return ($status === 'PAID');
	}

	/**
	 * Get enhanced invoice header with payment details.
	 */
	public function get_invoice_with_payments($invoice_no){
		$invoice_no = (string)$invoice_no;
		if ($invoice_no === '' || !$this->table_exists('iop_billing')) {
			return null;
		}
		$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
		$this->db->limit(1);
		$hdr = $this->db->get('iop_billing')->row();
		if (!$hdr) {
			return null;
		}
		$settle = $this->get_invoice_settlement($invoice_no);
		$hdr->_paid_amount = $settle['paid'];
		$hdr->_balance_due = max(0, $settle['total'] - $settle['paid']);
		$hdr->_payment_status = $this->compute_payment_status($invoice_no);
		$hdr->_payments = $this->get_payment_history($invoice_no);
		return $hdr;
	}

	/* ── End Billing Enhancements ──────────────────── */

	/* ================================================================== */
	/*  FLEXIBLE WORKFLOW — CASHIER DASHBOARD SUPPORT METHODS             */
	/* ================================================================== */

	public function get_outstanding_balances_all($filters = array()){
		if (!$this->table_exists('outstanding_balances')) return array();
		$this->db->select('O.*, P.firstname, P.lastname');
		$this->db->from('outstanding_balances O');
		$this->db->join('patient_personal_info P', 'P.patient_no = O.patient_no', 'left');
		$this->db->where(array('O.InActive' => 0));
		if (!empty($filters['status'])) {
			$this->db->where('O.status', $filters['status']);
		} else {
			$this->db->where('O.status', 'OUTSTANDING');
		}
		if (!empty($filters['patient_no'])) $this->db->where('O.patient_no', $filters['patient_no']);
		if (!empty($filters['source_module'])) $this->db->where('O.source_module', $filters['source_module']);
		if (!empty($filters['date_from'])) $this->db->where('DATE(O.created_at) >=', $filters['date_from']);
		if (!empty($filters['date_to']))   $this->db->where('DATE(O.created_at) <=', $filters['date_to']);
		$this->db->order_by('O.created_at', 'DESC');
		return $this->db->get()->result();
	}

	public function count_outstanding_balances(){
		if (!$this->table_exists('outstanding_balances')) return 0;
		return $this->db->where(array('status' => 'OUTSTANDING', 'InActive' => 0))->count_all_results('outstanding_balances');
	}

	public function get_pending_waiver_requests(){
		if (!$this->table_exists('waiver_requests')) return array();
		$this->db->select('W.*, P.firstname, P.lastname');
		$this->db->from('waiver_requests W');
		$this->db->join('patient_personal_info P', 'P.patient_no = W.patient_no', 'left');
		$this->db->where(array('W.status' => 'PENDING', 'W.InActive' => 0));
		$this->db->order_by('W.requested_at', 'DESC');
		return $this->db->get()->result();
	}

	public function count_pending_waiver_requests(){
		if (!$this->table_exists('waiver_requests')) return 0;
		return $this->db->where(array('status' => 'PENDING', 'InActive' => 0))->count_all_results('waiver_requests');
	}

	public function approve_waiver_admin($waiver_id, $admin_id, $approval_notes = ''){
		if (!$this->table_exists('waiver_requests')) return array('ok' => false, 'error' => 'Waiver system not initialised.');
		$waiver_id = (int)$waiver_id;
		$wr = $this->db->get_where('waiver_requests', array('id' => $waiver_id, 'InActive' => 0))->row();
		if (!$wr) return array('ok' => false, 'error' => 'Waiver request not found.');

		$now = date('Y-m-d H:i:s');
		$this->db->where('id', $waiver_id);
		$this->db->update('waiver_requests', array(
			'status'         => 'APPROVED',
			'approved_by'    => $admin_id,
			'approved_at'    => $now,
			'approval_notes' => $approval_notes,
		));

		$srcId = (int)$wr->source_id;
		if ($wr->source_module === 'PHARMACY' && $this->table_exists('iop_medication')) {
			$this->db->where('iop_med_id', $srcId);
			$updData = array('payment_status' => 'WAIVED');
			if ($this->column_exists('iop_medication', 'extended_status')) {
				$updData['extended_status'] = 'WAIVED';
			}
			$this->db->update('iop_medication', $updData);
			if ($this->table_exists('pharmacy_billing_queue')) {
				$this->db->where('iop_med_id', $srcId);
				$qUpd = array('payment_status' => 'WAIVED', 'waiver_approved_by' => $admin_id, 'waiver_approved_at' => $now, 'updated_at' => $now);
				if ($this->column_exists('pharmacy_billing_queue', 'extended_status')) $qUpd['extended_status'] = 'WAIVED';
				$this->db->update('pharmacy_billing_queue', $qUpd);
			}
		} elseif ($wr->source_module === 'LABORATORY' && $this->table_exists('iop_laboratory')) {
			if ($this->column_exists('iop_laboratory', 'extended_status')) {
				$this->db->where('io_lab_id', $srcId)->update('iop_laboratory', array('extended_status' => 'WAIVED', 'waiver_flag' => 1));
			}
		}
		return array('ok' => true, 'error' => '');
	}

	public function reject_waiver_admin($waiver_id, $admin_id, $notes = ''){
		if (!$this->table_exists('waiver_requests')) return array('ok' => false, 'error' => 'Waiver system not initialised.');
		$waiver_id = (int)$waiver_id;
		$this->db->where('id', $waiver_id);
		$this->db->update('waiver_requests', array(
			'status'         => 'REJECTED',
			'approved_by'    => $admin_id,
			'approved_at'    => date('Y-m-d H:i:s'),
			'approval_notes' => $notes,
		));
		return array('ok' => true, 'error' => '');
	}

	public function settle_outstanding_balance_admin($id, $admin_id){
		if (!$this->table_exists('outstanding_balances')) return false;
		$this->db->where('id', (int)$id);
		$this->db->update('outstanding_balances', array(
			'status'     => 'SETTLED',
			'settled_by' => $admin_id,
			'settled_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		));
		return true;
	}

	public function get_financial_audit_log($filters = array(), $limit = 50){
		if (!$this->table_exists('financial_audit_log')) return array();
		$this->db->select('*');
		$this->db->from('financial_audit_log');
		if (!empty($filters['patient_no'])) $this->db->where('patient_no', $filters['patient_no']);
		if (!empty($filters['source_module'])) $this->db->where('source_module', $filters['source_module']);
		if (!empty($filters['event_type'])) $this->db->where('event_type', $filters['event_type']);
		if (!empty($filters['date_from'])) $this->db->where('DATE(performed_at) >=', $filters['date_from']);
		if (!empty($filters['date_to']))   $this->db->where('DATE(performed_at) <=', $filters['date_to']);
		$this->db->order_by('performed_at', 'DESC');
		$this->db->limit((int)$limit);
		return $this->db->get()->result();
	}

	public function get_cashier_flexible_summary(){
		$s = array(
			'deferred_pharmacy'    => 0,
			'deferred_lab'         => 0,
			'outstanding_total'    => 0,
			'pending_waivers'      => 0,
			'external_purchases'   => 0,
			'emergency_overrides'  => 0,
		);
		if ($this->table_exists('pharmacy_billing_queue') && $this->column_exists('pharmacy_billing_queue', 'extended_status')) {
			$this->db->where("extended_status IN ('DEFERRED','UNABLE_TO_PAY')", null, false)->where('InActive', 0);
			$s['deferred_pharmacy'] = $this->db->count_all_results('pharmacy_billing_queue');
			$this->db->where("extended_status = 'EXTERNAL_PURCHASE'", null, false)->where('InActive', 0);
			$s['external_purchases'] = $this->db->count_all_results('pharmacy_billing_queue');
		}
		if ($this->table_exists('iop_laboratory') && $this->column_exists('iop_laboratory', 'deferred_flag')) {
			$this->db->where(array('deferred_flag' => 1, 'InActive' => 0));
			$s['deferred_lab'] = $this->db->count_all_results('iop_laboratory');
		}
		$s['outstanding_total'] = $this->count_outstanding_balances();
		$s['pending_waivers']   = $this->count_pending_waiver_requests();
		if ($this->table_exists('emergency_overrides')) {
			$this->db->where("DATE(override_at) = '".date('Y-m-d')."'", null, false)->where('InActive', 0);
			$s['emergency_overrides'] = $this->db->count_all_results('emergency_overrides');
		}
		return $s;
	}

	/* ================================================================== */

	public function getAll($limit = 10, $offset = 0){
		$this->db->select("
					A.invoice_no,
					A.receipt_no,
					A.iop_id,
					A.patient_no,
					A.dDate,
					A.total_amount,
					A.total_purchased,
					concat(B.firstname,' ',B.lastname) as patient
					",false);
		$search = $this->db->escape_like_str($this->input->post('search'));
		$where = "(
				A.invoice_no LIKE '%{$search}%' ESCAPE '!' OR 
				A.iop_id LIKE '%{$search}%' ESCAPE '!' OR 
				A.patient_no LIKE '%{$search}%' ESCAPE '!' OR 
				B.firstname LIKE '%{$search}%' ESCAPE '!' OR
				B.lastname LIKE '%{$search}%' ESCAPE '!'
				) AND 
				A.dDate BETWEEN ".$this->db->escape($this->input->post('cFrom'))." AND ".$this->db->escape($this->input->post('cTo'))." 
				AND A.InActive = 0";
		$this->db->where($where);
		$this->db->order_by('A.invoice_no','asc');
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$query = $this->db->get("iop_billing A", $limit, $offset);
		return $query->result();
	}

/* ── NHIS Private Helpers ────────────────────────── */

private function _nhis_cfg($key, $default = null){
$val = $this->get_nhis_api_config($key);
return ($val !== null) ? $val : $default;
}

private function _nhis_api_call($endpoint, $payload, $api_type, $nhis_number = null, $claim_id = null){
$mode = strtoupper((string)$this->_nhis_cfg('api_mode', 'MOCK'));
$req  = json_encode($payload);
if ($mode === 'MOCK'){
$resp = $this->_nhis_mock($api_type, $payload);
} else {
$base = rtrim((string)$this->_nhis_cfg('api_base_url', 'https://api.nhis.gov.gh/v1'), '/');
$url  = $base . '/' . ltrim($endpoint, '/');
$ch   = curl_init($url);
curl_setopt_array($ch, array(
CURLOPT_RETURNTRANSFER => true,
CURLOPT_POST           => true,
CURLOPT_POSTFIELDS     => $req,
CURLOPT_HTTPHEADER     => array('Content-Type: application/json','Authorization: Bearer '.$this->_nhis_cfg('api_key',''),'Accept: application/json'),
CURLOPT_TIMEOUT        => 30,
CURLOPT_SSL_VERIFYPEER => false,
));
$raw  = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);
if ($cerr || !$raw){ $resp = array('success' => false, 'error' => $cerr ?: 'Empty response'); }
else { $resp = json_decode($raw, true); if (!is_array($resp)){ $resp = array('success' => false, 'raw' => (string)$raw); } }
}
$uid = (isset($this->session) && is_object($this->session)) ? $this->session->userdata('user_id') : null;
$this->log_nhis_audit('API_CALL_'.$api_type, 'nhis_api_config', $claim_id, $req, json_encode($resp), $uid, null, null);
return $resp;
}

private function _nhis_mock($api_type, $payload){
$approvalRate = (int)$this->_nhis_cfg('mock_approval_rate', 70);
$rejectRate   = (int)$this->_nhis_cfg('mock_reject_rate', 10);
switch ($api_type){
case 'VERIFY':
$nhis = isset($payload['nhis_number']) ? strtoupper(trim((string)$payload['nhis_number'])) : '';
if (strlen($nhis) < 5){ return array('success' => false, 'error' => 'NHIS_NOT_FOUND', 'message' => 'NHIS card not found.'); }
if (substr($nhis, -1) === 'X'){ return array('success' => false, 'error' => 'CARD_EXPIRED', 'message' => 'NHIS card has expired. Renew or pay as cash.', 'expiry_date' => date('Y-m-d', strtotime('-30 days'))); }
return array('success' => true, 'nhis_number' => $nhis, 'name' => 'NHIS Verified Member', 'status' => 'ACTIVE', 'expiry_date' => date('Y-m-d', strtotime('+18 months')), 'scheme' => 'NHIS');
case 'OTAC':
return array('success' => true, 'otac' => strtoupper(substr(sha1(json_encode($payload).time()), 0, 8)), 'expiry' => date('Y-m-d H:i:s', strtotime('+72 hours')));
case 'SUBMIT':
$rand = rand(1, 100);
if ($rand <= $rejectRate){ return array('success' => true, 'status' => 'REJECTED', 'claim_ref_no' => 'CLM-'.strtoupper(uniqid()), 'message' => 'Claim rejected: Invalid OTAC.', 'rejection_reason' => 'INVALID_OTAC'); }
if ($rand <= ($rejectRate + (100 - $approvalRate - $rejectRate))){ return array('success' => true, 'status' => 'PROCESSING', 'claim_ref_no' => 'CLM-'.strtoupper(uniqid()), 'message' => 'Claim being processed.'); }
return array('success' => true, 'status' => 'APPROVED', 'claim_ref_no' => 'CLM-'.strtoupper(uniqid()), 'message' => 'Claim approved.', 'approved_amount' => isset($payload['claimed_amount']) ? (float)$payload['claimed_amount'] * 0.9 : 0);
default:
return array('success' => true, 'message' => 'OK');
}
}

/* ── NHIS OTAC Generation ─────────────────────────── */

public function generate_otac_for_visit($iop_id, $patient_no, $nhis_number = null, $created_by = null){
$this->ensure_nhis_day5_schema();
$iop_id = (string)$iop_id; $patient_no = (string)$patient_no;
if (empty($nhis_number) && $this->column_exists('patient_personal_info', 'nhis_number')){
$r = $this->db->select('nhis_number')->get_where('patient_personal_info', array('patient_no' => $patient_no, 'InActive' => 0))->row();
if ($r){ $nhis_number = $r->nhis_number; }
}
if (empty($nhis_number)) return null;
if ($this->table_exists('nhis_otac')){
$ex = $this->db->get_where('nhis_otac', array('iop_id' => $iop_id, 'otac_status' => 'ACTIVE', 'InActive' => 0))->row();
if ($ex) return $ex->otac_code;
}
$hours = (int)$this->_nhis_cfg('otac_expiry_hours', 72);
$resp  = $this->_nhis_api_call('otac/generate', array('nhis_number' => $nhis_number, 'iop_id' => $iop_id, 'facility_code' => $this->_nhis_cfg('facility_code', '')), 'OTAC', $nhis_number);
$code  = !empty($resp['otac']) ? strtoupper(trim($resp['otac'])) : strtoupper(substr(sha1($nhis_number.$iop_id.time()), 0, 8));
$exp   = !empty($resp['expiry']) ? $resp['expiry'] : date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
if ($this->table_exists('nhis_otac')){
$this->db->insert('nhis_otac', array('iop_id' => $iop_id, 'patient_no' => $patient_no, 'nhis_number' => $nhis_number, 'otac_code' => $code, 'otac_status' => 'ACTIVE', 'generated_at' => date('Y-m-d H:i:s'), 'expiry_at' => $exp, 'generated_by' => $created_by, 'InActive' => 0));
}
if ($this->column_exists('patient_details_iop', 'otac_code')){
$dbg = isset($this->db->db_debug) ? $this->db->db_debug : null;
if ($dbg !== null){ $this->db->db_debug = false; }
$this->db->where('IO_ID', $iop_id)->update('patient_details_iop', array('otac_code' => $code, 'otac_status' => 'ACTIVE'));
if ($dbg !== null){ $this->db->db_debug = $dbg; }
}
$this->log_nhis_audit('OTAC_GENERATED', 'nhis_otac', null, null, json_encode(array('otac' => $code, 'iop_id' => $iop_id)), $created_by, $patient_no, $iop_id);
return $code;
}

/* ── NHIS Claim Creation + XML ───────────────────── */

public function create_nhis_claim($iop_id, $patient_no, $created_by = null){
$CI = get_instance();
$router_class = isset($CI->router->class) ? $CI->router->class : null;
$router_method = isset($CI->router->method) ? $CI->router->method : null;
$uri = function_exists('uri_string') ? uri_string() : null;
log_message('error', 'NHIS_CLAIM_CALL_TRACE: ' . json_encode(array('method' => __METHOD__, 'file' => __FILE__, 'uri' => $uri, 'router_class' => $router_class, 'router_method' => $router_method, 'patient_no' => isset($patient_no) ? $patient_no : null, 'iop_id' => isset($iop_id) ? $iop_id : null)));
$this->ensure_nhis_day5_schema();
$iop_id = (string)$iop_id; $patient_no = (string)$patient_no;
if (!$this->table_exists('nhis_claims')) return null;
$ex = $this->db->where_in('status', array('PENDING','SUBMITTED','PROCESSING','APPROVED'))->where('iop_id', $iop_id)->where('InActive', 0)->get('nhis_claims')->row();
if ($ex) return (int)$ex->claim_id;
$pinfo = $this->column_exists('patient_personal_info', 'nhis_number') ? $this->db->select('nhis_number,nhis_status,nhis_expiry_date,nhis_scheme')->get_where('patient_personal_info', array('patient_no' => $patient_no, 'InActive' => 0))->row() : null;
$nhis_num = ($pinfo && isset($pinfo->nhis_number)) ? (string)$pinfo->nhis_number : null;
$otac_code = null;
if ($this->table_exists('nhis_otac')){ $ot = $this->db->get_where('nhis_otac', array('iop_id' => $iop_id, 'otac_status' => 'ACTIVE', 'InActive' => 0))->row(); if ($ot){ $otac_code = $ot->otac_code; } }
$lines = $this->_build_claim_lines($iop_id, $patient_no);
$claimed = 0; $copay = 0;
foreach ($lines as $l){ $claimed += (float)$l['unit_cost'] * (float)$l['quantity']; $copay += (float)$l['patient_copay'] * (float)$l['quantity']; }
$now = date('Y-m-d H:i:s');
$ref = 'CLM-'.str_pad(rand(10000, 99999), 8, '0', STR_PAD_LEFT);
$claimNumber = $this->generate_nhis_claim_number();
$trace = array('method' => __METHOD__, 'file' => __FILE__, 'uri' => function_exists('uri_string') ? uri_string() : null, 'patient_no' => isset($patient_no) ? $patient_no : null, 'iop_id' => isset($iop_id) ? $iop_id : null, 'claim_number' => $claimNumber);
log_message('error', 'NHIS_CLAIM_INSERT_TRACE: ' . json_encode($trace));
$this->db->insert('nhis_claims', array('claim_number' => $claimNumber, 'iop_id' => $iop_id, 'patient_no' => $patient_no, 'nhis_number' => $nhis_num, 'otac_code' => $otac_code, 'facility_code' => $this->_nhis_cfg('facility_code', ''), 'provider_code' => $this->_nhis_cfg('provider_code', ''), 'claim_ref_no' => $ref, 'status' => 'PENDING', 'claimed_amount' => round($claimed, 2), 'patient_copay' => round($copay, 2), 'created_by' => $created_by, 'created_at' => $now, 'updated_at' => $now, 'InActive' => 0));
$claim_id = (int)$this->db->insert_id();
if ($this->table_exists('nhis_claim_lines')){ foreach ($lines as $l){ $this->db->insert('nhis_claim_lines', array_merge($l, array('claim_id' => $claim_id, 'iop_id' => $iop_id, 'InActive' => 0))); } }
if ($this->column_exists('patient_details_iop', 'nhis_claim_id')){
$dbg = isset($this->db->db_debug) ? $this->db->db_debug : null;
if ($dbg !== null){ $this->db->db_debug = false; }
$this->db->where('IO_ID', $iop_id)->update('patient_details_iop', array('nhis_claim_id' => $claim_id));
if ($dbg !== null){ $this->db->db_debug = $dbg; }
}
$this->log_nhis_audit('CLAIM_CREATED', 'nhis_claims', $claim_id, null, json_encode(array('iop_id' => $iop_id, 'claimed_amount' => $claimed)), $created_by, $patient_no, $iop_id);
return $claim_id;
}

private function _build_claim_lines($iop_id, $patient_no){
$lines = array();
$lines[] = array('service_type' => 'CONSULTATION', 'service_code' => 'CONS-OPD', 'service_name' => 'OPD Consultation', 'quantity' => 1, 'unit_cost' => 18.00, 'nhis_tariff' => 18.00, 'patient_copay' => 0.00, 'nhis_covered' => 1, 'reference_id' => $iop_id);
if ($this->table_exists('iop_laboratory')){ $labs = $this->db->select("L.io_lab_id, L.laboratory_text, BP.particular_name, BP.charge_amount")->join('bill_particular BP', 'BP.particular_id = L.particular_id', 'left')->where(array('L.iop_id' => $iop_id, 'L.InActive' => 0))->get('iop_laboratory L')->result(); foreach ($labs as $lab){ $name = !empty($lab->particular_name) ? $lab->particular_name : $lab->laboratory_text; $amt = (float)($lab->charge_amount ?: 12.00); $lines[] = array('service_type' => 'LAB', 'service_code' => 'LAB-TEST', 'service_name' => $name ?: 'Lab Test', 'quantity' => 1, 'unit_cost' => $amt, 'nhis_tariff' => $amt, 'patient_copay' => 0.00, 'nhis_covered' => 1, 'reference_id' => (string)$lab->io_lab_id); } }
if ($this->table_exists('iop_medication')){ $meds = $this->db->select("M.iop_med_id, D.nDrugName, D.nPrice, M.nQty")->join('medicine_drug_name D', 'D.drug_id = M.medicine_id', 'left')->where(array('M.iop_id' => $iop_id, 'M.InActive' => 0))->get('iop_medication M')->result(); foreach ($meds as $m){ $lines[] = array('service_type' => 'MEDICATION', 'service_code' => 'MED', 'service_name' => $m->nDrugName ?: 'Medication', 'quantity' => (int)($m->nQty ?: 1), 'unit_cost' => (float)($m->nPrice ?: 0), 'nhis_tariff' => (float)($m->nPrice ?: 0), 'patient_copay' => 0.00, 'nhis_covered' => 1, 'reference_id' => (string)$m->iop_med_id); } }
return $lines;
}

public function generate_claim_xml($claim_id){
$claim_id = (int)$claim_id;
if (!$this->table_exists('nhis_claims')) return false;
$claim = $this->db->get_where('nhis_claims', array('claim_id' => $claim_id, 'InActive' => 0))->row();
if (!$claim) return false;
$lines = $this->table_exists('nhis_claim_lines') ? $this->db->get_where('nhis_claim_lines', array('claim_id' => $claim_id, 'InActive' => 0))->result() : array();
$pinfo = $this->db->select('firstname,lastname,birthday,nhis_scheme')->get_where('patient_personal_info', array('patient_no' => $claim->patient_no, 'InActive' => 0))->row();
$diag_code = 'Z00.0';
if ($this->table_exists('iop_diagnosis')){ $d = $this->db->where('iop_id', $claim->iop_id)->where('InActive', 0)->get('iop_diagnosis')->row(); if ($d && !empty($d->diagnosis_text)){ $diag_code = htmlspecialchars($d->diagnosis_text); } }
$pat_name = $pinfo ? htmlspecialchars(trim($pinfo->firstname.' '.$pinfo->lastname)) : 'Unknown';
$scheme   = ($pinfo && !empty($pinfo->nhis_scheme)) ? htmlspecialchars($pinfo->nhis_scheme) : 'NHIS';
$ref      = $claim->claim_ref_no ?: ('CLM-'.str_pad($claim_id, 8, '0', STR_PAD_LEFT));
$xml  = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
$xml .= '<NHIAClaim xmlns="http://www.nhia.gov.gh/claims/v1">'."\n";
$xml .= '  <Header><FacilityCode>'.htmlspecialchars((string)$claim->facility_code).'</FacilityCode><ProviderCode>'.htmlspecialchars((string)$claim->provider_code).'</ProviderCode><ClaimRef>'.$ref.'</ClaimRef><VisitDate>'.substr($claim->created_at, 0, 10).'</VisitDate></Header>'."\n";
$xml .= '  <Patient><NHISNumber>'.htmlspecialchars((string)$claim->nhis_number).'</NHISNumber><OTAC>'.htmlspecialchars((string)$claim->otac_code).'</OTAC><FullName>'.$pat_name.'</FullName><DateOfBirth>'.($pinfo ? $pinfo->birthday : '').'</DateOfBirth><Scheme>'.$scheme.'</Scheme></Patient>'."\n";
$xml .= '  <Diagnosis><PrimaryDiagnosis code="'.$diag_code.'">'.$diag_code.'</PrimaryDiagnosis></Diagnosis>'."\n";
$xml .= '  <Services>'."\n";
foreach ($lines as $ln){ $xml .= '    <Service type="'.htmlspecialchars($ln->service_type).'"><Code>'.htmlspecialchars((string)$ln->service_code).'</Code><Name>'.htmlspecialchars($ln->service_name).'</Name><Quantity>'.(float)$ln->quantity.'</Quantity><UnitCost>'.number_format((float)$ln->unit_cost, 2, '.', '').'</UnitCost><NHISTariff>'.number_format((float)$ln->nhis_tariff, 2, '.', '').'</NHISTariff><PatientCopay>'.number_format((float)$ln->patient_copay, 2, '.', '').'</PatientCopay><NHISCovered>'.($ln->nhis_covered ? 'YES' : 'NO').'</NHISCovered></Service>'."\n"; }
$xml .= '  </Services>'."\n";
$xml .= '  <Totals><ClaimedAmount>'.number_format((float)$claim->claimed_amount, 2, '.', '').'</ClaimedAmount><PatientCopay>'.number_format((float)$claim->patient_copay, 2, '.', '').'</PatientCopay></Totals>'."\n";
$xml .= '</NHIAClaim>'."\n";
$dbg = isset($this->db->db_debug) ? $this->db->db_debug : null;
if ($dbg !== null){ $this->db->db_debug = false; }
$this->db->where('claim_id', $claim_id)->update('nhis_claims', array('xml_payload' => $xml, 'claim_ref_no' => $ref, 'status' => 'READY', 'updated_at' => date('Y-m-d H:i:s')));
if ($dbg !== null){ $this->db->db_debug = $dbg; }
return $xml;
}

// ==================== UNIFIED BILLING TRANSACTION INTEGRATION ====================

/**
 * Initialize unified billing transaction schema.
 * Call this in controller constructors to ensure tables exist.
 */
public function ensure_unified_billing_schema(){
	$this->load->model('app/billing_transaction_model');
	return $this->billing_transaction_model->ensure_billing_transaction_schema();
}

/**
 * Get payment status from unified billing_transactions (SINGLE SOURCE OF TRUTH).
 */
public function get_unified_encounter_status($iop_id){
	$this->load->model('app/billing_transaction_model');
	$summary = $this->billing_transaction_model->get_encounter_summary($iop_id);
	
	$result = array(
		'total_amount'   => 0,
		'paid_amount'    => 0,
		'balance_amount' => 0,
		'item_count'     => 0,
		'paid_count'     => 0,
		'pending_count'  => 0,
		'payment_status' => 'PENDING',
		'departments'    => array()
	);
	
	if (empty($summary)) {
		return $result;
	}
	
	foreach ($summary as $dept) {
		$result['total_amount'] += (float)$dept->total_amount;
		$result['paid_amount'] += (float)$dept->paid_amount;
		$result['balance_amount'] += (float)$dept->balance_amount;
		$result['item_count'] += (int)$dept->item_count;
		$result['paid_count'] += (int)$dept->paid_count;
		$result['pending_count'] += (int)$dept->pending_count;
		$result['departments'][$dept->department] = $dept;
	}
	
	// Determine overall payment status
	if ($result['item_count'] > 0) {
		if ($result['paid_count'] >= $result['item_count']) {
			$result['payment_status'] = 'PAID';
		} elseif ($result['paid_count'] > 0) {
			$result['payment_status'] = 'PARTIAL';
		}
	}
	
	return $result;
}

/**
 * Sync receipt payment to unified billing_transactions.
 */
public function sync_receipt_to_unified($receipt_no, $user_id = null){
	$this->load->model('app/billing_transaction_model');
	return $this->billing_transaction_model->sync_receipt_payment($receipt_no, $user_id);
}

/**
 * Get patient financial ledger from unified system.
 */
public function get_patient_financial_ledger($patient_no, $limit = 100){
	$this->load->model('app/billing_transaction_model');
	return $this->billing_transaction_model->get_patient_ledger($patient_no, $limit);
}

/**
 * Get patient current balance from unified system.
 */
public function get_patient_balance($patient_no){
	$this->load->model('app/billing_transaction_model');
	return $this->billing_transaction_model->get_patient_balance($patient_no);
}

/**
 * Run reconciliation for all departments.
 */
public function run_billing_reconciliation($date = null){
	$this->load->model('app/billing_transaction_model');
	return $this->billing_transaction_model->run_reconciliation(null, $date);
}

/**
 * Get reconciliation issues.
 */
public function get_reconciliation_issues($filters = array()){
	$this->load->model('app/billing_transaction_model');
	return $this->billing_transaction_model->get_reconciliation_issues($filters);
}

/**
 * Get department daily summary from unified system.
 */
public function get_unified_department_summary($department, $date = null){
	$this->load->model('app/billing_transaction_model');
	return $this->billing_transaction_model->get_department_daily_summary($department, $date);
}

/**
 * Trigger NHIS claim generation for an encounter after billing is saved.
 * Called when billing is finalized for NHIS patients.
 */
public function trigger_nhis_claim_generation($encounter_id, $patient_no = null, $user_id = null) {
	// Check if patient is NHIS
	$payer_type = $this->determine_payer_type($patient_no);
	if ($payer_type !== 'NHIS') {
		return array('success' => false, 'message' => 'Patient is not NHIS');
	}

	// Load NHIS model and generate claim
	$this->load->model('app/nhis_model');
	return $this->nhis_model->generate_claim($encounter_id, $user_id);
}

/**
 * Check if NHIS claim exists for an encounter.
 */
public function has_nhis_claim($encounter_id) {
	if (!$this->table_exists('nhis_claims')) {
		return false;
	}
	return $this->db->where('encounter_id', $encounter_id)
		->where('claim_status !=', 'cancelled')
		->count_all_results('nhis_claims') > 0;
}

/**
 * Get NHIS claim for an encounter.
 */
public function get_nhis_claim_for_encounter($encounter_id) {
	if (!$this->table_exists('nhis_claims')) {
		return null;
	}
	return $this->db->where('encounter_id', $encounter_id)
		->where('claim_status !=', 'cancelled')
		->order_by('created_at', 'DESC')
		->get('nhis_claims')
		->row();
}

/* ================================================================== */
/*  UNIFIED SERVICE BILLING INTEGRATION                                */
/* ================================================================== */

/**
 * Ensure corporate billing schema exists (company_id on patient)
 */
public function ensure_corporate_billing_schema() {
	// Add company_id to patient_personal_info if not exists
	if ($this->table_exists('patient_personal_info') && !$this->column_exists('patient_personal_info', 'company_id')) {
		$this->db->query("ALTER TABLE `patient_personal_info` ADD COLUMN `company_id` INT DEFAULT NULL COMMENT 'FK to corporate_companies'");
	}
	
	// Load and initialize service billing model
	$this->load->model('app/service_billing_model');
	$this->service_billing_model->ensure_service_billing_schema();
}

/**
 * Create service order for a lab/sonography/procedure request
 * Integrates with the unified billing engine and adds to billing queue
 */
public function create_service_order_for_request($visit_id, $patient_no, $service_type, $service_id, $service_name, $base_price = 0, $requested_by = null, $encounter_type = 'OPD', $reference_table = null, $reference_id = null) {
	$this->load->model('app/service_billing_model');
	$this->service_billing_model->ensure_service_billing_schema();
	
	$data = array(
		'visit_id' => (string)$visit_id,
		'patient_no' => (string)$patient_no,
		'encounter_type' => $encounter_type,
		'service_type' => $service_type,
		'service_id' => $service_id ? (int)$service_id : null,
		'service_name' => $service_name,
		'base_price' => (float)$base_price,
		'requested_by' => $requested_by,
		'reference_table' => $reference_table,
		'reference_id' => $reference_id ? (int)$reference_id : null
	);
	
	// Set department based on service type
	switch (strtoupper($service_type)) {
		case 'LAB':
		case 'LABORATORY':
			$data['department'] = 'LABORATORY';
			break;
		case 'SONOGRAPHY':
		case 'RADIOLOGY':
			$data['department'] = 'RADIOLOGY';
			break;
		case 'PROCEDURE':
			$data['department'] = 'PROCEDURES';
			break;
		case 'MEDICATION':
			$data['department'] = 'PHARMACY';
			break;
		default:
			$data['department'] = 'GENERAL';
	}
	
	// Create service order
	$result = $this->service_billing_model->create_service_order($data);
	
	// Also add to unified billing queue for service gate enforcement
	$this->load->model('app/unified_billing_model');
	$item_type = strtoupper($service_type);
	if ($item_type === 'LABORATORY') $item_type = 'LAB';
	
	$queue_data = array(
		'iop_id' => (string)$visit_id,
		'patient_no' => (string)$patient_no,
		'item_type' => $item_type,
		'item_id' => $reference_id ? (string)$reference_id : ($service_id ? (string)$service_id : uniqid()),
		'item_name' => $service_name,
		'unit_price' => (float)$base_price,
		'quantity' => 1,
		'payer_type' => 'CASH', // Will be updated based on patient's payer
		'source_module' => $item_type,
		'source_ref' => $reference_id ? (string)$reference_id : ($service_id ? (string)$service_id : uniqid()),
		'requested_by' => $requested_by
	);
	
	// Check patient's NHIS status for payer_type
	$patient = $this->db->select('nhis_number, nhis_expiry_date, nhis_card_expiry, nhis_status')->get_where('patient_personal_info', array('patient_no' => (string)$patient_no))->row();
	if ($patient && !empty($patient->nhis_number)) {
		$valid = true;
		$expiry = !empty($patient->nhis_card_expiry) ? $patient->nhis_card_expiry : (!empty($patient->nhis_expiry_date) ? $patient->nhis_expiry_date : null);
		if ($expiry) {
			$valid = strtotime($expiry) >= strtotime(date('Y-m-d'));
		}
		if ($valid) {
			$queue_data['payer_type'] = 'NHIS';
		}
	}
	
	$this->unified_billing_model->add_to_billing_queue($queue_data);
	
	return $result;
}

/**
 * Check payment gate for a service using unified billing
 */
public function check_service_payment_gate($service_order_id) {
	$this->load->model('app/service_billing_model');
	return $this->service_billing_model->check_service_payment_gate($service_order_id);
}

/**
 * Get pending service orders for cashier queue
 */
public function get_pending_service_queue($limit = 100) {
	$this->load->model('app/service_billing_model');
	$this->service_billing_model->ensure_service_billing_schema();
	return $this->service_billing_model->get_pending_service_orders($limit);
}

/**
 * Mark service order as paid
 */
public function mark_service_order_paid($service_order_id, $invoice_no = null, $receipt_no = null, $user_id = null) {
	$this->load->model('app/service_billing_model');
	
	if (!$this->service_billing_model->table_exists('service_orders')) {
		return false;
	}
	
	$update = array(
		'payment_status' => 'PAID',
		'billing_status' => 'INVOICED',
		'service_status' => 'PAID',
		'updated_at' => date('Y-m-d H:i:s')
	);
	if ($invoice_no) $update['invoice_no'] = $invoice_no;
	if ($receipt_no) $update['receipt_no'] = $receipt_no;
	
	$this->db->where('id', (int)$service_order_id);
	$this->db->update('service_orders', $update);
	
	$this->service_billing_model->log_billing_audit('PAYMENT_RECEIVED', $service_order_id, $invoice_no, null,
		null, json_encode($update), null, null, 'Payment received', $user_id ?: 'system');
	
	return true;
}

/**
 * Get billing summary by coverage type for reporting
 */
public function get_coverage_billing_summary($date_from = null, $date_to = null) {
	$this->load->model('app/service_billing_model');
	$this->service_billing_model->ensure_service_billing_schema();
	return $this->service_billing_model->get_billing_summary($date_from, $date_to);
}

/**
 * Get total billing amount for today
 */
public function get_total_billing_today()
{
	$today = date('Y-m-d');
	$this->db->select('COALESCE(SUM(total_amount), 0) as total');
	$this->db->where('DATE(dDate)', $today);
	$this->db->where('InActive', 0);
	$result = $this->db->get('iop_billing')->row();
	return $result ? (float)$result->total : 0;
}

/**
 * Count pending payments
 */
public function count_pending_payments()
{
	$this->db->where('InActive', 0);
	$this->db->where("(payment_type IS NULL OR payment_type = '' OR LOWER(payment_type) NOT IN ('paid', 'insurance'))", null, false);
	return $this->db->count_all_results('iop_billing');
}

/**
 * Count NHIS claims today
 */
public function count_nhis_claims_today()
{
	if (!$this->table_exists('nhis_claims')) return 0;
	$today = date('Y-m-d');
	$this->db->where('DATE(created_at)', $today);
	$this->db->where('InActive', 0);
	return $this->db->count_all_results('nhis_claims');
}

/**
 * Count pending refunds
 */
public function count_pending_refunds()
{
	if (!$this->table_exists('billing_refunds')) return 0;
	$this->db->where('status', 'pending');
	$this->db->where('InActive', 0);
	return $this->db->count_all_results('billing_refunds');
}

/**
 * Get revenue by payment type for a date
 */
public function get_revenue_by_payment_type($date = null)
{
	$date = $date ?: date('Y-m-d');
	$result = [
		'cash' => ['amount' => 0, 'count' => 0],
		'nhis' => ['amount' => 0, 'count' => 0],
		'insurance' => ['amount' => 0, 'count' => 0]
	];
	
	// Cash payments
	$this->db->select('COALESCE(SUM(total_amount), 0) as amount, COUNT(*) as cnt');
	$this->db->where('DATE(dDate)', $date);
	$this->db->where('InActive', 0);
	$this->db->where("(payer_type IS NULL OR payer_type = '' OR UPPER(payer_type) = 'CASH')", null, false);
	$cash = $this->db->get('iop_billing')->row();
	if ($cash) {
		$result['cash']['amount'] = (float)$cash->amount;
		$result['cash']['count'] = (int)$cash->cnt;
	}
	
	// NHIS payments
	$this->db->select('COALESCE(SUM(total_amount), 0) as amount, COUNT(*) as cnt');
	$this->db->where('DATE(dDate)', $date);
	$this->db->where('InActive', 0);
	$this->db->where("UPPER(payer_type) = 'NHIS'", null, false);
	$nhis = $this->db->get('iop_billing')->row();
	if ($nhis) {
		$result['nhis']['amount'] = (float)$nhis->amount;
		$result['nhis']['count'] = (int)$nhis->cnt;
	}
	
	// Insurance payments
	$this->db->select('COALESCE(SUM(total_amount), 0) as amount, COUNT(*) as cnt');
	$this->db->where('DATE(dDate)', $date);
	$this->db->where('InActive', 0);
	$this->db->where("UPPER(payment_type) = 'INSURANCE'", null, false);
	$ins = $this->db->get('iop_billing')->row();
	if ($ins) {
		$result['insurance']['amount'] = (float)$ins->amount;
		$result['insurance']['count'] = (int)$ins->cnt;
	}
	
	return $result;
}

/**
 * Get department revenue for today
 */
public function get_department_revenue_today()
{
	$today = date('Y-m-d');
	
	// Get revenue by bill group
	$this->db->select('bg.group_name as department, COALESCE(SUM(t.amount), 0) as amount');
	$this->db->from('iop_billing_t t');
	$this->db->join('iop_billing h', 'h.invoice_no = t.invoice_no AND h.InActive = 0', 'inner');
	$this->db->join('bill_particular bp', 'bp.particular_name = t.bill_name', 'left');
	$this->db->join('bill_group_name bg', 'bg.group_id = bp.group_id', 'left');
	$this->db->where('DATE(h.dDate)', $today);
	$this->db->where('t.InActive', 0);
	$this->db->group_by('bg.group_name');
	$this->db->order_by('amount', 'DESC');
	$this->db->limit(10);
	
	return $this->db->get()->result();
}

/**
 * Get recent transactions
 */
public function get_recent_transactions($limit = 20)
{
	$this->db->select('b.*, p.firstname, p.lastname');
	$this->db->from('iop_billing b');
	$this->db->join('patient_personal_info p', 'p.patient_no = b.patient_no', 'left');
	$this->db->where('b.InActive', 0);
	$this->db->order_by('b.dDate', 'DESC');
	$this->db->limit($limit);
	return $this->db->get()->result();
}

/**
 * Sync invoice header total with line items sum
 * CRITICAL: Prevents header/line item total mismatches
 * Call this after adding/modifying line items
 */
public function sync_invoice_total($invoice_no)
{
    // Calculate sum of line items
    $this->db->select('COALESCE(SUM(amount), 0) as total');
    $this->db->where('invoice_no', $invoice_no);
    $this->db->where('InActive', 0);
    $result = $this->db->get('iop_billing_t')->row();
    $line_total = $result ? (float)$result->total : 0;

    // Get current header total
    $this->db->select('total_amount');
    $this->db->where('invoice_no', $invoice_no);
    $this->db->where('InActive', 0);
    $header = $this->db->get('iop_billing')->row();

    if (!$header) return false;

    $old_total = (float)$header->total_amount;

    // Update if different (with tolerance for floating point)
    if (abs($old_total - $line_total) > 0.01) {
        $this->db->where('invoice_no', $invoice_no);
        $this->db->update('iop_billing', array('total_amount' => $line_total));

        // Log the correction
        $this->log_nhis_audit('TOTAL_SYNC', 'iop_billing', $invoice_no,
            $old_total, $line_total, null, null, null,
            'Header total synced with line items: ' . $old_total . ' -> ' . $line_total);

        return true;
    }

    return false;
}

public function ensure_procedure_catalog_seeded($user_id = null)
{
	if (!$this->table_exists('bill_group_name') || !$this->table_exists('bill_particular')) {
		return array('success' => false, 'error' => 'Billing catalog tables missing');
	}

    $prevDebug = isset($this->db->db_debug) ? $this->db->db_debug : null;
    if ($prevDebug !== null) { $this->db->db_debug = false; }

    $seeded = 0;
    $group_id = 0;
    try {
        $this->db->select('group_id');
        $this->db->where(array('group_name' => 'PROCEDURES', 'InActive' => 0));
        $this->db->limit(1);
        $row = $this->db->get('bill_group_name')->row();
        if ($row && isset($row->group_id)) {
            $group_id = (int)$row->group_id;
        }
        if ($group_id <= 0) {
            $this->db->insert('bill_group_name', array(
                'group_name' => 'PROCEDURES',
                'group_desc' => 'Common procedures',
                'InActive' => 0
            ));
            $group_id = (int)$this->db->insert_id();
        }

        if ($group_id > 0) {
            $items = array(
                'WOUND DRESSING (SMALL)',
                'WOUND DRESSING (MEDIUM)',
                'WOUND DRESSING (LARGE)',
                'INJECTION (IM)',
                'INJECTION (IV)',
                'INJECTION (SC)',
                'IV CANNULATION',
                'IV INFUSION / DRIP SETUP',
                'NEBULIZATION',
                'OXYGEN THERAPY',
                'URINARY CATHETERIZATION',
                'RBG (RANDOM BLOOD GLUCOSE)',
                'BP CHECK',
                'ECG',
                'SUTURING (MINOR)',
                'SUTURE REMOVAL',
                'INCISION AND DRAINAGE (I&D)',
                'EAR SYRINGING',
                'WOUND DEBRIDEMENT (MINOR)',
                'NASOGASTRIC (NG) TUBE INSERTION',
                'NG TUBE REMOVAL',
                'ENEMA',
                'VACCINATION / IMMUNIZATION SERVICE',
                'WOUND TOILET',
                'DRESSING + BANDAGING',
                'PLASTER OF PARIS (POP) APPLICATION',
                'POP REMOVAL',
                'LOCAL ANAESTHESIA (MINOR)',
                'FOREIGN BODY REMOVAL (MINOR)',
                'BLOOD TRANSFUSION SETUP FEE'
            );
            for ($i = 0; $i < count($items); $i++) {
                $name = strtoupper(trim((string)$items[$i]));
                if ($name === '') { continue; }
                $this->db->select('particular_id');
                $this->db->where(array('group_id' => $group_id, 'particular_name' => $name, 'InActive' => 0));
                $this->db->limit(1);
                $ex = $this->db->get('bill_particular')->row();
                if ($ex) { continue; }
                $this->db->insert('bill_particular', array(
                    'group_id' => $group_id,
                    'particular_name' => $name,
                    'particular_desc' => '',
                    'charge_amount' => 0,
                    'InActive' => 0
                ));
                if ((int)$this->db->insert_id() > 0) {
                    $seeded++;
                }
            }
        }
    } catch (\Throwable $e) {
        if ($prevDebug !== null) { $this->db->db_debug = $prevDebug; }
        return array('success' => false, 'error' => $e->getMessage());
    }

    if ($prevDebug !== null) { $this->db->db_debug = $prevDebug; }
    return array('success' => true, 'group_id' => $group_id, 'seeded' => $seeded);
}

}
