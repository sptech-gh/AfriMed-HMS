<!DOCTYPE html>
<html>
<head>
<head>

<meta charset="UTF-8">
<title><?php echo getFacilityName(); ?></title>   
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
	<th style="border-bottom:1px #999 solid">Receipt No.</th>
	<th style="border-bottom:1px #999 solid">Date</th>
    <th style="border-bottom:1px #999 solid">Patient No.</th>
    <th style="border-bottom:1px #999 solid">Patient Name</th>
    <th style="border-bottom:1px #999 solid">Sub-total</th>
    <th style="border-bottom:1px #999 solid">Discount</th>
    <th style="border-bottom:1px #999 solid">Total</th>
</tr>
<?php foreach($daily_sales as $daily_sales){?>
<tr>
	<td align="left">&nbsp;&nbsp;<?php echo $daily_sales->receipt_no?></td>
	<td align="left">&nbsp;&nbsp;<?php echo date("M d, Y",strtotime($daily_sales->dDate))?></td>
    <td align="left">&nbsp;&nbsp;<?php echo $daily_sales->patient_no?></td>
    <td align="left">&nbsp;&nbsp;<?php echo $daily_sales->patient?></td>
    <td align="right"><?php echo $daily_sales->subtotal?>&nbsp;&nbsp;</td>
    <td align="right"><?php echo $daily_sales->discount?>&nbsp;&nbsp;</td>
    <td align="right"><?php echo $daily_sales->total_amount?>&nbsp;&nbsp;</td>
</tr>
<?php }?>
<tr>
	<th style="border-top:1px #999 solid; border-bottom:1px #999 solid">TOTAL</th>
    <td style="border-top:1px #999 solid; border-bottom:1px #999 solid"></td>
    <td style="border-top:1px #999 solid; border-bottom:1px #999 solid"></td>
    <td style="border-top:1px #999 solid; border-bottom:1px #999 solid"></td>
    <td style="border-top:1px #999 solid; border-bottom:1px #999 solid" align="right"><strong><?php echo number_format($total_sales->subtotal,2);?>&nbsp;&nbsp;</strong></td>
    <td style="border-top:1px #999 solid; border-bottom:1px #999 solid" align="right"><strong><?php echo number_format($total_sales->discount,2);?>&nbsp;&nbsp;</strong></td>
    <td style="border-top:1px #999 solid; border-bottom:1px #999 solid" align="right"><strong><?php echo number_format($total_sales->total_amount,2);?>&nbsp;&nbsp;</strong></td>
</tr>
</table>

</body>
</html>	