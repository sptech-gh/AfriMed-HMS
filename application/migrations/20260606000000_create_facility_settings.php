<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Migration: Create Facility Settings Table
 * 
 * Sets up the facility_settings table as the single source of truth
 * for facility branding, contacts, and identities, and seeds it 
 * with the values from the legacy company_info table.
 */
class Migration_Create_facility_settings extends CI_Migration
{
    public function up()
    {
        // 1) Create the facility_settings table
        $this->dbforge->add_field(array(
            'id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE,
                'auto_increment' => TRUE
            ),
            'facility_name' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'default' => 'Healthcare Facility'
            ),
            'facility_short_name' => array(
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => TRUE
            ),
            'facility_tagline' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ),
            'logo_path' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ),
            'logo_dark' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ),
            'logo_light' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ),
            'address' => array(
                'type' => 'TEXT',
                'null' => TRUE
            ),
            'phone' => array(
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => TRUE
            ),
            'email' => array(
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => TRUE
            ),
            'website' => array(
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => TRUE
            ),
            'tin' => array(
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => TRUE
            ),
            'registration_number' => array(
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => TRUE
            ),
            'footer_note' => array(
                'type' => 'TEXT',
                'null' => TRUE
            ),
            'created_at' => array(
                'type' => 'DATETIME',
                'null' => TRUE
            ),
            'updated_at' => array(
                'type' => 'DATETIME',
                'null' => TRUE
            )
        ));
        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('facility_settings', TRUE);

        // 2) Seed the default facility record from company_info if it exists
        if ($this->db->table_exists('company_info')) {
            $query = $this->db->get('company_info');
            $company = $query ? $query->row() : null;
            if ($company) {
                $this->db->insert('facility_settings', array(
                    'facility_name' => !empty($company->company_name) ? $company->company_name : 'Healthcare Facility',
                    'facility_short_name' => !empty($company->site_title) ? $company->site_title : '',
                    'facility_tagline' => !empty($company->hospital_tagline) ? $company->hospital_tagline : '',
                    'logo_path' => !empty($company->logo) ? $company->logo : '',
                    'logo_dark' => !empty($company->login_logo) ? $company->login_logo : '',
                    'logo_light' => !empty($company->header_logo) ? $company->header_logo : '',
                    'address' => !empty($company->company_address) ? $company->company_address : '',
                    'phone' => !empty($company->company_contactNo) ? $company->company_contactNo : '',
                    'email' => !empty($company->company_email) ? $company->company_email : '',
                    'tin' => !empty($company->TIN) ? $company->TIN : '',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ));
            } else {
                // Insert default fallback values
                $this->db->insert('facility_settings', array(
                    'facility_name' => 'Healthcare Facility',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ));
            }
        } else {
            // Insert default fallback values
            $this->db->insert('facility_settings', array(
                'facility_name' => 'Healthcare Facility',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ));
        }
    }

    public function down()
    {
        if ($this->db->table_exists('facility_settings')) {
            $this->dbforge->drop_table('facility_settings', TRUE);
        }
    }
}
