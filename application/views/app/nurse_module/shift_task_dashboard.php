<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Shift Tasks — <?php echo isset($patientInfo->firstname) ? htmlspecialchars($patientInfo->firstname.' '.$patientInfo->lastname) : 'Patient'; ?></title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" />
<style>
.st-wrap{max-width:1400px;margin:0 auto;padding:10px 15px}
.st-header{background:linear-gradient(135deg,#1e3a5f 0%,#2d6a9f 50%,#1e3a5f 100%);border-radius:10px;padding:18px 24px;margin-bottom:14px;color:#fff;box-shadow:0 4px 18px rgba(30,58,95,.22);position:relative;overflow:hidden}
.st-header::before{content:'';position:absolute;top:-50%;right:-10%;width:280px;height:280px;background:radial-gradient(circle,rgba(255,255,255,.07) 0%,transparent 70%);border-radius:50%}
.st-header h2{margin:0 0 2px;font-size:20px;font-weight:700}
.st-header .sub{opacity:.8;font-size:12px}
.st-pbar{display:flex;gap:18px;flex-wrap:wrap;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.15)}
.st-pbar .pi{font-size:12px;line-height:1.4}.st-pbar .pi label{font-weight:600;text-transform:uppercase;font-size:9px;letter-spacing:.5px;opacity:.65;display:block}.st-pbar .pi span{font-size:13px;font-weight:500}
.st-card{background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06);border:1px solid #e8ecf1;margin-bottom:14px}
.st-card-head{padding:12px 16px;border-bottom:1px solid #f0f2f5;display:flex;align-items:center;justify-content:space-between}
.st-card-head h3{margin:0;font-size:14px;font-weight:600;color:#1e3a5f}
.st-card-body{padding:14px 16px}
.st-filters{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.st-filters .form-control{font-size:12px;height:32px;border-radius:6px}
.st-filters .btn{font-size:12px;height:32px;border-radius:6px;padding:0 14px}
.overdue-alert{background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;border-radius:8px;padding:12px 16px;margin-bottom:14px;display:none}
.task-row{border:1px solid #e8ecf1;border-radius:8px;padding:12px 16px;margin-bottom:8px;display:flex;align-items:center;gap:12px;transition:all .2s}
.task-row:hover{box-shadow:0 2px 8px rgba(0,0,0,.08)}
.task-row.p-CRITICAL{border-left:4px solid #e74c3c}.task-row.p-URGENT{border-left:4px solid #f39c12}.task-row.p-NORMAL{border-left:4px solid #3498db}
.task-row .t-main{flex:1;min-width:0}
.task-row .t-title{font-weight:600;font-size:13px;color:#2c3e50}
.task-row .t-meta{font-size:11px;color:#7f8c8d;margin-top:2px}
.task-row .t-actions{display:flex;gap:4px;flex-shrink:0}
.s-badge{padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;display:inline-block}
.s-OPEN{background:#eaf4fe;color:#2980b9}.s-IN_PROGRESS{background:#fef9e7;color:#f39c12}
.s-COMPLETED{background:#e8f8f0;color:#27ae60}.s-CANCELLED{background:#fdedec;color:#e74c3c}
.s-HANDED_OVER{background:#f4ecf7;color:#8e44ad}
.cat-badge{font-size:9px;padding:1px 5px;border-radius:3px;background:#95a5a6;color:#fff;margin-left:4px}
.billing-section{display:none;background:#f8f9fa;border-radius:6px;padding:12px;margin-top:8px;border:1px dashed #bdc3c7}
.billing-section.active{display:block}
.catalog-results{max-height:200px;overflow-y:auto;border:1px solid #e0e4ea;border-radius:6px;display:none;position:absolute;z-index:100;background:#fff;width:100%;box-shadow:0 4px 12px rgba(0,0,0,.1)}
.catalog-results .cr-item{padding:8px 12px;cursor:pointer;border-bottom:1px solid #f5f6f8;font-size:12px}
.catalog-results .cr-item:hover{background:#f0f7ff}
.empty-state{text-align:center;padding:40px 20px;color:#bdc3c7}
.empty-state i{font-size:40px;display:block;margin-bottom:10px}
.empty-state p{font-size:13px;margin:0}
</style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
<?php require_once(APPPATH.'views/include/sidebar.php');?>
<aside class="right-side">
<section class="content">
<div class="st-wrap">

<?php
$pname = isset($patientInfo->firstname) ? htmlspecialchars($patientInfo->firstname.' '.$patientInfo->lastname) : 'Unknown';
$pno = isset($patient_no) ? htmlspecialchars($patient_no) : '';
$iop = isset($iop_id) ? htmlspecialchars($iop_id) : '';
$bed_label = isset($bed_info->bed_name) ? htmlspecialchars($bed_info->bed_name) : 'Unassigned';
$room_label = isset($bed_info->room_name) ? htmlspecialchars($bed_info->room_name) : '';
$ward_label = $room_label ?: 'General Ward';
?>

<!-- Patient Header -->
<div class="st-header">
    <h2><i class="fa fa-tasks"></i> Shift Tasks — <?= $pname ?></h2>
    <div class="sub">Patient-specific nursing task management</div>
    <div class="st-pbar">
        <div class="pi"><label>Patient No</label><span><?= $pno ?></span></div>
        <div class="pi"><label>IOP ID</label><span><?= $iop ?></span></div>
        <div class="pi"><label>Ward / Room</label><span><?= $ward_label ?></span></div>
        <div class="pi"><label>Bed</label><span><?= $bed_label ?></span></div>
        <?php if (isset($getOPDPatient->doctor_name)): ?>
        <div class="pi"><label>Doctor</label><span><?= htmlspecialchars($getOPDPatient->doctor_name) ?></span></div>
        <?php endif; ?>
    </div>
</div>

<?= isset($message) ? $message : '' ?>

<!-- Overdue Alert -->
<?php if (!empty($overdue_tasks)): ?>
<div class="overdue-alert" style="display:block">
    <i class="fa fa-exclamation-triangle"></i>
    <strong><?= count($overdue_tasks) ?></strong> overdue task(s) for this patient!
</div>
<?php endif; ?>

<!-- Filter + Actions Bar -->
<div class="st-card">
    <div class="st-card-body">
        <div class="st-filters">
            <input type="date" id="filterDate" class="form-control" value="<?= date('Y-m-d') ?>" style="width:140px" onchange="loadTasks()">
            <select id="filterShift" class="form-control" style="width:140px" onchange="loadTasks()">
                <option value="0">All Shifts</option>
                <?php foreach ($shifts as $s): ?>
                <option value="<?= $s->shift_id ?>" <?= (isset($current_shift_id) && $current_shift_id == $s->shift_id) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s->shift_name) ?> (<?= substr($s->start_time,0,5) ?>–<?= substr($s->end_time,0,5) ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" onclick="$('#newTaskModal').modal('show')"><i class="fa fa-plus"></i> New Task</button>
            <a href="<?= base_url() ?>app/shift_task_controller/ward_overview" class="btn btn-default"><i class="fa fa-exchange"></i> Ward Overview / Handover</a>
            <button class="btn btn-default" onclick="loadTasks()"><i class="fa fa-refresh"></i></button>
            <span class="label label-primary" id="taskCountBadge" style="font-size:12px;padding:4px 10px">0 tasks</span>
        </div>
    </div>
</div>

<!-- Task List -->
<div id="taskList"></div>

<!-- Task History -->
<div class="st-card" style="margin-top:10px">
    <div class="st-card-head" style="cursor:pointer" onclick="$('#historyBody').toggle()">
        <h3><i class="fa fa-history"></i> Task History (All Dates)</h3>
        <i class="fa fa-chevron-down"></i>
    </div>
    <div class="st-card-body" id="historyBody" style="display:none">
        <div id="historyList"></div>
    </div>
</div>

</div>
</section>
</aside>
</div>

<!-- New Task Modal -->
<div class="modal fade" id="newTaskModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
    <div class="modal-header" style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);color:#fff">
        <button type="button" class="close" data-dismiss="modal" style="color:#fff">&times;</button>
        <h4 class="modal-title"><i class="fa fa-plus"></i> New Task for <?= $pname ?></h4>
    </div>
    <div class="modal-body">
        <div class="row">
            <div class="col-md-8">
                <div class="form-group">
                    <label>Title <span class="text-red">*</span></label>
                    <input type="text" id="ntTitle" class="form-control" placeholder="e.g. Wound dressing change, IV fluid check">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Category</label>
                    <select id="ntCategory" class="form-control">
                        <?php foreach ($task_categories as $k => $v): ?>
                        <option value="<?= $k ?>"><?= htmlspecialchars($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>Description / Instructions</label>
            <textarea id="ntDesc" class="form-control" rows="2" placeholder="Clinical details, special instructions..."></textarea>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Priority</label>
                    <select id="ntPriority" class="form-control">
                        <option value="NORMAL">Normal</option>
                        <option value="URGENT">Urgent</option>
                        <option value="CRITICAL">Critical</option>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Due At</label>
                    <input type="datetime-local" id="ntDueAt" class="form-control">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Assign To</label>
                    <select id="ntAssignTo" class="form-control">
                        <option value="">— Unassigned —</option>
                        <?php if(isset($nurses_list) && is_array($nurses_list)): foreach($nurses_list as $n): ?>
                        <option value="<?= htmlspecialchars($n->user_id) ?>"><?= htmlspecialchars($n->firstname.' '.$n->lastname) ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
        </div>
        <?php if (!empty($billing_enabled)): ?>
        <div class="checkbox"><label><input type="checkbox" id="ntBillable" onchange="toggleBilling()"> <strong>Billable (attach supply)</strong></label></div>
        <div class="billing-section" id="billingSection">
            <div class="form-group" style="position:relative">
                <label>Search Supply Item</label>
                <input type="text" id="ntCatalogSearch" class="form-control" placeholder="Type to search..." oninput="searchCatalog()">
                <div class="catalog-results" id="catalogResults"></div>
            </div>
            <div id="selectedItem" style="display:none;padding:8px;background:#e8f8f0;border-radius:6px;margin-top:6px">
                <strong id="selItemName"></strong> — <span id="selItemPrice" class="text-success"></span>
                <input type="hidden" id="ntCatalogId">
            </div>
            <div class="form-group" style="margin-top:8px"><label>Qty</label><input type="number" id="ntQty" class="form-control" value="1" min="1" style="width:80px"></div>
        </div>
        <?php endif; ?>
    </div>
    <div class="modal-footer">
        <button class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" onclick="submitTask()"><i class="fa fa-save"></i> Create Task</button>
    </div>
</div></div></div>

<!-- Escalation Modal -->
<div class="modal fade" id="escalateModal" tabindex="-1">
<div class="modal-dialog modal-sm">
<div class="modal-content">
    <div class="modal-header" style="background:#e74c3c;color:#fff">
        <button type="button" class="close" data-dismiss="modal" style="color:#fff">&times;</button>
        <h4 class="modal-title"><i class="fa fa-exclamation-triangle"></i> Escalate</h4>
    </div>
    <div class="modal-body">
        <input type="hidden" id="escTaskId">
        <div class="form-group"><label>Escalate To</label>
            <select id="escTo" class="form-control">
                <?php if(isset($nurses_list)): foreach($nurses_list as $n): ?>
                <option value="<?= htmlspecialchars($n->user_id) ?>"><?= htmlspecialchars($n->firstname.' '.$n->lastname) ?></option>
                <?php endforeach; endif; ?>
            </select>
        </div>
        <div class="form-group"><label>Reason <span class="text-red">*</span></label><textarea id="escReason" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-danger" onclick="submitEscalation()"><i class="fa fa-exclamation-triangle"></i> Escalate</button></div>
</div></div></div>

<script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
<script>
var BASE = '<?= base_url() ?>app/shift_task_controller/';
var IOP_ID = '<?= $iop ?>';
var PAT_NO = '<?= $pno ?>';
var CSRF_NAME = '<?= $this->security->get_csrf_token_name() ?>';
var CSRF_HASH = '<?= $this->security->get_csrf_hash() ?>';

function csrfData(extra){ var d={}; d[CSRF_NAME]=CSRF_HASH; if(extra) for(var k in extra) d[k]=extra[k]; return d; }
function updateCsrf(r){ if(r&&r.csrf_token_hash) CSRF_HASH=r.csrf_token_hash; }
function esc(s){ return $('<span>').text(s).html(); }

function loadTasks(){
    $.post(BASE+'get_patient_tasks_ajax', csrfData({
        iop_id:IOP_ID, shift_date:$('#filterDate').val(), shift_id:$('#filterShift').val()
    }), function(r){
        updateCsrf(r);
        renderTasks(r.data||[], '#taskList');
        $('#taskCountBadge').text((r.data||[]).length+' tasks');
    },'json');
}

function loadHistory(){
    $.post(BASE+'get_patient_tasks_ajax', csrfData({
        iop_id:IOP_ID, shift_date:'', shift_id:0
    }), function(r){
        updateCsrf(r);
        // Use encounter-level endpoint for full history
    },'json');
}

function renderTasks(tasks, target){
    var $t=$(target); $t.empty();
    if(!tasks.length){
        $t.html('<div class="empty-state"><i class="fa fa-check-circle"></i><p>No tasks for this shift/date. Click <strong>New Task</strong> to add one.</p></div>');
        return;
    }
    for(var i=0;i<tasks.length;i++){
        var t=tasks[i], cls='p-'+(t.priority||'NORMAL');
        var sBadge='<span class="s-badge s-'+(t.status||'OPEN')+'">'+(t.status||'OPEN').replace('_',' ')+'</span>';
        var catBadge='<span class="cat-badge">'+esc(t.task_category||'')+'</span>';
        var due=t.due_at?'Due: '+t.due_at.substring(0,16):'';
        var assignee=t.assigned_to_name?'Assigned: '+esc(t.assigned_to_name):'Unassigned';
        var actions='';
        if(t.status==='OPEN') actions+='<button class="btn btn-xs btn-info" title="Start" onclick="changeStatus('+t.task_id+',\'IN_PROGRESS\')"><i class="fa fa-play"></i></button> ';
        if(t.status==='OPEN'&&(!t.billing_triggered||t.billing_triggered==='0')) actions+='<button class="btn btn-xs btn-danger" title="Cancel" onclick="changeStatus('+t.task_id+',\'CANCELLED\')"><i class="fa fa-times"></i></button> ';
        if(t.status==='IN_PROGRESS') actions+='<button class="btn btn-xs btn-success" title="Complete" onclick="changeStatus('+t.task_id+',\'COMPLETED\')"><i class="fa fa-check"></i></button> ';
        if(t.status!=='COMPLETED'&&t.status!=='CANCELLED') actions+='<button class="btn btn-xs btn-warning" title="Escalate" onclick="openEscalate('+t.task_id+')"><i class="fa fa-arrow-up"></i></button>';

        $t.append('<div class="task-row '+cls+'">'
            +'<div class="t-main">'
            +'<div class="t-title">'+esc(t.title||'')+catBadge+(t.escalated==='1'?' <span class="label label-danger" style="font-size:9px">ESCALATED</span>':'')+'</div>'
            +'<div class="t-meta"><strong>'+esc(t.task_no||'')+'</strong> &middot; '+esc(t.priority||'NORMAL')+' &middot; '+assignee+(due?' &middot; '+due:'')+'</div>'
            +(t.description?'<div class="t-meta" style="color:#555">'+esc(t.description)+'</div>':'')
            +'</div>'
            +'<div>'+sBadge+'</div>'
            +'<div class="t-actions">'+actions+'</div>'
            +'</div>');
    }
}

function changeStatus(taskId,newStatus){
    if(newStatus==='CANCELLED'&&!confirm('Cancel this task?')) return;
    $.post(BASE+'update_status', csrfData({task_id:taskId, new_status:newStatus}), function(r){
        updateCsrf(r);
        if(r.success) loadTasks(); else alert(r.error||'Failed');
    },'json');
}

function submitTask(){
    if(!$('#ntTitle').val().trim()){ alert('Title is required'); return; }
    $.post(BASE+'create', csrfData({
        iop_id:IOP_ID, patient_no:PAT_NO,
        title:$('#ntTitle').val(), description:$('#ntDesc').val(),
        task_category:$('#ntCategory').val(), priority:$('#ntPriority').val(),
        shift_id:$('#filterShift').val(), shift_date:$('#filterDate').val(),
        due_at:$('#ntDueAt').val(), assigned_to:$('#ntAssignTo').val(),
        is_billable:$('#ntBillable:checked').length?1:0,
        catalog_id:$('#ntCatalogId').val()||0, item_source:'PARTICULAR', quantity:$('#ntQty').val()||1
    }), function(r){
        updateCsrf(r);
        if(r.success){ $('#newTaskModal').modal('hide'); $('#ntTitle,#ntDesc,#ntDueAt').val(''); loadTasks(); }
        else alert(r.error||'Failed');
    },'json');
}

function toggleBilling(){ $('#billingSection').toggleClass('active',$('#ntBillable').is(':checked')); }
var searchTimer;
function searchCatalog(){
    clearTimeout(searchTimer); var term=$('#ntCatalogSearch').val();
    if(term.length<2){$('#catalogResults').hide();return;}
    searchTimer=setTimeout(function(){
        $.post(BASE+'search_catalog_ajax',csrfData({term:term}),function(r){
            updateCsrf(r); var html='';
            if(r.results&&r.results.length){ for(var i=0;i<r.results.length;i++){var it=r.results[i];
                html+='<div class="cr-item" onclick="selectCatalog('+it.catalog_id+',\''+esc(it.item_name)+'\','+it.price+')">'+esc(it.item_name)+' <strong class="text-success pull-right">GHS '+parseFloat(it.price).toFixed(2)+'</strong><br><small class="text-muted">'+esc(it.group_name||'')+'</small></div>';
            }}else html='<div class="cr-item text-muted">No items found</div>';
            $('#catalogResults').html(html).show();
        },'json');
    },300);
}
function selectCatalog(id,name,price){ $('#ntCatalogId').val(id);$('#selItemName').text(name);$('#selItemPrice').text('GHS '+parseFloat(price).toFixed(2));$('#selectedItem').show();$('#catalogResults').hide();$('#ntCatalogSearch').val(name); }

function openEscalate(tid){ $('#escTaskId').val(tid);$('#escReason').val('');$('#escalateModal').modal('show'); }
function submitEscalation(){
    if(!$('#escReason').val().trim()){alert('Reason required');return;}
    $.post(BASE+'escalate_task',csrfData({task_id:$('#escTaskId').val(),escalated_to:$('#escTo').val(),reason:$('#escReason').val()}),function(r){
        updateCsrf(r); if(r.success){$('#escalateModal').modal('hide');loadTasks();}else alert(r.error||'Failed');
    },'json');
}

$(function(){ loadTasks(); });
</script>
</body>
</html>
