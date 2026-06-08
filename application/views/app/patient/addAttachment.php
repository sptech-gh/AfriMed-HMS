
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
<?php echo $message;?>
<?php echo form_open_multipart(base_url().'app/patient/upload_attachment'); ?>
	<input type="hidden" name="patient_no" value="<?php echo $patient_no;?>">
    <fieldset>
    	<legend> UPLOAD ATTACHMENT </legend>
        
        <div class="table-responsive">
        <table cellpadding="5" cellspacing="5">
        <tr>
        	<td>Description</td>
            <td><?php echo form_input('description',set_value('description'),'id="description" class="form-control input-sm" placeholder="Description" style="width: 250px;"');?></td>
        </tr>
        <tr>
        	<td>Browse</td>
            <td><input type="file" name="userfile" size="20" class="form-control input-sm"/></td>
        </tr>
        <tr>
        	<td colspan="2">
            <a href="<?php echo base_url()?>app/patient/attachment/<?php echo $patient_no;?>" class="btn btn-sm btn-default">Cancel</a>
            <input type="submit" value="upload" class="btn btn-sm btn-primary"/><br>
            Note:3Mb of Maximum file size
            </td>
        </tr>
        </table>
        </div>
        
    	
        
        
    </fieldset>
    <?php echo form_close();?>

</body>