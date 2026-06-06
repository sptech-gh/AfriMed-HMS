<select name="drug_name" id="drug_name" class="form-control input-sm" style="width: 250px;" >
                                                            	<option value="">- Drug Name -</option>
																<?php 
																foreach($drug_name_lists as $drug_name_lists){
																	$nhisCode = isset($drug_name_lists->nhis_drug_code) ? trim((string)$drug_name_lists->nhis_drug_code) : '';
																	$nhisBadge = !empty($nhisCode) ? '✓NHIS' : '✗NHIS';
																	$displayName = $drug_name_lists->drug_name . ' [' . $nhisBadge . ']';
																?>
                                                            	<option value="<?php echo $drug_name_lists->drug_id;?>" data-nhis="<?php echo !empty($nhisCode) ? '1' : '0'; ?>"><?php echo htmlspecialchars($displayName); ?></option>
                                                                <?php }?>
                                                            </select>