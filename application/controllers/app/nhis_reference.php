<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php';

class Nhis_reference extends General {

	private $allowed_types = array('tariffs', 'icd10', 'gdrg', 'medicines', 'service_codes');

	public function __construct(){
		parent::__construct();
		if(General::is_logged_in() == FALSE){
			redirect(base_url().'login');
		}
		General::variable();
		require_role(array('admin'));
		$this->load->model('app/Nhis_reference_model', 'nhis_ref');
	}

	public function index(){
		$this->session->set_userdata(array(
			'tab'       => 'nhis',
			'module'    => 'nhis_reference',
			'subtab'    => '',
			'submodule' => ''
		));

		$this->nhis_ref->ensure_reference_schema();

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['datasets'] = $this->_get_dataset_info();
		$this->load->view('app/nhis/reference_data', $this->data);
	}

	public function import($type = ''){
		$this->session->set_userdata(array(
			'tab'       => 'nhis',
			'module'    => 'nhis_reference',
			'subtab'    => '',
			'submodule' => ''
		));

		$type = strtolower(trim((string)$type));
		if (!in_array($type, $this->allowed_types)) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Invalid dataset type.</div>');
			redirect(base_url().'app/nhis_reference');
			return;
		}

		$version = trim((string)$this->input->post('version'));
		if ($version === '') {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Version is required.</div>');
			redirect(base_url().'app/nhis_reference');
			return;
		}

		if (empty($_FILES['csv_file']['name']) || empty($_FILES['csv_file']['tmp_name'])) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">No file selected.</div>');
			redirect(base_url().'app/nhis_reference');
			return;
		}

		$dir = APPPATH . 'cache/nhis_imports';
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		$dest = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $type . '_' . date('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$_FILES['csv_file']['name']);
		if (!@move_uploaded_file($_FILES['csv_file']['tmp_name'], $dest)) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Upload failed.</div>');
			redirect(base_url().'app/nhis_reference');
			return;
		}

		$method = 'import_' . $type;
		if (!method_exists($this->nhis_ref, $method)) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Import method not available.</div>');
			redirect(base_url().'app/nhis_reference');
			return;
		}

		$result = $this->nhis_ref->$method($dest, $version);
		if (!empty($result['success'])) {
			$msg = strtoupper($type) . ' import complete. Inserted: ' . (int)$result['inserted'];
			$this->session->set_flashdata('message', '<div class="alert alert-success">' . htmlspecialchars($msg) . '</div>');
		} else {
			$err = isset($result['error']) ? (string)$result['error'] : 'Import failed';
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($err) . '</div>');
		}

		redirect(base_url().'app/nhis_reference');
	}

	private function _get_dataset_info(){
		$tables = array(
			'tariffs' => 'nhis_ref_tariffs',
			'icd10' => 'nhis_ref_icd10',
			'gdrg' => 'nhis_ref_gdrg',
			'medicines' => 'nhis_ref_medicines',
			'service_codes' => 'nhis_ref_service_codes'
		);

		$labels = array(
			'tariffs' => 'Tariffs',
			'icd10' => 'ICD-10',
			'gdrg' => 'G-DRG',
			'medicines' => 'Medicines',
			'service_codes' => 'Service Codes'
		);

		$out = array();
		foreach ($tables as $type => $table) {
			$info = array(
				'type' => $type,
				'label' => isset($labels[$type]) ? $labels[$type] : strtoupper($type),
				'table' => $table,
				'active_count' => 0,
				'total_count' => 0,
				'active_version' => null,
				'active_effective_date' => null
			);

			if ($this->db->table_exists($table)) {
				$info['total_count'] = (int)$this->db->count_all($table);
				$info['active_count'] = (int)$this->db->where('is_active', 1)->from($table)->count_all_results();
				$row = $this->db->select('version, effective_date')
					->from($table)
					->where('is_active', 1)
					->order_by('effective_date', 'DESC')
					->limit(1)
					->get()->row();
				if ($row) {
					$info['active_version'] = isset($row->version) ? (string)$row->version : null;
					$info['active_effective_date'] = isset($row->effective_date) ? (string)$row->effective_date : null;
				}
			}

			$out[] = $info;
		}
		return $out;
	}
}
