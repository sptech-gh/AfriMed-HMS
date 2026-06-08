<!DOCTYPE html>
<html>
    <head>
<head>

        <meta charset="UTF-8">
        <title>Hebrew Medical Center</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

  

        <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
        
           <!----------BOOTSTRAP DATEPICKER----------------------------->
    	<link rel="stylesheet" href="<?php echo base_url();?>public/datepicker/css/datepicker.css">
		<!---------------------------------------------------------->
        
        
        <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
          <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
        <![endif]-->
        
    </head>  
    <body class="skin-blue">
    <!-- header logo: style can be found in header.less -->
    <?php require_once(APPPATH.'views/include/header.php');?>
	<?php $canEditClinical = ((isset($userInfo) && isset($userInfo->module) && strtolower((string)$userInfo->module) === 'doctor') || (isset($hasAccesstoAdmin) && $hasAccesstoAdmin)); ?>
	<?php
		$_ipd_patient = (isset($patientInfo) && is_object($patientInfo)) ? $patientInfo : null;
		$_ipd_visit = (isset($getOPDPatient) && is_object($getOPDPatient)) ? $getOPDPatient : null;
		if (!$_ipd_patient || !$_ipd_visit) {
			echo "<div style='padding:16px'><div class='alert alert-warning'><i class='fa fa-warning'></i> Please open this page from an In-Patient record (missing visit context).</div></div>";
			echo "</body></html>";
			return;
		}
	?>
        
        <div class="wrapper row-offcanvas row-offcanvas-left">
            
            <?php require_once(APPPATH.'views/include/sidebar.php');?>

            <!-- Right side column. Contains the navbar and content of the page -->
            <aside class="right-side">                
                <!-- Content Header (Page header) -->
                <section class="content-header">
                     <?php if($this->session->userdata('emr_viewing') == "ipd_emr_viewing"){?>	
                   <h1>IPD Patient Information</h1>
                   <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">EMR sheet</a></li>
                        <li><a href="<?php echo base_url()?>app/emr/ipd">In-Patient</a></li>
                    </ol>
                    <?php }else{?>
                    <h1>IPD Patient Information</h1>
                   <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Doctor Module</a></li>
                        <li><a href="<?php echo base_url()?>app/doctor/ipd">In-Patient Master</a></li>
                        <li><a href="#">In-Patient Information</a></li>
                    </ol>
                    <?php }?>
                </section>

                <!-- Main content -->
                <section class="content">
                 
        
                 
                 
               
                 <div class="row">
                 	
                     <div class="col-md-3">
                    	 <div class="box">
                         	 <div class="box-header"></div>
                        	<div class="box-body table-responsive no-padding">
                            	<table width="100%" cellpadding="3" cellspacing="3">
                                <tr>
                                	<td width="15%" valign="top" align="center">
                                    <?php
									if(!$patientInfo->picture){
										$picture = "avatar.png";	
									}else{
										$picture = $patientInfo->picture;
									}
									?>
									<img src="<?php echo base_url();?>public/patient_picture/<?php echo $picture;?>" class="img-rounded" width="86" height="81">
                                    </td>
                                    <td>
                                    	<table width="100%">
                                        <tr>
                                        	<td><u>Patient No.</u></td>
                                        </tr>
                                        <tr>
                                			<td><?php echo $patientInfo->patient_no?></td>
                                		</tr>
                                        <tr>
                                        	<td><u>Patient Name</u></td>
                                        </tr>
                                        <tr>
                                			<td><?php echo $patientInfo->name?></td>
                                		</tr>
                                        </table>
                                    </td>
                                </tr>
                                </table>
                            </div>
                            <div class="box-footer clearfix">
                            	<div style="margin-top: 15px;">
                                 <ul class="nav nav-pills nav-stacked">
                                 	<li><a href="<?php echo base_url()?>app/ipd/view/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> General Information</a></li>
                                 
                                 	<li><a href="<?php echo base_url()?>app/ipd/diagnosis/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Diagnosis</a></li>
                                 	
                                 	<li><a href="<?php echo base_url()?>app/ipd/medication/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Medication</a></li>
                                    <li class="active"><a href="<?php echo base_url()?>app/ipd/complain/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Complain</a></li>
                                    <li><a href="<?php echo base_url()?>app/ipd/progress_note/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Progress Note</a></li>
                                    
                                    <li><a href="<?php echo base_url()?>app/ipd/bed_side_procedure/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Bed Side Procedure</a></li>
                                    <li><a href="<?php echo base_url()?>app/ipd/operation_theater/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Operation Theater</a></li>
                                    <li><a href="<?php echo base_url()?>app/ipd/patientHistory/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Patient History</a></li>
                                    <li><a href="<?php echo base_url()?>app/ipd/laboratory/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Laboratory</a></li>
                                    <li><a href="<?php echo base_url()?>app/ipd/discharge_summary/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Discharge Summary</a></li>
                                    <!--<li><a href="<?php echo base_url()?>app/opd/billing/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Admission Billing</a></li>-->
                                 </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                     
                     <div class="col-md-9"> 
                                <div class="nav-tabs-custom">
                                	<ul class="nav nav-tabs">
                                		<li class="active"><a href="#tab_1" data-toggle="tab">Complain</a></li>
                                        
                                	</ul>
                                    <div class="tab-content">
                                    	<div class="tab-pane active" id="tab_1">
                                        	<?php if (!$canEditClinical) { ?>
												<div class="alert alert-info">Read-only — Doctor access only</div>
											<?php } ?>
											<?php if($this->session->userdata('emr_viewing') == ""){?>	
											<?php if($canEditClinical && $getOPDPatient->nStatus == "Pending"){?>
											<a href="#" class="btn btn-primary btn-lg" data-toggle="modal" data-target="#complaintsModal"><i class="fa fa-plus-circle"></i> Add Complaints</a>
											
											<?php }}?>
                                            <a href="<?php echo base_url()?>app/ipd_print/print_complain/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                                            <a href="<?php echo base_url()?>app/ipd_print/pdf_complain/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>" class="btn btn-success" target="_blank"><i class="fa fa-print"></i> PDF</a>
                                            <div class="table-responsive">
                                            <table class="table table-hover table-striped">
                                            <thead>
                                            <tr>
                                            		<th>Complain</th>
                                            		<th>Complain (Others)</th>
                                                 <th>Remarks</th>
                                                 <th></th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach($patientComplain as $patientComplain){?>
                                            <tr>
                                            		<td><?php echo $patientComplain->complain_name?></td>
                                            		<td><?php echo $patientComplain->complain_text?></td>
                                                 <td><?php echo $patientComplain->remarks?></td>
                                                 <td>
                                                 <?php if($this->session->userdata('emr_viewing') == ""){?>	
                                                 <?php if($canEditClinical && $getOPDPatient->nStatus == "Pending"){?>
                                                 <form method="post" action="<?php echo base_url()?>app/ipd/delete_complain/<?php echo $patientComplain->iop_comp_id?>/<?php echo url_safe_id($getOPDPatient->IO_ID) ?>/<?php echo $getOPDPatient->patient_no?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove?');">
                                                     <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                     <button type="submit" class="btn btn-xs btn-danger">Remove</button>
                                                 </form>
                                                 <?php }}?>
                                                 </td>
                                            </tr>
                                            <?php }?>
                                            </tbody>
                                            </table>
                                            </div>
                                            
                                            <br><br><br><br><br><br><br>
                                            <br><br><br><br><br><br><br>
                                            <br><br><br><br><br><br><br>
                                        </div>
                           			</div>
                            <div class="box-footer clearfix">
                                	
                            </div>
                        </div>
                    </div>
                 </div>
                 
                </section><!-- /.content -->
            </aside><!-- /.right-side -->
        </div><!-- ./wrapper -->
  
        
         <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
         <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>     
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
        
         <!-- BDAY -->
         <script src="<?php echo base_url();?>public/datepicker/js/jquery-1.9.1.min.js"></script>
        <script src="<?php echo base_url();?>public/datepicker/js/bootstrap-datepicker.js"></script>
        <script type="text/javascript">
            // When the document is ready
            $(document).ready(function () {
                
                $('#cFrom').datepicker({
                    //format: "dd/mm/yyyy"
					format: "yyyy-mm-dd"
                });  
				
				$('#cTo').datepicker({
                    //format: "dd/mm/yyyy"
					format: "yyyy-mm-dd"
                });  
            
            });
        </script>
        <!-- END BDAY -->
        
        <style>
            #complaintsModal .modal-dialog { max-width: 860px; width: 96%; }
            #complaintsModal .modal-header { background:#3c8dbc; color:#fff; }
            #complaintsModal .modal-header .close { color:#fff; opacity:.8; }
            #complaintsModal .modal-body { background:#f5f7fa; max-height:72vh; overflow-y:auto; }
            .cm-chip-grid { margin:10px 0; }
            .cm-chip { display:inline-block; margin:4px; padding:7px 10px; border:1px solid #ccd6e0; border-radius:4px; background:#fff; cursor:pointer; }
            .cm-chip.selected { background:#3c8dbc; border-color:#367fa9; color:#fff; }
            .cm-selected-tag { display:inline-block; margin:3px; padding:5px 8px; border-radius:4px; background:#e8f3fb; border:1px solid #b7d9ef; }
            .cm-remove { margin-left:6px; cursor:pointer; color:#a94442; font-weight:bold; }
        </style>

        <div class="modal fade" id="complaintsModal" tabindex="-1" role="dialog" aria-labelledby="complaintsModalLabel">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        <h4 class="modal-title" id="complaintsModalLabel"><i class="fa fa-plus-circle"></i> Add Multiple Complaints</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                <input type="text" id="cm-search" class="form-control" placeholder="Search complaints...">
                            </div>
                        </div>
                        <div class="cm-chip-grid" id="cm-chip-grid">
                            <?php foreach ($ComplainList as $complaintOption): ?>
                                <span class="cm-chip"
                                      data-name="<?php echo htmlspecialchars(ucwords(strtolower($complaintOption->complain_name))); ?>">
                                    <?php echo htmlspecialchars(ucwords(strtolower($complaintOption->complain_name))); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <div class="well well-sm">
                            <strong>Selected Complaints</strong>
                            <div id="cm-selected-tags" style="margin-top:6px;">
                                <span class="text-muted" id="cm-empty-sel">None selected yet.</span>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Duration</label>
                                    <input type="text" id="cm-duration" class="form-control" placeholder="e.g. 3 days">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Severity</label>
                                    <select id="cm-severity" class="form-control">
                                        <option value="">-- Select --</option>
                                        <option value="Mild">Mild</option>
                                        <option value="Moderate">Moderate</option>
                                        <option value="Severe">Severe</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Onset</label>
                                    <select id="cm-onset" class="form-control">
                                        <option value="">-- Select --</option>
                                        <option value="Acute">Acute</option>
                                        <option value="Chronic">Chronic</option>
                                        <option value="Recurrent">Recurrent</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="input-group">
                            <input type="text" id="cm-custom-input" class="form-control" placeholder="Add custom complaint not listed above">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default" id="cm-custom-add"><i class="fa fa-plus"></i> Add</button>
                            </span>
                        </div>
                        <div id="cm-saving-overlay" style="display:none;align-items:center;justify-content:center;position:absolute;left:0;right:0;top:0;bottom:0;background:rgba(255,255,255,.85);z-index:10;">
                            <i class="fa fa-spinner fa-spin fa-2x"></i>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
                        <button type="button" class="btn btn-primary" id="cm-btn-save" disabled><i class="fa fa-check-circle"></i> <span id="cm-save-label">Save Complaints (0)</span></button>
                    </div>
                </div>
            </div>
        </div>
        
        
        
        
        
        
        
        
        
        <!-- Modal -->
                             <form method="post" action="<?php echo base_url()?>app/ipd/save_complain" onSubmit="return confirm('Are you sure you want to save?');">
                                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                            <input type="hidden" name="opd_no" value="<?php echo $getOPDPatient->IO_ID?>">
                                            <input type="hidden" name="patient_no" value="<?php echo $getOPDPatient->patient_no?>">
                            <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                                            <h4 class="modal-title" id="myModalLabel">Complain</h4>
                                        </div>
                                        <div class="modal-body">                                        <div class="table-responsive">
                                        <table class="table table-hover">
                                        <tbody>
                                        <tr>
                                        	<td>Complaints</td>
                                            <td>
                                            <select name="complain" id="complain" style="width: 100%;" required class="form-control input-sm" onchange="otherOptions(this.value)">
                                                            	<option value="">- Complaints -</option>
                                                                <option value="others">Others</option>
                                                            	<?php 
																foreach($ComplainList as $ComplainList){?>
                                                            	<option value="<?php echo $ComplainList->complain_id;?>"><?php echo $ComplainList->complain_name;?></option>
                                                                <?php }?>
                                                            </select>
                                            </td>
                                        </tr>
                                        <tr id="complain_txt" style="display: none;">
                                        	<td></td>
                                            <td><input name="complain_text" placeholder="type complains here" class="form-control input-sm" style="width: 100%;" /></td>
                                        </tr>
                                        <tr>
                                        	<td>Remarks</td>
                                            <td><textarea name="remarks" placeholder="Remarks" class="form-control input-sm" style="width: 100%;" rows="3"></textarea></td>
                                        </tr>
                                        </tbody>
                                        </table>
                                        </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                             <button name="btnSubmit" id="btnSubmit" class="btn btn-primary" type="submit" style="font-size:12px;">Save</button>
                                        </div>
                                       
                                    </div>
                                    <!-- /.modal-content -->
                                </div>
                                <!-- /.modal-dialog -->
                            </div>
                            </form>
                            <!-- /.modal -->   
        
    <script>
        function otherOptions(val){
            if(val == 'others'){
                $('#complain_txt').show();
            }else{
                $('#complain_txt').hide();
            }
        }
    </script>

    <script>
        (function($) {
            var selected = [];
            var baseUrl = '<?php echo base_url(); ?>';
            var ipdNo = '<?php echo url_safe_id($getOPDPatient->IO_ID); ?>';
            var patientNo = '<?php echo $getOPDPatient->patient_no; ?>';
            var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
            var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';

            function hasComplaint(name) {
                name = String(name || '').toLowerCase();
                return selected.some(function(item) { return item.toLowerCase() === name; });
            }

            function refreshSelected() {
                var $tags = $('#cm-selected-tags');
                $tags.empty();
                if (!selected.length) {
                    $tags.html('<span class="text-muted" id="cm-empty-sel">None selected yet.</span>');
                } else {
                    selected.forEach(function(name) {
                        var $tag = $('<span class="cm-selected-tag"></span>');
                        $tag.append(document.createTextNode(name + ' '));
                        $('<span class="cm-remove">&times;</span>').on('click', function() {
                            removeComplaint(name);
                        }).appendTo($tag);
                        $tags.append($tag);
                    });
                }
                $('#cm-save-label').text('Save Complaints (' + selected.length + ')');
                $('#cm-btn-save').prop('disabled', selected.length === 0);
            }

            function addComplaint(name) {
                name = $.trim(name || '');
                if (!name || hasComplaint(name)) return;
                selected.push(name);
                $('#cm-chip-grid .cm-chip').filter(function() {
                    return String($(this).data('name')).toLowerCase() === name.toLowerCase();
                }).addClass('selected');
                refreshSelected();
            }

            function removeComplaint(name) {
                selected = selected.filter(function(item) {
                    return item.toLowerCase() !== String(name).toLowerCase();
                });
                $('#cm-chip-grid .cm-chip').filter(function() {
                    return String($(this).data('name')).toLowerCase() === String(name).toLowerCase();
                }).removeClass('selected');
                refreshSelected();
            }

            function resetComplaintsModal() {
                selected = [];
                $('#cm-search,#cm-duration,#cm-custom-input').val('');
                $('#cm-severity,#cm-onset').val('');
                $('#cm-chip-grid .cm-chip').removeClass('selected').show();
                refreshSelected();
            }

            $('#cm-chip-grid').on('click', '.cm-chip', function() {
                var name = $(this).data('name');
                if (hasComplaint(name)) removeComplaint(name);
                else addComplaint(name);
            });

            $('#cm-search').on('input', function() {
                var term = $.trim($(this).val()).toLowerCase();
                $('#cm-chip-grid .cm-chip').each(function() {
                    var name = String($(this).data('name')).toLowerCase();
                    $(this).toggle(term === '' || name.indexOf(term) !== -1);
                });
            });

            $('#cm-custom-add').on('click', function() {
                addComplaint($('#cm-custom-input').val());
                $('#cm-custom-input').val('').focus();
            });
            $('#cm-custom-input').on('keydown', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#cm-custom-add').click();
                }
            });

            $('#cm-btn-save').on('click', function() {
                if (!selected.length) return;
                var entries = selected.map(function(name) {
                    return {
                        complain: name,
                        duration: $.trim($('#cm-duration').val()),
                        severity: $('#cm-severity').val(),
                        onset: $('#cm-onset').val()
                    };
                });
                var postData = {
                    opd_no: ipdNo,
                    patient_no: patientNo,
                    entries: JSON.stringify(entries)
                };
                postData[csrfName] = csrfHash;

                $('#cm-saving-overlay').css('display', 'flex');
                $('#cm-btn-save').prop('disabled', true);
                $.ajax({
                    url: baseUrl + 'app/ipd/save_complaint_batch',
                    type: 'POST',
                    data: postData,
                    dataType: 'json',
                    success: function(resp) {
                        $('#cm-saving-overlay').hide();
                        if (resp && resp.success) {
                            if (resp.redirect) window.location.href = resp.redirect;
                            else window.location.reload();
                            return;
                        }
                        $('#cm-btn-save').prop('disabled', false);
                        alert((resp && resp.message) ? resp.message : 'Error saving complaints. Please try again.');
                    },
                    error: function(xhr) {
                        $('#cm-saving-overlay').hide();
                        $('#cm-btn-save').prop('disabled', false);
                        var msg = 'Network error. Please try again.';
                        if (xhr.responseText) {
                            try {
                                var r = JSON.parse(xhr.responseText);
                                if (r.message) msg = r.message;
                            } catch(e) {}
                        }
                        alert(msg);
                    }
                });
            });

            $('#complaintsModal').on('hidden.bs.modal', resetComplaintsModal);
            $('#complaintsModal').on('shown.bs.modal', resetComplaintsModal);
        }(jQuery));
    </script>
        
        
    </body>
</html>
