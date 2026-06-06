<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

/**
 * Walk-In Registration Controller
 *
 * Handles walk-in clients who visit for direct services (Lab, Sonography,
 * Pharmacy, Procedures) WITHOUT being registered as OPD/IPD patients.
 *
 * Access: Admin, Cashier
 */
class Walkin extends General
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('app/walkin_model');
        $this->load->model('app/walkin_order_model');
        $this->load->model('general_model');
        if (General::is_logged_in() == FALSE) {
            redirect(base_url() . 'login');
            return;
        }
		if (function_exists('get_role_key') && get_role_key() === 'doctor') {
			redirect(base_url() . 'access_denied');
			return;
		}
		if (!has_role(array('admin', 'cashier')) && !(has_role('nurse') && has_privilege('walkin_access'))) {
			redirect(base_url() . 'access_denied');
			return;
		}
        General::variable();
        if (!$this->session->userdata('_schema_walkin_ok_v2')) {
            $this->walkin_model->ensure_schema();
            $this->session->set_userdata('_schema_walkin_ok_v2', 1);
        }
        if (!$this->session->userdata('_schema_walkin_order_ok_v1')) {
            $this->walkin_order_model->ensure_walkin_schema();
            $this->session->set_userdata('_schema_walkin_order_ok_v1', 1);
        }

        // Ensure procedure catalog exists for selection (idempotent)
        try {
            $this->load->model('app/billing_model');
            if (isset($this->billing_model) && method_exists($this->billing_model, 'ensure_procedure_catalog_seeded')) {
                $this->billing_model->ensure_procedure_catalog_seeded((string)$this->session->userdata('user_id'));
            }
        } catch (\Throwable $e) {
            log_message('error', 'walkin ctor ensure_procedure_catalog_seeded: ' . $e->getMessage());
        }
    }

    /* ═══════════════════════════════════════════════════════
     * DASHBOARD
     * ═══════════════════════════════════════════════════════ */

    public function index()
    {
        $this->session->set_userdata(['tab' => 'walkin', 'module' => 'walkin', 'subtab' => '', 'submodule' => '']);
        $this->_sync_paid_walkin_orders();
        $this->data['stats']             = $this->walkin_model->get_today_stats();
        $this->data['today_transactions'] = $this->walkin_model->get_today_transactions(50);
        $this->data['recent_walkins']    = $this->walkin_model->get_recent_walkins(10);
        $this->data['service_breakdown'] = $this->walkin_model->get_revenue_by_service_today();
        $this->data['message']           = $this->session->flashdata('message');
        $this->load->view('app/walkin/dashboard', $this->data);
    }

    private function _sync_paid_walkin_orders()
    {
        try {
            if (isset($this->walkin_order_model) && method_exists($this->walkin_order_model, 'reconcile_paid_orders_from_billing')) {
                $this->walkin_order_model->reconcile_paid_orders_from_billing(100, (string)$this->session->userdata('user_id'));
            }
        } catch (\Throwable $e) {
            log_message('error', 'walkin paid order reconciliation failed: ' . $e->getMessage());
        }
    }

    /* ═══════════════════════════════════════════════════════
     * REGISTER WALK-IN CLIENT
     * ═══════════════════════════════════════════════════════ */

    public function register()
    {
        $this->data['message'] = $this->session->flashdata('message');
        $this->load->view('app/walkin/register', $this->data);
    }

    public function save_client()
    {
        $name = trim((string)$this->input->post('client_name'));
        if ($name === '') {
            $this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-warning'></i> Client name is required.</div>");
            redirect(base_url() . 'app/walkin/register');
            return;
        }

        $cashier_id   = $this->session->userdata('user_id');
        $cashier_name = $this->session->userdata('username');

        $client_id = $this->walkin_model->register_client([
            'client_name' => $name,
            'phone'       => $this->input->post('phone'),
            'gender'      => $this->input->post('gender'),
            'referral'    => $this->input->post('referral'),
            'created_by'  => $cashier_id,
        ]);

        if (!$client_id) {
            $this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-warning'></i> Registration failed. Please try again.</div>");
            redirect(base_url() . 'app/walkin/register');
            return;
        }

        redirect(base_url() . 'app/walkin/add_transaction/' . $client_id);
    }

    /* ═══════════════════════════════════════════════════════
     * ADD TRANSACTION
     * ═══════════════════════════════════════════════════════ */

    public function add_transaction($client_id = 0)
    {
        $client_id = (int)$client_id;
        $client = $this->walkin_model->get_client($client_id);
        if (!$client) {
            $this->session->set_flashdata('message', "<div class='alert alert-danger'>Client not found.</div>");
            redirect(base_url() . 'app/walkin/register');
            return;
        }
        $this->data['client']       = $client;
        $this->data['transactions'] = $this->walkin_model->get_client_transactions($client_id);
        $this->data['message']      = $this->session->flashdata('message');
        $this->load->view('app/walkin/add_transaction', $this->data);
    }

    public function consultation_types()
{
    require_role(array('admin'));
    $this->session->set_userdata(['tab' => 'walkin', 'module' => 'walkin', 'subtab' => '', 'submodule' => '']);
    $this->data['message'] = $this->session->flashdata('message');
    $this->data['rows'] = $this->walkin_model->get_consultation_types(true);
    $this->data['edit'] = null;

    $edit_id = (int)$this->input->get('edit_id');
    if ($edit_id > 0) {
        $this->data['edit'] = $this->walkin_model->get_consultation_type($edit_id);
    }

    $this->load->view('app/walkin/consultation_types', $this->data);
}

