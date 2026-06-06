<input type="text" onkeypress="return isNumberKey(event)" value="<?php echo $getRate->charge_amount?>" name="rate" id="rate" placeholder="rate" class="form-control input-sm" style="width: 100%;" required>
<input type="hidden" name="bill_name" id="bill_name" value="<?php echo $getRate->particular_name?>">
<?php if (isset($nhis_covered) && $nhis_covered): ?>
<span class="label label-success" style="margin-top:4px;display:inline-block;"><i class="fa fa-check-circle"></i> NHIS Covered</span>
<?php elseif (isset($nhis_covered) && !$nhis_covered && isset($getRate->is_nhis_covered) && (int)$getRate->is_nhis_covered === 0): ?>
<span class="label label-default" style="margin-top:4px;display:inline-block;">Not NHIS Covered</span>
<?php endif; ?>