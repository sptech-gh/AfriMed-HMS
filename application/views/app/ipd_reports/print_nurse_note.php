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

<table cellpadding="5" cellspacing="5" width="100%" align="center">
<tr style="border-bottom:1px #999 solid">
	<th style="border-bottom:1px #999 solid">Date</th>
	<th style="border-bottom:1px #999 solid">Focus</th>
	<th style="border-bottom:1px #999 solid">Notes</th>
	<th style="border-bottom:1px #999 solid">Prepared by</th>
</tr>
<?php foreach($getNurseProgressNote as $rows){?>
                                           <tr>
                                           		<td align="center"><?php echo date("M d, Y h:i:s A",strtotime($rows->dDateTime));?></td>
                                                <td><?php echo $rows->focus?></td>
                                                <td><?php echo $rows->notes?></td>
                                                <td align="center"><?php 
												$ci_obj = & get_instance();
												$ci_obj->load->model('app/general_model');
												$pages = $ci_obj->general_model->getPreparedBy($rows->cPreparedBy);
												
												echo $pages->cPreparedBy?></td>
                                           </tr>
                                           <?php }?> 
</table>















</body>
</html>