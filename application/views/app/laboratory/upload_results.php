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
</head>  

<body>
<table cellpadding="5" cellspacing="5">
<tr>
	<td>

<!-- <img src="<?php #echo base_url();?>public/patient_picture/<?php #echo $picture;?>" class="img-rounded" width="150" height="150" style="border:1px solid #CCC;"> -->
    </td>
    <td valign="top">
	<?php
	$isReadOnly = isset($is_read_only) && $is_read_only;
	$labRow = isset($lab_row) ? $lab_row : null;
	$meta = isset($attachment_meta) ? $attachment_meta : null;
	$moduleBase = isset($module_base) && $module_base ? (string)$module_base : 'laboratory';
	?>
    <?php echo $message;?>
    <?php if ($labRow && isset($labRow->lab_result_upload) && trim((string)$labRow->lab_result_upload) !== '') { ?>
		<div style="margin-bottom:10px;">
			<a target="_blank" href="<?php echo base_url(); ?>app/<?php echo $moduleBase; ?>/download_result/<?php echo (int)$labRow->io_lab_id; ?>">View/Download Current PDF</a>
		</div>
		<?php if ($meta) { ?>
			<div style="margin-bottom:10px;">
				<div>File: <?php echo htmlspecialchars((string)$meta->original_filename, ENT_QUOTES, 'UTF-8'); ?></div>
				<div>Type: <?php echo htmlspecialchars((string)$meta->mime_type, ENT_QUOTES, 'UTF-8'); ?></div>
				<div>Size(KB): <?php echo htmlspecialchars((string)$meta->file_size_kb, ENT_QUOTES, 'UTF-8'); ?></div>
				<div>Uploaded: <?php echo date('M d, Y H:i', strtotime((string)$meta->uploaded_at)); ?></div>
			</div>
		<?php } ?>
	<?php } ?>
	<?php if (!$isReadOnly) { ?>
		<?php echo form_open_multipart(base_url().'app/'.$moduleBase.'/upload_lab_result'); ?>
		<input type="hidden" name="io_lab_id" value="<?php echo $lab;?>">
		<fieldset>
			<input type="file" name="result_upload" size="20" accept="application/pdf,image/jpeg,image/png" />
			<br />
			<input type="submit" value="upload" />
		</fieldset>
		<?php echo form_close();?>
	<?php } ?>
    </td>
</tr>
</table>

</body>
</html>