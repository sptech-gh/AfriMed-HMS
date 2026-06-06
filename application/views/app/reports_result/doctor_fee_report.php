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
	font-size:10px;
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
	<th style="border-bottom:1px #999 solid">No.</th>
    <th style="border-bottom:1px #999 solid">Doctor Name</th>
    <th style="border-bottom:1px #999 solid">Date</th>
    <th style="border-bottom:1px #999 solid">Fee Type</th>
    <th style="border-bottom:1px #999 solid">Total Fee</th>
    <th style="border-bottom:1px #999 solid">Notes</th>
</tr>
<?php 
$num = 0;
foreach($doctor_fee_report as $doctor_fee_report){

$num++;

?>
<tr>
	<td><?php echo $num?></td>
    <td><?php echo $doctor_fee_report->name?></td>
    <td align="center"><?php echo date("M d, Y",strtotime($doctor_fee_report->date))?></td>
    <td align="center"><?php echo $doctor_fee_report->feeType?></td>
    <td align="center"><?php echo $doctor_fee_report->totalFee?></td>
    <td align="center"><?php echo $doctor_fee_report->notes?></td>
</tr>
<?php }?>
</table>












</body>
</html>