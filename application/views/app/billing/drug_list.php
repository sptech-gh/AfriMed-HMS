<select name="drug_name" id="drug_name" class="form-control input-sm" style="width: 250px;" required onChange="getDrugRate(this.value);">
	<option value="">- Drug Name List -</option>
	<?php
	foreach ($drug_list as $drug_list) { ?>
		<?php
			$st = isset($drug_list->nStock) ? (string)$drug_list->nStock : '';
			$exp = isset($drug_list->expiry_date) ? (string)$drug_list->expiry_date : '';
			$nhisCode = isset($drug_list->nhis_drug_code) ? trim((string)$drug_list->nhis_drug_code) : '';
			$nhisBadge = !empty($nhisCode) ? '✓NHIS' : '✗NHIS';
			$txt = (string)$drug_list->drug_name . ' [' . $nhisBadge . ']';
			if (trim($st) !== '') {
				$txt .= ' (Stock: '.$st.')';
			}
			if (trim($exp) !== '') {
				$txt .= ' (Exp: '.$exp.')';
			}
		?>
		<option value="<?php echo $drug_list->drug_id; ?>" data-stock="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>" data-expiry="<?php echo htmlspecialchars($exp, ENT_QUOTES, 'UTF-8'); ?>" data-nhis="<?php echo !empty($nhisCode) ? '1' : '0'; ?>"><?php echo htmlspecialchars($txt, ENT_QUOTES, 'UTF-8'); ?></option>
	<?php } ?>
</select>
<input type="hidden" name="medicine_name" id="medicine_name" value="<?php echo @$medicineName->med_category_name ?>">
<input type="hidden" name="drug_name_a" id="drug_name_a">