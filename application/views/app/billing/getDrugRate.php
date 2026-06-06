<input type="text" onkeypress="return isNumberKey(event)" value="<?php echo $getDrugRate->nPrice?>" name="drug_rate" id="drug_rate" placeholder="rate" class="form-control input-sm" style="width: 100%;" required>
<input type="hidden" name="drug_name_a" id="drug_name_a" value="<?php echo $getDrugRate->drug_name?>">
<input type="hidden" name="drug_stock" id="drug_stock" value="<?php echo isset($getDrugRate->nStock) ? $getDrugRate->nStock : ''; ?>">
<input type="hidden" name="drug_reorder_level" id="drug_reorder_level" value="<?php echo isset($getDrugRate->re_order_level) ? $getDrugRate->re_order_level : ''; ?>">
<input type="hidden" name="drug_expiry_date" id="drug_expiry_date" value="<?php echo isset($getDrugRate->expiry_date) ? $getDrugRate->expiry_date : ''; ?>">
<?php
$isExpired = 0;
$exp = isset($getDrugRate->expiry_date) ? trim((string)$getDrugRate->expiry_date) : '';
if ($exp !== '') {
	$ts = strtotime($exp);
	if ($ts !== false && $ts < strtotime(date('Y-m-d'))) {
		$isExpired = 1;
	}
}
?>
<input type="hidden" name="drug_is_expired" id="drug_is_expired" value="<?php echo $isExpired; ?>">
<?php if (isset($nhis_covered) && $nhis_covered): ?>
<input type="hidden" name="drug_nhis_covered" id="drug_nhis_covered" value="1">
<span class="label label-success" style="margin-top:4px;display:inline-block;"><i class="fa fa-check-circle"></i> NHIS Covered</span>
<?php elseif (isset($nhis_covered) && !$nhis_covered && isset($getDrugRate->is_nhis_covered) && (int)$getDrugRate->is_nhis_covered === 0): ?>
<input type="hidden" name="drug_nhis_covered" id="drug_nhis_covered" value="0">
<span class="label label-warning" style="margin-top:4px;display:inline-block;"><i class="fa fa-exclamation-triangle"></i> Not NHIS Covered</span>
<?php else: ?>
<input type="hidden" name="drug_nhis_covered" id="drug_nhis_covered" value="">
<?php endif; ?>