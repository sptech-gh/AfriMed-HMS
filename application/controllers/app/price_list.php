<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

/**
 * Price List Controller
 * Centralized management of ALL billing prices
 */
class Price_list extends General
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('app/price_list_model');
        if (General::is_logged_in() == FALSE) {
            redirect(base_url() . 'login');
        }
        General::variable();
        require_role('admin');
    }

	public function import_hebrew_lab_prices()
	{
		$run = (string)$this->input->get('run');
		if ($run !== '1') {
			show_error('Confirmation required. Re-run with ?run=1', 400);
			return;
		}
		$user_id = $this->session->userdata('user_id');
		$res = $this->price_list_model->import_hebrew_lab_prices($user_id);
		if (is_array($res) && !empty($res['success'])) {
			$ins = isset($res['inserted']) ? (int)$res['inserted'] : 0;
			$skp = isset($res['skipped_existing']) ? (int)$res['skipped_existing'] : 0;
			$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Lab price import complete. Inserted {$ins} items, skipped {$skp} existing.</div>");
		} else {
			$err = is_array($res) && isset($res['error']) ? (string)$res['error'] : 'Import failed';
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Lab price import failed: " . htmlspecialchars($err) . "</div>");
		}
		redirect('app/price_list?category=laboratory');
	}

    /**
     * Main price list dashboard
     */
    public function index()
    {
        $this->session->set_userdata(array(
            'tab' => 'admin',
            'module' => 'price_list'
        ));

        $category = $this->input->get('category') ?: 'all';
        $search = $this->input->get('search') ?: '';

        $this->data['title'] = 'Price List Management';
        $this->data['category'] = $category;
        $this->data['search'] = $search;
        $this->data['prices'] = $this->price_list_model->get_all_prices($category, $search);
        $this->data['categories'] = $this->price_list_model->get_categories();
        $this->data['summary'] = $this->price_list_model->get_price_summary();
        $this->data['message'] = $this->session->flashdata('message');

        $this->load->view('app/price_list/index', $this->data);
    }

    /**
     * Update a single price via AJAX
     */
    public function update_price()
    {
        if (!$this->input->is_ajax_request()) {
            show_error('Direct access not allowed', 403);
            return;
        }

        $item_type = $this->input->post('item_type');
        $item_id = $this->input->post('item_id');
        $cash_price = floatval($this->input->post('cash_price'));
        $nhis_price = floatval($this->input->post('nhis_price'));

        $result = $this->price_list_model->update_price($item_type, $item_id, $cash_price, $nhis_price);

        echo json_encode($result);
    }

    /**
     * Bulk update prices
     */
    public function bulk_update()
    {
        $updates = $this->input->post('updates');
        
        if (empty($updates)) {
            $this->session->set_flashdata('message', '<div class="alert alert-warning">No changes to save.</div>');
            redirect('app/price_list');
            return;
        }

        $count = 0;
        foreach ($updates as $update) {
            if (isset($update['item_type'], $update['item_id'])) {
                $cash_price = isset($update['cash_price']) ? floatval($update['cash_price']) : null;
                $nhis_price = isset($update['nhis_price']) ? floatval($update['nhis_price']) : null;
                
                $res = $this->price_list_model->update_price($update['item_type'], $update['item_id'], $cash_price, $nhis_price);
                if (is_array($res) && isset($res['success']) && $res['success']) {
                    $count++;
                }
            }
        }

        $this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Updated {$count} prices successfully.</div>");
        redirect('app/price_list');
    }

    /**
     * Export prices to CSV
     */
    public function export()
    {
        $category = $this->input->get('category') ?: 'all';
        $prices = $this->price_list_model->get_all_prices($category, '');

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="price_list_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Category', 'Item Name', 'Description', 'Cash Price', 'NHIS Price', 'NHIS Covered', 'Item Type', 'Item ID']);

        foreach ($prices as $item) {
            fputcsv($output, [
                $item->category_name,
                $item->item_name,
                $item->description ?: '',
                $item->cash_price,
                $item->nhis_price,
                $item->is_nhis_covered ? 'Yes' : 'No',
                $item->item_type,
                $item->item_id
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Percentage adjustment page
     */
    public function adjust()
    {
        $this->data['title'] = 'Bulk Price Adjustment';
        $this->data['categories'] = $this->price_list_model->get_categories();
        $this->data['message'] = $this->session->flashdata('message');
        
        $this->load->view('app/price_list/adjust', $this->data);
    }

    /**
     * Apply percentage adjustment
     */
    public function apply_adjustment()
    {
        $category = $this->input->post('category');
        $adjustment_type = $this->input->post('adjustment_type'); // increase or decrease
        $percentage = floatval($this->input->post('percentage'));
        $price_type = $this->input->post('price_type'); // cash, nhis, or both

        if ($percentage <= 0 || $percentage > 100) {
            $this->session->set_flashdata('message', '<div class="alert alert-danger">Invalid percentage. Must be between 0.01 and 100.</div>');
            redirect('app/price_list/adjust');
            return;
        }

        $count = $this->price_list_model->apply_percentage_adjustment($category, $adjustment_type, $percentage, $price_type);

        $action = $adjustment_type === 'increase' ? 'increased' : 'decreased';
        $this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Successfully {$action} {$count} prices by {$percentage}%.</div>");
        redirect('app/price_list');
    }
}
