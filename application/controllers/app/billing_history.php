<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php'; 

class Billing_history extends General{

	private $limit = 10;

	public function __construct(){
		parent::__construct();
		$this->load->model("app/bill_history_model");
		$this->load->model("app/billing_model");
		$this->load->model("app/cashier_model");
		if(General::is_logged_in() == FALSE){
            redirect(base_url().'login');    
        }
		General::variable();
		require_role(array('admin', 'cashier', 'doctor'));
		if (!$this->session->userdata('_schema_billing_history_ok')) {
			$this->billing_model->ensure_billing_enhancements();
			$this->cashier_model->ensure_cashier_schema();
			$this->session->set_userdata('_schema_billing_history_ok', 1);
		}
	}
	
	public function index(){
			// user restriction function
				$this->session->set_userdata('page_name','billing_history');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					if (!has_role(array('admin', 'cashier', 'doctor'))) {
						redirect(base_url().'access_denied');
					}
				}
				// end of user restriction function
				
		//$this->session->set_userdata(array('tab'=>'configuration', 'module'=>'roles'));
		$this->session->set_userdata(array(
				 'tab'			=>		'billing',
				 'module'		=>		'bill_history',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
		
		$this->billList();
	}
	
	public function billList($offset = 0){
		$uri_segment = 4;
		$offset = $this->uri->segment($uri_segment);
		
		$roles = $this->bill_history_model->getAll($this->limit, $offset);
		
		$config['base_url'] = base_url().'app/billing_history/index/';
 		$config['total_rows'] = $this->bill_history_model->count_all();
 		$config['per_page'] = $this->limit;
		
		
		$config['uri_segment'] = $uri_segment;
		$config['full_tag_open'] = '<ul class="pagination pagination no-margin pull-right">';
		$config['full_tag_close'] = '</ul><!--pagination-->';

		$config['first_link'] = '&laquo; First';
		$config['first_tag_open'] = '<li class="prev page">';
		$config['first_tag_close'] = '</li>';

		$config['last_link'] = 'Last &raquo;';
		$config['last_tag_open'] = '<li class="next page">';
		$config['last_tag_close'] = '</li>';

		$config['next_link'] = 'Next &rarr;';
		$config['next_tag_open'] = '<li class="next page">';
		$config['next_tag_close'] = '</li>';

		$config['prev_link'] = '&larr; Previous';
		$config['prev_tag_open'] = '<li class="prev page">';
		$config['prev_tag_close'] = '</li>';

		$config['cur_tag_open'] = '<li class="active"><a href="">';
		$config['cur_tag_close'] = '</a></li>';

		$config['num_tag_open'] = '<li class="page">';
		$config['num_tag_close'] = '</li>';
		
		$this->pagination->initialize($config);
		$this->data['pagination'] = $this->pagination->create_links();
	
		$tmpl = array('table_open' => '<table class="table table-hover table-striped">');
        $this->table->set_template($tmpl);
		$this->table->set_empty("&nbsp;");
		$this->table->set_heading('Invoice No.','Patient No.','IOP No.','Patient Name','Date','Amount','Status','');
		$i = 0 + $offset;
		
		
		
		$imgPrint = "<i class='fa fa-print'></i>";
		$imgdownload = "<i class='fa fa-download'></i>";
		
		foreach ($roles as $roles)
		{	
				$paidAmt = isset($roles->paid_amount) ? (float)$roles->paid_amount : 0.0;
				$totalAmt = isset($roles->total_amount) ? (float)$roles->total_amount : 0.0;
				$eps = 0.009;
				$statusLabel = "UNPAID";
				if ($paidAmt > $eps && $paidAmt + $eps < $totalAmt) {
					$statusLabel = "PARTIAL";
				} else if ($paidAmt + $eps >= $totalAmt && $totalAmt > $eps) {
					$statusLabel = "PAID";
				}
				if($statusLabel === 'PAID'){
					$a = '<span class="label label-success">PAID</span>';
				}else{
					$a = anchor('app/billing_history/view/'.$roles->invoice_no,'<i class="fa fa-credit-card"></i> Pay','class="btn btn-xs btn-primary" title="Make Payment"');
				}
				
				if ($statusLabel === 'PAID') {
					$statusBadge = '<span class="label label-success">PAID</span>';
				} else if ($statusLabel === 'PARTIAL') {
					$statusBadge = '<span class="label label-warning">PARTIAL</span>';
				} else {
					$statusBadge = '<span class="label label-danger">UNPAID</span>';
				}
				
				$this->table->add_row( 
									anchor('app/billing_history/view/'.$roles->invoice_no,$roles->invoice_no),
									$roles->patient_no, 
									$roles->iop_id, 
									$roles->patient, 
									date("M d, Y", strtotime($roles->dDate)), 
									number_format($roles->total_amount,2),
									$statusBadge,
									anchor('app/opd/printInv/'.$roles->iop_id.'/'.$roles->patient_no.'/'.$roles->invoice_no,'<i class="fa fa-print"></i>','target="_blank" title="Print Invoice"').'&nbsp; '.
									$a
		);
		}
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();
		$this->data['billing_summary'] = $this->billing_model->get_billing_summary();
		$this->data['outstanding_count'] = $this->billing_model->count_outstanding_invoices();
		$this->load->view('app/billing_history/index',$this->data);	
	}
	
	
	public function view($invoiceno){
		
		$this->data['header'] = $this->bill_history_model->getHeader($invoiceno);
		$this->data['details'] = $this->bill_history_model->details($invoiceno);
		$this->data['patientInfo'] = $this->bill_history_model->patientInfo($invoiceno);
		$paidAmt = $this->bill_history_model->get_paid_amount($invoiceno);
		$totalAmt = ($this->data['header'] && isset($this->data['header']->total_amount)) ? (float)$this->data['header']->total_amount : 0.0;
		$eps = 0.009;
		$statusLabel = 'UNPAID';
		if ($paidAmt > $eps && $paidAmt + $eps < $totalAmt) {
			$statusLabel = 'PARTIAL';
		} else if ($paidAmt + $eps >= $totalAmt && $totalAmt > $eps) {
			$statusLabel = 'PAID';
		}
		$this->data['paid_amount'] = $paidAmt;
		$this->data['payment_status'] = $statusLabel;
		$this->data['balance_due'] = max(0, $totalAmt - $paidAmt);
		$this->data['payment_history'] = $this->billing_model->get_payment_history($invoiceno);
		$this->data['payment_types'] = $this->billing_model->payment_type();
		$this->data['message'] = $this->session->flashdata('message');
		
		$this->load->view('app/billing_history/view',$this->data);	
			
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
}