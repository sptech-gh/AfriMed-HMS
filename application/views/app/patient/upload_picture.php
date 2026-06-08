<!DOCTYPE html>
<html lang="en"><head>
<head>

    <meta charset="utf-8">
    <title><?php echo $title;?></title>
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">
 	
   		<link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
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
            body {
                background: transparent !important;
            }
        </style>
</head>  

<body>
<div class="table-responsive">
<table cellpadding="5" cellspacing="5">
<tr>
	<td>
<?php

        if(!$patient->picture){
            $picture = "avatar.png";	
        }else{
            $picture = $patient->picture;
        }

?>

<img src="<?php echo base_url();?>public/patient_picture/<?php echo $picture;?>" class="img-rounded" width="150" height="150" style="border:1px solid #CCC;">
    </td>
    <td valign="top">
    <?php echo $message;?>
    <?php echo form_open_multipart(base_url().'app/patient/upload_na'); ?>
    <input type="hidden" name="patient_no" value="<?php echo $patient->patient_no;?>">
    <fieldset>
    	<legend style="font-size: 16px; font-weight: 600; color: var(--hms-text); border-bottom: 1px solid var(--hms-border); padding-bottom: 5px; margin-bottom: 10px;"> CHANGE PICTURE </legend>
    	<input type="file" name="userfile" size="20" class="form-control input-sm" style="width: auto; display: inline-block; margin-bottom: 10px;" />
        <br />
        <input type="submit" value="upload" class="btn btn-sm btn-primary" />
    </fieldset>
    <?php echo form_close();?>
    </td>
</tr>
</table>
</div>

</body>
</html>