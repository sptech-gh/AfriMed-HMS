<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Ghs_report_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
	}

	private function table_exists($t)
	{
		$q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($t));
		return ($q && $q->num_rows() > 0);
	}

	private function column_exists($table, $col)
	{
		$q = $this->db->query("SHOW COLUMNS FROM `" . str_replace('`', '', $table) . "` LIKE " . $this->db->escape($col));
		return ($q && $q->num_rows() > 0);
	}

	/* ================================================================== */
	/*  1. OPD ATTENDANCE REPORT (GHS Standard)                           */
	/* ================================================================== */

	public function opd_attendance($date_from, $date_to)
	{
		$sql = "
			SELECT
				o.date_visit AS visit_date,
				COUNT(*) AS total,
				SUM(CASE WHEN LOWER(g.cValue) = 'male' THEN 1 ELSE 0 END) AS male,
				SUM(CASE WHEN LOWER(g.cValue) = 'female' THEN 1 ELSE 0 END) AS female,
				SUM(CASE WHEN p.birthday IS NOT NULL AND TIMESTAMPDIFF(YEAR, p.birthday, o.date_visit) < 18 THEN 1 ELSE 0 END) AS children,
				SUM(CASE WHEN p.birthday IS NULL OR TIMESTAMPDIFF(YEAR, p.birthday, o.date_visit) >= 18 THEN 1 ELSE 0 END) AS adults
			FROM patient_details_iop o
			LEFT JOIN patient_personal_info p ON p.patient_no = o.patient_no
			LEFT JOIN system_parameters g ON g.param_id = p.gender
			WHERE o.date_visit >= ? AND o.date_visit <= ?
			AND o.patient_type = 'OPD'
			AND o.InActive = 0
			GROUP BY o.date_visit
			ORDER BY o.date_visit ASC
		";
		$q = $this->db->query($sql, array($date_from, $date_to));
		return $q ? $q->result() : array();
	}

	public function opd_attendance_summary($date_from, $date_to)
	{
		$sql = "
			SELECT
				COUNT(*) AS total,
				SUM(CASE WHEN LOWER(g.cValue) = 'male' THEN 1 ELSE 0 END) AS male,
				SUM(CASE WHEN LOWER(g.cValue) = 'female' THEN 1 ELSE 0 END) AS female,
				SUM(CASE WHEN p.birthday IS NOT NULL AND TIMESTAMPDIFF(YEAR, p.birthday, o.date_visit) < 18 THEN 1 ELSE 0 END) AS children,
				SUM(CASE WHEN p.birthday IS NULL OR TIMESTAMPDIFF(YEAR, p.birthday, o.date_visit) >= 18 THEN 1 ELSE 0 END) AS adults
			FROM patient_details_iop o
			LEFT JOIN patient_personal_info p ON p.patient_no = o.patient_no
			LEFT JOIN system_parameters g ON g.param_id = p.gender
			WHERE o.date_visit >= ? AND o.date_visit <= ?
			AND o.patient_type = 'OPD'
			AND o.InActive = 0
		";
		$q = $this->db->query($sql, array($date_from, $date_to));
		$row = $q ? $q->row() : null;
		if (!$row) return array('total' => 0, 'male' => 0, 'female' => 0, 'children' => 0, 'adults' => 0);
		return array(
			'total'    => (int)$row->total,
			'male'     => (int)$row->male,
			'female'   => (int)$row->female,
			'children' => (int)$row->children,
			'adults'   => (int)$row->adults
		);
	}

	/* ================================================================== */
	/*  2. DIAGNOSIS REPORT (Top diagnoses — GHS Morbidity)               */
	/* ================================================================== */

	public function top_diagnoses($date_from, $date_to, $limit = 20)
	{
		$sql = "
			SELECT
				COALESCE(d.diagnosis_name, id.diagnosis_text, 'Unknown') AS diagnosis_name,
				COUNT(*) AS total_cases,
				SUM(CASE WHEN LOWER(g.cValue) = 'male' THEN 1 ELSE 0 END) AS male,
				SUM(CASE WHEN LOWER(g.cValue) = 'female' THEN 1 ELSE 0 END) AS female,
				SUM(CASE WHEN p.birthday IS NOT NULL AND TIMESTAMPDIFF(YEAR, p.birthday, v.date_visit) < 5 THEN 1 ELSE 0 END) AS under_5,
				SUM(CASE WHEN p.birthday IS NOT NULL AND TIMESTAMPDIFF(YEAR, p.birthday, v.date_visit) >= 5 AND TIMESTAMPDIFF(YEAR, p.birthday, v.date_visit) < 18 THEN 1 ELSE 0 END) AS age_5_17,
				SUM(CASE WHEN p.birthday IS NULL OR TIMESTAMPDIFF(YEAR, p.birthday, v.date_visit) >= 18 THEN 1 ELSE 0 END) AS age_18_plus
			FROM iop_diagnosis id
			LEFT JOIN patient_details_iop v ON v.IO_ID = id.iop_id
			LEFT JOIN patient_personal_info p ON p.patient_no = v.patient_no
			LEFT JOIN system_parameters g ON g.param_id = p.gender
			LEFT JOIN diagnosis d ON d.diagnosis_id = id.diagnosis_id
			WHERE id.InActive = 0
			AND id.dDate >= ? AND id.dDate <= ?
			GROUP BY COALESCE(d.diagnosis_name, id.diagnosis_text, 'Unknown')
			ORDER BY total_cases DESC
			LIMIT ?
		";
		$q = $this->db->query($sql, array($date_from . ' 00:00:00', $date_to . ' 23:59:59', (int)$limit));
		return $q ? $q->result() : array();
	}

	/* ================================================================== */
	/*  3. PHARMACY CONSUMPTION REPORT (GHS Drug Accountability)          */
	/* ================================================================== */

	public function pharmacy_consumption($date_from, $date_to)
	{
		// Opening balance = stock at start of period (approximate: current stock + dispensed - received in period)
		// Received = stock adjustments (restock type) within period
		// Dispensed = medications dispensed within period
		// Closing balance = current stock

		$drugs = array();
		$drugRows = $this->db->select('drug_id, drug_name, nStock')
			->from('medicine_drug_name')
			->where('InActive', 0)
			->order_by('drug_name', 'ASC')
			->get()->result();

		foreach ($drugRows as $d) {
			$did = (int)$d->drug_id;

			// Dispensed in period
			$dispensed = 0;
			$dq = $this->db->query("
				SELECT COALESCE(SUM(a.dose_given), 0) AS dispensed
				FROM iop_medication_administration a
				WHERE a.InActive = 0
				AND a.status IN ('DISPENSED', 'PARTIAL')
				AND a.dDateTime >= ? AND a.dDateTime <= ?
				AND a.iop_med_id IN (
					SELECT m.iop_med_id FROM iop_medication m WHERE m.medicine_id = ?
				)
			", array($date_from . ' 00:00:00', $date_to . ' 23:59:59', $did));
			if ($dq && $dq->row()) $dispensed = (float)$dq->row()->dispensed;

			// Received in period (from pharmacy_stock_adjustment or pharmacy_stock_requests approved)
			$received = 0;
			if ($this->table_exists('pharmacy_stock_adjustment')) {
				$rq = $this->db->query("
					SELECT COALESCE(SUM(CASE WHEN qty_change > 0 THEN qty_change ELSE 0 END), 0) AS received
					FROM pharmacy_stock_adjustment
					WHERE drug_id = ?
					AND created_at >= ? AND created_at <= ?
				", array($did, $date_from . ' 00:00:00', $date_to . ' 23:59:59'));
				if ($rq && $rq->row()) $received = (float)$rq->row()->received;
			}

			$closing = (float)$d->nStock;
			$opening = $closing - $received + $dispensed;
			if ($opening < 0) $opening = 0;

			if ($dispensed > 0 || $received > 0) {
				$drugs[] = (object)array(
					'drug_name' => $d->drug_name,
					'opening'   => $opening,
					'received'  => $received,
					'dispensed' => $dispensed,
					'closing'   => $closing
				);
			}
		}

		return $drugs;
	}

	/* ================================================================== */
	/*  4. NHIS vs CASH REPORT                                            */
	/* ================================================================== */

	public function nhis_vs_cash($date_from, $date_to)
	{
		$result = array(
			'nhis_patients'  => 0,
			'cash_patients'  => 0,
			'nhis_revenue'   => 0,
			'cash_revenue'   => 0,
			'total_revenue'  => 0,
			'nhis_visits'    => 0,
			'cash_visits'    => 0
		);

		$hasPayer = $this->column_exists('iop_billing', 'payer_type');

		if ($hasPayer) {
			$sql = "
				SELECT
					payer_type,
					COUNT(DISTINCT patient_no) AS patients,
					COUNT(*) AS visits,
					COALESCE(SUM(total_amount), 0) AS revenue
				FROM iop_billing
				WHERE InActive = 0
				AND dDate >= ? AND dDate <= ?
				GROUP BY payer_type
			";
			$q = $this->db->query($sql, array($date_from, $date_to));
			$rows = $q ? $q->result() : array();
			foreach ($rows as $r) {
				$pt = strtoupper(trim((string)$r->payer_type));
				if ($pt === 'NHIS') {
					$result['nhis_patients'] = (int)$r->patients;
					$result['nhis_visits']   = (int)$r->visits;
					$result['nhis_revenue']  = (float)$r->revenue;
				} else {
					$result['cash_patients'] += (int)$r->patients;
					$result['cash_visits']   += (int)$r->visits;
					$result['cash_revenue']  += (float)$r->revenue;
				}
			}
		} else {
			$sql = "
				SELECT
					COUNT(DISTINCT patient_no) AS patients,
					COUNT(*) AS visits,
					COALESCE(SUM(total_amount), 0) AS revenue
				FROM iop_billing
				WHERE InActive = 0
				AND dDate >= ? AND dDate <= ?
			";
			$q = $this->db->query($sql, array($date_from, $date_to));
			$r = $q ? $q->row() : null;
			if ($r) {
				$result['cash_patients'] = (int)$r->patients;
				$result['cash_visits']   = (int)$r->visits;
				$result['cash_revenue']  = (float)$r->revenue;
			}
		}

		$result['total_revenue'] = $result['nhis_revenue'] + $result['cash_revenue'];

		// Monthly breakdown
		$monthly = array();
		if ($hasPayer) {
			$mq = $this->db->query("
				SELECT
					DATE_FORMAT(dDate, '%Y-%m') AS month_key,
					MIN(DATE_FORMAT(dDate, '%M %Y')) AS month_label,
					payer_type,
					COALESCE(SUM(total_amount), 0) AS revenue,
					COUNT(*) AS invoices
				FROM iop_billing
				WHERE InActive = 0
				AND dDate >= ? AND dDate <= ?
				GROUP BY DATE_FORMAT(dDate, '%Y-%m'), payer_type
				ORDER BY month_key ASC
			", array($date_from, $date_to));
			$mrows = $mq ? $mq->result() : array();
			foreach ($mrows as $m) {
				$mk = $m->month_key;
				if (!isset($monthly[$mk])) {
					$monthly[$mk] = (object)array(
						'month' => $m->month_label,
						'nhis_revenue' => 0, 'cash_revenue' => 0,
						'nhis_invoices' => 0, 'cash_invoices' => 0
					);
				}
				if (strtoupper(trim($m->payer_type)) === 'NHIS') {
					$monthly[$mk]->nhis_revenue = (float)$m->revenue;
					$monthly[$mk]->nhis_invoices = (int)$m->invoices;
				} else {
					$monthly[$mk]->cash_revenue += (float)$m->revenue;
					$monthly[$mk]->cash_invoices += (int)$m->invoices;
				}
			}
		}
		$result['monthly'] = array_values($monthly);

		return $result;
	}

	/* ================================================================== */
	/*  5. REVENUE REPORT (by department/service)                         */
	/* ================================================================== */

	public function revenue_by_category($date_from, $date_to)
	{
		$categories = array(
			'Consultation'  => 0,
			'Pharmacy'      => 0,
			'Laboratory'    => 0,
			'Imaging'       => 0,
			'Other'         => 0
		);

		// From billing details joined via invoice_no
		$sql = "
			SELECT
				bg.group_name,
				COALESCE(SUM(d.amount), 0) AS revenue
			FROM iop_billing_t d
			LEFT JOIN iop_billing h ON h.invoice_no = d.invoice_no
			LEFT JOIN bill_particular bp ON bp.particular_name = d.bill_name
			LEFT JOIN bill_group_name bg ON bg.group_id = bp.group_id
			WHERE d.InActive = 0
			AND h.InActive = 0
			AND h.dDate >= ? AND h.dDate <= ?
			GROUP BY bg.group_name
		";
		$q = $this->db->query($sql, array($date_from, $date_to));
		$rows = $q ? $q->result() : array();

		foreach ($rows as $r) {
			$gn = strtolower(trim((string)$r->group_name));
			$rev = (float)$r->revenue;

			if (strpos($gn, 'consult') !== false || strpos($gn, 'registration') !== false) {
				$categories['Consultation'] += $rev;
			} elseif (strpos($gn, 'pharm') !== false || strpos($gn, 'drug') !== false || strpos($gn, 'medic') !== false) {
				$categories['Pharmacy'] += $rev;
			} elseif (strpos($gn, 'lab') !== false || strpos($gn, 'test') !== false || strpos($gn, 'haemat') !== false || strpos($gn, 'biochem') !== false || strpos($gn, 'micro') !== false || strpos($gn, 'serol') !== false || strpos($gn, 'pathol') !== false || strpos($gn, 'special') !== false) {
				$categories['Laboratory'] += $rev;
			} elseif (strpos($gn, 'imag') !== false || strpos($gn, 'x-ray') !== false || strpos($gn, 'x -ray') !== false || strpos($gn, 'sono') !== false || strpos($gn, 'ultra') !== false || strpos($gn, 'scan') !== false || strpos($gn, 'ct ') !== false) {
				$categories['Imaging'] += $rev;
			} else {
				$categories['Other'] += $rev;
			}
		}

		return $categories;
	}

	public function revenue_daily($date_from, $date_to)
	{
		$sql = "
			SELECT
				dDate AS bill_date,
				COALESCE(SUM(total_amount), 0) AS total,
				COUNT(*) AS invoices
			FROM iop_billing
			WHERE InActive = 0
			AND dDate >= ? AND dDate <= ?
			GROUP BY dDate
			ORDER BY dDate ASC
		";
		$q = $this->db->query($sql, array($date_from, $date_to));
		return $q ? $q->result() : array();
	}

	public function revenue_total($date_from, $date_to)
	{
		$sql = "
			SELECT
				COALESCE(SUM(total_amount), 0) AS total_revenue,
				COUNT(*) AS total_invoices
			FROM iop_billing
			WHERE InActive = 0
			AND dDate >= ? AND dDate <= ?
		";
		$q = $this->db->query($sql, array($date_from, $date_to));
		$row = $q ? $q->row() : null;
		return array(
			'total_revenue'  => $row ? (float)$row->total_revenue : 0,
			'total_invoices' => $row ? (int)$row->total_invoices : 0
		);
	}

	/* ================================================================== */
	/*  6. DAILY RETURNS REPORT (GHS Standard Summary)                    */
	/* ================================================================== */

	public function daily_returns($report_date)
	{
		$day_start = $report_date . ' 00:00:00';
		$day_end   = $report_date . ' 23:59:59';

		// OPD attendance
		$opd = $this->db->query("
			SELECT COUNT(*) AS cnt FROM patient_details_iop
			WHERE date_visit = ? AND patient_type = 'OPD' AND InActive = 0
		", array($report_date));
		$opd_count = ($opd && $opd->row()) ? (int)$opd->row()->cnt : 0;

		// Admissions (IPD)
		$adm = $this->db->query("
			SELECT COUNT(*) AS cnt FROM patient_details_iop
			WHERE date_visit = ? AND patient_type = 'IPD' AND nStatus = 'Pending' AND InActive = 0
		", array($report_date));
		$admissions = ($adm && $adm->row()) ? (int)$adm->row()->cnt : 0;

		// Discharges
		$dis = 0;
		if ($this->table_exists('iop_discharge_summary')) {
			$dq = $this->db->query("
				SELECT COUNT(*) AS cnt FROM iop_discharge_summary
				WHERE dDate = ? AND InActive = 0
			", array($report_date));
			$dis = ($dq && $dq->row()) ? (int)$dq->row()->cnt : 0;
		}

		// Deaths (from discharge summary where condition indicates death)
		$deaths = 0;
		if ($this->table_exists('iop_discharge_summary')) {
			$deq = $this->db->query("
				SELECT COUNT(*) AS cnt FROM iop_discharge_summary
				WHERE dDate = ? AND InActive = 0
				AND LOWER(condition_upon_discharge) IN ('dead','died','death','expired','doa')
			", array($report_date));
			$deaths = ($deq && $deq->row()) ? (int)$deq->row()->cnt : 0;
		}

		// Revenue
		$rev = $this->db->query("
			SELECT COALESCE(SUM(total_amount), 0) AS total FROM iop_billing
			WHERE dDate = ? AND InActive = 0
		", array($report_date));
		$revenue = ($rev && $rev->row()) ? (float)$rev->row()->total : 0;

		// Payments received
		$pay = 0;
		if ($this->table_exists('iop_receipt')) {
			$pq = $this->db->query("
				SELECT COALESCE(SUM(amountPaid), 0) AS total FROM iop_receipt
				WHERE DATE(dDate) = ? AND InActive = 0
			", array($report_date));
			$pay = ($pq && $pq->row()) ? (float)$pq->row()->total : 0;
		}

		// Prescriptions dispensed
		$rx = 0;
		if ($this->table_exists('iop_medication_administration')) {
			$rxq = $this->db->query("
				SELECT COUNT(*) AS cnt FROM iop_medication_administration
				WHERE dDateTime >= ? AND dDateTime <= ? AND InActive = 0 AND status IN ('DISPENSED','PARTIAL')
			", array($day_start, $day_end));
			$rx = ($rxq && $rxq->row()) ? (int)$rxq->row()->cnt : 0;
		}

		// Lab tests completed
		$lab = 0;
		$lq = $this->db->query("
			SELECT COUNT(*) AS cnt FROM iop_laboratory
			WHERE InActive = 0 AND dDate = ?
		", array($report_date));
		$lab = ($lq && $lq->row()) ? (int)$lq->row()->cnt : 0;

		return array(
			'date'           => $report_date,
			'opd_attendance' => $opd_count,
			'admissions'     => $admissions,
			'discharges'     => $dis,
			'deaths'         => $deaths,
			'revenue'        => $revenue,
			'payments'       => $pay,
			'prescriptions'  => $rx,
			'lab_tests'      => $lab
		);
	}

	/**
	 * Daily returns for a date range (one row per day).
	 */
	public function daily_returns_range($date_from, $date_to)
	{
		$results = array();
		$start = new DateTime($date_from);
		$end   = new DateTime($date_to);
		$end->modify('+1 day');
		$interval = new DateInterval('P1D');
		$period = new DatePeriod($start, $interval, $end);

		foreach ($period as $dt) {
			$d = $dt->format('Y-m-d');
			$results[] = $this->daily_returns($d);
		}
		return $results;
	}
}
