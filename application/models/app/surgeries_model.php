<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Surgeries_model extends CI_Model{
	
	public function __construct(){
		parent::__construct();	
	}
	
	public function getAll($limit = 10, $offset = 0){
		$this->db->order_by('surgery_name','asc');
		$search = $this->db->escape_like_str($this->input->post('search'));
		$where = "(surgery_name LIKE '%{$search}%' ESCAPE '!' OR surgery_desc LIKE '%{$search}%' ESCAPE '!') 
				AND InActive = 0";
		$this->db->where($where);
		$query = $this->db->get("surgical_package", $limit, $offset);
		return $query->result();
	}
	
	public function count_all(){
		$this->db->order_by('surgery_name','asc');
		$search = $this->db->escape_like_str($this->input->post('search'));
		$where = "(surgery_name LIKE '%{$search}%' ESCAPE '!' OR surgery_desc LIKE '%{$search}%' ESCAPE '!') 
				AND InActive = 0";
		$this->db->where($where);
		$query = $this->db->get("surgical_package");
		return $query->result();
	}
	
	
}