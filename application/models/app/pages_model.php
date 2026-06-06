<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Pages_model extends CI_Model{
	
	public function __construct(){
		parent::__construct();	
	}
	
	public function getAll($limit = 10, $offset = 0){
		$this->db->order_by('page_module','asc');
		$search = $this->db->escape_like_str($this->input->post('search'));
		$where = "(page_name LIKE '%{$search}%' ESCAPE '!' OR page_module LIKE '%{$search}%' ESCAPE '!') 
				AND InActive = 0";
		$this->db->where($where);
		$query = $this->db->get("pages", $limit, $offset);
		return $query->result();
	}
	
	public function count_all(){
		$this->db->order_by('page_module','asc');
		$search = $this->db->escape_like_str($this->input->post('search'));
		$where = "(page_name LIKE '%{$search}%' ESCAPE '!' OR page_module LIKE '%{$search}%' ESCAPE '!') 
				AND InActive = 0";
		$this->db->where($where);
		$query = $this->db->get("pages");
		return $query->num_rows();
	}
	
	public function save(){
		$this->data = array(
			'page_module'	=>		$this->input->post('page_module'),
			'page_name'		=>		$this->input->post('page_name'),
			'page_link'		=>		$this->input->post('page_link'),
			'InActive'		=>		0
		);	
		
		$query = $this->db->insert("pages",$this->data);
		if($this->db->affected_rows() == 1){
			return true;
		}else{
			return false;
		}
	}	
	
	public function getPage($id){
		$this->db->where('page_id',$id);
		$query = $this->db->get('pages');
		return $query->row();		
	}
		
	public function update(){
		$this->data = array(
			'page_module'	=>		$this->input->post('page_module'),
			'page_name'		=>		$this->input->post('page_name'),
			'page_link'		=>		$this->input->post('page_link')
		);	
		
		$this->db->where('page_id', $this->input->post('id'));
		$query = $this->db->update("pages",$this->data);
		if($this->db->affected_rows() == 1){
			return true;
		}else{
			return false;
		}
	}		
	
	public function delete($id){
		$this->data = array('InActive' =>  1);
		$this->db->where('page_id',$id);
		$query =  $this->db->update("pages",$this->data);	
		if($this->db->affected_rows() == 1){
			return true;
		}else{
			return false;
		}
	}
		
	
}