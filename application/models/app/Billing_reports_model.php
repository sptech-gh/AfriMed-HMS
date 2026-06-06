<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Billing Reports Model
 * 
 * Generates standardized reports for the Unified Billing System.
 * Supports Revenue, Cashier Performance, NHIS Claims, and Department reports.
 * 
 * @author HMS Development Team
 * @version 1.0.0
 */
class Billing_reports_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /* ================================================================== */
    /*  REVENUE REPORTS                                                   */
    /* ================================================================== */
    
    /**
     * Get daily revenue summary
     */
    public function get_daily_revenue($date = null)
    {
        $date = $date ?: date('Y-m-d');
        
        $report = [
            'date' => $date,
            'total_billed' => 0,
            'total_collected' => 0,
            'total_outstanding' => 0,
            'cash_collected' => 0,
            'nhis_claims' => 0,
            'momo_collected' => 0,
            'card_collected' => 0,
            'by_department' => [],
            'by_service_type' => [],
            'by_payment_method' => []
        ];
        
        // Total billed (from billing_master)
        if ($this->db->table_exists('billing_master')) {
            $this->db->select('SUM(total_amount) as total_billed, SUM(balance_due) as outstanding');
            $this->db->where('DATE(created_at)', $date);
            $this->db->where('InActive', 0);
            $row = $this->db->get('billing_master')->row();
            $report['total_billed'] = $row ? (float)$row->total_billed : 0;
            $report['total_outstanding'] = $row ? (float)$row->outstanding : 0;
        }
        
        // Total collected (from billing_payments)
        if ($this->db->table_exists('billing_payments')) {
            $this->db->select('SUM(amount) as total, payment_method');
            $this->db->where('DATE(payment_date)', $date);
            $this->db->where('InActive', 0);
            $this->db->group_by('payment_method');
            $payments = $this->db->get('billing_payments')->result();
            
            foreach ($payments as $p) {
                $method = strtoupper($p->payment_method);
                $amount = (float)$p->total;
                $report['total_collected'] += $amount;
                $report['by_payment_method'][$method] = $amount;
                
                if ($method === 'CASH') $report['cash_collected'] = $amount;
                if ($method === 'NHIS') $report['nhis_claims'] = $amount;
                if ($method === 'MOMO' || $method === 'MOBILE_MONEY') $report['momo_collected'] = $amount;
                if ($method === 'CARD') $report['card_collected'] = $amount;
            }
        }
        
        // Revenue by service type
        if ($this->db->table_exists('billing_items')) {
            $this->db->select('service_type, SUM(line_total) as total');
            $this->db->from('billing_items bi');
            $this->db->join('billing_master bm', 'bm.bill_id = bi.bill_id');
            $this->db->where('DATE(bm.created_at)', $date);
            $this->db->where('bi.InActive', 0);
            $this->db->group_by('service_type');
            $services = $this->db->get()->result();
            
            foreach ($services as $s) {
                $report['by_service_type'][$s->service_type] = (float)$s->total;
            }
        }
        
        // Revenue by department
        if ($this->db->table_exists('billing_items')) {
            $this->db->select('department, SUM(line_total) as total');
            $this->db->from('billing_items bi');
            $this->db->join('billing_master bm', 'bm.bill_id = bi.bill_id');
            $this->db->where('DATE(bm.created_at)', $date);
            $this->db->where('bi.InActive', 0);
            $this->db->where('bi.department IS NOT NULL');
            $this->db->group_by('department');
            $depts = $this->db->get()->result();
            
            foreach ($depts as $d) {
                $report['by_department'][$d->department] = (float)$d->total;
            }
        }
        
        return $report;
    }
    
    /**
     * Get revenue summary for date range
     */
    public function get_revenue_summary($from_date, $to_date)
    {
        $report = [
            'from_date' => $from_date,
            'to_date' => $to_date,
            'total_billed' => 0,
            'total_collected' => 0,
            'total_outstanding' => 0,
            'daily_breakdown' => [],
            'by_service_type' => [],
            'by_payment_method' => [],
            'top_services' => []
        ];
        
        // Total billed
        if ($this->db->table_exists('billing_master')) {
            $this->db->select('SUM(total_amount) as billed, SUM(amount_paid) as collected, SUM(balance_due) as outstanding');
            $this->db->where('DATE(created_at) >=', $from_date);
            $this->db->where('DATE(created_at) <=', $to_date);
            $this->db->where('InActive', 0);
            $row = $this->db->get('billing_master')->row();
            $report['total_billed'] = $row ? (float)$row->billed : 0;
            $report['total_collected'] = $row ? (float)$row->collected : 0;
            $report['total_outstanding'] = $row ? (float)$row->outstanding : 0;
        }
        
        // Daily breakdown
        if ($this->db->table_exists('billing_payments')) {
            $this->db->select('DATE(payment_date) as pay_date, SUM(amount) as total');
            $this->db->where('DATE(payment_date) >=', $from_date);
            $this->db->where('DATE(payment_date) <=', $to_date);
            $this->db->where('InActive', 0);
            $this->db->group_by('DATE(payment_date)');
            $this->db->order_by('pay_date', 'ASC');
            $daily = $this->db->get('billing_payments')->result();
            
            foreach ($daily as $d) {
                $report['daily_breakdown'][$d->pay_date] = (float)$d->total;
            }
        }
        
        // By payment method
        if ($this->db->table_exists('billing_payments')) {
            $this->db->select('payment_method, SUM(amount) as total, COUNT(*) as count');
            $this->db->where('DATE(payment_date) >=', $from_date);
            $this->db->where('DATE(payment_date) <=', $to_date);
            $this->db->where('InActive', 0);
            $this->db->group_by('payment_method');
            $methods = $this->db->get('billing_payments')->result();
            
            foreach ($methods as $m) {
                $report['by_payment_method'][$m->payment_method] = [
                    'total' => (float)$m->total,
                    'count' => (int)$m->count
                ];
            }
        }
        
        // Top services
        if ($this->db->table_exists('billing_items')) {
            $this->db->select('service_name, service_type, SUM(line_total) as total, COUNT(*) as count');
            $this->db->from('billing_items bi');
            $this->db->join('billing_master bm', 'bm.bill_id = bi.bill_id');
            $this->db->where('DATE(bm.created_at) >=', $from_date);
            $this->db->where('DATE(bm.created_at) <=', $to_date);
            $this->db->where('bi.InActive', 0);
            $this->db->group_by('service_name, service_type');
            $this->db->order_by('total', 'DESC');
            $this->db->limit(20);
            $report['top_services'] = $this->db->get()->result();
        }
        
        return $report;
    }
    
    /* ================================================================== */
    /*  CASHIER PERFORMANCE REPORTS                                       */
    /* ================================================================== */
    
    /**
     * Get cashier performance report
     */
    public function get_cashier_performance($from_date, $to_date, $cashier_id = null)
    {
        $report = [
            'from_date' => $from_date,
            'to_date' => $to_date,
            'cashiers' => []
        ];
        
        if (!$this->db->table_exists('billing_payments')) {
            return $report;
        }
        
        // Build query
        $this->db->select('bp.received_by, u.cName as cashier_name, 
                          COUNT(*) as transaction_count,
                          SUM(bp.amount) as total_collected,
                          SUM(CASE WHEN bp.payment_method = "CASH" THEN bp.amount ELSE 0 END) as cash_total,
                          SUM(CASE WHEN bp.payment_method = "NHIS" THEN bp.amount ELSE 0 END) as nhis_total,
                          SUM(CASE WHEN bp.payment_method NOT IN ("CASH","NHIS") THEN bp.amount ELSE 0 END) as other_total');
        $this->db->from('billing_payments bp');
        $this->db->join('user u', 'u.user_id = bp.received_by', 'left');
        $this->db->where('DATE(bp.payment_date) >=', $from_date);
        $this->db->where('DATE(bp.payment_date) <=', $to_date);
        $this->db->where('bp.InActive', 0);
        
        if ($cashier_id) {
            $this->db->where('bp.received_by', $cashier_id);
        }
        
        $this->db->group_by('bp.received_by');
        $this->db->order_by('total_collected', 'DESC');
        
        $cashiers = $this->db->get()->result();
        
        foreach ($cashiers as $c) {
            $report['cashiers'][] = [
                'cashier_id' => $c->received_by,
                'cashier_name' => $c->cashier_name ?: 'Unknown',
                'transaction_count' => (int)$c->transaction_count,
                'total_collected' => (float)$c->total_collected,
                'cash_total' => (float)$c->cash_total,
                'nhis_total' => (float)$c->nhis_total,
                'other_total' => (float)$c->other_total
            ];
        }
        
        return $report;
    }
    
    /**
     * Get cashier daily breakdown
     */
    public function get_cashier_daily($cashier_id, $date)
    {
        $report = [
            'cashier_id' => $cashier_id,
            'date' => $date,
            'transactions' => [],
            'summary' => [
                'total' => 0,
                'by_method' => []
            ]
        ];
        
        if (!$this->db->table_exists('billing_payments')) {
            return $report;
        }
        
        $this->db->select('bp.*, bm.bill_no, bm.patient_no');
        $this->db->from('billing_payments bp');
        $this->db->join('billing_master bm', 'bm.bill_id = bp.bill_id', 'left');
        $this->db->where('bp.received_by', $cashier_id);
        $this->db->where('DATE(bp.payment_date)', $date);
        $this->db->where('bp.InActive', 0);
        $this->db->order_by('bp.payment_date', 'ASC');
        
        $transactions = $this->db->get()->result();
        
        foreach ($transactions as $t) {
            $report['transactions'][] = $t;
            $report['summary']['total'] += (float)$t->amount;
            
            $method = $t->payment_method;
            if (!isset($report['summary']['by_method'][$method])) {
                $report['summary']['by_method'][$method] = 0;
            }
            $report['summary']['by_method'][$method] += (float)$t->amount;
        }
        
        return $report;
    }
    
    /* ================================================================== */
    /*  NHIS CLAIMS REPORTS                                               */
    /* ================================================================== */
    
    /**
     * Get NHIS claims summary
     */
    public function get_nhis_claims_summary($from_date, $to_date)
    {
        $report = [
            'from_date' => $from_date,
            'to_date' => $to_date,
            'total_claims' => 0,
            'total_amount' => 0,
            'pending_claims' => 0,
            'pending_amount' => 0,
            'approved_claims' => 0,
            'approved_amount' => 0,
            'rejected_claims' => 0,
            'rejected_amount' => 0,
            'by_service_type' => [],
            'claims_list' => []
        ];
        
        // Check billing_items for NHIS-covered items
        if ($this->db->table_exists('billing_items')) {
            $this->db->select('bi.service_type, bi.service_name, bi.line_total, bi.gate_status,
                              bm.bill_no, bm.patient_no, bm.created_at, bm.payment_status');
            $this->db->from('billing_items bi');
            $this->db->join('billing_master bm', 'bm.bill_id = bi.bill_id');
            $this->db->where('DATE(bm.created_at) >=', $from_date);
            $this->db->where('DATE(bm.created_at) <=', $to_date);
            $this->db->where('bi.InActive', 0);
            
            // Look for NHIS payments or NHIS visit types
            $this->db->group_start();
            $this->db->where('bm.visit_type', 'NHIS');
            $this->db->or_like('bm.payment_status', 'NHIS');
            $this->db->group_end();
            
            $items = $this->db->get()->result();
            
            foreach ($items as $item) {
                $amount = (float)$item->line_total;
                $report['total_claims']++;
                $report['total_amount'] += $amount;
                
                // Track by service type
                $type = $item->service_type;
                if (!isset($report['by_service_type'][$type])) {
                    $report['by_service_type'][$type] = ['count' => 0, 'amount' => 0];
                }
                $report['by_service_type'][$type]['count']++;
                $report['by_service_type'][$type]['amount'] += $amount;
                
                // Determine claim status
                $status = strtoupper($item->payment_status);
                if ($status === 'PAID') {
                    $report['approved_claims']++;
                    $report['approved_amount'] += $amount;
                } elseif ($status === 'REJECTED' || $status === 'DENIED') {
                    $report['rejected_claims']++;
                    $report['rejected_amount'] += $amount;
                } else {
                    $report['pending_claims']++;
                    $report['pending_amount'] += $amount;
                }
                
                $report['claims_list'][] = $item;
            }
        }
        
        // Also check legacy NHIS tables if they exist
        if ($this->db->table_exists('nhis_claim_queue')) {
            $this->db->select('claim_status, SUM(claim_amount) as total, COUNT(*) as count');
            $this->db->where('DATE(created_at) >=', $from_date);
            $this->db->where('DATE(created_at) <=', $to_date);
            $this->db->group_by('claim_status');
            $legacy = $this->db->get('nhis_claim_queue')->result();
            
            foreach ($legacy as $l) {
                $status = strtoupper($l->claim_status);
                $amount = (float)$l->total;
                $count = (int)$l->count;
                
                $report['total_claims'] += $count;
                $report['total_amount'] += $amount;
                
                if ($status === 'APPROVED' || $status === 'PAID') {
                    $report['approved_claims'] += $count;
                    $report['approved_amount'] += $amount;
                } elseif ($status === 'REJECTED' || $status === 'DENIED') {
                    $report['rejected_claims'] += $count;
                    $report['rejected_amount'] += $amount;
                } else {
                    $report['pending_claims'] += $count;
                    $report['pending_amount'] += $amount;
                }
            }
        }
        
        return $report;
    }
    
    /**
     * Get NHIS claims for export (batch submission)
     */
    public function get_nhis_claims_for_export($from_date, $to_date)
    {
        $claims = [];
        
        if (!$this->db->table_exists('billing_master')) {
            return $claims;
        }
        
        // Get all NHIS bills with their items
        $this->db->select('bm.*, p.firstname, p.middlename, p.lastname, p.birthday, p.nhis_no');
        $this->db->from('billing_master bm');
        $this->db->join('patient_details p', 'p.patient_no = bm.patient_no', 'left');
        $this->db->where('DATE(bm.created_at) >=', $from_date);
        $this->db->where('DATE(bm.created_at) <=', $to_date);
        $this->db->where('bm.InActive', 0);
        $this->db->where('bm.visit_type', 'NHIS');
        $this->db->order_by('bm.created_at', 'ASC');
        
        $bills = $this->db->get()->result();
        
        foreach ($bills as $bill) {
            // Get items for this bill
            $this->db->where('bill_id', $bill->bill_id);
            $this->db->where('InActive', 0);
            $items = $this->db->get('billing_items')->result();
            
            $claims[] = [
                'bill' => $bill,
                'items' => $items,
                'patient_name' => trim($bill->firstname . ' ' . $bill->middlename . ' ' . $bill->lastname),
                'nhis_no' => $bill->nhis_no ?? ''
            ];
        }
        
        return $claims;
    }
    
    /* ================================================================== */
    /*  DEPARTMENT REPORTS                                                */
    /* ================================================================== */
    
    /**
     * Get department revenue breakdown
     */
    public function get_department_revenue($from_date, $to_date)
    {
        $report = [
            'from_date' => $from_date,
            'to_date' => $to_date,
            'departments' => []
        ];
        
        if (!$this->db->table_exists('billing_items')) {
            return $report;
        }
        
        $this->db->select('COALESCE(bi.department, bi.service_type) as dept_name, 
                          SUM(bi.line_total) as total_revenue,
                          COUNT(*) as item_count,
                          COUNT(DISTINCT bm.bill_id) as bill_count');
        $this->db->from('billing_items bi');
        $this->db->join('billing_master bm', 'bm.bill_id = bi.bill_id');
        $this->db->where('DATE(bm.created_at) >=', $from_date);
        $this->db->where('DATE(bm.created_at) <=', $to_date);
        $this->db->where('bi.InActive', 0);
        $this->db->group_by('COALESCE(bi.department, bi.service_type)');
        $this->db->order_by('total_revenue', 'DESC');
        
        $depts = $this->db->get()->result();
        
        foreach ($depts as $d) {
            $report['departments'][] = [
                'name' => $d->dept_name,
                'total_revenue' => (float)$d->total_revenue,
                'item_count' => (int)$d->item_count,
                'bill_count' => (int)$d->bill_count
            ];
        }
        
        return $report;
    }
    
    /* ================================================================== */
    /*  OUTSTANDING REPORTS                                               */
    /* ================================================================== */
    
    /**
     * Get outstanding bills summary
     */
    public function get_outstanding_summary()
    {
        $report = [
            'total_outstanding' => 0,
            'total_bills' => 0,
            'by_age' => [
                '0-7' => ['count' => 0, 'amount' => 0],
                '8-30' => ['count' => 0, 'amount' => 0],
                '31-60' => ['count' => 0, 'amount' => 0],
                '61-90' => ['count' => 0, 'amount' => 0],
                '90+' => ['count' => 0, 'amount' => 0]
            ],
            'top_debtors' => []
        ];
        
        if (!$this->db->table_exists('billing_master')) {
            return $report;
        }
        
        // Get all outstanding bills
        $this->db->select('bm.*, p.firstname, p.middlename, p.lastname, p.phone,
                          DATEDIFF(NOW(), bm.created_at) as days_outstanding');
        $this->db->from('billing_master bm');
        $this->db->join('patient_details p', 'p.patient_no = bm.patient_no', 'left');
        $this->db->where('bm.balance_due >', 0);
        $this->db->where('bm.InActive', 0);
        $this->db->where_in('bm.payment_status', ['PENDING', 'PARTIAL']);
        $this->db->order_by('bm.balance_due', 'DESC');
        
        $bills = $this->db->get()->result();
        
        foreach ($bills as $bill) {
            $balance = (float)$bill->balance_due;
            $days = (int)$bill->days_outstanding;
            
            $report['total_outstanding'] += $balance;
            $report['total_bills']++;
            
            // Age bucket
            if ($days <= 7) {
                $report['by_age']['0-7']['count']++;
                $report['by_age']['0-7']['amount'] += $balance;
            } elseif ($days <= 30) {
                $report['by_age']['8-30']['count']++;
                $report['by_age']['8-30']['amount'] += $balance;
            } elseif ($days <= 60) {
                $report['by_age']['31-60']['count']++;
                $report['by_age']['31-60']['amount'] += $balance;
            } elseif ($days <= 90) {
                $report['by_age']['61-90']['count']++;
                $report['by_age']['61-90']['amount'] += $balance;
            } else {
                $report['by_age']['90+']['count']++;
                $report['by_age']['90+']['amount'] += $balance;
            }
        }
        
        // Top debtors (limit to 20)
        $report['top_debtors'] = array_slice($bills, 0, 20);
        
        return $report;
    }
}
