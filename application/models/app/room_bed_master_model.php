<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Room_bed_master_model extends CI_Model{
	
	public function __construct(){
		parent::__construct();	
	}
	
	public function getAll($limit = 10, $offset = 0){
		$this->db->order_by('A.bed_name','asc');
		$search = trim((string)$this->session->userdata("search_room_bed_master"));
		$this->db->where('A.InActive', 0);
		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('A.bed_name', $search);
			$this->db->or_like('B.room_name', $search);
			$this->db->or_like('C.floor_name', $search);
			$this->db->or_like('D.category_name', $search);
			$this->db->group_end();
		}
		$this->db->join("room_master B","B.room_master_id = A.room_master_id","left outer");
		$this->db->join("floor C","C.floor_id = B.floor","left outer");
		$this->db->join("room_category D","D.category_id = B.category_id","left outer");
		$query = $this->db->get("room_beds A", $limit, $offset);
		return $query->result();
	}
	
	public function count_all(){
		$this->db->order_by('A.bed_name','asc');
		$search = trim((string)$this->session->userdata("search_room_bed_master"));
		$this->db->where('A.InActive', 0);
		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('A.bed_name', $search);
			$this->db->or_like('B.room_name', $search);
			$this->db->or_like('C.floor_name', $search);
			$this->db->or_like('D.category_name', $search);
			$this->db->group_end();
		}
		$this->db->join("room_master B","B.room_master_id = A.room_master_id","left outer");
		$this->db->join("floor C","C.floor_id = B.floor","left outer");
		$this->db->join("room_category D","D.category_id = B.category_id","left outer");
		$query = $this->db->get("room_beds A");
		return $query->num_rows();
	}
	
	
	public function validate_room(){
		$this->db->where(array(
			'bed_name'				=>		$this->input->post('bed_name'),
			'room_master_id'		=>		$this->input->post('room'),
			'InActive'				=>		0
		));
		$query = $this->db->get("room_beds");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function validate_room_edit(){
		$this->db->where(array(
			'bed_name'				=>		$this->input->post('bed_name'),
			'room_master_id'		=>		$this->input->post('room'),
			'room_bed_id !='		=>		$this->input->post('id'),
			'InActive'				=>		0
		));
		$query = $this->db->get("room_beds");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function save(){
		$this->data = array(
			'room_master_id'		=>		$this->input->post('room'),
			'bed_name'				=>		$this->input->post('bed_name'),
			'nStatus'				=>		'Vacant',
			'InActive'				=>		0
		);	
		$this->db->insert("room_beds",$this->data);
	}
	
	public function getRoom($id){
		$this->db->where("room_bed_id",$id);	
		$query = $this->db->get("room_beds");
		return $query->row();
	}
	
	public function edit_save(){
		$this->data = array(
			'room_master_id'		=>		$this->input->post('room'),
			'bed_name'				=>		$this->input->post('bed_name')
		);	
		$this->db->where("room_bed_id",$this->input->post('id'));	
		$this->db->update("room_beds",$this->data);
	}
	
	public function delete($id){
		$this->data = array(
			'InActive'		=>		1
		);	
		$this->db->where("room_bed_id",$id);	
		$this->db->update("room_beds",$this->data);
	}
	
	
	
	
}