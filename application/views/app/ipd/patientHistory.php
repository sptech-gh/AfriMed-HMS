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
                                    <li><a href="<?php echo base_url()?>app/ipd/complain/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Complain</a></li>
                                    <li><a href="<?php echo base_url()?>app/ipd/progress_note/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Progress Note</a></li>
                                    
                                    <li><a href="<?php echo base_url()?>app/ipd/bed_side_procedure/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Bed Side Procedure</a></li>
                                    <li><a href="<?php echo base_url()?>app/ipd/operation_theater/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Operation Theater</a></li>
                                    <li class="active"><a href="<?php echo base_url()?>app/ipd/patientHistory/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>"> Patient History</a></li>
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
                                		<li class="active"><a href="#tab_1" data-toggle="tab">Patient History</a></li>
                                        
                                	</ul>
                                    <div class="tab-content">
                                    	<div class="tab-pane active" id="tab_1">
                                            <?php if (!$canEditClinical): ?>
                                            <div class="alert alert-info"><i class="fa fa-lock"></i> <strong>Read-Only</strong> — Doctor or Administrator access required to edit clinical history.</div>
                                            <?php endif; ?>
                                            <?php echo isset($message) ? $message : ''; ?>

                                            <?php if (!empty($clinical_summary['allergies']) || !empty($clinical_summary['warnings'])): ?>
                                            <div class="row" style="margin-bottom:10px;">
                                                <?php if (!empty($clinical_summary['allergies'])): ?>
                                                <div class="col-md-6">
                                                    <div class="callout callout-danger" style="margin-bottom:0;padding:8px 15px;">
                                                        <strong><i class="fa fa-exclamation-circle"></i> Known Allergies:</strong>
                                                        <?php echo htmlspecialchars($clinical_summary['allergies']); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (!empty($clinical_summary['warnings'])): ?>
                                                <div class="col-md-6">
                                                    <div class="callout callout-warning" style="margin-bottom:0;padding:8px 15px;">
                                                        <strong><i class="fa fa-warning"></i> Warnings:</strong>
                                                        <?php echo htmlspecialchars($clinical_summary['warnings']); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>

                                            <form method="post" action="<?php echo base_url()?>app/ipd/save_patientHistory" id="clinicalHistoryFormIpd">
                                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                            <input type="hidden" name="opd_no" value="<?php echo $getOPDPatient->IO_ID?>">
                                            <input type="hidden" name="patient_no" value="<?php echo $getOPDPatient->patient_no?>">

                                            <!-- 1. History of Presenting Complaint -->
                                            <div class="panel panel-primary">
                                                <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#sec_hpc_ipd">
                                                    <h4 class="panel-title"><i class="fa fa-file-text-o"></i> 1. History of Presenting Complaint (HPC) <small class="pull-right"><i class="fa fa-chevron-down"></i></small></h4>
                                                </div>
                                                <div id="sec_hpc_ipd" class="collapse in">
                                                    <div class="panel-body">
                                                        <p class="text-muted" style="font-size:12px;margin-bottom:5px;"><i class="fa fa-info-circle"></i> Chronological description of symptoms — onset, duration, progression, severity, aggravating &amp; relieving factors.</p>
                                                        <textarea name="history_presenting_complaint" class="form-control" rows="5" placeholder="e.g. Patient is a 45-year-old male presenting with a 3-day history of productive cough, onset was sudden, associated with fever and chest pain on inspiration..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->history_presenting_complaint) ? $getOPDPatient->history_presenting_complaint : ''); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 2. Past History -->
                                            <div class="panel panel-info">
                                                <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#sec_past_ipd">
                                                    <h4 class="panel-title"><i class="fa fa-history"></i> 2. Past History <small class="pull-right"><i class="fa fa-chevron-down"></i></small></h4>
                                                </div>
                                                <div id="sec_past_ipd" class="collapse in">
                                                    <div class="panel-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <label><i class="fa fa-medkit"></i> Past Medical History (PMHx)</label>
                                                                <p class="text-muted" style="font-size:11px;margin-bottom:4px;">Previous illnesses, hospitalizations, chronic conditions</p>
                                                                <textarea name="past_medical_history" class="form-control" rows="4" placeholder="e.g. HTN diagnosed 2015, DM Type 2 since 2018, Asthma..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->past_medical_history) ? $getOPDPatient->past_medical_history : ''); ?></textarea>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label><i class="fa fa-scissors"></i> Past Surgical History (PSHx)</label>
                                                                <p class="text-muted" style="font-size:11px;margin-bottom:4px;">Previous operations and procedures with dates</p>
                                                                <textarea name="past_surgical_history" class="form-control" rows="4" placeholder="e.g. Appendectomy 2012, LSCS 2019..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->past_surgical_history) ? $getOPDPatient->past_surgical_history : ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="row" style="margin-top:10px;">
                                                            <div class="col-md-6">
                                                                <label><i class="fa fa-tablet"></i> Drug History / Current Medications</label>
                                                                <p class="text-muted" style="font-size:11px;margin-bottom:4px;">Medications in use before this visit</p>
                                                                <textarea name="drug_history" class="form-control" rows="4" placeholder="e.g. Metformin 500mg BD, Amlodipine 5mg OD..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->drug_history) ? $getOPDPatient->drug_history : ''); ?></textarea>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label><i class="fa fa-female"></i> Gynae / Obstetric History <small class="text-muted">(females)</small></label>
                                                                <p class="text-muted" style="font-size:11px;margin-bottom:4px;">LMP, Gravida, Para, Deliveries, Complications</p>
                                                                <textarea name="gynae_obstetric_history" class="form-control" rows="4" placeholder="LMP: 01/04/2026&#10;G3P2+1 (2 SVD, 1 miscarriage)..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->gynae_obstetric_history) ? $getOPDPatient->gynae_obstetric_history : ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 3. Allergies & Warnings -->
                                            <div class="panel panel-danger">
                                                <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#sec_allergy_ipd">
                                                    <h4 class="panel-title"><i class="fa fa-warning"></i> 3. Allergies &amp; Clinical Warnings <small class="pull-right"><i class="fa fa-chevron-down"></i></small></h4>
                                                </div>
                                                <div id="sec_allergy_ipd" class="collapse in">
                                                    <div class="panel-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <label class="text-danger"><i class="fa fa-exclamation-circle"></i> Allergies</label>
                                                                <p class="text-muted" style="font-size:11px;margin-bottom:4px;">Drug, food, environmental allergies &amp; reactions</p>
                                                                <textarea name="allergies" class="form-control" rows="3" placeholder="e.g. Penicillin — rash; Sulfa drugs — anaphylaxis..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->allergies) ? $getOPDPatient->allergies : ''); ?></textarea>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="text-warning"><i class="fa fa-bell"></i> Clinical Warnings</label>
                                                                <p class="text-muted" style="font-size:11px;margin-bottom:4px;">Important clinical alerts and safety precautions</p>
                                                                <textarea name="warnings" class="form-control" rows="3" placeholder="e.g. Fall risk, Difficult IV access, Latex allergy..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->warnings) ? $getOPDPatient->warnings : ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 4. Family & Social History -->
                                            <div class="panel panel-success">
                                                <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#sec_fsh_ipd">
                                                    <h4 class="panel-title"><i class="fa fa-users"></i> 4. Family &amp; Social History <small class="pull-right"><i class="fa fa-chevron-down"></i></small></h4>
                                                </div>
                                                <div id="sec_fsh_ipd" class="collapse in">
                                                    <div class="panel-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <label><i class="fa fa-sitemap"></i> Family History (FHx)</label>
                                                                <p class="text-muted" style="font-size:11px;margin-bottom:4px;">Hereditary / familial conditions in blood relatives</p>
                                                                <textarea name="family_history" class="form-control" rows="3" placeholder="e.g. Father: HTN, DM; Mother: Breast Ca..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->family_history) ? $getOPDPatient->family_history : ''); ?></textarea>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label><i class="fa fa-coffee"></i> Social History (SHx)</label>
                                                                <p class="text-muted" style="font-size:11px;margin-bottom:4px;">Smoking, Alcohol, Occupation, Lifestyle</p>
                                                                <textarea name="social_history" class="form-control" rows="3" placeholder="e.g. Non-smoker, Social drinker, Teacher..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->social_history) ? $getOPDPatient->social_history : ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="row" style="margin-top:10px;">
                                                            <div class="col-md-12">
                                                                <label><i class="fa fa-user"></i> Personal History</label>
                                                                <p class="text-muted" style="font-size:11px;margin-bottom:4px;">Diet, Sleep, Exercise, Marital status</p>
                                                                <textarea name="personal_history" class="form-control" rows="2" placeholder="e.g. Married, 2 children, vegetarian diet..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->personal_history) ? $getOPDPatient->personal_history : ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 5. On Direct Questioning -->
                                            <div class="panel panel-warning">
                                                <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#sec_odq_ipd">
                                                    <h4 class="panel-title"><i class="fa fa-question-circle"></i> 5. On Direct Questioning (ODQ / Review of Systems) <small class="pull-right"><i class="fa fa-chevron-down"></i></small></h4>
                                                </div>
                                                <div id="sec_odq_ipd" class="collapse">
                                                    <div class="panel-body">
                                                        <p class="text-muted" style="font-size:12px;margin-bottom:8px;"><i class="fa fa-info-circle"></i> Systematic review of body systems. Document positive findings and relevant negatives.</p>
                                                        <textarea name="on_direct_questioning" class="form-control" rows="7" placeholder="CVS: No chest pain, palpitations, or orthopnea.&#10;RS: No cough, dyspnea, or hemoptysis.&#10;GIT: No nausea, vomiting. Appetite normal.&#10;GU: No dysuria or frequency.&#10;MSK: No joint pain or swelling.&#10;CNS: No headache, dizziness, or weakness.&#10;Skin: No rash or lesions." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->on_direct_questioning) ? $getOPDPatient->on_direct_questioning : ''); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 6. Physical Examination -->
                                            <div class="panel panel-default">
                                                <div class="panel-heading" style="cursor:pointer;background:#605ca8;color:#fff;" data-toggle="collapse" data-target="#sec_exam_ipd">
                                                    <h4 class="panel-title" style="color:#fff;"><i class="fa fa-stethoscope"></i> 6. Physical Examination <small class="pull-right"><i class="fa fa-chevron-down"></i></small></h4>
                                                </div>
                                                <div id="sec_exam_ipd" class="collapse">
                                                    <div class="panel-body">
                                                        <div class="form-group">
                                                            <label><i class="fa fa-eye"></i> General Examination</label>
                                                            <p class="text-muted" style="font-size:11px;margin-bottom:4px;">Consciousness, Build, Pallor, Jaundice, Cyanosis, Clubbing, Lymphadenopathy, Edema</p>
                                                            <textarea name="examination_general" class="form-control" rows="3" placeholder="e.g. Conscious, alert, oriented. Well-built. No pallor, jaundice, cyanosis, clubbing, lymphadenopathy, or pedal edema." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->examination_general) ? $getOPDPatient->examination_general : ''); ?></textarea>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label><i class="fa fa-heartbeat"></i> Cardiovascular System (CVS)</label>
                                                                    <textarea name="examination_cardiovascular" class="form-control" rows="3" placeholder="e.g. Pulse 80bpm regular. JVP not raised. S1S2 heard, no murmurs." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->examination_cardiovascular) ? $getOPDPatient->examination_cardiovascular : ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label><i class="fa fa-cloud"></i> Respiratory System (RS)</label>
                                                                    <textarea name="examination_respiratory" class="form-control" rows="3" placeholder="e.g. Chest bilaterally symmetrical. Normal vesicular breath sounds." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->examination_respiratory) ? $getOPDPatient->examination_respiratory : ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label><i class="fa fa-medkit"></i> Gastrointestinal / Abdomen (GIT)</label>
                                                                    <textarea name="examination_gastrointestinal" class="form-control" rows="3" placeholder="e.g. Abdomen soft, non-tender. No hepatosplenomegaly. Bowel sounds normal." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->examination_gastrointestinal) ? $getOPDPatient->examination_gastrointestinal : ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label><i class="fa fa-bolt"></i> Central Nervous System (CNS)</label>
                                                                    <textarea name="examination_neurological" class="form-control" rows="3" placeholder="e.g. GCS 15/15. Pupils equal and reactive. No focal neurological deficit." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->examination_neurological) ? $getOPDPatient->examination_neurological : ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label><i class="fa fa-male"></i> Musculoskeletal System (MSK)</label>
                                                                    <textarea name="examination_musculoskeletal" class="form-control" rows="2" placeholder="e.g. Full range of motion. No deformity or swelling." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->examination_musculoskeletal) ? $getOPDPatient->examination_musculoskeletal : ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label><i class="fa fa-plus-square"></i> Other Systems / Additional Findings</label>
                                                                    <textarea name="examination_other" class="form-control" rows="2" placeholder="e.g. Skin, Eyes, ENT, Genitourinary, PR/PV exam findings..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->examination_other) ? $getOPDPatient->examination_other : ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label><i class="fa fa-file-text"></i> Examination Summary / Overall Findings</label>
                                                            <textarea name="examination_findings" class="form-control" rows="3" placeholder="Summary of significant examination findings and overall clinical impression..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->examination_findings) ? $getOPDPatient->examination_findings : ''); ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Action Buttons -->
                                            <div class="row" style="margin-top:10px;margin-bottom:20px;">
                                                <div class="col-md-12">
                                                    <?php if($this->session->userdata('emr_viewing') == "" && $canEditClinical): ?>
                                                    <button type="submit" class="btn btn-primary" onclick="return confirm('Save clinical history?');"><i class="fa fa-save"></i> Save Clinical History</button>
                                                    <?php endif; ?>
                                                    <a href="<?php echo base_url()?>app/ipd_print/print_patient_history/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                                                    <a href="<?php echo base_url()?>app/ipd_print/pdf_patient_history/<?php echo $getOPDPatient->IO_ID;?>/<?php echo $getOPDPatient->patient_no;?>" class="btn btn-success" target="_blank"><i class="fa fa-file-pdf-o"></i> PDF</a>
                                                </div>
                                            </div>

                                            </form>
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
        
        
    </body>
</html>
