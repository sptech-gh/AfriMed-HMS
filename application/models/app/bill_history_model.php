<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Bill_history_model extends CI_Model{
	
	public function __construct(){
		parent::__construct();	
	}

	public function table_exists($table_name){
		$table_name = (string) $table_name;
		$q = $this->db->query("SHOW TABLES LIKE ".$this->db->escape($table_name));
		return ($q && $q->num_rows() > 0);
	}

	public function get_paid_amount($invoice_no){
		$invoice_no = (string)$invoice_no;
		if (!$this->table_exists('iop_receipt')) {
			return 0.0;
		}
		$this->db->select('SUM(amountPaid) AS paid_amount', false);
		$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
		$q = $this->db->get('iop_receipt');
		$r = $q ? $q->row() : null;
		return ($r && isset($r->paid_amount)) ? (float)$r->paid_amount : 0.0;
	}
	
	public function getAll($limit = 10, $offset = 0){
		$this->db->select("
					A.invoice_no,
					A.receipt_no,
					A.iop_id,
					A.patient_no,
					A.dDate,
					A.total_amount,
					A.total_purchased,
					(SELECT SUM(R.amountPaid) FROM iop_receipt R WHERE R.invoice_no = A.invoice_no AND R.InActive = 0) AS paid_amount,
					concat(B.firstname,' ',B.lastname) as patient
					",false);
		$search = (string)$this->input->post('search');
		$cFrom = trim((string)$this->input->post('cFrom'));
		$cTo = trim((string)$this->input->post('cTo'));
		if ($cFrom === '') {
			$cFrom = date('Y-m-d');
		}
		if ($cTo === '') {
			$cTo = date('Y-m-d');
		}
		$searchEsc = $this->db->escape_like_str($search);
		$where = "(
				A.invoice_no like '%".$searchEsc."%' or 
				A.iop_id like '%".$searchEsc."%' or 
				A.patient_no like '%".$searchEsc."%' or 
				B.firstname like '%".$searchEsc."%' or
				B.lastname like '%".$searchEsc."%'
				) and 
				A.dDate between ".$this->db->escape($cFrom)." and ".$this->db->escape($cTo)." 
				and A.InActive = 0";
		$this->db->where($where);
		$this->db->order_by('A.invoice_no','asc');
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$query = $this->db->get("iop_billing A", $limit, $offset);
		return $query->result();
	}
	
	public function count_all(){
		$this->db->select("
					A.invoice_no,
					A.iop_id,
					A.patient_no,
					A.dDate,
					A.total_amount,
					A.total_purchased,
					concat(B.firstname,' ',B.lastname) as patient
					",false);
		$search = (string)$this->input->post('search');
		$cFrom = trim((string)$this->input->post('cFrom'));
		$cTo = trim((string)$this->input->post('cTo'));
		if ($cFrom === '') {
			$cFrom = date('Y-m-d');
		}
		if ($cTo === '') {
			$cTo = date('Y-m-d');
		}
		$searchEsc = $this->db->escape_like_str($search);
		$where = "(
				A.invoice_no like '%".$searchEsc."%' or 
				A.iop_id like '%".$searchEsc."%' or 
				A.patient_no like '%".$searchEsc."%' or 
				B.firstname like '%".$searchEsc."%' or
				B.lastname like '%".$searchEsc."%'
				) and 
				A.dDate between ".$this->db->escape($cFrom)." and ".$this->db->escape($cTo)."  
				and A.InActive = 0";
		$this->db->where($where);
		$this->db->order_by('A.invoice_no','asc');
		$this->db->join("patient_personal_info B","A.patient_no = B.patient_no","left outer");
		$this->db->join("patient_details_iop C","C.patient_no = B.patient_no","left outer");
		$query = $this->db->get("iop_billing A");
		return $query->num_rows();
	}
	
	
	public function getHeader($invoiceno){
		$this->db->where("invoice_no",$invoiceno);
		$query = $this->db->get("iop_billing");
		return $query->row();
	}
	
	public function details($invoiceno){
		$invoiceno = (string)$invoiceno;
		if ($this->table_exists('iop_billing_line_meta')) {
			$this->db->select('T.*, M.source_module, M.source_ref');
			$this->db->from('iop_billing_t T');
			$this->db->join('iop_billing_line_meta M', 'M.invoice_no = T.invoice_no AND M.detail_id = T.id AND M.InActive = 0', 'left');
			$this->db->where(array('T.invoice_no' => $invoiceno, 'T.InActive' => 0));
			$q = $this->db->get();
			return $q ? $q->result() : array();
		}
		$this->db->where(array(
			'invoice_no'	=>		$invoiceno,
			'InActive'		=>		0
		));
		$query = $this->db->get("iop_billing_t");
		return $query->result();
	}
	
	public function patientInfo($invoiceno){
		$this->db->select("
				concat(B.firstname,' ',B.lastname) as patient,
				C.IO_ID,
				C.patient_no,
				C.patient_type,
				C.date_visit,
				C.time_visit
		",false);
		$this->db->where("A.invoice_no",$invoiceno);
		$this->db->join("patient_personal_info B","A.patient_no = B.patient_no","left outer");
		$this->db->join("patient_details_iop C","A.iop_id = C.IO_ID","left outer");
		$query = $this->db->get("iop_billing A");
		return $query->row();
	}
	
	
	
	
	
	
	
}