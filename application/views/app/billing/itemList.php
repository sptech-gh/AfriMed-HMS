<select name="particular" id="particular" class="form-control input-sm" style="width: 100%;" onChange="getItemRate(this.value);">
	<option value="">- Particular Item -</option>
	<?php 
	foreach($itemList as $itemList){?>
	<?php
		$amt = isset($itemList->charge_amount) ? (float)$itemList->charge_amount : 0.0;
		$label = (string)$itemList->particular_name;
		if ($amt > 0) {
			$label .= ' - GH₵' . number_format($amt, 2);
		}
	?>
	<option value="<?php echo $itemList->particular_id;?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');?></option>
	<?php }?>
</select>
<input type="hidden" name="particular_name" id="particular_name" value="<?php if($particularName){ echo $particularName->group_name; } ?>">
