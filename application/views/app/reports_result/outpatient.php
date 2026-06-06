<!DOCTYPE html>
<html>
<head>
<head>

<meta charset="UTF-8">
<title>Hebrew Medical Center</title>   
</head>  
<style>
body{
	font-family:Verdana, Geneva, sans-serif;
	font-size:12px;
}
</style>
<body>
<center>
	<font size="+1"><?php echo $companyInfo->company_name;?></font></b><br>                   
    <?php echo $companyInfo->company_address;?><br>     
    <?php echo $companyInfo->company_contactNo;?><br><br>
    <?php echo $reports_title;?>
</center>   
<br /><br />
<table cellpadding="2" cellspacing="2" align="center" width="100%">
<tr>
	<th style="border-bottom:1px #999 solid">Date Visited</th>
	<th style="border-bottom:1px #999 solid">OP No.</th>
    <th style="border-bottom:1px #999 solid">Reg. No.</th>
    <th style="border-bottom:1px #999 solid">Patient Name</th>
    <th style="border-bottom:1px #999 solid">Consultant Doctor</th>
    <th style="border-bottom:1px #999 solid">Refferal Doctor</th>
    <th style="border-bottom:1px #999 solid">Department</th>
    <th style="border-bottom:1px #999 solid">Status</th>
</tr>
<?php foreach($outpatient as $outpatient){?>
<tr>
	<td align="center"><?php echo date("M d, Y",strtotime($outpatient->date_visit))." ".$outpatient->time_visit?></td>
    <td align="center"><?php echo $outpatient->IO_ID?></td>
    <td align="center"><?php echo $outpatient->patient_no?></td>
    <td align="center"><?php echo $outpatient->name?></td>
    <td align="center"><?php echo $outpatient->consultant?></td>
    <td align="center"><?php echo $outpatient->refferal?></td>
    <td align="center"><?php echo $outpatient->dept_name?></td>
    <td align="center">
	<?php 
	if($outpatient->nStatus == "Pending"){
		echo "Checked In";
	}else{
		echo "Discharged";
	}
	?></td>
</tr>
<?php }?>
</table>












</body>
</html>