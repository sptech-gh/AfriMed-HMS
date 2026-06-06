<?php if (isset($hasAccesstoDoctor) && $hasAccesstoDoctor && isset($encounter_is_owner) && !$encounter_is_owner) { ?>
<div class="alert alert-warning">
	<strong>You are not the assigned doctor</strong> for this encounter.
	<?php if (isset($encounter_owner_doctor_id) && trim((string)$encounter_owner_doctor_id) !== '') { ?>
		Assigned doctor ID: <strong><?php echo htmlspecialchars((string)$encounter_owner_doctor_id); ?></strong>.
	<?php } ?>
	<?php if (isset($encounter_can_override) && $encounter_can_override) { ?>
		<a class="btn btn-xs btn-danger" style="margin-left:10px;" href="<?php echo base_url().'app/doctor_override/grant/'.rawurlencode(isset($encounter_type) ? (string)$encounter_type : '').'/'.rawurlencode(isset($getOPDPatient->IO_ID) ? (string)$getOPDPatient->IO_ID : '').'/'.rawurlencode(isset($patientInfo->patient_no) ? (string)$patientInfo->patient_no : ''); ?>?return=<?php echo rawurlencode(current_url()); ?>" onclick="return confirm('Override and view/write anyway? This will be logged.');">Override / View Anyway</a>
	<?php } else { ?>
		<span class="text-muted" style="margin-left:10px;">Override not permitted for your role.</span>
	<?php } ?>
</div>
<?php } ?>
