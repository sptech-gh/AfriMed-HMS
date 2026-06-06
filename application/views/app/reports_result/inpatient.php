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
	<th style="border-bottom:1px #999 solid">Date Admitted</th>
	<th style="border-bottom:1px #999 solid">IP No.</th>
    <th style="border-bottom:1px #999 solid">Reg. No.</th>
    <th style="border-bottom:1px #999 solid">Patient Name</th>
    <th style="border-bottom:1px #999 solid">Incharge Doctor</th>
    <th style="border-bottom:1px #999 solid">Department</th>
    <th style="border-bottom:1px #999 solid">Room & Bed No.</th>
    <th style="border-bottom:1px #999 solid">Status</th>
</tr>
<?php foreach($inpatient as $inpatient){?>
<tr>
	<td align="center"><?php echo date("M d, Y",strtotime($inpatient->date_visit))." ".$inpatient->time_visit?></td>
    <td align="center">
	<a target="_blank" href="<?php echo base_url()?>app/ipd/view/<?php echo $inpatient->IO_ID?>/<?php echo $inpatient->patient_no?>"><?php echo $inpatient->IO_ID?></a>
	</td>
    <td align="center"><?php echo $inpatient->patient_no?></td>
    <td align="center"><?php echo $inpatient->name?></td>
    <td align="center"><?php echo $inpatient->doctor?></td>
    <td align="center"><?php echo $inpatient->dept_name?></td>
    <td align="center"><?php echo "Rm.".$inpatient->room_name." Bed No.".$inpatient->bed_name?></td>
    <td align="center">
	<?php 
	if($inpatient->nStatus == "Pending"){
		echo "Admitted";
	}else{
		echo "Discharged";
	}
	?></td>
</tr>
<?php }?>
</table>












</body>
</html>