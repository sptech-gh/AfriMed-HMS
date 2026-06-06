<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Laboratory_model extends CI_Model
{

	public function __construct()
	{
		parent::__construct();
	}

	public function pending_labs()
	{
		$query = $this->db->query("SELECT l.*,CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) AS patient_name,bp.particular_name, ppi.patient_no,ppi.birthday,l.laboratory_text FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no JOIN bill_particular bp ON bp.particular_id = l.laboratory_id WHERE l.result = '' and l.InActive = 0
		UNION 
		SELECT l.*,CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) AS patient_name,l.laboratory_text as bparticular_name, ppi.patient_no,ppi.birthday,l.laboratory_text FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no  WHERE l.result = '' AND l.category_id = 'others' and l.InActive = 0");
		return $query->result();
	}

	public function pending_lab_requests($limit = 10, $offset = 0)
	{
		$limit = (int)$limit;
		$offset = (int)$offset;
		$query = $this->db->query(
			"SELECT DISTINCT(l.iop_id),CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) AS patient_name, ppi.patient_no,ppi.birthday,l.dDate FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no WHERE l.result = '' and l.InActive = 0 LIMIT ? OFFSET ?",
			array($limit, $offset)
		);
		return $query->result();
	}

	public function getAll($limit = 10, $offset = 0, $iop_id)
	{
		$limit = (int)$limit;
		$offset = (int)$offset;
		$iop_id = $this->db->escape($iop_id);
		$query = $this->db->query(
			"SELECT l.*,CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) AS patient_name,bp.particular_name, ppi.patient_no,ppi.birthday,l.laboratory_text FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no JOIN bill_particular bp ON bp.particular_id = l.laboratory_id WHERE l.result = '' and l.iop_id={$iop_id} and l.InActive = 0
		UNION 
		SELECT l.*,CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) AS patient_name,l.laboratory_text as bparticular_name, ppi.patient_no,ppi.birthday,l.laboratory_text FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no WHERE l.result = '' AND l.category_id = 'others' and l.iop_id={$iop_id} and l.InActive = 0 LIMIT ? OFFSET ?",
			array($limit, $offset)
		);
		return $query->result();
	}


	public function getAllzz($limit = 10, $offset = 0, $iop_id)
	{
		$limit = (int)$limit;
		$offset = (int)$offset;
		$iop_id = $this->db->escape($iop_id);
		$query = $this->db->query(
			"SELECT l.*,CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) AS patient_name,bp.particular_name, ppi.patient_no,ppi.birthday,l.laboratory_text FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no JOIN bill_particular bp ON bp.particular_id = l.laboratory_id WHERE l.result = '' and l.iop_id={$iop_id} and l.InActive = 0
		UNION 
		SELECT l.*,CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) AS patient_name,l.laboratory_text as bparticular_name, ppi.patient_no,ppi.birthday,l.laboratory_text FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no WHERE l.result = '' AND l.category_id = 'others' and l.iop_id={$iop_id} and l.InActive = 0 LIMIT ? OFFSET ?",
			array($limit, $offset)
		);
		return $query->result();
	}

	// public function getAll($limit = 10, $offset = 0){
	// 	$this->db->order_by('io_lab_id','asc');
	// 	$where = "result = '' ";
	// 	$this->db->where($where);
	// 	$query = $this->db->get("iop_laboratory", $limit, $offset);
	// 	return $query->result();
	// }

	public function count_all_pending_request()
	{
		$query = $this->db->query("SELECT count(DISTINCT(l.iop_id)) FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no JOIN bill_particular bp ON bp.particular_id = l.laboratory_id  WHERE l.result = '' and l.InActive = 0 ");
		// $query = $this->db->get("iop_laboratory");
		return $query->num_rows();
		// return print_r($query);
	}

	public function count_all()
	{
		$this->db->order_by('io_lab_id', 'asc');
		$where = "result = '' ";
		$this->db->where($where);
		$query = $this->db->get("iop_laboratory");
		return $query->num_rows();
	}


	public function lab_enquiry()
	{
		$from = $this->input->post('cFrom');
		$to = $this->input->post('cTo');
		$query = $this->db->query("SELECT DISTINCT(l.iop_id),CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) AS patient_name, ppi.patient_no,l.dDate FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no  WHERE DATE(l.dDate) BETWEEN DATE(?) AND DATE(?) AND l.result != '' AND l.InActive = 0", array($from, $to));
		return $query->result();
	}



	public function upload_lab_result_pdf($lab_data = array(), $io_lab_id)
	{
		$this->data = array(
			'lab_result_upload'	=>	$lab_data['file_name'],
			'findings' => 'uploaded',
			'result' => 'uploaded'
		);
		// var_dump($io_lab_id);
		$this->db->where('io_lab_id', $io_lab_id);
		$this->db->update('iop_laboratory', $this->data);
	}




	public function validate_complain_edit()
	{
		$this->db->where(array(
			'complain_name'		=>		$this->input->post('complain'),
			'complain_id !='	=>		$this->input->post('id'),
			'InActive'			=>		0
		));
		$query = $this->db->get("complain");
		if ($query->num_rows() > 0) {
			return true;
		} else {
			return false;
		}
	}

	public function validate_complain()
	{
		$this->db->where(array(
			'complain_name'	=>		$this->input->post('complain'),
			'InActive'			=>		0
		));
		$query = $this->db->get("complain");
		if ($query->num_rows() > 0) {
			return true;
		} else {
			return false;
		}
	}

	public function delete($id)
	{
		$this->data = array(
			'InActive'		=> 1
		);
		$this->db->where("io_lab_id", $id);
		$this->db->update('iop_laboratory', $this->data);
	}

	public function edit_save()
	{
		if (!$this->input->post('findings')) {
			$findings = '';
		} else {
			$findings = $this->input->post('findings');
		}

		if (!$this->input->post('result')) {
			$result = '';
		} else {
			$result = $this->input->post('result');
		}

		$this->data = array(
			'findings'		=> $findings,
			'result'		=> $result
		);
		$this->db->where("io_lab_id", $this->input->post('io_lab_id'));
		$this->db->update('iop_laboratory', $this->data);
	}

	public function save()
	{
		$this->data = array(
			'complain_name'		=> strtoupper($this->input->post('complain')),
			'complain_desc'		=> $this->input->post('description'),
			'InActive'			=> 0
		);
		$this->db->insert('complain', $this->data);
	}

	public function getComplain($id)
	{
		$query = $this->db->get_where("complain", array("complain_id" => $id));
		return $query->row();
	}
}
