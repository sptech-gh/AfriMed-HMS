<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Insurance_company_model extends CI_Model{
	
	public function __construct(){
		parent::__construct();	
	}

	/* ================================================================== */
	/*  SCHEMA MIGRATION                                                   */
	/* ================================================================== */

	private function column_exists($table, $col){
		$fields = $this->db->list_fields($table);
		return in_array($col, $fields);
	}

	public function ensure_insurance_schema(){
		if (!$this->column_exists('insurance_comp', 'insurance_type')) {
			$this->db->query("ALTER TABLE `insurance_comp` ADD COLUMN `insurance_type` varchar(30) DEFAULT 'PRIVATE'");
		}
		if (!$this->column_exists('insurance_comp', 'billing_type')) {
			$this->db->query("ALTER TABLE `insurance_comp` ADD COLUMN `billing_type` varchar(30) DEFAULT 'PRIVATE_INSURANCE'");
		}
		if (!$this->column_exists('insurance_comp', 'pricing_percentage')) {
			$this->db->query("ALTER TABLE `insurance_comp` ADD COLUMN `pricing_percentage` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentage adjustment: +10.00 = +10%, -5.00 = -5%'");
		}
		$this->_seed_default_insurance();
	}

	private function _seed_default_insurance(){
		$defaults = array(
			array('company_name' => 'None / Self-Pay', 'insurance_type' => 'NONE', 'billing_type' => 'CASH',
				  'company_address' => '', 'phone_no' => '', 'email_address' => '', 'contact_person' => '', 'notes' => 'Patient pays cash — no insurance cover'),
			array('company_name' => 'NHIS', 'insurance_type' => 'NHIS', 'billing_type' => 'NHIS',
				  'company_address' => 'National Health Insurance Authority, Ghana', 'phone_no' => '', 'email_address' => '', 'contact_person' => '', 'notes' => 'National Health Insurance Scheme'),
			array('company_name' => 'Enterprise Insurance', 'insurance_type' => 'PRIVATE', 'billing_type' => 'PRIVATE_INSURANCE',
				  'company_address' => 'Accra, Ghana', 'phone_no' => '', 'email_address' => '', 'contact_person' => '', 'notes' => ''),
			array('company_name' => 'Glico Healthcare', 'insurance_type' => 'PRIVATE', 'billing_type' => 'PRIVATE_INSURANCE',
				  'company_address' => 'Accra, Ghana', 'phone_no' => '', 'email_address' => '', 'contact_person' => '', 'notes' => ''),
			array('company_name' => 'Star Health Insurance', 'insurance_type' => 'PRIVATE', 'billing_type' => 'PRIVATE_INSURANCE',
				  'company_address' => 'Accra, Ghana', 'phone_no' => '', 'email_address' => '', 'contact_person' => '', 'notes' => ''),
			array('company_name' => 'Acacia Health Insurance', 'insurance_type' => 'PRIVATE', 'billing_type' => 'PRIVATE_INSURANCE',
				  'company_address' => 'Accra, Ghana', 'phone_no' => '', 'email_address' => '', 'contact_person' => '', 'notes' => ''),
		);
		foreach ($defaults as $d) {
			$q = $this->db->query("SELECT in_com_id FROM insurance_comp WHERE company_name = ? AND InActive = 0 LIMIT 1", array($d['company_name']));
			if ($q->num_rows() === 0) {
				$this->db->insert('insurance_comp', array(
					'company_name'    => $d['company_name'],
					'company_address' => $d['company_address'],
					'phone_no'        => $d['phone_no'],
					'email_address'   => $d['email_address'],
					'contact_person'  => $d['contact_person'],
					'fax_no'          => '',
					'contact_no_person' => '',
					'contact_email'   => '',
					'notes'           => $d['notes'],
					'insurance_type'  => $d['insurance_type'],
					'billing_type'    => $d['billing_type'],
					'InActive'        => 0
				));
			}
		}
	}

	/* ================================================================== */
	/*  LOOKUP HELPERS                                                     */
	/* ================================================================== */

	public function get_active_insurance_list(){
		$this->db->order_by('FIELD(insurance_type, "NONE", "NHIS", "PRIVATE", "CORPORATE"), company_name', '', false);
		$this->db->where('InActive', 0);
		return $this->db->get('insurance_comp')->result();
	}

	public function get_insurance_by_id($id){
		$q = $this->db->get_where('insurance_comp', array('in_com_id' => (int)$id, 'InActive' => 0), 1);
		return $q ? $q->row() : null;
	}

	public function get_billing_type_for_insurance($insurance_id){
		$row = $this->get_insurance_by_id($insurance_id);
		if (!$row) return 'CASH';
		$bt = strtoupper(trim((string)(isset($row->billing_type) ? $row->billing_type : '')));
		return ($bt !== '') ? $bt : 'CASH';
	}
	
	public function getAll($limit = 10, $offset = 0){
		$this->db->order_by('company_name','asc');
		$search = $this->db->escape_like_str($this->input->post('search'));
		$where = "(
				company_name LIKE '%{$search}%' ESCAPE '!' OR 
				company_address LIKE '%{$search}%' ESCAPE '!' OR 
				email_address LIKE '%{$search}%' ESCAPE '!'
				) 
				AND InActive = 0";
		$this->db->where($where);
		$query = $this->db->get("insurance_comp", $limit, $offset);
		return $query->result();
	}
	
	public function count_all(){
		$this->db->order_by('company_name','asc');
		$search = $this->db->escape_like_str($this->input->post('search'));
		$where = "(
				company_name LIKE '%{$search}%' ESCAPE '!' OR 
				company_address LIKE '%{$search}%' ESCAPE '!' OR 
				email_address LIKE '%{$search}%' ESCAPE '!'
				) 
				AND InActive = 0";
		$this->db->where($where);
		$query = $this->db->get("insurance_comp");
		return $query->num_rows();
	}
	
	
	public function validate_company_edit(){
		$this->db->where(array(
			'company_name'	=>		$this->input->post('company_name'),
			'in_com_id !='	=>		$this->input->post('id'),
			'InActive'		=>		0
		));
		$query = $this->db->get("insurance_comp");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function validate_company(){
		$this->db->where(array(
			'company_name'	=>		$this->input->post('company_name'),
			'InActive'		=>		0
		));
		$query = $this->db->get("insurance_comp");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function delete($id){
		$this->data = array(
			'InActive'			=>		1
		);	
		$this->db->where("in_com_id",$id);
		$this->db->update("insurance_comp",$this->data);
	}
	
	public function edit_save(){
		$this->data = array(
			'company_name'			=>		$this->input->post('company_name'),
			'company_address'		=>		$this->input->post('address'),
			'phone_no'				=>		$this->input->post('phone_no'),
			'fax_no'				=>		$this->input->post('fax_no'),
			'email_address'			=>		$this->input->post('email_address'),
			'contact_person'		=>		$this->input->post('contact_person'),
			'contact_no_person'		=>		$this->input->post('contact_no_person'),
			'contact_email'			=>		$this->input->post('contact_email'),
			'notes'					=>		$this->input->post('remarks'),
			'insurance_type'		=>		$this->input->post('insurance_type'),
			'billing_type'			=>		$this->input->post('billing_type'),
			'pricing_percentage'	=>		(float)$this->input->post('pricing_percentage')
		);	
		$this->db->where("in_com_id",$this->input->post('id'));
		$this->db->update("insurance_comp",$this->data);
	}
	
	public function save(){
		$this->data = array(
			'company_name'			=>		$this->input->post('company_name'),
			'company_address'		=>		$this->input->post('address'),
			'phone_no'				=>		$this->input->post('phone_no'),
			'fax_no'				=>		$this->input->post('fax_no'),
			'email_address'			=>		$this->input->post('email_address'),
			'contact_person'		=>		$this->input->post('contact_person'),
			'contact_no_person'		=>		$this->input->post('contact_no_person'),
			'contact_email'			=>		$this->input->post('contact_email'),
			'notes'					=>		$this->input->post('remarks'),
			'insurance_type'		=>		$this->input->post('insurance_type'),
			'billing_type'			=>		$this->input->post('billing_type'),
			'pricing_percentage'	=>		(float)$this->input->post('pricing_percentage'),
			'InActive'				=>		0
		);	
		$this->db->insert("insurance_comp",$this->data);
	}
	
	public function getInsurance_company($id){
		$query = $this->db->get_where("insurance_comp",array("in_com_id"=>$id));	
		return $query->row();
	}

	/**
	 * Get company pricing percentage for bill calculation
	 * Returns decimal percentage (e.g., 10.00 for +10%, -5.00 for -5%)
	 */
	public function get_company_pricing_percentage($insurance_id){
		if (empty($insurance_id)) return 0.00;
		$row = $this->get_insurance_by_id($insurance_id);
		if (!$row) return 0.00;
		return isset($row->pricing_percentage) ? (float)$row->pricing_percentage : 0.00;
	}

	/**
	 * Apply company pricing adjustment to base amount
	 * Returns array with original, adjusted, and difference
	 */
	public function apply_company_pricing($base_amount, $insurance_id){
		// Delegate to Price_engine_model (Single Source of Truth for pricing math).
		$this->load->model('app/Price_engine_model', 'price_engine_model');
		$res = $this->price_engine_model->apply_company_pricing($base_amount, $insurance_id);
		// Preserve legacy return shape (insurance_id key) for backward compatibility.
		$res['insurance_id'] = $insurance_id;
		return $res;
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
}