<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Billing Reports Controller
 * 
 * Generates standardized billing reports for revenue, cashier performance,
 * NHIS claims, and department analytics.
 * 
 * @author HMS Development Team
 * @version 1.0.0
 */
class Billing_reports extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('app/Billing_reports_model');
        $this->load->model('app/billing_model');
        $this->load->helper(['url', 'form']);
        $this->load->library(['session']);
        
        if (!$this->session->userdata('user_id')) {
            redirect('login');
        }
        
        // Only admin, cashier, accountant can access reports
        $role = strtolower($this->session->userdata('role') ?? '');
        if (!in_array($role, ['admin', 'superadmin', 'administrator', 'cashier', 'accountant', 'finance'])) {
            $this->session->set_flashdata('error', 'Access denied - Finance/Admin role required');
            redirect('app/dashboard');
        }
        
        $this->data = [
            'base_url' => base_url(),
            'page_title' => 'Billing Reports'
        ];
    }
    
    /**
     * Reports Dashboard
     */
    public function index()
    {
        $this->data['page_title'] = 'Billing Reports Dashboard';
        
        // Get today's summary
        $this->data['daily_revenue'] = $this->Billing_reports_model->get_daily_revenue();
        
        // Quick stats
        $this->data['outstanding'] = $this->Billing_reports_model->get_outstanding_summary();
        
        $this->load->view('app/billing_reports/dashboard', $this->data);
    }
    
    /**
     * Daily Revenue Report
     */
    public function daily_revenue($date = null)
    {
        $date = $date ?: date('Y-m-d');
        
        $this->data['page_title'] = 'Daily Revenue Report';
        $this->data['report_date'] = $date;
        $this->data['report'] = $this->Billing_reports_model->get_daily_revenue($date);
        
        $this->load->view('app/billing_reports/daily_revenue', $this->data);
    }
    
    /**
     * Revenue Summary Report (Date Range)
     */
    public function revenue_summary()
    {
        $from_date = $this->input->get('from') ?: date('Y-m-01');
        $to_date = $this->input->get('to') ?: date('Y-m-d');
        
        $this->data['page_title'] = 'Revenue Summary Report';
        $this->data['from_date'] = $from_date;
        $this->data['to_date'] = $to_date;
        $this->data['report'] = $this->Billing_reports_model->get_revenue_summary($from_date, $to_date);
        
        $this->load->view('app/billing_reports/revenue_summary', $this->data);
    }
    
    /**
     * Cashier Performance Report
     */
    public function cashier_performance()
    {
        $from_date = $this->input->get('from') ?: date('Y-m-01');
        $to_date = $this->input->get('to') ?: date('Y-m-d');
        $cashier_id = $this->input->get('cashier');
        
        $this->data['page_title'] = 'Cashier Performance Report';
        $this->data['from_date'] = $from_date;
        $this->data['to_date'] = $to_date;
        $this->data['report'] = $this->Billing_reports_model->get_cashier_performance($from_date, $to_date, $cashier_id);
        
        // Get list of cashiers for filter
        $this->db->select('user_id, cName');
        $this->db->where_in('user_role', [3, 4]); // Cashier roles
        $this->data['cashiers'] = $this->db->get('user')->result();
        
        $this->load->view('app/billing_reports/cashier_performance', $this->data);
    }
    
    /**
     * NHIS Claims Report
     */
    public function nhis_claims()
    {
        $from_date = $this->input->get('from') ?: date('Y-m-01');
        $to_date = $this->input->get('to') ?: date('Y-m-d');
        
        $this->data['page_title'] = 'NHIS Claims Report';
        $this->data['from_date'] = $from_date;
        $this->data['to_date'] = $to_date;
        $this->data['report'] = $this->Billing_reports_model->get_nhis_claims_summary($from_date, $to_date);
        
        $this->load->view('app/billing_reports/nhis_claims', $this->data);
    }
    
    /**
     * Export NHIS Claims to CSV
     */
    public function export_nhis_claims()
    {
        $from_date = $this->input->get('from') ?: date('Y-m-01');
        $to_date = $this->input->get('to') ?: date('Y-m-d');
        
        $claims = $this->Billing_reports_model->get_nhis_claims_for_export($from_date, $to_date);
        
        // Generate CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="nhis_claims_' . $from_date . '_to_' . $to_date . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Header row
        fputcsv($output, ['Bill No', 'Date', 'Patient No', 'Patient Name', 'NHIS No', 'Service', 'Amount', 'Status']);
        
        foreach ($claims as $claim) {
            foreach ($claim['items'] as $item) {
                fputcsv($output, [
                    $claim['bill']->bill_no,
                    $claim['bill']->created_at,
                    $claim['bill']->patient_no,
                    $claim['patient_name'],
                    $claim['nhis_no'],
                    $item->service_name,
                    $item->line_total,
                    $claim['bill']->payment_status
                ]);
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Department Revenue Report
     */
    public function department_revenue()
    {
        $from_date = $this->input->get('from') ?: date('Y-m-01');
        $to_date = $this->input->get('to') ?: date('Y-m-d');
        
        $this->data['page_title'] = 'Department Revenue Report';
        $this->data['from_date'] = $from_date;
        $this->data['to_date'] = $to_date;
        $this->data['report'] = $this->Billing_reports_model->get_department_revenue($from_date, $to_date);
        
        $this->load->view('app/billing_reports/department_revenue', $this->data);
    }
    
    /**
     * Outstanding Bills Report
     */
    public function outstanding()
    {
        $this->data['page_title'] = 'Outstanding Bills Report';
        $this->data['report'] = $this->Billing_reports_model->get_outstanding_summary();
        
        $this->load->view('app/billing_reports/outstanding', $this->data);
    }
    
    /**
     * Print Daily Summary (Thermal/A4)
     */
    public function print_daily($date = null)
    {
        $date = $date ?: date('Y-m-d');
        
        $this->data['report_date'] = $date;
        $this->data['report'] = $this->Billing_reports_model->get_daily_revenue($date);
        $this->data['hospital_name'] = $this->config->item('hospital_name') ?: 'Hospital Management System';
        
        $this->load->view('app/billing_reports/print_daily', $this->data);
    }
}
