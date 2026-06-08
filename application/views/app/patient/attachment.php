
<link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url();?>public/css/hms-enhanced.css?v=<?php echo time(); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript">
(function(){
    try {
        var stored = localStorage.getItem('hms_ui_theme');
        var theme = (stored === 'dark' || stored === 'light') ? stored : 'light';
        if (theme === 'dark') {
            document.documentElement.classList.add('theme-dark');
            document.documentElement.classList.remove('theme-light');
        } else {
            document.documentElement.classList.add('theme-light');
            document.documentElement.classList.remove('theme-dark');
        }
    } catch (e) {}
})();
</script>
<style>
	body{
		background: transparent !important;
	}
</style>
<body>



<div class="table-responsive">
<table cellpadding="3" cellspacing="3" width="100%">
<tr>
	<Td><a href="<?php echo base_url();?>app/patient/addAttachment/<?php echo $patient_no;?>" class="btn btn-default"><i class="fa fa-plus"></i> Add Attachment</a></Td>
    <td></td>
</tr>
<tr>
	<td colspan="2">
    	<?php echo $message;?>
    	<table class="table table-hover table-striped">
        <thead>
        	<tr>
                <th>Description</th>
                <th>File Name</th>
                <th>File Size</th>
                <th>File Type</th>
                <th>Uploaded By</th>
                <th>Uploaded Date & time</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
        	<?php foreach($patientAttachment as $patientAttachment){ ?>
        	<tr>
            	<td><a href="<?php echo base_url(); ?>public/patient_attachment/<?php echo $patientAttachment->file_name?>" target="_blank"><?php echo $patientAttachment->description?></a></td>
                <td><?php echo $patientAttachment->file_name?></td>
                <td><?php echo $patientAttachment->file_size?></td>
                <td><?php echo $patientAttachment->file_type?></td>
                <td><?php echo $patientAttachment->name?></td>
                <td><?php echo date("M d, Y h:m", strtotime($patientAttachment->date_uploaded));?></td>
                <td><a href="<?php echo base_url(); ?>app/patient/remove_attachment/<?php echo $patientAttachment->attach_id?>/<?php echo $patientAttachment->patient_no?>" onClick="return confirm('Are you sure you want to remove?');">Remove</a></td>
            </tr>
            <?php } ?>
        </tbody>
        </table>
    </td>
</tr>
</table>
</div>
</body>