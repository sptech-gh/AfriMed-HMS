<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ward Overview — Shift Handover</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" />
<style>
.wo-wrap{max-width:1400px;margin:0 auto;padding:10px 15px}
.wo-header{background:linear-gradient(135deg,#8e44ad 0%,#9b59b6 50%,#8e44ad 100%);border-radius:10px;padding:18px 24px;margin-bottom:14px;color:#fff;box-shadow:0 4px 18px rgba(142,68,173,.22)}
.wo-header h2{margin:0;font-size:20px;font-weight:700}
.wo-header .sub{opacity:.8;font-size:12px}
.wo-card{background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06);border:1px solid #e8ecf1;margin-bottom:14px}
.wo-card-head{padding:12px 16px;border-bottom:1px solid #f0f2f5;display:flex;align-items:center;justify-content:space-between}
.wo-card-head h3{margin:0;font-size:14px;font-weight:600;color:#1e3a5f}
.wo-card-body{padding:14px 16px}
.handover-banner{background:linear-gradient(135deg,#e67e22,#f39c12);color:#fff;border-radius:8px;padding:14px 18px;margin-bottom:14px}
.overdue-row{border-left:4px solid #e74c3c;background:#fdedec;border-radius:6px;padding:10px 14px;margin-bottom:6px;font-size:12px}
</style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
<?php require_once(APPPATH.'views/include/sidebar.php');?>
<aside class="right-side">
<section class="content">
<div class="wo-wrap">

<div class="wo-header">
    <h2><i class="fa fa-exchange"></i> Ward Overview & Handover</h2>
    <div class="sub">Shift-level task summary for handover and overdue monitoring</div>
</div>

<?= isset($message) ? $message : '' ?>

<!-- Pending Handovers -->
<?php if (!empty($pending_handovers)): ?>
<div class="handover-banner">
    <strong><i class="fa fa-bell"></i> Incoming Handover</strong>
    <?php foreach ($pending_handovers as $ho): ?>
    <div style="margin-top:6px;font-size:12px">
        <strong><?= htmlspecialchars($ho->handover_no) ?></strong> from <?= htmlspecialchars(isset($ho->handover_by_name)?$ho->handover_by_name:$ho->handover_by) ?>
        <button class="btn btn-xs btn-warning" style="margin-left:10px" onclick="ackHandover(<?=(int)$ho->handover_id?>)"><i class="fa fa-check"></i> Acknowledge</button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Overdue Tasks -->
<?php if (!empty($overdue_tasks)): ?>
<div class="wo-card">
    <div class="wo-card-head" style="background:#fdedec"><h3><i class="fa fa-exclamation-triangle text-red"></i> Overdue Tasks (<?=count($overdue_tasks)?>)</h3></div>
    <div class="wo-card-body">
        <?php foreach ($overdue_tasks as $t): ?>
        <div class="overdue-row">
            <strong><?= htmlspecialchars($t->task_no) ?></strong> — <?= htmlspecialchars($t->title) ?>
            <span class="pull-right"><?= htmlspecialchars(isset($t->patient_name)?$t->patient_name:$t->patient_no) ?> | Due: <?= htmlspecialchars($t->due_at) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Handover Panel -->
<div class="wo-card">
    <div class="wo-card-head">
        <h3><i class="fa fa-exchange"></i> Start Handover</h3>
    </div>
    <div class="wo-card-body">
        <div class="row" style="margin-bottom:10px">
            <div class="col-md-3">
                <label>Date</label>
                <input type="date" id="hoDate" class="form-control input-sm" value="<?=date('Y-m-d')?>">
            </div>
            <div class="col-md-3">
                <label>Outgoing Shift</label>
                <select id="hoShift" class="form-control input-sm">
                    <?php foreach ($shifts as $s): ?>
                    <option value="<?=$s->shift_id?>" <?=(isset($current_shift_id)&&$current_shift_id==$s->shift_id)?'selected':''?>><?=htmlspecialchars($s->shift_name)?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3" style="padding-top:22px">
                <button class="btn btn-sm btn-info" onclick="loadHandoverTasks()"><i class="fa fa-search"></i> Load Pending Tasks</button>
            </div>
        </div>
        <div id="hoTaskList"></div>
        <div class="form-group" style="margin-top:10px">
            <label>Handover Notes</label>
            <textarea id="hoNotes" class="form-control" rows="3" placeholder="Shift summary, critical observations, pending items..."></textarea>
        </div>
        <button class="btn btn-primary" onclick="submitHandover()"><i class="fa fa-paper-plane"></i> Submit Handover</button>
    </div>
</div>

<div style="margin-top:10px">
    <a href="<?=base_url()?>app/shift_task_controller" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to Patient Tasks</a>
</div>

</div>
</section>
</aside>
</div>

<script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
<script>
var BASE='<?=base_url()?>app/shift_task_controller/';
var CSRF_NAME='<?=$this->security->get_csrf_token_name()?>';
var CSRF_HASH='<?=$this->security->get_csrf_hash()?>';
function csrfData(x){var d={};d[CSRF_NAME]=CSRF_HASH;if(x)for(var k in x)d[k]=x[k];return d;}
function updateCsrf(r){if(r&&r.csrf_token_hash)CSRF_HASH=r.csrf_token_hash;}
function esc(s){return $('<span>').text(s).html();}

function loadHandoverTasks(){
    $.post(BASE+'get_handover_tasks',csrfData({ward:'GENERAL',shift_date:$('#hoDate').val(),shift_id:$('#hoShift').val()}),function(r){
        updateCsrf(r); var html='';
        if(r.data&&r.data.length){
            for(var i=0;i<r.data.length;i++){var t=r.data[i];
                html+='<div class="checkbox"><label><input type="checkbox" class="ho-task" value="'+t.task_id+'"> '
                    +'<strong>'+esc(t.task_no||'')+'</strong> — '+esc(t.title||'')+' ('+esc(t.patient_name||t.patient_no||'')+')'
                    +' <span class="label label-'+(t.priority==='CRITICAL'?'danger':t.priority==='URGENT'?'warning':'info')+'">'+esc(t.priority||'')+'</span>'
                    +'</label></div>';
            }
        }else html='<p class="text-muted">No pending tasks for this shift.</p>';
        $('#hoTaskList').html(html);
    },'json');
}

function submitHandover(){
    var ids=[];$('.ho-task:checked').each(function(){ids.push($(this).val());});
    if(!ids.length){alert('Select at least one task.');return;}
    $.post(BASE+'create_handover',csrfData({ward:'GENERAL',outgoing_shift_id:$('#hoShift').val(),shift_date:$('#hoDate').val(),task_ids:ids,general_notes:$('#hoNotes').val()}),function(r){
        updateCsrf(r);
        if(r.success){alert('Handover '+r.handover_no+' created!');location.reload();}
        else alert(r.error||'Failed');
    },'json');
}

function ackHandover(id){
    $.post(BASE+'acknowledge_handover',csrfData({handover_id:id}),function(r){
        updateCsrf(r);
        if(r.success){alert('Acknowledged. '+r.tasks_reset+' task(s) reassigned.');location.reload();}
        else alert(r.error||'Failed');
    },'json');
}
</script>
</body>
</html>
