<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

/**
 * Consumable_order controller
 *
 * RBAC: nurse + receptionist for create/fulfill.
 * Doctors can view only.
 */
class Consumable_order extends General
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('app/Consumable_order_model');
		$this->load->model('app/ipd_model');
		$this->load->model('app/patient_model');
		$this->load->model('app/billing_model');
		if (General::is_logged_in() == FALSE) {
			redirect(base_url() . 'login');
		}
		General::variable();
		require_role(array('nurse', 'receptionist', 'doctor'));
	}

	private function _normalize_id($value)
	{
		if (function_exists('sanitize_id_for_db')) {
			return sanitize_id_for_db((string)$value);
		}
		return trim(urldecode((string)$value));
	}

	private function _can_create_order()
	{
		return ($this->current_user_is_nurse() || $this->current_user_is_reception() || $this->current_user_is_admin());
	}

	// =========================================================================
	// DASHBOARD
	// =========================================================================

	public function index()
	{
		$iop_id = $this->_normalize_id($this->uri->segment(4));
		$patient_no = $this->_normalize_id($this->uri->segment(5));

		if ($iop_id === '' || $patient_no === '') {
			if ($this->input->post('iop_no') != '' && $this->input->post('patient_no') != '') {
				$iop_id = $this->_normalize_id($this->input->post('iop_no'));
				$patient_no = $this->_normalize_id($this->input->post('patient_no'));
			}
		}

		if ($iop_id === '' || $patient_no === '') {
			$this->session->set_userdata(array(
				'tab' => 'nurse_module', 'module' => 'consumable_order',
			));
			$this->data['module_title'] = 'Consumable Orders';
			$this->data['module'] = 'consumable_order';
			$this->load->view('app/nurse_module/pick', $this->data);
			return;
		}

		$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_id);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['orders'] = $this->Consumable_order_model->get_orders_for_encounter($iop_id);
		$this->data['pending_items'] = $this->Consumable_order_model->get_pending_fulfillment($iop_id);
		$this->data['can_create'] = $this->_can_create_order();
		$this->data['iop_id'] = $iop_id;
		$this->data['patient_no'] = $patient_no;

		$this->load->view('app/nurse_module/consumable_dashboard', $this->data);
	}

	// =========================================================================
	// CREATE ORDER
	// =========================================================================

	public function create()
	{
		if (!$this->_can_create_order()) {
			redirect(base_url() . 'access_denied');
			return;
		}
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$iop_id = $this->_normalize_id($this->input->post('iop_id'));
		$patient_no = $this->_normalize_id($this->input->post('patient_no'));
		$notes = trim((string)$this->input->post('notes'));
		$actor = (string)$this->session->userdata('user_id');

		// Parse items from POST
		$item_count = (int)$this->input->post('item_count');
		$items = array();
		for ($i = 1; $i <= $item_count; $i++) {
			$source = $this->input->post('item_source_' . $i);
			$cat_id = $this->input->post('catalog_id_' . $i);
			$qty = $this->input->post('quantity_' . $i);
			if ((int)$cat_id > 0 && (float)$qty > 0) {
				$items[] = array(
					'item_source' => $source ?: 'PARTICULAR',
					'catalog_id'  => (int)$cat_id,
					'quantity'    => (float)$qty,
				);
			}
		}

		$result = $this->Consumable_order_model->create_order($iop_id, $patient_no, $items, $actor, $notes);

		if (!empty($result['ok'])) {
			$this->session->set_flashdata('message',
				"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i>"
				. "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
				. "Consumable order <strong>" . htmlspecialchars($result['order_no']) . "</strong> created successfully! "
				. "Amount: GHS " . number_format($result['net_amount'], 2) . "</div>"
			);
		} else {
			$this->session->set_flashdata('message',
				"<div class='alert alert-danger alert-dismissable'><i class='fa fa-times'></i>"
				. "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
				. "Failed to create order: " . htmlspecialchars($result['error']) . "</div>"
			);
		}

		$this->session->set_userdata('abc', '1');
		redirect(base_url() . 'app/consumable_order/index/' . urlencode($iop_id) . '/' . urlencode($patient_no));
	}

	// =========================================================================
	// FULFILL ITEM
	// =========================================================================

	public function fulfill_item()
	{
		if (!$this->_can_create_order()) {
			redirect(base_url() . 'access_denied');
			return;
		}
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$item_id = (int)$this->input->post('item_id');
		$qty = (float)$this->input->post('quantity');
		$iop_id = $this->_normalize_id($this->input->post('iop_id'));
		$patient_no = $this->_normalize_id($this->input->post('patient_no'));
		$actor = (string)$this->session->userdata('user_id');

		if ($qty <= 0) $qty = 1;

		$result = $this->Consumable_order_model->fulfill_order_item($item_id, $qty, $actor);

		if (!empty($result['ok'])) {
			$this->session->set_flashdata('message',
				"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i>"
				. "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
				. "Item fulfilled successfully.</div>"
			);
		} else {
			$this->session->set_flashdata('message',
				"<div class='alert alert-danger alert-dismissable'><i class='fa fa-times'></i>"
				. "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
				. htmlspecialchars($result['error']) . "</div>"
			);
		}

		$this->session->set_userdata('abc', '1');
		redirect(base_url() . 'app/consumable_order/index/' . urlencode($iop_id) . '/' . urlencode($patient_no));
	}

	// =========================================================================
	// CANCEL ORDER
	// =========================================================================

	public function cancel()
	{
		if (!$this->_can_create_order()) {
			redirect(base_url() . 'access_denied');
			return;
		}
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$order_id = (int)$this->input->post('order_id');
		$iop_id = $this->_normalize_id($this->input->post('iop_id'));
		$patient_no = $this->_normalize_id($this->input->post('patient_no'));
		$reason = trim((string)$this->input->post('reason'));
		$actor = (string)$this->session->userdata('user_id');

		$result = $this->Consumable_order_model->cancel_order($order_id, $actor, $reason);

		if (!empty($result['ok'])) {
			$this->session->set_flashdata('message',
				"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i>"
				. "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
				. "Order cancelled.</div>"
			);
		} else {
			$this->session->set_flashdata('message',
				"<div class='alert alert-danger alert-dismissable'><i class='fa fa-times'></i>"
				. "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
				. htmlspecialchars($result['error']) . "</div>"
			);
		}

		$this->session->set_userdata('abc', '1');
		redirect(base_url() . 'app/consumable_order/index/' . urlencode($iop_id) . '/' . urlencode($patient_no));
	}

	// =========================================================================
	// AJAX ENDPOINTS
	// =========================================================================

	public function search_catalog_ajax()
	{
		header('Content-Type: application/json');
		$term = trim((string)$this->input->post('term'));
		$results = $this->Consumable_order_model->search_consumable_catalog($term, 20);
		echo json_encode(array('ok' => true, 'results' => $results));
	}

	public function resolve_price_ajax()
	{
		header('Content-Type: application/json');
		$this->load->model('app/Price_engine_model');

		$item_source = strtoupper(trim((string)$this->input->post('item_source')));
		$catalog_id = (int)$this->input->post('catalog_id');
		$patient_no = trim((string)$this->input->post('patient_no'));
		$qty = (float)$this->input->post('quantity');
		if ($qty <= 0) $qty = 1;

		$item_type = ($item_source === 'DRUG') ? 'DRUG' : 'SERVICE';
		$payer = $this->billing_model->determine_payer_type($patient_no);

		$price = $this->Price_engine_model->resolve(array(
			'item_type'  => $item_type,
			'item_id'    => $catalog_id,
			'patient_no' => $patient_no,
			'payer_type' => $payer,
			'quantity'   => $qty,
		));

		echo json_encode($price);
	}

	public function order_detail()
	{
		$order_no = $this->_normalize_id($this->uri->segment(4));
		$order = $this->Consumable_order_model->get_order_by_no($order_no);
		if (!$order) {
			$this->session->set_flashdata('message',
				"<div class='alert alert-warning'>Order not found.</div>"
			);
			redirect(base_url() . 'app/consumable_order/index');
			return;
		}

		$this->data['order'] = $order;
		$this->data['items'] = $this->Consumable_order_model->get_order_items($order->order_id);
		$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($order->iop_id);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($order->patient_no);
		$this->data['can_create'] = $this->_can_create_order();
		$this->data['message'] = $this->session->flashdata('message');

		$this->load->view('app/nurse_module/consumable_order_detail', $this->data);
	}
}
