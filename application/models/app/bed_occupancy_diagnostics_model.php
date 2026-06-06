<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

class Bed_occupancy_diagnostics_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
	}

	public function scan($limit = 50)
	{
		$limit = (int)$limit;
		if ($limit <= 0) {
			$limit = 50;
		}

		$result = array(
			'ok' => true,
			'duplicated_patient_no_in_room_beds' => array(),
			'occupied_beds_missing_patient_no' => array(),
			'active_ipd_admissions_missing_bed' => array(),
			'active_ipd_admissions_bed_mismatch' => array(),
		);

		// 1) Duplicate patient_no across active beds (InActive=0)
		$qdup = $this->db->query(
			"SELECT patient_no, COUNT(*) AS c\n".
			"FROM room_beds\n".
			"WHERE InActive = 0 AND patient_no IS NOT NULL AND TRIM(patient_no) <> ''\n".
			"GROUP BY patient_no\n".
			"HAVING c > 1\n".
			"ORDER BY c DESC\n".
			"LIMIT {$limit}"
		);
		$result['duplicated_patient_no_in_room_beds'] = $qdup ? $qdup->result_array() : array();

		// 2) Occupied beds with missing patient_no
		$qmiss = $this->db->query(
			"SELECT room_bed_id, room_master_id, bed_name, nStatus, patient_no\n".
			"FROM room_beds\n".
			"WHERE InActive = 0\n".
			"  AND LOWER(TRIM(nStatus)) = 'occupied'\n".
			"  AND (patient_no IS NULL OR TRIM(patient_no) = '')\n".
			"LIMIT {$limit}"
		);
		$result['occupied_beds_missing_patient_no'] = $qmiss ? $qmiss->result_array() : array();

		// 3) Active IPD admissions missing an occupied bed row
		$qipdMissing = $this->db->query(
			"SELECT a.IO_ID, a.patient_no, a.room_id\n".
			"FROM patient_details_iop a\n".
			"LEFT JOIN room_beds b ON b.room_bed_id = a.room_id AND b.InActive = 0\n".
			"WHERE a.InActive = 0\n".
			"  AND a.patient_type = 'IPD'\n".
			"  AND a.nStatus = 'Pending'\n".
			"  AND (a.room_id IS NULL OR a.room_id = 0 OR b.room_bed_id IS NULL)\n".
			"LIMIT {$limit}"
		);
		$result['active_ipd_admissions_missing_bed'] = $qipdMissing ? $qipdMissing->result_array() : array();

		// 4) Active IPD admissions whose bed row exists but is not occupied by them
		$qipdMismatch = $this->db->query(
			"SELECT a.IO_ID, a.patient_no, a.room_id, b.nStatus AS bed_status, b.patient_no AS bed_patient_no\n".
			"FROM patient_details_iop a\n".
			"JOIN room_beds b ON b.room_bed_id = a.room_id AND b.InActive = 0\n".
			"WHERE a.InActive = 0\n".
			"  AND a.patient_type = 'IPD'\n".
			"  AND a.nStatus = 'Pending'\n".
			"  AND (LOWER(TRIM(b.nStatus)) <> 'occupied' OR b.patient_no <> a.IO_ID)\n".
			"LIMIT {$limit}"
		);
		$result['active_ipd_admissions_bed_mismatch'] = $qipdMismatch ? $qipdMismatch->result_array() : array();

		$result['ok'] = (
			empty($result['duplicated_patient_no_in_room_beds'])
			&& empty($result['occupied_beds_missing_patient_no'])
			&& empty($result['active_ipd_admissions_missing_bed'])
			&& empty($result['active_ipd_admissions_bed_mismatch'])
		);

		return $result;
	}
}
