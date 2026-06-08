<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Hebrew Medical Center</title>
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
<link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="<?php echo base_url();?>public/datepicker/css/datepicker.css">
<link href="<?php echo base_url();?>public/timepicker/bootstrap-timepicker.min.css" rel="stylesheet"/>
<style>
.io-total { font-weight:bold; background:#f5f5f5 !important; }
.io-positive { color:#27ae60; }
.io-negative { color:#c0392b; }
.glucose-normal { color:#27ae60; }
.glucose-high { color:#c0392b; }
.glucose-low { color:#e67e22; }
</style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
<?php require_once(APPPATH.'views/include/sidebar.php');?>
<aside class="right-side">
<section class="content-header">
<h1>Fluid Balance & Glucose Monitoring</h1>
<ol class="breadcrumb">
<li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
<li><a href="#">Nurse Module</a></li>
<li class="active">Intake/Output</li>
</ol>
</section>
<section class="content">
<div class="row">
<!-- Patient Info Sidebar -->
<div class="col-md-3">
<div class="box">
<div class="box-header"></div>
<div class="box-body table-responsive no-padding">
<table width="100%" cellpadding="3" cellspacing="3">
<tr>
<td width="15%" valign="top" align="center">
<?php $picture = $patientInfo->picture ? $patientInfo->picture : 'avatar.png'; ?>
<img src="<?php echo base_url();?>public/patient_picture/<?php echo $picture;?>" class="img-rounded" width="86" height="81">
</td>
<td>
<table width="100%">
<tr><td><u>Patient No.</u></td></tr>
<tr><td><?php echo $patientInfo->patient_no?></td></tr>
<tr><td><u>Patient Name</u></td></tr>
<tr><td><?php echo $patientInfo->name?></td></tr>
</table>
</td>
</tr>
</table>
</div>
<div class="box-footer clearfix">
<table class="table">
<tr><td><u>IOP No.</u></td></tr>
<tr><td><?php echo $getOPDPatient->IO_ID;?></td></tr>
<tr><td><u>Date Time Admit</u></td></tr>
<tr><td><?php echo date("M d, Y", strtotime($getOPDPatient->date_visit));?>&nbsp;<?php echo date("H:i:s A", strtotime($getOPDPatient->time_visit));?></td></tr>
<tr><td><u>In-Charge Doctor</u></td></tr>
<tr><td><?php echo $getOPDPatient->con_doctor;?></td></tr>
<tr><td><u>Room</u></td></tr>
<tr><td><?php echo $getOPDPatient->room_name;?></td></tr>
<tr><td><u>Bed No.</u></td></tr>
<tr><td><?php echo $getOPDPatient->bed_name;?></td></tr>
</table>
</div>
</div>

<!-- I/O Balance Summary -->
<?php if (isset($io_totals)): ?>
<div class="box box-info">
<div class="box-header with-border"><h3 class="box-title"><i class="fa fa-calculator"></i> Fluid Balance Summary</h3></div>
<div class="box-body" style="font-size:13px;">
<table class="table table-condensed">
<tr><td><strong>Total Intake</strong></td><td class="text-right"><strong><?php echo number_format($io_totals['total_intake'],0); ?> ml</strong></td></tr>
<tr><td>&nbsp; IV Fluids</td><td class="text-right"><?php echo number_format($io_totals['intake_iv'],0); ?> ml</td></tr>
<tr><td>&nbsp; Oral</td><td class="text-right"><?php echo number_format($io_totals['intake_oral'],0); ?> ml</td></tr>
<tr><td>&nbsp; Blood Products</td><td class="text-right"><?php echo number_format($io_totals['intake_blood'],0); ?> ml</td></tr>
<tr><td>&nbsp; NG Tube/Feeds</td><td class="text-right"><?php echo number_format($io_totals['intake_ng'],0); ?> ml</td></tr>
<tr><td colspan="2"><hr style="margin:5px 0;"></td></tr>
<tr><td><strong>Total Output</strong></td><td class="text-right"><strong><?php echo number_format($io_totals['total_output'],0); ?> ml</strong></td></tr>
<tr><td>&nbsp; Urine</td><td class="text-right"><?php echo number_format($io_totals['output_urine'],0); ?> ml</td></tr>
<tr><td>&nbsp; Faeces</td><td class="text-right"><?php echo number_format($io_totals['output_faeces'],0); ?> ml</td></tr>
<tr><td>&nbsp; Vomit/Emesis</td><td class="text-right"><?php echo number_format($io_totals['output_vomit'],0); ?> ml</td></tr>
<tr><td>&nbsp; Drainage</td><td class="text-right"><?php echo number_format($io_totals['output_drainage'],0); ?> ml</td></tr>
<tr><td>&nbsp; Insensible Loss</td><td class="text-right"><?php echo number_format($io_totals['output_insensible'],0); ?> ml</td></tr>
<tr><td colspan="2"><hr style="margin:5px 0;"></td></tr>
<tr class="io-total"><td>Net Balance</td><td class="text-right <?php echo $io_totals['balance'] >= 0 ? 'io-positive' : 'io-negative'; ?>"><?php echo ($io_totals['balance'] >= 0 ? '+' : '').number_format($io_totals['balance'],0); ?> ml</td></tr>
</table>
</div>
</div>
<?php endif; ?>
</div>

<!-- Main Content -->
<div class="col-md-9">
<div class="nav-tabs-custom">
<ul class="nav nav-tabs">
<li class="active"><a href="#tab_intake" data-toggle="tab"><i class="fa fa-arrow-down"></i> Intake</a></li>
<li><a href="#tab_output" data-toggle="tab"><i class="fa fa-arrow-up"></i> Output</a></li>
<li><a href="#tab_glucose" data-toggle="tab"><i class="fa fa-tint"></i> Glucose Monitoring</a></li>
</ul>
<div class="tab-content">

<!-- INTAKE TAB -->
<div class="tab-pane active" id="tab_intake">
<?php echo $message;?>
<a href="#" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#intakeModal"><i class="fa fa-plus"></i> Add Intake Record</a>
<a href="<?php echo base_url()?>app/ipd_print/print_intake/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>" class="btn btn-default btn-sm" target="_blank"><i class="fa fa-print"></i> Print</a>
<div style="margin-top:10px; overflow:auto; max-height:400px;" class="table-responsive">
<table class="table table-hover table-striped table-condensed nursing-table">
<thead>
<tr>
<th>Date & Time</th>
<th>Particulars</th>
<th>IV Fluids (ml)</th>
<th>Oral (ml)</th>
<th>Blood Products (ml)</th>
<th>NG Tube/Feeds (ml)</th>
<th>Stool (count)</th>
<th>Urine (count)</th>
<th>Recorded By</th>
<th></th>
</tr>
</thead>
<tbody>
<?php foreach($getIntake as $row){?>
<tr>
<td data-label="Date & Time"><small><?php echo date("M d, Y h:i A",strtotime($row->dDateTime));?></small></td>
<td data-label="Particulars"><?php echo $row->particulars?></td>
<td data-label="IV Fluids (ml)"><?php echo $row->IV_fluids?></td>
<td data-label="Oral (ml)"><?php echo $row->oral?></td>
<td data-label="Blood Products (ml)"><?php echo isset($row->blood_products) ? $row->blood_products : '0'; ?></td>
<td data-label="NG Tube/Feeds (ml)"><?php echo isset($row->ng_tube_feeds) ? $row->ng_tube_feeds : '0'; ?></td>
<td data-label="Stool (count)"><?php echo $row->no_stool?></td>
<td data-label="Urine (count)"><?php echo $row->no_urine?></td>
<td data-label="Recorded By"><?php $ci=&get_instance();$ci->load->model('app/general_model');$p=$ci->general_model->getPreparedBy($row->cPreparedBy);echo $p->cPreparedBy;?></td>
<td data-label="Actions">
<form method="post" action="<?php echo base_url()?>app/nurse_module/delete_intake/<?php echo $row->intake_id?>/<?php echo url_safe_id($getOPDPatient->IO_ID)?>/<?php echo $getOPDPatient->patient_no?>" style="display:inline;" onsubmit="return confirm('Remove this intake record?');">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
<button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
</form>
</td>
</tr>
<?php }?>
</tbody>
</table>
</div>
</div>

<!-- OUTPUT TAB -->
<div class="tab-pane" id="tab_output">
<a href="#" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#outputModal"><i class="fa fa-plus"></i> Add Output Record</a>
<a href="<?php echo base_url()?>app/ipd_print/print_output/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>" class="btn btn-default btn-sm" target="_blank"><i class="fa fa-print"></i> Print</a>
<div style="margin-top:10px; overflow:auto; max-height:400px;" class="table-responsive">
<table class="table table-hover table-striped table-condensed nursing-table">
<thead>
<tr>
<th>Date & Time</th>
<th>Urine (ml)</th>
<th>Faeces (ml)</th>
<th>Vomit/Emesis (ml)</th>
<th>Drainage (ml)</th>
<th>Drain Site</th>
<th>Stool Count</th>
<th>Consistency</th>
<th>Insensible Loss (ml)</th>
<th>Recorded By</th>
<th></th>
</tr>
</thead>
<tbody>
<?php foreach($getOutput as $row){?>
<tr>
<td data-label="Date & Time"><small><?php echo date("M d, Y h:i A",strtotime($row->dDateTime));?></small></td>
<td data-label="Urine (ml)"><?php echo $row->urine?></td>
<td data-label="Faeces (ml)"><?php echo $row->feaces?></td>
<td data-label="Vomit/Emesis (ml)"><?php echo isset($row->vomit) ? $row->vomit : '0'; ?></td>
<td data-label="Drainage (ml)"><?php echo isset($row->drainage) ? $row->drainage : '0'; ?></td>
<td data-label="Drain Site"><?php echo isset($row->drainage_site) ? htmlspecialchars($row->drainage_site) : '—'; ?></td>
<td data-label="Stool Count"><?php echo isset($row->stool_count) ? $row->stool_count : '—'; ?></td>
<td data-label="Consistency"><?php echo isset($row->stool_consistency) ? htmlspecialchars($row->stool_consistency) : '—'; ?></td>
<td data-label="Insensible Loss (ml)"><?php echo $row->respitation?></td>
<td data-label="Recorded By"><?php $ci=&get_instance();$ci->load->model('app/general_model');$p=$ci->general_model->getPreparedBy($row->cPreparedBy);echo $p->cPreparedBy;?></td>
<td data-label="Actions">
<form method="post" action="<?php echo base_url()?>app/nurse_module/delete_output/<?php echo $row->output_id?>/<?php echo url_safe_id($getOPDPatient->IO_ID)?>/<?php echo $getOPDPatient->patient_no?>" style="display:inline;" onsubmit="return confirm('Remove this output record?');">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
<button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
</form>
</td>
</tr>
<?php }?>
</tbody>
</table>
</div>
</div>

<!-- GLUCOSE TAB -->
<div class="tab-pane" id="tab_glucose">
<a href="#" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#glucoseModal"><i class="fa fa-plus"></i> Add Glucose Reading</a>
<div style="margin-top:10px; overflow:auto; max-height:400px;" class="table-responsive">
<table class="table table-hover table-striped table-condensed nursing-table">
<thead><tr><th>Date & Time</th><th>Type</th><th>Value (mmol/L)</th><th>Status</th><th>Notes</th><th>Recorded By</th></tr></thead>
<tbody>
<?php if(isset($glucoseReadings) && !empty($glucoseReadings)): ?>
<?php foreach($glucoseReadings as $g): ?>
<?php
$val = (float)$g->glucose_value;
$cls = 'glucose-normal'; $status = 'Normal';
if ($val < 3.9) { $cls = 'glucose-low'; $status = 'Low'; }
elseif ($val > 11.1) { $cls = 'glucose-high'; $status = 'High'; }
elseif ($val > 7.8) { $cls = 'glucose-high'; $status = 'Elevated'; }
?>
<tr>
<td data-label="Date & Time"><small><?php echo date("M d, Y h:i A", strtotime($g->dDateTime)); ?></small></td>
<td data-label="Type"><span class="label label-default"><?php echo htmlspecialchars($g->glucose_type); ?></span></td>
<td data-label="Value (mmol/L)" class="<?php echo $cls; ?>"><strong><?php echo number_format($val,1); ?></strong> mmol/L</td>
<td data-label="Status"><span class="<?php echo $cls; ?>"><?php echo $status; ?></span></td>
<td data-label="Notes"><?php echo $g->notes ? htmlspecialchars($g->notes) : '—'; ?></td>
<td data-label="Recorded By"><?php echo htmlspecialchars($g->nurse_name); ?></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="6" class="text-center text-muted" style="padding:20px;"><i class="fa fa-tint" style="font-size:24px;"></i><br>No glucose readings recorded.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</div><!-- tab-content -->
</div><!-- nav-tabs-custom -->
</div>
</div>
</section>
</aside>
</div>

<!-- INTAKE MODAL -->
<form method="post" action="<?php echo base_url()?>app/nurse_module/save_intake" onSubmit="return confirm('Save intake record?');">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
<input type="hidden" name="opd_no" value="<?php echo $getOPDPatient->IO_ID?>">
<input type="hidden" name="patient_no" value="<?php echo $getOPDPatient->patient_no?>">
<div class="modal fade" id="intakeModal" tabindex="-1" role="dialog">
<div class="modal-dialog modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-arrow-down"></i> Intake Record</h4></div>
<div class="modal-body">
<div class="row">
<div class="col-md-6"><div class="form-group"><label>Date</label><input type="text" name="dDate" id="dDate" value="<?php echo date("Y-m-d");?>" class="form-control input-sm" required></div></div>
<div class="col-md-6"><div class="form-group"><label>Time</label><div class="input-group"><input type="text" class="form-control input-sm timepicker" name="cTime" id="cTime"/><span class="input-group-addon"><i class="fa fa-clock-o"></i></span></div></div></div>
</div>
<div class="form-group"><label>Particulars</label><input type="text" name="particular" class="form-control input-sm" required placeholder="e.g. Normal Saline, Dextrose 5%"></div>
<div class="row">
<div class="col-md-6"><div class="form-group"><label>I/V Fluids (ml)</label><input type="number" name="fluids" class="form-control input-sm" value="0" min="0" required></div></div>
<div class="col-md-6"><div class="form-group"><label>Oral Fluids (ml)</label><input type="number" name="oral" class="form-control input-sm" value="0" min="0" required></div></div>
</div>
<div class="row">
<div class="col-md-6"><div class="form-group"><label>Blood Products (ml)</label><input type="number" name="blood_products" class="form-control input-sm" value="0" min="0"></div></div>
<div class="col-md-6"><div class="form-group"><label>NG Tube/Feeds (ml)</label><input type="number" name="ng_tube_feeds" class="form-control input-sm" value="0" min="0"></div></div>
</div>
<div class="row">
<div class="col-md-6"><div class="form-group"><label>No. of Stool</label><input type="number" name="no_stool" class="form-control input-sm" value="0" min="0" required></div></div>
<div class="col-md-6"><div class="form-group"><label>No. of Urine</label><input type="number" name="no_urine" class="form-control input-sm" value="0" min="0" required></div></div>
</div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Save</button></div>
</div></div></div>
</form>

<!-- OUTPUT MODAL -->
<form method="post" action="<?php echo base_url()?>app/nurse_module/save_output" onSubmit="return confirm('Save output record?');">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
<input type="hidden" name="opd_no" value="<?php echo $getOPDPatient->IO_ID?>">
<input type="hidden" name="patient_no" value="<?php echo $getOPDPatient->patient_no?>">
<div class="modal fade" id="outputModal" tabindex="-1" role="dialog">
<div class="modal-dialog modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-arrow-up"></i> Output Record</h4></div>
<div class="modal-body">
<div class="row">
<div class="col-md-6"><div class="form-group"><label>Date</label><input type="text" name="dDate2" id="dDate2" value="<?php echo date("Y-m-d");?>" class="form-control input-sm" required></div></div>
<div class="col-md-6"><div class="form-group"><label>Time</label><div class="input-group"><input type="text" class="form-control input-sm timepicker" name="cTime2" id="cTime2"/><span class="input-group-addon"><i class="fa fa-clock-o"></i></span></div></div></div>
</div>
<div class="row">
<div class="col-md-6"><div class="form-group"><label>Urine (ml)</label><input type="number" name="urine" class="form-control input-sm" value="0" min="0" required></div></div>
<div class="col-md-6"><div class="form-group"><label>Faeces (ml)</label><input type="number" name="feaces" class="form-control input-sm" value="0" min="0" required></div></div>
</div>
<div class="row">
<div class="col-md-6"><div class="form-group"><label>Vomit/Emesis (ml)</label><input type="number" name="vomit" class="form-control input-sm" value="0" min="0"></div></div>
<div class="col-md-6"><div class="form-group"><label>Drainage (ml)</label><input type="number" name="drainage" class="form-control input-sm" value="0" min="0"></div></div>
</div>
<div class="row">
<div class="col-md-6"><div class="form-group"><label>Drainage Site</label><select name="drainage_site" class="form-control input-sm"><option value="">— None —</option><option>Wound Drain</option><option>Chest Drain</option><option>NG Aspirate</option><option>Abdominal Drain</option><option>Other</option></select></div></div>
<div class="col-md-6"><div class="form-group"><label>Insensible Loss (ml)</label><input type="number" name="respitation" class="form-control input-sm" value="0" min="0" required></div></div>
</div>
<div class="row">
<div class="col-md-4"><div class="form-group"><label>Stool Count</label><input type="number" name="stool_count" class="form-control input-sm" value="0" min="0"></div></div>
<div class="col-md-4"><div class="form-group"><label>Stool Consistency</label><select name="stool_consistency" class="form-control input-sm"><option value="">—</option><option>Formed</option><option>Semi-formed</option><option>Loose</option><option>Watery</option><option>Bloody</option><option>Mucoid</option></select></div></div>
<div class="col-md-4"><div class="form-group"><label>Skin Loss (ml)</label><input type="number" name="skin" class="form-control input-sm" value="0" min="0" required></div></div>
</div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Save</button></div>
</div></div></div>
</form>

<!-- GLUCOSE MODAL -->
<form method="post" action="<?php echo base_url()?>app/nurse_module/save_glucose" onSubmit="return confirm('Save glucose reading?');">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
<input type="hidden" name="opd_no" value="<?php echo $getOPDPatient->IO_ID?>">
<input type="hidden" name="patient_no" value="<?php echo $getOPDPatient->patient_no?>">
<div class="modal fade" id="glucoseModal" tabindex="-1" role="dialog">
<div class="modal-dialog modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-tint"></i> Blood Glucose Reading</h4></div>
<div class="modal-body">
<div class="row">
<div class="col-md-6"><div class="form-group"><label>Date</label><input type="text" name="glucose_date" id="glucoseDate" value="<?php echo date("Y-m-d");?>" class="form-control input-sm" required></div></div>
<div class="col-md-6"><div class="form-group"><label>Time</label><div class="input-group"><input type="text" class="form-control input-sm timepicker" name="glucose_time"/><span class="input-group-addon"><i class="fa fa-clock-o"></i></span></div></div></div>
</div>
<div class="row">
<div class="col-md-6"><div class="form-group"><label>Glucose Value (mmol/L) <span class="text-danger">*</span></label><input type="number" step="0.1" name="glucose_value" class="form-control input-sm" required placeholder="e.g. 5.6" min="0.1"></div></div>
<div class="col-md-6"><div class="form-group"><label>Type</label><select name="glucose_type" class="form-control input-sm"><option value="RBS">RBS (Random Blood Sugar)</option><option value="FBS">FBS (Fasting Blood Sugar)</option><option value="2HPP">2HPP (2hr Post-Prandial)</option></select></div></div>
</div>
<div class="form-group"><label>Notes <small class="text-muted">(optional)</small></label><textarea name="glucose_notes" class="form-control input-sm" rows="2" placeholder="e.g. Before meal, post-insulin..."></textarea></div>
<div class="alert alert-info" style="font-size:11px;margin-bottom:0;"><i class="fa fa-info-circle"></i> <strong>Reference:</strong> Normal FBS: 3.9–5.5 mmol/L | Normal RBS: 3.9–7.8 mmol/L | Diabetic: >11.1 mmol/L</div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Save</button></div>
</div></div></div>
</form>

<script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
<script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
<script src="<?php echo base_url();?>public/datepicker/js/bootstrap-datepicker.js"></script>
<script src="<?php echo base_url();?>public/timepicker/js/plugins/timepicker/bootstrap-timepicker.min.js" type="text/javascript"></script>
<script>
$(document).ready(function(){
$('#dDate,#dDate2,#glucoseDate').datepicker({format:"yyyy-mm-dd",autoclose:true,todayHighlight:true});
$(".timepicker").timepicker({showInputs:false});
});
</script>
</body>
</html>