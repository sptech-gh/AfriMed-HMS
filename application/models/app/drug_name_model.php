<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Drug_name_model extends CI_Model{
	
	public function __construct(){
		parent::__construct();	
	}

	private function column_exists_safe($table, $column){
		$q = $this->db->query("SHOW COLUMNS FROM `".str_replace('`','',$table)."` LIKE ".$this->db->escape($column));
		return ($q && $q->num_rows() > 0);
	}
	
	public function getAll($limit = 10, $offset = 0){
		$this->db->select("
				A.drug_id,
				A.drug_name,
				D.med_category_name,
				B.cValue as 'cType',
				A.nPrice,
				A.drug_desc
		");
		$this->db->order_by('A.drug_name','asc');
		$search = $this->db->escape_like_str($this->input->post('search'));
		$where = "(
				A.drug_name LIKE '%{$search}%' ESCAPE '!' OR 
				A.drug_desc LIKE '%{$search}%' ESCAPE '!' OR 
				D.med_category_name LIKE '%{$search}%' ESCAPE '!'
				) 
				AND A.InActive = 0";
		$this->db->where($where);
		$this->db->join("system_parameters B","B.param_id = A.cType","left outer");
		$this->db->join("system_parameters C","C.param_id = A.uom","left outer");
		$this->db->join("medicine_category D","D.cat_id = A.med_cat_id","left outer");
		$query = $this->db->get("medicine_drug_name A", $limit, $offset);
		return $query->result();
	}
	
	public function count_all(){
		$this->db->select("
				A.drug_name,
				D.med_category_name,
				B.cValue,
				A.nPrice,
				A.drug_desc
		");
		$this->db->order_by('A.drug_name','asc');
		$search = $this->db->escape_like_str($this->input->post('search'));
		$where = "(
				A.drug_name LIKE '%{$search}%' ESCAPE '!' OR 
				A.drug_desc LIKE '%{$search}%' ESCAPE '!' OR 
				D.med_category_name LIKE '%{$search}%' ESCAPE '!'
				) 
				AND A.InActive = 0";
		$this->db->where($where);
		$this->db->join("system_parameters B","B.param_id = A.cType","left outer");
		$this->db->join("system_parameters C","C.param_id = A.uom","left outer");
		$this->db->join("medicine_category D","D.cat_id = A.med_cat_id","left outer");
		$query = $this->db->get("medicine_drug_name A");
		return $query->num_rows();
	}
	
	public function getType(){
		$this->db->where(array(
			'cCode'		=>		'type_medicine',
			'InActive'	=>		0
		));
		$this->db->order_by("cValue","ASC");
		$query = $this->db->get("system_parameters");	
		return $query->result();
	}
	
	public function getCategory(){
		$this->db->where("InActive","0");
		$this->db->order_by("med_category_name","ASC");
		$query = $this->db->get("medicine_category");	
		return $query->result();
	}
	
	public function getUOM(){
		$this->db->where(array(
			'cCode'		=>		'medicine_uom',
			'InActive'	=>		0
		));
		$this->db->order_by("cValue","ASC");
		$query = $this->db->get("system_parameters");	
		return $query->result();
	}

	public function getDefaultUOM(){
		$this->db->where(array(
			'cCode'		=>		'medicine_uom',
			'InActive'	=>		0
		));
		$this->db->order_by("cValue","ASC");
		$query = $this->db->get("system_parameters", 1);
		$row = $query->row();
		return $row ? $row->param_id : 0;
	}

	public function getDrugForms(){
		$forms = array();
		if ($this->db->table_exists('medication_form')) {
			$rows = $this->db->select('form')
				->where('is_active', 1)
				->order_by('form')
				->get('medication_form')
				->result();
			foreach ($rows as $row) {
				if (isset($row->form) && trim((string)$row->form) !== '') {
					$forms[] = trim((string)$row->form);
				}
			}
		}
		if (empty($forms)) {
			$forms = array('Tablet','Capsule','Syrup','Injection','Suspension','Cream','Ointment','Drops','Inhaler','Patch');
		}
		return $forms;
	}
	
	public function validate_drugName_edit(){
		$this->db->where(array(
			'med_cat_id'	=>		$this->input->post('category'),
			'drug_name'		=>		$this->input->post('drug_name'),
			'drug_id !='		=>		$this->input->post('id'),
			'InActive'		=>		0
		));
		$query = $this->db->get("medicine_drug_name");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function validate_drugName(){
		$this->db->where(array(
			'med_cat_id'	=>		$this->input->post('category'),
			'drug_name'		=>		$this->input->post('drug_name'),
			'InActive'		=>		0
		));
		$query = $this->db->get("medicine_drug_name");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function save(){
		$price = $this->input->post('price');
		$cashPrice = $this->input->post('cash_price');
		if ($cashPrice === null || trim((string)$cashPrice) === '' || (float)$cashPrice <= 0) {
			$cashPrice = $price;
		}
		$this->data = array(
			'drug_name'			=> 	strtoupper($this->input->post('drug_name')),
			'med_cat_id'		=> 	$this->input->post('category'),
			'cType'				=> 	$this->input->post('cType'),
			'drug_desc'			=> 	$this->input->post('description'),
			'uom'				=> 	$this->input->post('uom'),
			're_order_level'	=> 	$this->input->post('reorder'),
			'nPrice'			=> 	$price,
			'nStock'			=> 	$this->input->post('stock'),
			'InActive'			=> 	0
		);
		if ($this->column_exists_safe('medicine_drug_name', 'is_nhis_covered')) {
			$this->data['is_nhis_covered'] = $this->input->post('is_nhis_covered') ? 1 : 0;
			$this->data['nhis_price'] = (float)$this->input->post('nhis_price');
			$this->data['cash_price'] = (float)$cashPrice;
		}
		if ($this->column_exists_safe('medicine_drug_name', 'generic_name')) {
			$this->data['generic_name'] = trim((string)$this->input->post('generic_name'));
		}
		if ($this->column_exists_safe('medicine_drug_name', 'dosage_form')) {
			$this->data['dosage_form'] = trim((string)$this->input->post('dosage_form'));
		}
		if ($this->column_exists_safe('medicine_drug_name', 'strength')) {
			$this->data['strength'] = trim((string)$this->input->post('strength'));
		}
		$this->db->insert('medicine_drug_name',$this->data);
	}
	
	public function edit_save(){
		$price = $this->input->post('price');
		$cashPrice = $this->input->post('cash_price');
		if ($cashPrice === null || trim((string)$cashPrice) === '' || (float)$cashPrice <= 0) {
			$cashPrice = $price;
		}
		$this->data = array(
			'drug_name'			=> 	strtoupper($this->input->post('drug_name')),
			'med_cat_id'		=> 	$this->input->post('category'),
			'cType'				=> 	$this->input->post('cType'),
			'drug_desc'			=> 	$this->input->post('description'),
			'uom'				=> 	$this->input->post('uom'),
			're_order_level'	=> 	$this->input->post('reorder'),
			'nPrice'			=> 	$price,
			'nStock'			=> 	$this->input->post('stock')
		);
		if ($this->column_exists_safe('medicine_drug_name', 'is_nhis_covered')) {
			$this->data['is_nhis_covered'] = $this->input->post('is_nhis_covered') ? 1 : 0;
			$this->data['nhis_price'] = (float)$this->input->post('nhis_price');
			$this->data['cash_price'] = (float)$cashPrice;
		}
		if ($this->column_exists_safe('medicine_drug_name', 'generic_name')) {
			$this->data['generic_name'] = trim((string)$this->input->post('generic_name'));
		}
		if ($this->column_exists_safe('medicine_drug_name', 'dosage_form')) {
			$this->data['dosage_form'] = trim((string)$this->input->post('dosage_form'));
		}
		if ($this->column_exists_safe('medicine_drug_name', 'strength')) {
			$this->data['strength'] = trim((string)$this->input->post('strength'));
		}
		$this->db->where("drug_id",$this->input->post('id'));
		$this->db->update('medicine_drug_name',$this->data);
	}
	
	public function delete($id){
		$this->data = array(
			'InActive'			=> 	1
		);	
		$this->db->where("drug_id",$id);
		$this->db->update('medicine_drug_name',$this->data);
	}
	
	public function getDrugName($id){
		$query = $this->db->get_where("medicine_drug_name",array('drug_id' => $id));	
		return $query->row();
	}
	
	
	
	
	
	
	
	
	
	
	
	
}
