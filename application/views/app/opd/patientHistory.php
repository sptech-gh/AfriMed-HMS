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
        
        <!----------BOOTSTRAP DATEPICKER----------------------------->
    	<link rel="stylesheet" href="<?php echo base_url();?>public/datepicker/css/datepicker.css">
		<!---------------------------------------------------------->
        
        
        <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
          <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
        <![endif]-->

		<style>
			.opd-content-header-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
			.opd-content-header-row h1 { margin:0; font-size:20px; font-weight:600; }
			.right-side > .content-header > .breadcrumb { float:none; clear:both; position:static; margin-top:8px; }

			.opd-patient-sidebar .box { box-shadow:0 1px 3px rgba(0,0,0,0.08); border:1px solid rgba(0,0,0,0.04); }
			.opd-patient-card { border-top:3px solid #3c8dbc; }
			.opd-patient-card .box-body { padding:15px; }
			.opd-patient-card .media { display:flex; gap:12px; align-items:center; }
			.opd-patient-card .media-left img { width:64px; height:64px; object-fit:cover; }
			.opd-patient-card .media-heading { margin:0; font-size:14px; font-weight:700; }
			.opd-patient-meta { margin-top:2px; font-size:12px; color:#777; }
			.opd-patient-meta strong { color:#333; }

			.opd-side-nav { margin:0; }
			.opd-side-nav > li > a { padding:10px 12px; border-radius:4px; font-size:13px; }
			.opd-side-nav > li.active > a,
			.opd-side-nav > li.active > a:hover { background:#3c8dbc; color:#fff; }
			.opd-side-nav > li > a:hover { background:#f5f7fa; }

			.opd-tabs-custom { border-top:3px solid #3c8dbc; box-shadow:0 1px 3px rgba(0,0,0,0.08); }
			.opd-tabs-custom > .nav-tabs > li > a { font-weight:600; font-size:13px; padding:10px 14px; }
			.opd-tabs-custom > .tab-content { padding:12px; }

			.opd-history-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }

			@media (min-width: 992px) {
				.opd-patient-sidebar { position:sticky; top:70px; }
			}
		</style>
        
    </head>  
    <body class="skin-blue">
        <!-- header logo: style can be found in header.less -->
        <?php require_once(APPPATH.'views/include/header.php');?>
		<?php $canEditClinical = ((isset($userInfo) && isset($userInfo->module) && strtolower((string)$userInfo->module) === 'doctor') || (isset($hasAccesstoAdmin) && $hasAccesstoAdmin)); ?>
        
        <div class="wrapper row-offcanvas row-offcanvas-left">
            
            <?php require_once(APPPATH.'views/include/sidebar.php');?>

            <!-- Right side column. Contains the navbar and content of the page -->
            <aside class="right-side">                
                <!-- Content Header (Page header) -->
                <section class="content-header">
                   <?php if($this->session->userdata('emr_viewing') == "opd_emr_viewing"){?>	
                   <h1>OPD Patient Information</h1>
                   <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">EMR sheet</a></li>
                        <li><a href="<?php echo base_url()?>app/emr/opd">Out-Patient Master</a></li>
                    </ol>
                    <?php }else if(!isset($hasAccesstoDoctor) || !$hasAccesstoDoctor){?>
                    <h1>OPD Patient Information</h1>
                   <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Patient Management</a></li>
                        <li><a href="<?php echo base_url()?>app/opd/index">Out-Patient Master</a></li>
                        <li class="active">OPD Patient Information</li>
                    </ol>
                    <?php }else{?>
                    <h1>OPD Patient Information</h1>
                   <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Doctor Module</a></li>
                        <li><a href="<?php echo base_url()?>app/doctor/opd">Out-Patient Master</a></li>
                        <li class="active">OPD Patient Information</li>
                    </ol>
                    <?php }?>
                </section>

                <!-- Main content -->
                <section class="content">
                 
        
                 
                 
               
                 <div class="row">
                 	
 					<div class="col-md-3 opd-patient-sidebar">
						<div class="box opd-patient-card">
							<div class="box-body">
								<?php
								if(!$patientInfo->picture){
									$picture = "avatar.png";
								}else{
									$picture = $patientInfo->picture;
								}
								?>
								<div class="media">
									<div class="media-left">
										<img src="<?php echo base_url();?>public/patient_picture/<?php echo $picture;?>" class="img-circle" alt="Patient" />
									</div>
									<div class="media-body">
										<h4 class="media-heading"><?php echo htmlspecialchars((string)$patientInfo->name); ?></h4>
										<div class="opd-patient-meta">
											Patient No: <strong><?php echo htmlspecialchars((string)$patientInfo->patient_no); ?></strong>
										</div>
									</div>
								</div>
							</div>
							<div class="box-footer clearfix" style="padding:12px;">
								<ul class="nav nav-pills nav-stacked opd-side-nav">
							 	<li><a href="<?php echo base_url()?>app/opd/view/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> General Information</a></li>
						
						 	<li><a href="<?php echo base_url()?>app/opd/diagnosis/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> Diagnosis</a></li>
						 	
						 	<li><a href="<?php echo base_url()?>app/opd/medication/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> Medication</a></li>
                                    <li><a href="<?php echo base_url()?>app/opd/complain/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> Complain</a></li>
                                    <li><a href="<?php echo base_url()?>app/opd/vitalSign/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> Vital Sign</a></li>
                                    <li class="active"><a href="<?php echo base_url()?>app/opd/patientHistory/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> Patient History</a></li>
						 	<li><a href="<?php echo base_url()?>app/opd/laboratory/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> Laboratory</a></li>
								<li><a href="<?php echo base_url()?>app/opd/procedures/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> Procedures</a></li>
                                    <li><a href="<?php echo base_url()?>app/opd/discharge_summary/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> Discharge Summary</a></li>
						 	<?php require_once(APPPATH.'views/app/opd/_detain_admit_menu.php'); ?>
                                    <!--<li><a href="<?php echo base_url()?>app/opd/billing/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>"> Admission Billing</a></li>-->
                                 </ul>
							</div>
						</div>
					</div>
					<div class="col-md-9"> 
                                <div class="nav-tabs-custom opd-tabs-custom">
								<ul class="nav nav-tabs">
									<li class="active"><a href="#tab_1" data-toggle="tab">Patient History</a></li>
									<li><a href="#tab_timeline" data-toggle="tab"><i class="fa fa-clock-o"></i> Clinical Timeline</a></li>
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

                                            <form method="post" action="<?php echo base_url()?>app/opd/save_patientHistory" id="clinicalHistoryForm">
                                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                            <input type="hidden" name="opd_no" value="<?php echo url_safe_id($getOPDPatient->IO_ID)?>">
                                            <input type="hidden" name="patient_no" value="<?php echo $getOPDPatient->patient_no?>">

                                            <!-- 1. History of Presenting Complaint -->
                                            <div class="panel panel-primary">
                                                <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#sec_hpc">
                                                    <h4 class="panel-title"><i class="fa fa-file-text-o"></i> 1. History of Presenting Complaint (HPC) <small class="pull-right"><i class="fa fa-chevron-down"></i></small></h4>
                                                </div>
                                                <div id="sec_hpc" class="collapse in">
                                                    <div class="panel-body">
                                                        <p class="text-muted" style="font-size:12px;margin-bottom:5px;"><i class="fa fa-info-circle"></i> Chronological description of symptoms — onset, duration, progression, severity, aggravating &amp; relieving factors.</p>
                                                        <textarea name="history_presenting_complaint" class="form-control" rows="5" placeholder="e.g. Patient is a 45-year-old male presenting with a 3-day history of productive cough, onset was sudden, associated with fever and chest pain on inspiration..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->history_presenting_complaint) ? $getOPDPatient->history_presenting_complaint : ''); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 2. Past Medical & Surgical History -->
                                            <div class="panel panel-info">
                                                <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#sec_past">
                                                    <h4 class="panel-title"><i class="fa fa-history"></i> 2. Past History <small class="pull-right"><i class="fa fa-chevron-down"></i></small></h4>
                                                </div>
                                                <div id="sec_past" class="collapse in">
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
                                                                <textarea name="drug_history" class="form-control" rows="4" placeholder="e.g. Metformin 500mg BD, Amlodipine 5mg OD, Salbutamol inhaler PRN..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->drug_history) ? $getOPDPatient->drug_history : ''); ?></textarea>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label><i class="fa fa-female"></i> Gynae / Obstetric History <small class="text-muted">(females)</small></label>
                                                                <p class="text-muted" style="font-size:11px;margin-bottom:4px;">LMP, Gravida, Para, Deliveries, Complications</p>
                                                                <textarea name="gynae_obstetric_history" class="form-control" rows="4" placeholder="LMP: 01/04/2026&#10;G3P2+1 (2 SVD, 1 miscarriage)&#10;No contraception currently..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->gynae_obstetric_history) ? $getOPDPatient->gynae_obstetric_history : ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 3. Allergies & Warnings -->
                                            <div class="panel panel-danger">
                                                <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#sec_allergy">
                                                    <h4 class="panel-title"><i class="fa fa-warning"></i> 3. Allergies &amp; Clinical Warnings <small class="pull-right"><i class="fa fa-chevron-down"></i></small></h4>
                                                </div>
                                                <div id="sec_allergy" class="collapse in">
                                                    <div class="panel-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <label class="text-danger"><i class="fa fa-exclamation-circle"></i> Allergies</label>
                                                                <p class="text-muted" style="font-size:11px;margin-bottom:4px;">Drug, food, environmental allergies &amp; reactions</p>
                                                                <textarea name="allergies" class="form-control" rows="3" placeholder="e.g. Penicillin — rash; Sulfa drugs — anaphylaxis; Shellfish..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->allergies) ? $getOPDPatient->allergies : ''); ?></textarea>
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
                                                <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#sec_fsh">
                                                    <h4 class="panel-title"><i class="fa fa-users"></i> 4. Family &amp; Social History <small class="pull-right"><i class="fa fa-chevron-down"></i></small></h4>
                                                </div>
                                                <div id="sec_fsh" class="collapse in">
                                                    <div class="panel-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <label><i class="fa fa-sitemap"></i> Family History (FHx)</label>
                                                                <p class="text-muted" style="font-size:11px;margin-bottom:4px;">Hereditary / familial conditions in blood relatives</p>
                                                                <textarea name="family_history" class="form-control" rows="3" placeholder="e.g. Father: HTN, DM; Mother: Breast Ca; Sibling: Sickle cell disease..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->family_history) ? $getOPDPatient->family_history : ''); ?></textarea>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label><i class="fa fa-coffee"></i> Social History (SHx)</label>
                                                                <p class="text-muted" style="font-size:11px;margin-bottom:4px;">Smoking, Alcohol, Occupation, Living conditions, Lifestyle</p>
                                                                <textarea name="social_history" class="form-control" rows="3" placeholder="e.g. Non-smoker, Social drinker (weekends), Teacher, Lives with wife and 2 children..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->social_history) ? $getOPDPatient->social_history : ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="row" style="margin-top:10px;">
                                                            <div class="col-md-12">
                                                                <label><i class="fa fa-user"></i> Personal History</label>
                                                                <p class="text-muted" style="font-size:11px;margin-bottom:4px;">Diet, Sleep, Exercise, Marital status, Religion, Hobbies</p>
                                                                <textarea name="personal_history" class="form-control" rows="2" placeholder="e.g. Married, 2 children, vegetarian, regular exercise, Muslim..." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->personal_history) ? $getOPDPatient->personal_history : ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 5. On Direct Questioning (ODQ) -->
                                            <div class="panel panel-warning">
                                                <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#sec_odq">
                                                    <h4 class="panel-title"><i class="fa fa-question-circle"></i> 5. On Direct Questioning (ODQ / Review of Systems) <small class="pull-right"><i class="fa fa-chevron-down"></i></small></h4>
                                                </div>
                                                <div id="sec_odq" class="collapse">
                                                    <div class="panel-body">
                                                        <p class="text-muted" style="font-size:12px;margin-bottom:8px;"><i class="fa fa-info-circle"></i> Systematic review of body systems. Document positive findings and relevant negatives.</p>
                                                        <textarea name="on_direct_questioning" class="form-control" rows="7" placeholder="CVS: No chest pain, palpitations, or orthopnea.&#10;RS: No cough, dyspnea, or hemoptysis.&#10;GIT: No nausea, vomiting. Appetite normal.&#10;GU: No dysuria or frequency.&#10;MSK: No joint pain or swelling.&#10;CNS: No headache, dizziness, or weakness.&#10;Skin: No rash or lesions." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->on_direct_questioning) ? $getOPDPatient->on_direct_questioning : ''); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 6. Physical Examination -->
                                            <div class="panel panel-default">
                                                <div class="panel-heading" style="cursor:pointer;background:#605ca8;color:#fff;" data-toggle="collapse" data-target="#sec_exam">
                                                    <h4 class="panel-title" style="color:#fff;"><i class="fa fa-stethoscope"></i> 6. Physical Examination <small class="pull-right"><i class="fa fa-chevron-down"></i></small></h4>
                                                </div>
                                                <div id="sec_exam" class="collapse">
                                                    <div class="panel-body">
                                                        <div class="form-group">
                                                            <label><i class="fa fa-eye"></i> General Examination</label>
                                                            <p class="text-muted" style="font-size:11px;margin-bottom:4px;">Consciousness, Build, Pallor, Jaundice, Cyanosis, Clubbing, Lymphadenopathy, Edema, Dehydration</p>
                                                            <textarea name="examination_general" class="form-control" rows="3" placeholder="e.g. Patient is conscious, alert, and oriented in time, place and person. Well-built and well-nourished. No pallor, jaundice, cyanosis, clubbing, lymphadenopathy, or pedal edema." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->examination_general) ? $getOPDPatient->examination_general : ''); ?></textarea>
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
                                                                    <textarea name="examination_respiratory" class="form-control" rows="3" placeholder="e.g. Chest bilaterally symmetrical. Normal vesicular breath sounds. No added sounds." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->examination_respiratory) ? $getOPDPatient->examination_respiratory : ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label><i class="fa fa-medkit"></i> Gastrointestinal / Abdomen (GIT)</label>
                                                                    <textarea name="examination_gastrointestinal" class="form-control" rows="3" placeholder="e.g. Abdomen soft, non-tender. No hepatosplenomegaly. Bowel sounds present and normal." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->examination_gastrointestinal) ? $getOPDPatient->examination_gastrointestinal : ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label><i class="fa fa-bolt"></i> Central Nervous System (CNS)</label>
                                                                    <textarea name="examination_neurological" class="form-control" rows="3" placeholder="e.g. GCS 15/15. Pupils equal and reactive to light. Cranial nerves intact. No focal deficit." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->examination_neurological) ? $getOPDPatient->examination_neurological : ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label><i class="fa fa-male"></i> Musculoskeletal System (MSK)</label>
                                                                    <textarea name="examination_musculoskeletal" class="form-control" rows="2" placeholder="e.g. Full range of motion all joints. No deformity, swelling or tenderness." <?php echo $canEditClinical ? '' : 'readonly'; ?>><?php echo htmlspecialchars(isset($getOPDPatient->examination_musculoskeletal) ? $getOPDPatient->examination_musculoskeletal : ''); ?></textarea>
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
                                                <div class="col-md-12 opd-history-actions">
                                                    <?php if($this->session->userdata('emr_viewing') == "" && $canEditClinical && $getOPDPatient->nStatus == "Pending"): ?>
                                                    <button type="submit" class="btn btn-primary" onclick="return confirm('Save clinical history?');"><i class="fa fa-save"></i> Save Clinical History</button>
                                                    <?php endif; ?>
                                                    <a href="<?php echo base_url()?>app/ipd_print/print_patient_history/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                                                    <a href="<?php echo base_url()?>app/ipd_print/pdf_patient_history/<?php echo url_safe_id($getOPDPatient->IO_ID);?>/<?php echo $getOPDPatient->patient_no;?>" class="btn btn-success" target="_blank"><i class="fa fa-file-pdf-o"></i> PDF</a>
                                                </div>
                                            </div>

                                            </form>
                                        </div>
                                        <div class="tab-pane" id="tab_timeline">
                                            <div id="clinical_timeline_container">
                                                <div class="text-center" style="padding:30px;"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading clinical timeline...</div>
                                            </div>
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
		<?php require_once(APPPATH.'views/app/opd/_detain_admit_modals.php'); ?>
        	
        	<!-- BDAY -->
        <script src="<?php echo base_url();?>public/datepicker/js/bootstrap-datepicker.js"></script>
        <script type="text/javascript">
            $(document).ready(function () {
                $('#cFrom').datepicker({ format: "yyyy-mm-dd" });
                $('#cTo').datepicker({ format: "yyyy-mm-dd" });
            });
        </script>
        <!-- END BDAY -->

        <!-- Clinical Timeline -->
        <style>
        .timeline-wrapper { position:relative; padding:10px 0 10px 40px; }
        .timeline-wrapper::before { content:''; position:absolute; left:18px; top:0; bottom:0; width:3px; background:#ddd; }
        .tl-item { position:relative; margin-bottom:20px; }
        .tl-dot { position:absolute; left:-30px; top:4px; width:16px; height:16px; border-radius:50%; border:3px solid #3c8dbc; background:#fff; z-index:1; }
        .tl-dot.tl-diagnosis { border-color:#f39c12; }
        .tl-dot.tl-medication { border-color:#00a65a; }
        .tl-dot.tl-lab { border-color:#dd4b39; }
        .tl-dot.tl-scan { border-color:#605ca8; }
        .tl-dot.tl-visit { border-color:#3c8dbc; }
        .tl-card { background:#fff; border:1px solid #e8e8e8; border-radius:4px; padding:12px 15px; box-shadow:0 1px 3px rgba(0,0,0,.06); }
        .tl-card h5 { margin:0 0 6px; font-weight:600; }
        .tl-card .tl-meta { font-size:12px; color:#999; margin-bottom:4px; }
        .tl-card .tl-body { font-size:13px; color:#555; }
        .tl-type-badge { display:inline-block; font-size:10px; padding:2px 8px; border-radius:10px; color:#fff; margin-right:6px; }
        .tl-type-badge.bg-visit { background:#3c8dbc; }
        .tl-type-badge.bg-diagnosis { background:#f39c12; }
        .tl-type-badge.bg-medication { background:#00a65a; }
        .tl-type-badge.bg-lab { background:#dd4b39; }
        .tl-type-badge.bg-scan { background:#605ca8; }
        .tl-type-badge.bg-note { background:#777; }
        </style>
        <script type="text/javascript">
        $(document).ready(function(){
            var patientNo = '<?php echo addslashes((string)$getOPDPatient->patient_no); ?>';
            var timelineLoaded = false;
            $('a[href="#tab_timeline"]').on('shown.bs.tab click', function(){
                if (timelineLoaded) return;
                timelineLoaded = true;
                $('#clinical_timeline_container').html('<div class="text-center" style="padding:30px;"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading clinical timeline...</div>');
                $.ajax({
                    url: '<?php echo base_url(); ?>app/medical_data/patient_timeline',
                    type: 'GET',
                    dataType: 'json',
                    data: { patient_no: patientNo },
                    success: function(data){
                        if (!data || !Array.isArray(data) || data.length === 0) {
                            $('#clinical_timeline_container').html(
                                '<div class="alert alert-info">' +
                                '<i class="fa fa-user-plus fa-2x pull-left" style="margin-right:15px;"></i>' +
                                '<h4 style="margin-top:0;">First-Time Patient</h4>' +
                                '<p>This patient has no previous clinical records. This appears to be their first visit.</p>' +
                                '</div>'
                            );
                            return;
                        }
                        var html = '<div class="timeline-wrapper">';
                        for (var i = 0; i < data.length; i++) {
                            var e = data[i];
                            var t = (e.type || 'visit').toLowerCase();
                            var dotCls = 'tl-visit';
                            var badgeCls = 'bg-visit';
                            var icon = e.icon || 'fa-stethoscope';
                            if (t === 'diagnosis') { dotCls='tl-diagnosis'; badgeCls='bg-diagnosis'; icon=e.icon||'fa-stethoscope'; }
                            else if (t === 'medication') { dotCls='tl-medication'; badgeCls='bg-medication'; icon=e.icon||'fa-medkit'; }
                            else if (t === 'lab_result' || t === 'lab' || t === 'laboratory') { dotCls='tl-lab'; badgeCls='bg-lab'; icon=e.icon||'fa-flask'; }
                            else if (t === 'vitals') { dotCls='tl-visit'; badgeCls='bg-visit'; icon=e.icon||'fa-heartbeat'; }
                            else if (t === 'scan' || t === 'imaging') { dotCls='tl-scan'; badgeCls='bg-scan'; icon='fa-video-camera'; }
                            else if (t === 'note') { badgeCls='bg-note'; icon='fa-file-text-o'; }
                            var eventDate = e.event_date || e.date || e.created_at || '';
                            var detail = e.detail || e.details || '';
                            html += '<div class="tl-item">';
                            html += '<div class="tl-dot ' + dotCls + '"></div>';
                            html += '<div class="tl-card">';
                            html += '<div class="tl-meta"><span class="tl-type-badge ' + badgeCls + '"><i class="fa ' + icon + '"></i> ' + tlEsc(t.replace('_',' ')) + '</span> ' + tlEsc(eventDate) + (e.visit_id ? ' <small class="text-muted">(Visit: '+tlEsc(e.visit_id)+')</small>' : '') + '</div>';
                            html += '<h5>' + tlEsc(e.title || e.type || 'Record') + '</h5>';
                            if (detail) html += '<div class="tl-body">' + tlEsc(detail) + '</div>';
                            if (e.extra) html += '<div class="tl-body text-muted"><small>' + tlEsc(e.extra) + '</small></div>';
                            html += '</div></div>';
                        }
                        html += '</div>';
                        $('#clinical_timeline_container').html(html);
                    },
                    error: function(xhr, status, error){
                        console.log('Timeline error:', status, error, xhr.responseText);
                        $('#clinical_timeline_container').html(
                            '<div class="alert alert-warning">' +
                            '<i class="fa fa-exclamation-triangle"></i> Could not load timeline. ' +
                            '<small class="text-muted">(Error: ' + (error || status || 'Unknown') + ')</small>' +
                            '</div>'
                        );
                    }
                });
            });
            function tlEsc(s){ if(!s) return ''; var d=document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }
        });
        </script>
        
    </body>
</html>