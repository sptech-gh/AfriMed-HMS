<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Make_datevisit_nullable extends CI_Migration
{
    public function up()
    {
        // Alter dateVisit to be datetime NULL
        $this->db->query("ALTER TABLE patient_appointment MODIFY COLUMN dateVisit datetime NULL;");
    }

    public function down()
    {
        // Restore to NOT NULL (warning: this will fail if any null records exist)
        $this->db->query("ALTER TABLE patient_appointment MODIFY COLUMN dateVisit datetime NOT NULL;");
    }
}
