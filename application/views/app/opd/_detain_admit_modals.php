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
?>

<div class="modal fade" id="modalDetainPatient" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header bg-yellow">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title"><i class="fa fa-clock-o"></i> Detain Patient (Observation)</h4>
			</div>
			<div class="modal-body">
				<div id="detainAlert"></div>
				<p class="text-muted"><i class="fa fa-info-circle"></i> Detention is free until midnight. If the patient remains after 12:00am, the system will convert this OPD visit to an IPD admission for ward billing.</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-warning" id="btnConfirmDetain"><i class="fa fa-check"></i> Confirm Detention</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modalAdmitPatient" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header bg-red">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title"><i class="fa fa-hospital-o"></i> Queue Patient for IPD Admission</h4>
			</div>
			<div class="modal-body">
				<div id="admitAlert"></div>
				<div class="form-group">
					<label>Admission Reason / Clinical Indication</label>
					<textarea id="admitReason" class="form-control" rows="3" placeholder="Enter reason for admission..."></textarea>
				</div>
				<p class="text-muted"><i class="fa fa-info-circle"></i> This will notify IPD Registration staff to assign a ward and bed.</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-danger" id="btnConfirmAdmit"><i class="fa fa-bed"></i> Confirm Admission</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
	(function(){
		if (typeof jQuery === 'undefined') { return; }
		jQuery(function ($) {
			$('#btnConfirmDetain').off('click.detain').on('click.detain', function () {
				var btn = $(this).prop('disabled', true).text('Submitting...');
				$.post('<?php echo base_url(); ?>app/opd/detain_patient_ajax', {
					'<?php echo $this->security->get_csrf_token_name(); ?>': '<?php echo $this->security->get_csrf_hash(); ?>',
					iop_id: '<?php echo isset($getOPDPatient->IO_ID) ? htmlspecialchars((string)$getOPDPatient->IO_ID, ENT_QUOTES) : ''; ?>',
					patient_no: '<?php echo isset($getOPDPatient->patient_no) ? htmlspecialchars((string)$getOPDPatient->patient_no, ENT_QUOTES) : ''; ?>'
				}, function (res) {
					if (res && res.ok) {
						$('#detainAlert').html('<div class="alert alert-success"><i class="fa fa-check"></i> Patient marked as detained.</div>');
						btn.text('Done');
						setTimeout(function(){ window.location.reload(); }, 700);
					} else {
						$('#detainAlert').html('<div class="alert alert-danger">' + (res ? res.error : 'Error') + '</div>');
						btn.prop('disabled', false).text('Confirm Detention');
					}
				}, 'json').fail(function (xhr) {
					var msg = 'Request failed. Please try again.';
					if (xhr.status === 403) { msg = 'Session expired. Please refresh the page and try again.'; }
					$('#detainAlert').html('<div class="alert alert-danger">' + msg + '</div>');
					btn.prop('disabled', false).text('Confirm Detention');
				});
			});

			$('#btnConfirmAdmit').off('click.admit').on('click.admit', function () {
				var reason = $.trim($('#admitReason').val());
				if (reason === '') {
					$('#admitAlert').html('<div class="alert alert-warning">Please enter an admission reason.</div>');
					return;
				}
				var btn = $(this).prop('disabled', true).text('Submitting...');
				$.post('<?php echo base_url(); ?>app/opd/admit_patient_from_opd', {
					'<?php echo $this->security->get_csrf_token_name(); ?>': '<?php echo $this->security->get_csrf_hash(); ?>',
					iop_id: '<?php echo isset($getOPDPatient->IO_ID) ? htmlspecialchars((string)$getOPDPatient->IO_ID, ENT_QUOTES) : ''; ?>',
					patient_no: '<?php echo isset($getOPDPatient->patient_no) ? htmlspecialchars((string)$getOPDPatient->patient_no, ENT_QUOTES) : ''; ?>',
					reason: reason,
					doctor_id: '<?php echo (string)$this->session->userdata('user_id'); ?>'
				}, function (res) {
					if (res && res.ok) {
						$('#admitAlert').html('<div class="alert alert-success"><i class="fa fa-check"></i> ' + res.message + '</div>');
						btn.text('Done');
					} else {
						$('#admitAlert').html('<div class="alert alert-danger">' + (res ? res.error : 'Error') + '</div>');
						btn.prop('disabled', false).text('Confirm Admission');
					}
				}, 'json').fail(function (xhr) {
					var msg = 'Request failed. Please try again.';
					if (xhr.status === 403) { msg = 'Session expired. Please refresh the page and try again.'; }
					$('#admitAlert').html('<div class="alert alert-danger">' + msg + '</div>');
					btn.prop('disabled', false).text('Confirm Admission');
				});
			});
		});
	})();
</script>
