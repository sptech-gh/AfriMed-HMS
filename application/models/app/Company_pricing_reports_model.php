<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Company Pricing Reports Model
 * 
 * Generates reports for company cover pricing analysis and revenue by company.
 * 
 * @author HMS Development Team
 * @version 1.0.0
 */
class Company_pricing_reports_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get revenue summary grouped by insurance company
     * Shows base revenue vs adjusted revenue
     */
    public function get_revenue_by_company($from_date, $to_date, $company_id = null)
    {
        $sql = "
            SELECT 
                ic.in_com_id as company_id,
                ic.company_name,
                ic.insurance_type,
                ic.billing_type,
                ic.pricing_percentage,
                COUNT(DISTINCT bm.bill_id) as total_bills,
                COUNT(DISTINCT bm.patient_no) as total_patients,
                SUM(bm.total_amount) as gross_revenue,
                SUM(bm.discount_amount) as total_discounts,
                SUM(bm.net_amount) as net_revenue,
                SUM(bm.paid_amount) as collected_amount,
                SUM(bm.balance_due) as outstanding_balance
            FROM billing_master bm
            LEFT JOIN insurance_comp ic ON ic.in_com_id = bm.insurance_id
            WHERE bm.InActive = 0
            AND DATE(bm.created_at) BETWEEN ? AND ?
            AND bm.payer_type IN ('INSURANCE', 'COMPANY', 'CORPORATE')
        ";
        
        $params = [$from_date, $to_date];
        
        if ($company_id) {
            $sql .= " AND bm.insurance_id = ?";
            $params[] = $company_id;
        }
        
        $sql .= "
            GROUP BY ic.in_com_id, ic.company_name, ic.insurance_type, ic.billing_type, ic.pricing_percentage
            ORDER BY net_revenue DESC
        ";
        
        $query = $this->db->query($sql, $params);
        return $query->result();
    }

    /**
     * Get detailed pricing adjustment impact
     * Shows difference between base prices and adjusted prices
     */
    public function get_pricing_adjustment_impact($from_date, $to_date, $company_id = null)
    {
        $sql = "
            SELECT 
                ic.in_com_id as company_id,
                ic.company_name,
                ic.pricing_percentage,
                bi.service_type,
                COUNT(*) as item_count,
                SUM(bi.base_price * bi.quantity) as base_revenue,
                SUM(bi.adjusted_price * bi.quantity) as adjusted_revenue,
                SUM((bi.adjusted_price - bi.base_price) * bi.quantity) as adjustment_amount,
                AVG(bi.adjustment_percentage) as avg_adjustment_pct
            FROM billing_items bi
            INNER JOIN billing_master bm ON bm.bill_id = bi.bill_id
            LEFT JOIN insurance_comp ic ON ic.in_com_id = bi.company_id
            WHERE bi.InActive = 0
            AND bm.InActive = 0
            AND DATE(bm.created_at) BETWEEN ? AND ?
            AND bi.adjustment_percentage != 0
        ";
        
        $params = [$from_date, $to_date];
        
        if ($company_id) {
            $sql .= " AND bi.company_id = ?";
            $params[] = $company_id;
        }
        
        $sql .= "
            GROUP BY ic.in_com_id, ic.company_name, ic.pricing_percentage, bi.service_type
            ORDER BY adjustment_amount DESC
        ";
        
        $query = $this->db->query($sql, $params);
        return $query->result();
    }

    /**
     * Get summary of pricing impact (totals)
     */
    public function get_pricing_impact_summary($from_date, $to_date)
    {
        $sql = "
            SELECT 
                SUM(bi.base_price * bi.quantity) as total_base_revenue,
                SUM(bi.adjusted_price * bi.quantity) as total_adjusted_revenue,
                SUM((bi.adjusted_price - bi.base_price) * bi.quantity) as total_adjustment,
                COUNT(DISTINCT bi.company_id) as companies_with_adjustments,
                COUNT(*) as total_adjusted_items
            FROM billing_items bi
            INNER JOIN billing_master bm ON bm.bill_id = bi.bill_id
            WHERE bi.InActive = 0
            AND bm.InActive = 0
            AND DATE(bm.created_at) BETWEEN ? AND ?
            AND bi.adjustment_percentage != 0
        ";
        
        $query = $this->db->query($sql, [$from_date, $to_date]);
        return $query->row();
    }

    /**
     * Get company-wise patient billing details
     */
    public function get_company_patient_bills($from_date, $to_date, $company_id)
    {
        $sql = "
            SELECT 
                bm.bill_no,
                bm.patient_no,
                CONCAT(ppi.firstname, ' ', ppi.lastname) as patient_name,
                bm.total_amount,
                bm.net_amount,
                bm.paid_amount,
                bm.balance_due,
                bm.payment_status,
                bm.created_at as bill_date,
                COUNT(bi.item_id) as item_count,
                SUM((bi.adjusted_price - bi.base_price) * bi.quantity) as total_adjustment
            FROM billing_master bm
            INNER JOIN patient_personal_info ppi ON ppi.patient_no = bm.patient_no
            LEFT JOIN billing_items bi ON bi.bill_id = bm.bill_id AND bi.InActive = 0
            WHERE bm.InActive = 0
            AND bm.insurance_id = ?
            AND DATE(bm.created_at) BETWEEN ? AND ?
            GROUP BY bm.bill_id
            ORDER BY bm.created_at DESC
        ";
        
        $query = $this->db->query($sql, [$company_id, $from_date, $to_date]);
        return $query->result();
    }

    /**
     * Get list of companies with pricing adjustments set
     */
    public function get_companies_with_pricing()
    {
        $this->db->where('pricing_percentage !=', 0);
        $this->db->where('InActive', 0);
        $this->db->order_by('company_name', 'ASC');
        return $this->db->get('insurance_comp')->result();
    }

    /**
     * Get all active companies for filter dropdown
     */
    public function get_all_active_companies()
    {
        $this->db->where('InActive', 0);
        $this->db->order_by('company_name', 'ASC');
        return $this->db->get('insurance_comp')->result();
    }

    /**
     * Calculate potential revenue impact if pricing percentage is changed
     * For forecasting/simulation purposes
     */
    public function simulate_pricing_change($company_id, $new_percentage, $from_date, $to_date)
    {
        $sql = "
            SELECT 
                SUM(bi.base_price * bi.quantity) as base_revenue,
                SUM(bi.base_price * bi.quantity * (1 + ?/100)) as projected_revenue,
                SUM(bi.base_price * bi.quantity * (1 + ?/100)) - SUM(bi.base_price * bi.quantity) as projected_difference,
                COUNT(*) as affected_items
            FROM billing_items bi
            INNER JOIN billing_master bm ON bm.bill_id = bi.bill_id
            WHERE bi.InActive = 0
            AND bm.InActive = 0
            AND bi.company_id = ?
            AND DATE(bm.created_at) BETWEEN ? AND ?
        ";
        
        $query = $this->db->query($sql, [$new_percentage, $new_percentage, $company_id, $from_date, $to_date]);
        return $query->row();
    }
}