public function save_consultation_type()
{
    require_role(array('admin'));
    if ($this->input->method(TRUE) !== 'POST') {
        redirect(base_url() . 'app/walkin/consultation_types');
        return;
    }

    $user_id = $this->session->userdata('user_id');
    $id = (int)$this->input->post('id');
    $name = trim((string)$this->input->post('name'));
    $price_cash = (float)$this->input->post('price_cash');
    $price_nhis = (float)$this->input->post('price_nhis');
    $inactive = $this->input->post('InActive') ? 1 : 0;

    if ($name === '') {
        $this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-warning'></i> Name is required.</div>");
        redirect(base_url() . 'app/walkin/consultation_types' . ($id > 0 ? ('?edit_id=' . $id) : ''));
        return;
    }

    $ok = $this->walkin_model->save_consultation_type(array(
        'id' => $id,
        'name' => $name,
        'price_cash' => $price_cash,
        'price_nhis' => $price_nhis,
        'InActive' => $inactive,
    ), $user_id);

    if ($ok) {
        $this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Consultation type saved.</div>");
    } else {
        $this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-warning'></i> Could not save consultation type.</div>");
    }

    redirect(base_url() . 'app/walkin/consultation_types');
}

    public function save_transaction()
    {
        $client_id   = (int)$this->input->post('walkin_client_id');
        $service_type = trim((string)$this->input->post('service_type'));
        $description = trim((string)$this->input->post('description'));
        $amount      = (float)$this->input->post('amount');

        if ($service_type === 'Pharmacy') {
            $cashier_id   = $this->session->userdata('user_id');
            $cashier_name = $this->session->userdata('username');

			$use_walkin_order = true;

            $payment_method = trim((string)$this->input->post('payment_method'));
            if ($payment_method === 'NHIS') {
                $this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-warning'></i> NHIS is not allowed for Walk-In Pharmacy.</div>");
                redirect(base_url() . 'app/walkin/add_transaction/' . $client_id);
                return;
            }

            $drug_ids = $this->input->post('pharmacy_drug_id');
            $qtys = $this->input->post('pharmacy_qty');
            $prices = $this->input->post('pharmacy_unit_price');

            $items = array();
            if (is_array($drug_ids) && is_array($qtys) && is_array($prices)) {
                $n = max(count($drug_ids), count($qtys), count($prices));
                for ($i = 0; $i < $n; $i++) {
                    $items[] = array(
                        'drug_id' => isset($drug_ids[$i]) ? $drug_ids[$i] : 0,
                        'qty' => isset($qtys[$i]) ? $qtys[$i] : 0,
                        'unit_price' => isset($prices[$i]) ? $prices[$i] : 0,
                    );
                }
            }

			if ($use_walkin_order) {
				$this->load->model('app/pharmacy_model');
				$client = $this->walkin_model->get_client($client_id);
				$wo_items = array();
				foreach ($items as $it) {
					$drug_id = isset($it['drug_id']) ? (int)$it['drug_id'] : 0;
					$qty = isset($it['qty']) ? (float)$it['qty'] : 0.0;
					$unit_price = isset($it['unit_price']) ? (float)$it['unit_price'] : 0.0;
					if ($drug_id <= 0 || $qty <= 0) {
						continue;
					}
					$drug = $this->pharmacy_model->get_drug_stock($drug_id);
					$drug_name = ($drug && isset($drug->drug_name)) ? (string)$drug->drug_name : '';
					if ($drug_name === '') {
						$drug_name = 'Drug #' . $drug_id;
					}
					$wo_items[] = array(
						'department' => 'PHARMACY',
						'item_type' => 'PHARMACY',
						'catalog_ref' => 'drug_id:' . $drug_id,
						'item_name' => $drug_name,
						'quantity' => $qty,
						'unit_price' => $unit_price,
						'pricing_source_type' => 'CUSTOM',
					);
				}
				if (empty($wo_items)) {
					$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-warning'></i> No valid pharmacy items.</div>");
					redirect(base_url() . 'app/walkin/add_transaction/' . $client_id);
					return;
				}

				$create = $this->walkin_order_model->create_walkin_order_with_items(array(
					'walkin_client_id' => $client_id,
					'customer_name' => $client && isset($client->client_name) ? (string)$client->client_name : null,
					'phone' => $client && isset($client->phone) ? (string)$client->phone : null,
					'gender' => $client && isset($client->gender) ? (string)$client->gender : null,
					'transaction_type' => 'WALKIN-PHARMACY',
					'payer_type' => 'CASH',
					'notes' => $this->input->post('notes'),
				), $wo_items, (string)$cashier_id);
				if (!$create || empty($create['success'])) {
					$err = isset($create['error']) ? (string)$create['error'] : 'Failed to create walk-in order.';
					$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-warning'></i> " . htmlspecialchars($err) . "</div>");
					redirect(base_url() . 'app/walkin/add_transaction/' . $client_id);
					return;
				}

				$walkin_order_id = isset($create['walkin_order_id']) ? (string)$create['walkin_order_id'] : '';
				if ($walkin_order_id === '') {
					$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-warning'></i> Failed to create walk-in order.</div>");
					redirect(base_url() . 'app/walkin/add_transaction/' . $client_id);
					return;
				}

				$this->load->model('app/unified_billing_model');
				$this->unified_billing_model->retry_failed_queue_by_subject('WALKIN_ORDER', $walkin_order_id, null, $cashier_id);
				$invoice = $this->unified_billing_model->generate_invoice_by_subject('WALKIN_ORDER', $walkin_order_id, null, $cashier_id);
				if (!$invoice || empty($invoice['success']) || empty($invoice['invoice_no'])) {
					$err = isset($invoice['error']) ? (string)$invoice['error'] : 'Unable to create pharmacy invoice.';
					$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-warning'></i> " . htmlspecialchars($err) . "</div>");
					redirect(base_url() . 'app/walkin/add_transaction/' . $client_id);
					return;
				}

				$invoice_no = (string)$invoice['invoice_no'];
				$this->db->where('walkin_order_id', $walkin_order_id);
				$this->db->where('InActive', 0);
				$this->db->update('walkin_orders', array(
					'invoice_no' => $invoice_no,
					'payment_status' => 'INVOICED',
				));

				$payment_status = $this->input->post('payment_status') ?: 'Paid';
				if ($payment_status === 'Paid') {
					$pay = $this->unified_billing_model->process_payment_by_subject(
						'WALKIN_ORDER',
						$walkin_order_id,
						$invoice_no,
						isset($create['net_amount']) ? (float)$create['net_amount'] : 0.0,
						$payment_method,
						$cashier_id,
						null,
						$this->input->post('notes')
					);
					if (!$pay || empty($pay['success'])) {
						$err = isset($pay['error']) ? (string)$pay['error'] : 'Invoice created, but payment failed.';
						$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-warning'></i> " . htmlspecialchars($err) . "</div>");
						redirect(base_url() . 'app/cashier/invoice/' . urlencode($invoice_no));
						return;
					}
					$receipt_no = isset($pay['receipt_no']) ? (string)$pay['receipt_no'] : '';
					try {
						$sync = $this->walkin_order_model->mark_order_paid_authorized($walkin_order_id, $receipt_no, $cashier_id);
						if (!is_array($sync) || empty($sync['success'])) {
							$err = is_array($sync) && isset($sync['error']) ? (string)$sync['error'] : 'unknown sync failure';
							log_message('error', 'walkin save_transaction payment recorded but order authorization sync failed: order=' . $walkin_order_id . ' invoice=' . $invoice_no . ' receipt=' . $receipt_no . ' error=' . $err);
						}
					} catch (\Throwable $e) {
						log_message('error', 'walkin save_transaction order authorization sync exception: order=' . $walkin_order_id . ' invoice=' . $invoice_no . ' receipt=' . $receipt_no . ' error=' . $e->getMessage());
					}
					$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Pharmacy payment recorded successfully." . ($receipt_no !== '' ? " Receipt #" . htmlspecialchars($receipt_no) : "") . "</div>");
					redirect(base_url() . 'app/cashier/invoice/' . urlencode($invoice_no));
					return;
				}

				redirect(base_url() . 'app/cashier/invoice/' . urlencode($invoice_no));
				return;
			}

            $result = $this->walkin_model->add_pharmacy_transaction(array(
                'walkin_client_id' => $client_id,
                'payment_method' => $payment_method,
                'payment_status' => $this->input->post('payment_status') ?: 'Paid',
                'notes' => $this->input->post('notes'),
                'cashier_id' => $cashier_id,
                'cashier_name' => $cashier_name,
            ), $items);

            if (!$result || !isset($result['success']) || !$result['success']) {
                $err = isset($result['error']) ? $result['error'] : 'Failed to save pharmacy transaction.';
                $this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-warning'></i> " . htmlspecialchars($err) . "</div>");
                redirect(base_url() . 'app/walkin/add_transaction/' . $client_id);
                return;
            }

            redirect(base_url() . 'app/walkin/receipt/' . (int)$result['id']);
            return;
        }

		// Non-pharmacy services are itemized (catalog + qty) and amount is auto-computed.
		$svc_item_ids = $this->input->post('service_item_id');
		$svc_qtys = $this->input->post('service_qty');
		$items = array();
		if (is_array($svc_item_ids) && is_array($svc_qtys)) {
			$n = max(count($svc_item_ids), count($svc_qtys));
			for ($i = 0; $i < $n; $i++) {
				$items[] = array(
					'item_id' => isset($svc_item_ids[$i]) ? $svc_item_ids[$i] : 0,
					'qty' => isset($svc_qtys[$i]) ? $svc_qtys[$i] : 0,
				);
			}
		}
		if ($client_id <= 0 || !is_array($items) || count($items) === 0) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-warning'></i> Please add at least one service item.</div>");
			redirect(base_url() . 'app/walkin/add_transaction/' . $client_id);
			return;
		}

        $cashier_id   = $this->session->userdata('user_id');
        $cashier_name = $this->session->userdata('username');

		$payment_method = trim((string)$this->input->post('payment_method'));
		$payment_status = $this->input->post('payment_status') ?: 'Paid';

		$result = $this->walkin_model->add_catalog_service_transaction(array(
			'walkin_client_id' => $client_id,
			'service_type' => $service_type,
			'payment_method' => $payment_method,
			'payment_status' => $payment_status,
			'notes' => $this->input->post('notes'),
			'extra_description' => $description,
			'cashier_id' => $cashier_id,
			'cashier_name' => $cashier_name,
		), $items);

        if (!$result) {
            $this->session->set_flashdata('message', "<div class='alert alert-danger'>Failed to save transaction.</div>");
            redirect(base_url() . 'app/walkin/add_transaction/' . $client_id);
            return;
        }

        redirect(base_url() . 'app/walkin/receipt/' . $result['id']);
    }

	public function search_lab_tests_json()
	{
		$term = trim((string)$this->input->get('q'));
		$this->load->model('app/billing_model');
		$out = isset($this->billing_model) && method_exists($this->billing_model, 'search_walkin_lab_tests')
			? $this->billing_model->search_walkin_lab_tests($term, 20)
			: array();
		$this->output->set_content_type('application/json')->set_output(json_encode($out));
	}

	public function search_procedures_json()
	{
		$term = trim((string)$this->input->get('q'));
		$this->load->model('app/billing_model');
		$out = isset($this->billing_model) && method_exists($this->billing_model, 'search_walkin_procedures')
			? $this->billing_model->search_walkin_procedures($term, 20)
			: array();
		$this->output->set_content_type('application/json')->set_output(json_encode($out));
	}

	public function search_bill_particulars_json()
	{
		$term = trim((string)$this->input->get('q'));
		$this->load->model('app/billing_model');
		$out = isset($this->billing_model) && method_exists($this->billing_model, 'search_walkin_particulars')
			? $this->billing_model->search_walkin_particulars($term, 20)
			: array();
		$this->output->set_content_type('application/json')->set_output(json_encode($out));
	}

	public function search_consultation_types_json()
	{
		$term = trim((string)$this->input->get('q'));
		$pm = trim((string)$this->input->get('pm'));
		$out = $this->walkin_model->search_consultation_types($term, $pm, 20);
		$this->output->set_content_type('application/json')->set_output(json_encode($out));
	}

	public function search_sonography_tests_json()
	{
		$term = trim((string)$this->input->get('q'));
		$this->load->model('app/Ghana_test_catalog_model');
		if (isset($this->Ghana_test_catalog_model) && method_exists($this->Ghana_test_catalog_model, 'ensure_catalog_tables')) {
			$this->Ghana_test_catalog_model->ensure_catalog_tables();
		}
		// Keep Walk-In search results consistent with Doctor Sonography Requests:
		// OPD::laboratory uses Ghana_test_catalog_model::get_sonography_tests() (merged GHS + legacy bill_particular).
		$rows = isset($this->Ghana_test_catalog_model) && method_exists($this->Ghana_test_catalog_model, 'get_sonography_tests')
			? $this->Ghana_test_catalog_model->get_sonography_tests(null, ($term !== '' ? $term : null), false)
			: array();
		if (is_array($rows) && count($rows) > 20) {
			$rows = array_slice($rows, 0, 20);
		}
		$out = array();
		foreach ((array)$rows as $r) {
			$testId = isset($r->test_id) ? (int)$r->test_id : (isset($r['test_id']) ? (int)$r['test_id'] : 0);
			$testName = isset($r->test_name) ? (string)$r->test_name : (isset($r['test_name']) ? (string)$r['test_name'] : '');
			$price = isset($r->price) ? (float)$r->price : (isset($r['price']) ? (float)$r['price'] : 0.0);
			$out[] = array(
				'item_id' => $testId,
				'label' => $testName,
				'value' => $testName,
				'unit_price' => $price,
			);
		}
		$this->output->set_content_type('application/json')->set_output(json_encode($out));
	}

	public function search_radiology_tests_json()
	{
		$term = trim((string)$this->input->get('q'));
		$this->load->model('app/radiology_model');
		if (isset($this->radiology_model) && method_exists($this->radiology_model, 'ensure_radiology_schema')) {
			$this->radiology_model->ensure_radiology_schema();
		}
		$rows = isset($this->radiology_model) && method_exists($this->radiology_model, 'search_tests')
			? $this->radiology_model->search_tests($term)
			: array();
		$out = array();
		foreach ((array)$rows as $r) {
			$out[] = array(
				'item_id' => isset($r->id) ? (int)$r->id : 0,
				'label' => isset($r->test_name) ? (string)$r->test_name : '',
				'value' => isset($r->test_name) ? (string)$r->test_name : '',
				'unit_price' => isset($r->price) ? (float)$r->price : 0.0,
			);
		}
		$this->output->set_content_type('application/json')->set_output(json_encode($out));
	}

    public function search_drugs_json()
    {
        $term = trim((string)$this->input->get('q'));
        if ($term === '') {
            $this->output->set_content_type('application/json')->set_output(json_encode(array()));
            return;
        }

        $this->load->model('app/pharmacy_model');
        $rows = $this->pharmacy_model->search_drugs($term, 20);
        $out = array();
        foreach ($rows as $r) {
            $label = isset($r->drug_name) ? (string)$r->drug_name : '';
            if (isset($r->generic_name) && trim((string)$r->generic_name) !== '') {
                $label .= ' (' . trim((string)$r->generic_name) . ')';
            }
            $out[] = array(
                'drug_id' => isset($r->drug_id) ? (int)$r->drug_id : 0,
                'label' => $label,
                'value' => $label,
                'nStock' => isset($r->nStock) ? (float)$r->nStock : 0,
                'nPrice' => isset($r->nPrice) ? (float)$r->nPrice : 0,
            );
        }
        $this->output->set_content_type('application/json')->set_output(json_encode($out));
    }

    /* ═══════════════════════════════════════════════════════
     * RECEIPT
     * ═══════════════════════════════════════════════════════ */

    public function receipt($txn_id = 0)
    {
        $txn_id = (int)$txn_id;
        $txn = $this->walkin_model->get_receipt_data($txn_id);
        if (!$txn) {
            $this->session->set_flashdata('message', "<div class='alert alert-danger'>Transaction not found.</div>");
            redirect(base_url() . 'app/walkin');
            return;
        }

        if (empty($this->data['companyInfo'])) {
            $this->data['companyInfo'] = $this->general_model->companyInfo();
        }
        $this->data['txn'] = $txn;
        $this->load->view('app/walkin/receipt', $this->data);
    }

    public function print_receipt($txn_id = 0)
    {
        $txn_id = (int)$txn_id;
        $txn = $this->walkin_model->get_receipt_data($txn_id);
        if (!$txn) {
            redirect(base_url() . 'app/walkin');
            return;
        }
        if (empty($this->data['companyInfo'])) {
            $this->data['companyInfo'] = $this->general_model->companyInfo();
        }
        $this->data['txn'] = $txn;
        $this->load->view('app/walkin/print_receipt', $this->data);
    }

    /* ═══════════════════════════════════════════════════════
     * CANCEL TRANSACTION
     * ═══════════════════════════════════════════════════════ */

    public function cancel_transaction($txn_id = 0)
    {
        $txn_id    = (int)$txn_id;
        $cashier_id = $this->session->userdata('user_id');
        $ok = $this->walkin_model->cancel_transaction($txn_id, $cashier_id);
        if ($ok) {
            $this->session->set_flashdata('message', "<div class='alert alert-warning'><i class='fa fa-ban'></i> Transaction cancelled.</div>");
        } else {
            $this->session->set_flashdata('message', "<div class='alert alert-danger'>Could not cancel transaction.</div>");
        }
        redirect(base_url() . 'app/walkin');
    }

    public function mark_paid($txn_id = 0)
    {
        $txn_id = (int)$txn_id;
        $cashier_id = (string)$this->session->userdata('user_id');
        $result = $this->walkin_model->mark_transaction_paid($txn_id, $cashier_id);

        if ($result && isset($result['success']) && $result['success']) {
            $this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check-circle'></i> Payment marked as PAID.</div>");
        } else {
            $err = ($result && isset($result['error'])) ? $result['error'] : 'Could not mark as paid.';
            $this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-warning'></i> " . htmlspecialchars($err) . "</div>");
        }

        redirect(base_url() . 'app/walkin/receipt/' . $txn_id);
    }

    /* ═══════════════════════════════════════════════════════
     * TRANSACTION HISTORY / SEARCH (JSON)
     * ═══════════════════════════════════════════════════════ */

    public function history()
    {
        $this->_sync_paid_walkin_orders();
        $filters = [
            'date_from'      => $this->input->get('date_from'),
            'date_to'        => $this->input->get('date_to'),
            'service_type'   => $this->input->get('service_type'),
            'payment_status' => $this->input->get('payment_status'),
            'search'         => $this->input->get('q'),
        ];
        $page    = max(1, (int)$this->input->get('page'));
        $limit   = 25;
        $offset  = ($page - 1) * $limit;

        $result = $this->walkin_model->get_transactions_paginated($filters, $limit, $offset);

        $this->data['rows']        = $result['rows'];
        $this->data['total']       = $result['total'];
        $this->data['page']        = $page;
        $this->data['limit']       = $limit;
        $this->data['filters']     = $filters;
        $this->data['message']     = $this->session->flashdata('message');
        $this->load->view('app/walkin/history', $this->data);
    }

	public function fulfillment_report()
	{
		if (!has_role(array('admin', 'cashier'))) {
			redirect(base_url() . 'access_denied');
			return;
		}
		$date_from = trim((string)$this->input->get('date_from'));
		$date_to = trim((string)$this->input->get('date_to'));
		if ($date_from === '') $date_from = date('Y-m-d');
		if ($date_to === '') $date_to = $date_from;
		$this->walkin_order_model->ensure_walkin_schema();
		$this->data['rows'] = $this->walkin_order_model->get_fulfillment_reconciliation($date_from, $date_to);
		$this->data['date_from'] = $date_from;
		$this->data['date_to'] = $date_to;
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/walkin/fulfillment_report', $this->data);
	}

    /* ═══════════════════════════════════════════════════════
     * AJAX — CLIENT SEARCH
     * ═══════════════════════════════════════════════════════ */

    public function search_clients_json()
    {
        $term    = trim((string)$this->input->get('q'));
        $clients = $this->walkin_model->search_clients($term, 20);
        $this->output->set_content_type('application/json')->set_output(json_encode($clients));
    }
}
