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
	margin:0px;
	padding:0px;
}
</style>
<body>
<center>
	<font size="+1"><?php echo $companyInfo->company_name;?></font></b><br>                   
    <?php echo $companyInfo->company_address;?><br>     
    <?php echo $companyInfo->company_contactNo;?><br><br>
    <?php echo $reports_title;?><br><br>
    <?php echo "Count of Data: ". count($patient_list);?>
</center>   
<br /><br />
<table cellpadding="2" cellspacing="2" align="center" width="100%">
<tr>
	<th style="border-bottom:1px #999 solid">Reg. No.</th>
    <th style="border-bottom:1px #999 solid">Patient Name</th>
    <th style="border-bottom:1px #999 solid">Address</th>
    <th style="border-bottom:1px #999 solid">Mobile No.</th>
    <th style="border-bottom:1px #999 solid">Birthday</th>
    <th style="border-bottom:1px #999 solid">Age</th>
    <th style="border-bottom:1px #999 solid">Date Entry</th>
    <th style="border-bottom:1px #999 solid">Gender</th>
    <th style="border-bottom:1px #999 solid">Civil Status</th>
    <th style="border-bottom:1px #999 solid">Blood Group</th>
    <th style="border-bottom:1px #999 solid">Insurance Company</th>
</tr>
<?php foreach($patient_list as $patient_list){?>
<tr>
	<td><?php echo $patient_list->patient_no?></td>
    <td><?php echo $patient_list->name?></td>
    <td><?php echo $patient_list->address?></td>
    <td><?php echo $patient_list->mobile_no?></td>
    <td align="center"><?php echo date("M d, Y",strtotime($patient_list->birthday))?></td>
    <td align="center"><?php echo $patient_list->age?></td>
    <td align="center"><?php echo date("M d, Y h:i:s A",strtotime($patient_list->date_entry))?></td>
    <td align="center"><?php echo $patient_list->gender?></td>
    <td align="center"><?php echo $patient_list->civil_status?></td>
    <td align="center"><?php echo $patient_list->blood_group?></td>
</tr>
<?php }?>
</table>












</body>
</html>