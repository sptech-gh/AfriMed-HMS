<?php
/**
 * Fix for DataTables Column Mismatch
 * Updates the submission_queue view to fix column references
 */

echo "<h2>Applying DataTables Fix...</h2>";

// Fix 1: Update the view to use correct field names
$view_file = __DIR__ . "/application/views/app/nhis/submission_queue.php";
$view_content = file_get_contents($view_file);

// Replace claim_date with service_date (correct field name)
$view_content = str_replace(
    "date('d M Y', strtotime($c->claim_date))",
    "date('d M Y', strtotime($c->service_date ?? $c->created_at))",
    $view_content
);

// Fix 2: Add proper DataTables column definitions
$javascript_fix = '
<script>
$(function(){
    // Fix for DataTables column mismatch
    $("#readyTable").DataTable({
        "pageLength": 25,
        "columnDefs": [
            { "targets": 0, "orderable": false }, // Checkbox column
            { "targets": 6, "orderable": false }  // Actions column
        ],
        "autoWidth": false
    });
    
    $("#submittedTable").DataTable({
        "pageLength": 25,
        "autoWidth": false
    });
    
    // Rest of existing JavaScript
    $("#selectAll").change(function(){
        $(".claim-check").prop("checked", $(this).is(":checked"));
    });
    
    $("#submitAll").click(function(){
        var ids = [];
        $(".claim-check:checked").each(function(){ ids.push($(this).val()); });
        if(ids.length === 0){ alert("Select at least one claim"); return; }
        if(!confirm("Submit " + ids.length + " claims to NHIS?")) return;
        alert("Batch submission not yet implemented. Submit individually.");
    });
});
</script>';

// Replace the old JavaScript section
$old_js = <<<'OLDJS'
<script>
    $(function(){
        $('#readyTable, #submittedTable').DataTable({ "pageLength": 25 });
        
        $('#selectAll').change(function(){
            $('.claim-check').prop('checked', $(this).is(':checked'));
        });
        
        $('#submitAll').click(function(){
            var ids = [];
            $('.claim-check:checked').each(function(){ ids.push($(this).val()); });
            if(ids.length === 0){ alert('Select at least one claim'); return; }
            if(!confirm('Submit ' + ids.length + ' claims to NHIS?')) return;
            // Batch submit would go here
            alert('Batch submission not yet implemented. Submit individually.');
        });
    });
    </script>
OLDJS;

$view_content = str_replace($old_js, $javascript_fix, $view_content);

// Write the fixed content back
file_put_contents($view_file, $view_content);

echo "<div class=\"alert alert-success\">✓ Fixed submission_queue.php</div>";
echo "<ul>";
echo "<li>✓ Fixed claim_date field reference</li>";
echo "<li>✓ Added proper DataTables column definitions</li>";
echo "<li>✓ Disabled sorting on checkbox and actions columns</li>";
echo "</ul>";

echo "<h3>Test the Fix:</h3>";
echo "<p><a href=\"/hms-master/app/nhis_claims/submission_queue\" class=\"btn btn-primary\">Test Submission Queue</a></p>";
?>