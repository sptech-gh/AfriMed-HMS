<?php foreach($patientMedication as $patientMedication){
    // Determine item type and label based on source_module
    $sourceModule = isset($patientMedication->source_module) ? $patientMedication->source_module : 'PHARMACY';
    $itemType = 'particular'; // default
    $typeLabelClass = 'label-info';
    $typeLabelText = 'Service';
    
    switch($sourceModule) {
        case 'PHARMACY':
            $itemType = 'medicine';
            $typeLabelClass = 'label-success';
            $typeLabelText = 'Medicine';
            break;
        case 'LAB':
            $itemType = 'laboratory';
            $typeLabelClass = 'label-primary';
            $typeLabelText = 'Laboratory';
            break;
        case 'SONOGRAPHY':
            $itemType = 'sonography';
            $typeLabelClass = 'label-warning';
            $typeLabelText = 'Sonography';
            break;
        case 'IPD_ROOM':
            $itemType = 'room';
            $typeLabelClass = 'label-default';
            $typeLabelText = 'Room';
            break;
        case 'IPD_OT':
            $itemType = 'surgery';
            $typeLabelClass = 'label-danger';
            $typeLabelText = 'Surgery';
            break;
        case 'IPD_BED_SIDE':
            $itemType = 'procedure';
            $typeLabelClass = 'label-info';
            $typeLabelText = 'Procedure';
            break;
        case 'REGISTRATION':
            $itemType = 'registration';
            $typeLabelClass = 'label-default';
            $typeLabelText = 'Registration';
            break;
        case 'CONSULTATION':
            $itemType = 'consultation';
            $typeLabelClass = 'label-info';
            $typeLabelText = 'Consult';
            break;
        default:
            $itemType = 'particular';
            $typeLabelClass = 'label-info';
            $typeLabelText = 'Service';
    }
    $isMedicine = ($sourceModule === 'PHARMACY');
?>

<script language="javascript">
(function() {
    var sourceRef = '<?php echo isset($patientMedication->source_ref) ? addslashes($patientMedication->source_ref) : ""; ?>';
    
    // Check if this item already exists in the billing list (prevent duplicates)
    var isDuplicate = false;
    if (sourceRef && sourceRef !== '') {
        var existingRows = window.opener.document.getElementById('myTable').getElementsByTagName('tr');
        for (var r = 0; r < existingRows.length; r++) {
            var existingRefInput = existingRows[r].querySelector('input[name^="source_ref"]');
            if (existingRefInput && existingRefInput.value === sourceRef) {
                isDuplicate = true;
                break;
            }
        }
    }
    
    // Also check by bill_name to prevent same item being added twice
    var particular = '<?php if($patientMedication->drug_name){echo addslashes($patientMedication->drug_name);}else{echo addslashes($patientMedication->medicine_text);}?>';
    if (false && !isDuplicate && particular !== '') {
        var existingRows = window.opener.document.getElementById('myTable').getElementsByTagName('tr');
        for (var r = 0; r < existingRows.length; r++) {
            var existingNameInput = existingRows[r].querySelector('input[name^="bill_name"]');
            if (existingNameInput && existingNameInput.value === particular) {
                isDuplicate = true;
                break;
            }
        }
    }
    
    if (isDuplicate) {
        console.log('Skipping duplicate item: ' + particular);
        return; // Skip this item
    }

    var tbl = window.opener.document.getElementById('myTable').getElementsByTagName('tr');
    var lastRow = tbl.length;	

    var category,qty,rate,note,amount,isPackage,dosage,advice,instruction,sourceModule,frequency,days;

    qty = '<?php echo $patientMedication->total_qty;?>';
    note = "";
    category = "";
    dosage = '<?php echo addslashes(isset($patientMedication->dosage) ? $patientMedication->dosage : "");?>';
    advice = '<?php echo addslashes(isset($patientMedication->advice) ? $patientMedication->advice : "");?>';
    instruction = '<?php echo addslashes(isset($patientMedication->instruction) ? $patientMedication->instruction : "");?>';
    frequency = '<?php echo addslashes(isset($patientMedication->frequency) ? $patientMedication->frequency : "");?>';
    days = '<?php echo isset($patientMedication->days) ? (int)$patientMedication->days : 0;?>';
    rate = '<?php echo $patientMedication->nPrice;?>';
    isPackage = '<?php echo $patientMedication->isPackage;?>';
    sourceModule = '<?php echo $sourceModule; ?>';
    var itemType = '<?php echo $itemType; ?>';
    var isMedicine = <?php echo $isMedicine ? 'true' : 'false'; ?>;
    
    // Build prescription info string for display (only for medicines)
    var prescriptionInfo = [];
    if (isMedicine) {
        if (dosage && dosage !== '') prescriptionInfo.push(dosage);
        if (frequency && frequency !== '') prescriptionInfo.push(frequency);
        if (days && days > 0) prescriptionInfo.push(days + ' days');
    }
    var prescriptionStr = prescriptionInfo.join(' | ');
    if (prescriptionStr === '' && instruction !== '' && isMedicine) prescriptionStr = instruction;

    amount = eval(qty) * eval(rate);
    amount = amount.toFixed(2); 

    // Build medication info HTML for display (medications from prescription only)
    var medInfoHtml = '';
    if (isMedicine) {
        medInfoHtml = '<small>';
        if (prescriptionStr) medInfoHtml += '<strong>Rx:</strong> ' + prescriptionStr + '<br>';
        if (advice) medInfoHtml += '<strong>Advice:</strong> ' + advice + '<br>';
        if (instruction && instruction !== prescriptionStr) medInfoHtml += '<strong>Note:</strong> ' + instruction;
        medInfoHtml += '</small>';
        if (medInfoHtml === '<small></small>') medInfoHtml = '<span class="text-muted">See Rx</span>';
    } else {
        medInfoHtml = '<span class="text-muted">N/A</span>';
    }

    var a=window.opener.document.getElementById('myTable').insertRow(-1);
    var b=a.insertCell(0);
    var c=a.insertCell(1);
    var cType=a.insertCell(2);
    var d=a.insertCell(3);
    var e=a.insertCell(4);
    var f=a.insertCell(5);
    var g=a.insertCell(6);
    var h=a.insertCell(7);
    var k=a.insertCell(8);
                        
    b.innerHTML = "<input type=\"hidden\" name=\"isPackage" + lastRow + "\" id=\"isPackage" + lastRow + "\" value=\""+ isPackage + "\"><input type=\"hidden\" name=\"item_type" + lastRow + "\" id=\"item_type" + lastRow + "\" value=\""+ itemType + "\"><input type=\"hidden\" name=\"source_module" + lastRow + "\" id=\"source_module" + lastRow + "\" value=\""+ sourceModule + "\"><input type=\"hidden\" name=\"source_ref" + lastRow + "\" id=\"source_ref" + lastRow + "\" value=\""+ sourceRef + "\"><input type=\"text\" size = \"7\" style=\"width:98%; background-color:#F9F9f9; border:1px solid #ccc; text-align:right\" name=\"id" + lastRow + "\" id=\"id" + lastRow + "\" value=\""+ lastRow + ". \" readonly=\"true\">";
    c.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; background-color:#F9F9f9; border:1px solid #ccc;\" name=\"bill_name" + lastRow + "\" id=\"bill_name" + lastRow + "\" value=\""+ particular + "\" readonly=\"true\">";
    cType.innerHTML = '<span class="label <?php echo $typeLabelClass; ?>"><?php echo $typeLabelText; ?></span>';
    d.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; text-align:right\" name=\"qty" + lastRow + "\" id=\"qty" + lastRow + "\" class=\"" + lastRow + "\" value=\""+ qty + "\" onBlur=\"return validate_input(this.className,'qty')\" onkeyup=\"validate_gross(this.className,'qty')\" onkeypress=\"return isNumberKey(event)\" >";
    e.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; text-align:right\" name=\"rate" + lastRow + "\" id=\"rate" + lastRow + "\" class=\"" + lastRow + "\" value=\""+ rate + "\" onBlur=\"return validate_input(this.className,'rate')\" onkeyup=\"validate_gross(this.className,'rate')\" onkeypress=\"return isNumberKey(event)\">";
    f.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; background-color:#F9F9f9; border:1px solid #ccc; text-align:right\" name=\"amount" + lastRow + "\" id=\"amount" + lastRow + "\" value=\""+ amount + "\" readonly=\"true\">";
    g.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%;\" name=\"note" + lastRow + "\" id=\"note" + lastRow + "\" value=\""+ note + "\">";
    // Combined medication info column with hidden fields for form submission
    h.innerHTML = medInfoHtml + "<input type=\"hidden\" name=\"dosage" + lastRow + "\" id=\"dosage" + lastRow + "\" value=\""+ dosage + "\"><input type=\"hidden\" name=\"advice" + lastRow + "\" id=\"advice" + lastRow + "\" value=\""+ advice + "\"><input type=\"hidden\" name=\"instruction" + lastRow + "\" id=\"instruction" + lastRow + "\" value=\""+ instruction + "\"><input type=\"hidden\" name=\"frequency" + lastRow + "\" value=\""+ frequency + "\"><input type=\"hidden\" name=\"days" + lastRow + "\" value=\""+ days + "\">";
    k.innerHTML = "<img src=\"<?php echo base_url()?>public/img/b_drop.png\" onclick=\"deleteRow(this)\" style=\"cursor:pointer;\">";
                        
    window.opener.document.getElementById("hdnrowcnt").value = lastRow;
    window.opener.getGross();
})();
</script>

<?php }?>
<script>
window.opener.closeModal();
window.close();
</script>