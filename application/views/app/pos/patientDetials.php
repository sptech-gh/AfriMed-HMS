<?php if (!isset($patientDetials) || $patientDetials === null): ?>
<div class="alert alert-warning">
    <i class="fa fa-warning"></i> <strong>No active visit found.</strong><br>
    <small>The patient may not have an OPD/IPD visit registered today. 
    <a href="<?php echo base_url(); ?>app/opd" class="alert-link">Register OPD Visit</a> or 
    <a href="<?php echo base_url(); ?>app/pos" class="alert-link">Search for patient</a>.</small>
</div>
<?php else: ?>
<table width="100%" cellpadding="3" cellspacing="3">
<tr>
	<td width="15%" valign="top" align="center">
    <?php
	$picture = "avatar.png";
	if(isset($patientDetials->picture) && $patientDetials->picture){
		$picture = $patientDetials->picture;
	}
	?>
		<img src="<?php echo base_url();?>public/patient_picture/<?php echo $picture;?>" class="img-rounded" width="86" height="81">
	</td>
	<td>
		<table cellpadding="2" width="100%">
        <tr>
        	<td><strong>Patient No.</strong></td>
            <td><?php echo isset($patientDetials->patient_no) ? $patientDetials->patient_no : '';?></td>
        </tr>
        <tr>
        	<td><strong>IOP No.</strong></td>
            <td><?php echo isset($patientDetials->IO_ID) ? $patientDetials->IO_ID : '';?></td>
        </tr>
        <tr>
        	<td colspan="2"><strong>Patient Name.</strong></td>
        </tr>
        <tr>
        	<td colspan="2"><?php echo isset($patientDetials->patient) ? $patientDetials->patient : '';?></td>
        </tr>
        </table>
	</td>
</tr>
</table>
<input type="hidden" name="opd_no" id="opd_no" value="<?php echo isset($patientDetials->IO_ID) ? $patientDetials->IO_ID : '';?>">
<input type="hidden" name="patient_no" id="patient_no" value="<?php echo isset($patientDetials->patient_no) ? $patientDetials->patient_no : '';?>">
<input type="hidden" name="insurance_card_status" id="insurance_card_status" value="<?php echo isset($patientDetials->insurance_card_status) ? strtoupper(trim((string)$patientDetials->insurance_card_status)) : 'ACTIVE'; ?>">
<?php endif; ?>