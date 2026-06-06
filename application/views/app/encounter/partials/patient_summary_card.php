<?php
$__enc_picture = (isset($patientInfo) && isset($patientInfo->picture) && $patientInfo->picture) ? $patientInfo->picture : "avatar.png";
$__enc_show_nhis = (isset($show_nhis_badge) && $show_nhis_badge);
?>
<table width="100%" cellpadding="3" cellspacing="3">
	<tr>
		<td width="15%" valign="top" align="center">
			<img src="<?php echo base_url();?>public/patient_picture/<?php echo $__enc_picture;?>" class="img-rounded" width="86" height="81">
		</td>
		<td>
			<table width="100%">
				<tr>
					<td><u>Patient No.</u></td>
				</tr>
				<tr>
					<td><?php echo $patientInfo->patient_no?></td>
				</tr>
				<tr>
					<td><u>Patient Name</u></td>
				</tr>
				<tr>
					<td><?php echo $patientInfo->name?></td>
				</tr>
				<?php if ($__enc_show_nhis) {
					$v_nhis = isset($patientInfo->nhis_number) ? trim((string)$patientInfo->nhis_number) : '';
					$v_nhis_st = isset($patientInfo->nhis_status) ? strtoupper(trim((string)$patientInfo->nhis_status)) : '';
					if ($v_nhis !== ''):
				?>
				<tr>
					<td>
						<?php if ($v_nhis_st === 'ACTIVE'): ?>
							<span class="label label-success"><i class="fa fa-check-circle"></i> NHIS Active</span>
						<?php elseif ($v_nhis_st === 'EXPIRED'): ?>
							<span class="label label-danger"><i class="fa fa-exclamation-triangle"></i> NHIS Expired</span>
						<?php elseif ($v_nhis_st === 'INVALID'): ?>
							<span class="label label-warning"><i class="fa fa-ban"></i> NHIS Invalid</span>
						<?php else: ?>
							<span class="label label-default">NHIS</span>
						<?php endif; ?>
						<small style="color:#888;margin-left:4px;"><?php echo htmlspecialchars($v_nhis); ?></small>
					</td>
				</tr>
				<?php endif; } ?>
			</table>
		</td>
	</tr>
</table>
