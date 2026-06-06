<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'controllers/General.php');

/**
 * Company Pricing Reports Controller
 * 
 * Generates reports for company cover pricing analysis and revenue by company.
 * 
 * @author HMS Development Team
 * @version 1.0.0
 */
class Company_pricing_reports extends General
{
    private $user_id;
    private $user_role;

    public function __construct()
    {
        parent::__construct();
        
        if (General::is_logged_in() == FALSE) {
            redirect(base_url() . 'login');
        }
        General::variable();
        
        // Finance and admin roles only
        require_role(array('admin', 'superadmin', 'cashier', 'accountant', 'finance'));
        
        $this->load->model('app/Company_pricing_reports_model');
        $this->load->model('app/insurance_company_model');
        $this->load->helper(['url', 'form', 'date']);
        
        $this->user_id = $this->session->userdata('user_id');
        $this->user_role = get_role_key();
    }

    /**
     * Revenue by Company Report
     */
    public function revenue_by_company()
    {
        $from_date = $this->input->get('from') ?: date('Y-m-01');
        $to_date = $this->input->get('to') ?: date('Y-m-d');
        $company_id = $this->input->get('company_id') ?: null;
        
        $this->data['page_title'] = 'Revenue by Company Report';
        $this->data['from_date'] = $from_date;
        $this->data['to_date'] = $to_date;
        $this->data['selected_company'] = $company_id;
        
        // Get filter data
        $this->data['companies'] = $this->Company_pricing_reports_model->get_all_active_companies();
        
        // Get report data
        $this->data['report'] = $this->Company_pricing_reports_model->get_revenue_by_company(
            $from_date, 
            $to_date, 
            $company_id
        );
        
        // Calculate totals
        $this->data['totals'] = $this->_calculate_company_totals($this->data['report']);
        
        $this->load->view('app/company_pricing_reports/revenue_by_company', $this->data);
    }

    /**
     * Pricing Adjustment Impact Report
     */
    public function pricing_impact()
    {
        $from_date = $this->input->get('from') ?: date('Y-m-01');
        $to_date = $this->input->get('to') ?: date('Y-m-d');
        $company_id = $this->input->get('company_id') ?: null;
        
        $this->data['page_title'] = 'Pricing Adjustment Impact Report';
        $this->data['from_date'] = $from_date;
        $this->data['to_date'] = $to_date;
        $this->data['selected_company'] = $company_id;
        
        // Get filter data
        $this->data['companies'] = $this->Company_pricing_reports_model->get_all_active_companies();
        
        // Get report data
        $this->data['impact_details'] = $this->Company_pricing_reports_model->get_pricing_adjustment_impact(
            $from_date, 
            $to_date, 
            $company_id
        );
        
        $this->data['impact_summary'] = $this->Company_pricing_reports_model->get_pricing_impact_summary(
            $from_date, 
            $to_date
        );
        
        $this->load->view('app/company_pricing_reports/pricing_impact', $this->data);
    }

    /**
     * Company Patient Bills Detail
     */
    public function company_bills($company_id)
    {
        $from_date = $this->input->get('from') ?: date('Y-m-01');
        $to_date = $this->input->get('to') ?: date('Y-m-d');
        
        // Get company info
        $this->data['company'] = $this->insurance_company_model->get_insurance_by_id($company_id);
        
        if (!$this->data['company']) {
            $this->session->set_flashdata('error', 'Company not found');
            redirect('app/company_pricing_reports/revenue_by_company');
        }
        
        $this->data['page_title'] = 'Patient Bills - ' . $this->data['company']->company_name;
        $this->data['from_date'] = $from_date;
        $this->data['to_date'] = $to_date;
        $this->data['company_id'] = $company_id;
        
        // Get bills for this company
        $this->data['bills'] = $this->Company_pricing_reports_model->get_company_patient_bills(
            $from_date,
            $to_date,
            $company_id
        );
        
        $this->load->view('app/company_pricing_reports/company_bills', $this->data);
    }

    /**
     * Pricing Simulation Tool
     * Allows forecasting revenue impact of pricing changes
     */
    public function simulate_pricing()
    {
        $this->data['page_title'] = 'Pricing Change Simulator';
        $this->data['companies'] = $this->Company_pricing_reports_model->get_all_active_companies();
        
        $from_date = $this->input->post('from') ?: date('Y-m-01', strtotime('-3 months'));
        $to_date = $this->input->post('to') ?: date('Y-m-d');
        $company_id = $this->input->post('company_id');
        $new_percentage = $this->input->post('new_percentage');
        
        $this->data['from_date'] = $from_date;
        $this->data['to_date'] = $to_date;
        $this->data['selected_company'] = $company_id;
        $this->data['new_percentage'] = $new_percentage;
        
        if ($this->input->post('simulate') && $company_id && $new_percentage !== '') {
            $this->data['simulation_result'] = $this->Company_pricing_reports_model->simulate_pricing_change(
                $company_id,
                $new_percentage,
                $from_date,
                $to_date
            );
            
            $this->data['company_info'] = $this->insurance_company_model->get_insurance_by_id($company_id);
        }
        
        $this->load->view('app/company_pricing_reports/simulate_pricing', $this->data);
    }

    /**
     * Companies with Special Pricing
     * Quick view of all companies that have pricing adjustments set
     */
    public function companies_with_pricing()
    {
        $this->data['page_title'] = 'Companies with Special Pricing';
        $this->data['companies'] = $this->Company_pricing_reports_model->get_companies_with_pricing();
        
        $this->load->view('app/company_pricing_reports/companies_with_pricing', $this->data);
    }

    /**
     * Calculate totals from report data
     */
    private function _calculate_company_totals($report_data)
    {
        $totals = [
            'total_bills' => 0,
            'total_patients' => 0,
            'gross_revenue' => 0,
            'total_discounts' => 0,
            'net_revenue' => 0,
            'collected_amount' => 0,
            'outstanding_balance' => 0
        ];

        foreach ($report_data as $row) {
            $totals['total_bills'] += $row->total_bills;
            $totals['total_patients'] += $row->total_patients;
            $totals['gross_revenue'] += $row->gross_revenue;
            $totals['total_discounts'] += $row->total_discounts;
            $totals['net_revenue'] += $row->net_revenue;
            $totals['collected_amount'] += $row->collected_amount;
            $totals['outstanding_balance'] += $row->outstanding_balance;
        }

        return $totals;
    }
}
