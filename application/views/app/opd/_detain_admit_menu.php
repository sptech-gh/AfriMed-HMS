<?php
$isDoctor = false;
if (function_exists('has_role')) {
	$isDoctor = (has_role('doctor') || has_role('admin'));
} else {
	$isDoctor = (isset($hasAccesstoDoctor) && $hasAccesstoDoctor);
}
if (!$isDoctor) {
	return;
}

$detStartAt = isset($getOPDPatient->detention_start_at) ? trim((string)$getOPDPatient->detention_start_at) : '';
$convAt = isset($getOPDPatient->converted_to_admission_at) ? trim((string)$getOPDPatient->converted_to_admission_at) : '';
$convIpd = isset($getOPDPatient->converted_ipd_iop_id) ? trim((string)$getOPDPatient->converted_ipd_iop_id) : '';

$isConverted = ($convAt !== '' && $convAt !== '0000-00-00 00:00:00');
$isDetained = ($detStartAt !== '' && $detStartAt !== '0000-00-00 00:00:00');

$patientNo = isset($getOPDPatient->patient_no) ? (string)$getOPDPatient->patient_no : '';
$iopId = isset($getOPDPatient->IO_ID) ? (string)$getOPDPatient->IO_ID : '';

$activeIpdIop = '';
if ($patientNo !== '' && isset($this->db) && method_exists($this->db, 'table_exists') && $this->db->table_exists('patient_details_iop')) {
	$this->db->select('IO_ID');
	$this->db->where(array('patient_no' => $patientNo, 'patient_type' => 'IPD', 'nStatus' => 'Pending', 'InActive' => 0));
	$this->db->limit(1);
	$row = $this->db->get('patient_details_iop')->row();
	if ($row && isset($row->IO_ID)) {
		$activeIpdIop = (string)$row->IO_ID;
	}
}

$hasPendingAdmissionRequest = false;
if ($iopId !== '' && isset($this->db) && method_exists($this->db, 'table_exists') && $this->db->table_exists('opd_admission_queue')) {
	$this->db->select('queue_id');
	$this->db->where(array('iop_id' => $iopId, 'admission_status' => 'PENDING_ASSIGNMENT', 'InActive' => 0));
	$this->db->limit(1);
	$q = $this->db->get('opd_admission_queue')->row();
	$hasPendingAdmissionRequest = (bool)$q;
}

echo '<li>';
if ($isConverted) {
	echo '<a href="#" class="text-muted" onclick="return false;" title="Converted to IPD">'
		. '<i class="fa fa-clock-o"></i> Detention Converted '
		. ($convIpd !== '' ? '(' . htmlspecialchars($convIpd) . ')' : '')
		. '</a>';
} elseif ($activeIpdIop !== '') {
	echo '<a href="#" class="text-muted" onclick="return false;" title="Patient already has an active IPD admission">'
		. '<i class="fa fa-bed"></i> Already Admitted '
		. '(' . htmlspecialchars($activeIpdIop) . ')'
		. '</a>';
} elseif ($isDetained) {
	echo '<a href="#" class="text-warning" onclick="return false;" title="Patient is detained for observation">'
		. '<i class="fa fa-clock-o"></i> Detained since '
		. htmlspecialchars(date('M d, Y H:i', strtotime($detStartAt)))
		. '</a>';
} else {
	echo '<a href="#" class="text-warning" data-toggle="modal" data-target="#modalDetainPatient" title="Mark patient as detained for observation">'
		. '<i class="fa fa-clock-o"></i> Detain Patient (Observation)'
		. '</a>';
}
echo '</li>';

echo '<li>';
if ($activeIpdIop !== '' || $isConverted) {
	echo '<a href="#" class="text-muted" onclick="return false;" title="Admission already exists">'
		. '<i class="fa fa-hospital-o"></i> Admit Patient (IPD)'
		. '</a>';
} elseif ($hasPendingAdmissionRequest) {
	echo '<a href="#" class="text-muted" onclick="return false;" title="Admission already requested">'
		. '<i class="fa fa-hospital-o"></i> Admission Requested'
		. '</a>';
} else {
	echo '<a href="#" class="text-danger" data-toggle="modal" data-target="#modalAdmitPatient" title="Queue patient for IPD admission">'
		. '<i class="fa fa-hospital-o"></i> Admit Patient (IPD)'
		. '</a>';
}
echo '</li>';
