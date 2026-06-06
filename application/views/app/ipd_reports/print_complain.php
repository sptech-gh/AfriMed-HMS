<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<head>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo $reports_title?></title>
</head>  
<style>
body{
	font-family:Verdana, Geneva, sans-serif;
	font-size:10px;
}
</style>
<body>

<center>
<font size="+1"><?php echo $companyInfo->company_name;?></font></b><br>                   
                            <?php echo $companyInfo->company_address;?><br>     
                            <?php echo $companyInfo->company_contactNo;?><br><br>
                            <?php echo $reports_title?>
</center>   
<br /><br />
<table align="center" width="100%" border="1" cellpadding="2" cellspacing="2" style="border:1px #999; border-collapse:collapse">
<tr>
	<td><strong>Patient Name</strong><br /><br /><?php echo strtoupper($patientInfo->name);?></td>
    <td><strong>Reg No./IOP No.</strong><br /><br /><?php echo strtoupper($patientInfo->patient_no." / ".$getOPDPatient->IO_ID)?></td>
    <td><strong>Gender</strong><br /><br /><?php echo strtoupper($patientInfo->gender)?></td>
    <td><strong>Date of Birth</strong><br /><br /><?php echo date("M d, Y",strtotime($patientInfo->birthday));?></td>
</tr>
<tr>
	<td><strong>Address</strong><br /><br /><?php echo strtoupper($patientInfo->address)?></td>
    <td><strong>Age</strong><br /><br /><?php echo strtoupper($patientInfo->age)?></td>
    <td><strong>Contact No.</strong><br /><br /><?php echo strtoupper($patientInfo->phone_no)?></td>
    <td><strong>Civil Status</strong><br /><br /><?php echo strtoupper($patientInfo->civil_status)?></td>
</tr>
</table>
<br />

<table cellpadding="5" cellspacing="5" width="100%" align="center" style="border-collapse:collapse;">
<tr style="background:#f0f0f0;">
	<th style="border:1px solid #999; padding:5px;">Date &amp; Time</th>
	<th style="border:1px solid #999; padding:5px;">Complaint</th>
	<th style="border:1px solid #999; padding:5px;">Free Text</th>
	<th style="border:1px solid #999; padding:5px;">Severity</th>
	<th style="border:1px solid #999; padding:5px;">Duration</th>
	<th style="border:1px solid #999; padding:5px;">Onset</th>
	<th style="border:1px solid #999; padding:5px;">Remarks</th>
</tr>
<?php foreach($patientComplain as $patientComplain){?>
<tr>
	<td style="border:1px solid #ccc; padding:4px; white-space:nowrap;">
		<?php echo $patientComplain->dDate ? date("d M Y H:i", strtotime($patientComplain->dDate)) : '—'; ?>
	</td>
	<td style="border:1px solid #ccc; padding:4px;">
		<?php echo $patientComplain->complain_name ? htmlspecialchars($patientComplain->complain_name) : '—'; ?>
	</td>
	<td style="border:1px solid #ccc; padding:4px;">
		<?php echo (isset($patientComplain->complain_text) && $patientComplain->complain_text !== '') ? htmlspecialchars($patientComplain->complain_text) : '—'; ?>
	</td>
	<td style="border:1px solid #ccc; padding:4px;">
		<?php echo (isset($patientComplain->severity) && $patientComplain->severity !== '') ? htmlspecialchars($patientComplain->severity) : '—'; ?>
	</td>
	<td style="border:1px solid #ccc; padding:4px;">
		<?php echo (isset($patientComplain->duration) && $patientComplain->duration !== '') ? htmlspecialchars($patientComplain->duration) : '—'; ?>
	</td>
	<td style="border:1px solid #ccc; padding:4px;">
		<?php echo (isset($patientComplain->onset) && $patientComplain->onset !== '') ? htmlspecialchars($patientComplain->onset) : '—'; ?>
	</td>
	<td style="border:1px solid #ccc; padding:4px;">
		<?php echo $patientComplain->remarks ? htmlspecialchars($patientComplain->remarks) : '—'; ?>
	</td>
</tr>
<?php }?>
</table>















</body>
</html>