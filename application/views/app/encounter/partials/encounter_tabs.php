<?php
$encounter_tabs = isset($encounter_tabs) && is_array($encounter_tabs) ? $encounter_tabs : array();
?>
<ul class="nav nav-tabs">
	<?php foreach ($encounter_tabs as $t) {
		$id = isset($t['id']) ? (string)$t['id'] : '';
		$label = isset($t['label']) ? (string)$t['label'] : '';
		$active = isset($t['active']) && $t['active'];
		if ($id === '' || $label === '') continue;
	?>
	<li class="<?php echo $active ? 'active' : ''; ?>"><a href="#<?php echo htmlspecialchars($id); ?>" data-toggle="tab"><?php echo htmlspecialchars($label); ?></a></li>
	<?php } ?>
</ul>
