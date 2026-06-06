<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Migration: Unified Billing Schema
 */
class Migration_Unified_Billing_Schema extends CI_Migration {

    public function up()
    {
        // Billing Master Table
        if (!$this->db->table_exists('billing_master')) {
            $this->dbforge->add_field([
                'bill_id' => ['type' => 'BIGINT', 'unsigned' => TRUE, 'auto_increment' => TRUE],
                'bill_no' => ['type' => 'VARCHAR', 'constraint' => 50, 'unique' => TRUE],
                'patient_no' => ['type' => 'VARCHAR', 'constraint' => 25],
                'visit_id' => ['type' => 'VARCHAR', 'constraint' => 25],
                'visit_type' => ['type' => 'ENUM', 'constraint' => ['OPD','IPD','EMERGENCY','PHARMACY'], 'default' => 'OPD'],
                'total_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0.00],
                'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0.00],
                'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0.00],
                'net_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0.00],
                'paid_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0.00],
                'balance_due' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0.00],
                'payment_status' => ['type' => 'ENUM', 'constraint' => ['PENDING','PARTIAL','PAID','CANCELLED'], 'default' => 'PENDING'],
                'payer_type' => ['type' => 'ENUM', 'constraint' => ['CASH','NHIS','INSURANCE'], 'default' => 'CASH'],
                'created_by' => ['type' => 'VARCHAR', 'constraint' => 25],
                'created_at' => ['type' => 'DATETIME', 'default' => 'CURRENT_TIMESTAMP'],
                'InActive' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0]
            ]);
            $this->dbforge->add_key('bill_id', TRUE);
            $this->dbforge->create_table('billing_master');
        }

        // Billing Items Table
        if (!$this->db->table_exists('billing_items')) {
            $this->dbforge->add_field([
                'item_id' => ['type' => 'BIGINT', 'unsigned' => TRUE, 'auto_increment' => TRUE],
                'bill_id' => ['type' => 'BIGINT', 'unsigned' => TRUE],
                'service_type' => ['type' => 'ENUM', 'constraint' => ['LABORATORY','RADIOLOGY','SONOGRAPHY','PHARMACY','PROCEDURE','OTHER']],
                'service_name' => ['type' => 'VARCHAR', 'constraint' => 255],
                'department' => ['type' => 'VARCHAR', 'constraint' => 100],
                'quantity' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 1.00],
                'unit_price' => ['type' => 'DECIMAL', 'constraint' => '18,2'],
                'gross_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2'],
                'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0.00],
                'net_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2'],
                'gate_status' => ['type' => 'ENUM', 'constraint' => ['BLOCKED','RELEASED'], 'default' => 'BLOCKED'],
                'InActive' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'default' => 'CURRENT_TIMESTAMP']
            ]);
            $this->dbforge->add_key('item_id', TRUE);
            $this->dbforge->add_key('bill_id');
            $this->dbforge->create_table('billing_items');
        }

        // Billing Payments Table
        if (!$this->db->table_exists('billing_payments')) {
            $this->dbforge->add_field([
                'payment_id' => ['type' => 'BIGINT', 'unsigned' => TRUE, 'auto_increment' => TRUE],
                'bill_id' => ['type' => 'BIGINT', 'unsigned' => TRUE],
                'payment_no' => ['type' => 'VARCHAR', 'constraint' => 50, 'unique' => TRUE],
                'amount' => ['type' => 'DECIMAL', 'constraint' => '18,2'],
                'payment_method' => ['type' => 'ENUM', 'constraint' => ['CASH','MOMO','CARD','BANK_TRANSFER','NHIS']],
                'reference_no' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => TRUE],
                'collected_by' => ['type' => 'VARCHAR', 'constraint' => 25],
                'collected_at' => ['type' => 'DATETIME'],
                'InActive' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0]
            ]);
            $this->dbforge->add_key('payment_id', TRUE);
            $this->dbforge->add_key('bill_id');
            $this->dbforge->create_table('billing_payments');
        }
    }

    public function down()
    {
        $this->dbforge->drop_table('billing_payments');
        $this->dbforge->drop_table('billing_items');
        $this->dbforge->drop_table('billing_master');
    }
}
