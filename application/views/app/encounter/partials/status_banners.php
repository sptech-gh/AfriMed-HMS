<?php
$isClinicallyCleared = false;
if (isset($getOPDPatient) && $getOPDPatient && isset($getOPDPatient->clinical_clearance_status) && $getOPDPatient->clinical_clearance_status == 1) {
	$isClinicallyCleared = true;
}
if ($isClinicallyCleared):
?>
<div class="alert alert-success">
	<i class="fa fa-lock"></i> <strong>Visit Clinically Cleared</strong> - This visit is locked. No further orders can be added.
	<?php if (isset($getOPDPatient->clinically_cleared_at)): ?>
		<small class="pull-right">Cleared: <?php echo date('M d, Y H:i', strtotime($getOPDPatient->clinically_cleared_at)); ?></small>
	<?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$isClinicallyCleared && isset($vitals_done) && !$vitals_done) { ?>
<div class="alert alert-warning">
	<i class="fa fa-heartbeat"></i> <strong>Vitals Pending</strong> - Vitals must be recorded before consultation.
	<?php if ((isset($isNurse) && $isNurse) || (isset($isReception) && $isReception)) { if (isset($vitals_record_url) && $vitals_record_url != '') { ?>
		<a href="<?php echo $vitals_record_url; ?>" class="btn btn-xs btn-primary" style="margin-left:10px;"><i class="fa fa-heartbeat"></i> Record Vitals</a>
	<?php } } ?>
</div>
<?php } ?>
