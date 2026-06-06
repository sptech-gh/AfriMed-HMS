<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

class Ghs_reports extends General
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('app/ghs_report_model');
		$this->load->model('general_model');
		if (General::is_logged_in() == FALSE) {
			redirect(base_url() . 'login');
			return;
		}
		General::variable();
		require_role(array('admin', 'doctor', 'nurse', 'receptionist', 'cashier', 'laboratory', 'pharmacist', 'sonographer'));
	}

	/* ── Dashboard ────────────────────────────────────── */

	public function index()
	{
		$this->require_report_scope('clinical');
		$this->session->set_userdata(array(
			'tab'       => 'ghs_reports',
			'module'    => 'ghs_dashboard',
			'subtab'    => '',
			'submodule' => ''
		));
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/ghs_reports/dashboard', $this->data);
	}

	/* ── 1. OPD Attendance ────────────────────────────── */

	public function opd_attendance()
	{
		$this->session->set_userdata(array(
			'tab'       => 'ghs_reports',
			'module'    => 'ghs_opd_attendance',
			'subtab'    => '',
			'submodule' => ''
		));

		$date_from = trim((string)$this->input->get_post('date_from'));
		$date_to   = trim((string)$this->input->get_post('date_to'));
		if ($date_from === '') $date_from = date('Y-m-01');
		if ($date_to === '')   $date_to   = date('Y-m-d');

		$this->data['date_from'] = $date_from;
		$this->data['date_to']   = $date_to;
		$this->data['rows']      = $this->ghs_report_model->opd_attendance($date_from, $date_to);
		$this->data['summary']   = $this->ghs_report_model->opd_attendance_summary($date_from, $date_to);

		$this->audit_report_generation('opd', 'ghs_opd_attendance', 'browser');
		$this->load->view('app/ghs_reports/opd_attendance', $this->data);
	}

	/* ── 2. Diagnosis Report ──────────────────────────── */

	public function diagnosis()
	{
		$this->require_report_scope('clinical');
		$this->session->set_userdata(array(
			'tab'       => 'ghs_reports',
			'module'    => 'ghs_diagnosis',
			'subtab'    => '',
			'submodule' => ''
		));

		$date_from = trim((string)$this->input->get_post('date_from'));
		$date_to   = trim((string)$this->input->get_post('date_to'));
		if ($date_from === '') $date_from = date('Y-m-01');
		if ($date_to === '')   $date_to   = date('Y-m-d');

		$this->data['date_from']  = $date_from;
		$this->data['date_to']    = $date_to;
		$this->data['diagnoses']  = $this->ghs_report_model->top_diagnoses($date_from, $date_to, 30);

		$this->audit_report_generation('opd', 'ghs_diagnosis', 'browser');
		$this->load->view('app/ghs_reports/diagnosis', $this->data);
	}

	/* ── 3. Pharmacy Consumption ──────────────────────── */

	public function pharmacy_consumption()
	{
		$this->require_report_scope('clinical');
		$this->session->set_userdata(array(
			'tab'       => 'ghs_reports',
			'module'    => 'ghs_pharmacy',
			'subtab'    => '',
			'submodule' => ''
		));

		$date_from = trim((string)$this->input->get_post('date_from'));
		$date_to   = trim((string)$this->input->get_post('date_to'));
		if ($date_from === '') $date_from = date('Y-m-01');
		if ($date_to === '')   $date_to   = date('Y-m-d');

		$this->data['date_from'] = $date_from;
		$this->data['date_to']   = $date_to;
		$this->data['drugs']     = $this->ghs_report_model->pharmacy_consumption($date_from, $date_to);

		$this->audit_report_generation('financial', 'ghs_pharmacy_consumption', 'browser');
		$this->load->view('app/ghs_reports/pharmacy_consumption', $this->data);
	}

	/* ── 4. NHIS vs Cash ─────────────────────────────── */

	public function nhis_cash()
	{
		$this->require_report_scope('financial');
		$this->session->set_userdata(array(
			'tab'       => 'ghs_reports',
			'module'    => 'ghs_nhis_cash',
			'subtab'    => '',
			'submodule' => ''
		));

		$date_from = trim((string)$this->input->get_post('date_from'));
		$date_to   = trim((string)$this->input->get_post('date_to'));
		if ($date_from === '') $date_from = date('Y-m-01');
		if ($date_to === '')   $date_to   = date('Y-m-d');

		$this->data['date_from'] = $date_from;
		$this->data['date_to']   = $date_to;
		$this->data['report']    = $this->ghs_report_model->nhis_vs_cash($date_from, $date_to);

		$this->audit_report_generation('financial', 'ghs_nhis_cash', 'browser');
		$this->load->view('app/ghs_reports/nhis_cash', $this->data);
	}

	/* ── 5. Revenue Report ───────────────────────────── */

	public function revenue()
	{
		$this->require_report_scope('financial');
		$this->session->set_userdata(array(
			'tab'       => 'ghs_reports',
			'module'    => 'ghs_revenue',
			'subtab'    => '',
			'submodule' => ''
		));

		$date_from = trim((string)$this->input->get_post('date_from'));
		$date_to   = trim((string)$this->input->get_post('date_to'));
		if ($date_from === '') $date_from = date('Y-m-01');
		if ($date_to === '')   $date_to   = date('Y-m-d');

		$this->data['date_from']   = $date_from;
		$this->data['date_to']     = $date_to;
		$this->data['by_category'] = $this->ghs_report_model->revenue_by_category($date_from, $date_to);
		$this->data['daily']       = $this->ghs_report_model->revenue_daily($date_from, $date_to);
		$this->data['totals']      = $this->ghs_report_model->revenue_total($date_from, $date_to);

		$this->audit_report_generation('financial', 'ghs_revenue', 'browser');
		$this->load->view('app/ghs_reports/revenue', $this->data);
	}

	/* ── 6. Daily Returns ────────────────────────────── */

	public function daily_returns()
	{
		$this->session->set_userdata(array(
			'tab'       => 'ghs_reports',
			'module'    => 'ghs_daily_returns',
			'subtab'    => '',
			'submodule' => ''
		));

		$date_from = trim((string)$this->input->get_post('date_from'));
		$date_to   = trim((string)$this->input->get_post('date_to'));
		if ($date_from === '') $date_from = date('Y-m-d');
		if ($date_to === '')   $date_to   = date('Y-m-d');

		$this->data['date_from'] = $date_from;
		$this->data['date_to']   = $date_to;
		$this->data['returns']   = $this->ghs_report_model->daily_returns_range($date_from, $date_to);

		$this->audit_report_generation('opd', 'ghs_daily_returns', 'browser');
		$this->load->view('app/ghs_reports/daily_returns', $this->data);
	}

	/* ── Excel Export ────────────────────────────────── */

	public function export_csv()
	{
		$report = trim((string)$this->input->get('report'));
		$date_from = trim((string)$this->input->get('date_from'));
		$date_to   = trim((string)$this->input->get('date_to'));
		if ($date_from === '') $date_from = date('Y-m-01');
		if ($date_to === '')   $date_to   = date('Y-m-d');

		$filename = 'ghs_' . $report . '_' . $date_from . '_' . $date_to . '.csv';

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		$out = fopen('php://output', 'w');

		switch ($report) {
			case 'opd_attendance':
				fputcsv($out, array('Date', 'Male', 'Female', 'Children', 'Adults', 'Total'));
				$rows = $this->ghs_report_model->opd_attendance($date_from, $date_to);
				foreach ($rows as $r) {
					fputcsv($out, array($r->visit_date, $r->male, $r->female, $r->children, $r->adults, $r->total));
				}
				break;

			case 'diagnosis':
				fputcsv($out, array('Diagnosis', 'Total Cases', 'Male', 'Female', 'Under 5', 'Age 5-17', '18+'));
				$rows = $this->ghs_report_model->top_diagnoses($date_from, $date_to, 50);
				foreach ($rows as $r) {
					fputcsv($out, array($r->diagnosis_name, $r->total_cases, $r->male, $r->female, $r->under_5, $r->age_5_17, $r->age_18_plus));
				}
				break;

			case 'pharmacy_consumption':
				fputcsv($out, array('Drug Name', 'Opening Balance', 'Received', 'Dispensed', 'Closing Balance'));
				$rows = $this->ghs_report_model->pharmacy_consumption($date_from, $date_to);
				foreach ($rows as $r) {
					fputcsv($out, array($r->drug_name, $r->opening, $r->received, $r->dispensed, $r->closing));
				}
				break;

			case 'nhis_cash':
				$data = $this->ghs_report_model->nhis_vs_cash($date_from, $date_to);
				fputcsv($out, array('Metric', 'NHIS', 'Cash', 'Total'));
				fputcsv($out, array('Patients', $data['nhis_patients'], $data['cash_patients'], $data['nhis_patients'] + $data['cash_patients']));
				fputcsv($out, array('Visits', $data['nhis_visits'], $data['cash_visits'], $data['nhis_visits'] + $data['cash_visits']));
				fputcsv($out, array('Revenue', $data['nhis_revenue'], $data['cash_revenue'], $data['total_revenue']));
				break;

			case 'revenue':
				fputcsv($out, array('Date', 'Total Revenue', 'Invoices'));
				$rows = $this->ghs_report_model->revenue_daily($date_from, $date_to);
				foreach ($rows as $r) {
					fputcsv($out, array($r->bill_date, $r->total, $r->invoices));
				}
				break;

			case 'daily_returns':
				fputcsv($out, array('Date', 'OPD', 'Admissions', 'Discharges', 'Deaths', 'Revenue', 'Payments', 'Prescriptions', 'Lab Tests'));
				$rows = $this->ghs_report_model->daily_returns_range($date_from, $date_to);
				foreach ($rows as $r) {
					fputcsv($out, array($r['date'], $r['opd_attendance'], $r['admissions'], $r['discharges'], $r['deaths'], $r['revenue'], $r['payments'], $r['prescriptions'], $r['lab_tests']));
				}
				break;

			default:
				fputcsv($out, array('Error: Unknown report type'));
		}

		fclose($out);
		$this->audit_report_generation('financial', 'ghs_export_' . $report, 'csv');
		exit;
	}
}
