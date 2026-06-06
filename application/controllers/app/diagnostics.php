<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php';

class Diagnostics extends General
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('app/system_diagnostics_model');
		if (General::is_logged_in() == FALSE) {
			redirect(base_url().'login');
		}
		General::variable();
		require_role('admin');
	}

	public function phase2_billing()
	{
		$report = $this->system_diagnostics_model->phase2_readiness_report();
		$this->output
			->set_content_type('application/json')
			->set_output(json_encode($report));
	}

	public function walkin_fulfillment()
	{
		$report = $this->system_diagnostics_model->walkin_fulfillment_reconciliation_report();
		$this->output
			->set_content_type('application/json')
			->set_output(json_encode($report));
	}
}
