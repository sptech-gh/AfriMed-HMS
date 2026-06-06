<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Particular_bills_model extends CI_Model{
	
	public function __construct(){
		parent::__construct();	
	}

	private function column_exists_safe($table, $column){
		$q = $this->db->query("SHOW COLUMNS FROM `".str_replace('`','',$table)."` LIKE ".$this->db->escape($column));
		return ($q && $q->num_rows() > 0);
	}
	
	public function getAll($limit = 10, $offset = 0){
		$this->db->select("A.particular_id,A.particular_name,B.group_name,A.particular_desc,A.charge_amount");
		$this->db->order_by('A.particular_name','asc');
		$search = trim((string)$this->session->userdata("search_particular_bill"));
		$this->db->where('A.InActive', 0);
		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('A.particular_name', $search);
			$this->db->or_like('A.particular_desc', $search);
			$this->db->or_like('B.group_name', $search);
			$this->db->group_end();
		}
		$this->db->join("bill_group_name B","B.group_id = A.group_id","left outer");
		$query = $this->db->get("bill_particular A", $limit, $offset);
		return $query->result();
	}
	
	public function count_all(){
		$this->db->select("A.particular_name,B.group_name,A.particular_desc,A.charge_amount");
		$this->db->order_by('A.particular_name','asc');
		$search = trim((string)$this->session->userdata("search_particular_bill"));
		$this->db->where('A.InActive', 0);
		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('A.particular_name', $search);
			$this->db->or_like('A.particular_desc', $search);
			$this->db->or_like('B.group_name', $search);
			$this->db->group_end();
		}
		$this->db->join("bill_group_name B","B.group_id = A.group_id","left outer");
		$query = $this->db->get("bill_particular A");
		return $query->num_rows();
	}
	
	
	public function group_name(){
		$this->db->where("InActive","0");
		$this->db->order_by("group_name","ASC");
		$query = $this->db->get("bill_group_name");	
		return $query->result();
	}
	
	public function validate_particular_name_edit(){
		$this->db->where(array(
			'group_id'			=>		$this->input->post('group_name'),
			'particular_name'	=>		$this->input->post('partcular_name'),
			'particular_id !='	=>		$this->input->post('id'),
			'InActive'			=>		0
		));
		$query = $this->db->get("bill_particular");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function validate_particular_name(){
		$this->db->where(array(
			'group_id'			=>		$this->input->post('group_name'),
			'particular_name'	=>		$this->input->post('partcular_name'),
			'InActive'			=>		0
		));
		$query = $this->db->get("bill_particular");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function save(){
		$this->data = array(
			'group_id'			=>		$this->input->post('group_name'),
			'particular_name'	=>		strtoupper($this->input->post('partcular_name')),
			'particular_desc'	=>		$this->input->post('description'),
			'charge_amount'		=>		$this->input->post('amount'),
			'InActive'			=>		0
		);
		if ($this->column_exists_safe('bill_particular', 'is_nhis_covered')) {
			$this->data['is_nhis_covered'] = $this->input->post('is_nhis_covered') ? 1 : 0;
			$this->data['nhis_charge_amount'] = (float)$this->input->post('nhis_charge_amount');
		}
		$this->db->insert("bill_particular",$this->data);
	}
	
	public function getBillName($id){
		$select = "A.particular_id,A.particular_name,B.group_name,B.group_id,A.particular_desc,A.charge_amount";
		if ($this->column_exists_safe('bill_particular', 'is_nhis_covered')) {
			$select .= ",A.is_nhis_covered,A.nhis_charge_amount";
		}
		$this->db->select($select);
		$this->db->where("A.particular_id",$id);
		$this->db->join("bill_group_name B","B.group_id = A.group_id","left outer");
		$query = $this->db->get("bill_particular A");
		return $query->row();
	}
	
	public function edit_save(){
		$this->data = array(
			'group_id'			=>		$this->input->post('group_name'),
			'particular_name'	=>		strtoupper($this->input->post('partcular_name')),
			'particular_desc'	=>		$this->input->post('description'),
			'charge_amount'		=>		$this->input->post('amount')
		);
		if ($this->column_exists_safe('bill_particular', 'is_nhis_covered')) {
			$this->data['is_nhis_covered'] = $this->input->post('is_nhis_covered') ? 1 : 0;
			$this->data['nhis_charge_amount'] = (float)$this->input->post('nhis_charge_amount');
		}
		$this->db->where("particular_id",$this->input->post('id'));
		$this->db->update("bill_particular",$this->data);
	}
	
	public function delete($id){
		$this->data = array(
			'InActive'			=>		1
		);	
		$this->db->where("particular_id",$id);
		$this->db->update("bill_particular",$this->data);
	}
	
	
	
	
	
	
	
}