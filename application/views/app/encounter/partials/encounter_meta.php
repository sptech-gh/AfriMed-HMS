<?php
$__rows = (isset($encounter_meta_rows) && is_array($encounter_meta_rows)) ? $encounter_meta_rows : array();
$__emptyHtml = (isset($encounter_meta_empty_html) && $encounter_meta_empty_html !== null) ? $encounter_meta_empty_html : '';
?>
<table class="table">
	<thead>
	<?php if (!empty($__rows)) { foreach ($__rows as $__r) { ?>
		<tr>
			<td><?php echo $__r['label']; ?></td>
			<td><?php echo $__r['value']; ?></td>
		</tr>
	<?php } } else { ?>
		<tr>
			<td colspan="2"><?php echo $__emptyHtml; ?></td>
		</tr>
	<?php } ?>
	</thead>
</table>
