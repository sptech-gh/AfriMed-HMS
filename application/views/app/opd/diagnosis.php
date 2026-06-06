<!DOCTYPE html>
<html>

<head>

        <meta charset="UTF-8">
        <title>Hebrew Medical Center</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">



        <link href="<?php echo base_url() ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />

        <!----------BOOTSTRAP DATEPICKER----------------------------->
        <link rel="stylesheet" href="<?php echo base_url(); ?>public/datepicker/css/datepicker.css">
        <!---------------------------------------------------------->

        <!-- <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" /> -->

        <!-- jQuery UI CSS -->
        <link rel="stylesheet" href="<?php echo base_url(); ?>public/css/jQueryUI/jquery-ui-1.10.3.custom.min.css">

        <style>
            .ui-autocomplete {
                position: absolute;
                cursor: default;
                z-index: 999999999 !important;
            }
        </style>


        <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
        <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
        <![endif]-->

    </head>

<body class="skin-blue">
    <!-- header logo: style can be found in header.less -->
    <?php require_once(APPPATH . 'views/include/header.php'); ?>
	<?php $canEditClinical = ((isset($userInfo) && isset($userInfo->module) && strtolower((string)$userInfo->module) === 'doctor') || (isset($hasAccesstoAdmin) && $hasAccesstoAdmin)); ?>

    <div class="wrapper row-offcanvas row-offcanvas-left">

        <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>

        <!-- Right side column. Contains the navbar and content of the page -->
        <aside class="right-side">
            <!-- Content Header (Page header) -->
            <section class="content-header">
                <?php if ($this->session->userdata('emr_viewing') == "opd_emr_viewing") { ?>
                    <h1>OPD Patient Information</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">EMR sheet</a></li>
                        <li><a href="<?php echo base_url() ?>app/emr/opd">Out-Patient Master</a></li>
                    </ol>
                <?php } else if (!isset($hasAccesstoDoctor) || !$hasAccesstoDoctor) { ?>
                    <h1>OPD Patient Information</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Patient Management</a></li>
                        <li><a href="<?php echo base_url() ?>app/opd/index">Out-Patient Master</a></li>
                        <li class="active">OPD Patient Information</li>
                    </ol>
                <?php } else { ?>
                    <h1>OPD Patient Information</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Doctor Module</a></li>
                        <li><a href="<?php echo base_url() ?>app/doctor/opd">Out-Patient Master</a></li>
                        <li class="active">OPD Patient Information</li>
                    </ol>
                <?php } ?>
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
                                            if (!$patientInfo->picture) {
                                                $picture = "avatar.png";
                                            } else {
                                                $picture = $patientInfo->picture;
                                            }
                                            ?>
                                            <img src="<?php echo base_url(); ?>public/patient_picture/<?php echo $picture; ?>" class="img-rounded" width="86" height="81">
                                        </td>
                                        <td>
                                            <table width="100%">
                                                <tr>
                                                    <td><u>Patient No.</u></td>
                                                </tr>
                                                <tr>
                                                    <td><?php echo $patientInfo->patient_no ?></td>
                                                </tr>
                                                <tr>
                                                    <td><u>Patient Name</u></td>
                                                </tr>
                                                <tr>
                                                    <td><?php echo $patientInfo->name ?></td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="box-footer clearfix">
                                <div style="margin-top: 15px;">
                                    <ul class="nav nav-pills nav-stacked">
                                        <li><a href="<?php echo base_url() ?>app/opd/view/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> General Information</a></li>

                                        <li class="active"><a href="<?php echo base_url() ?>app/opd/diagnosis/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Diagnosis</a></li>

                                        <li><a href="<?php echo base_url() ?>app/opd/medication/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Medication</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/complain/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Complain</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/vitalSign/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Vital Sign</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/patientHistory/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Patient History</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/laboratory/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Laboratory</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/procedures/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Procedures</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/discharge_summary/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Discharge Summary</a></li>
                                        <?php require_once(APPPATH.'views/app/opd/_detain_admit_menu.php'); ?>
                                        <!--<li><a href="<?php echo base_url() ?>app/opd/billing/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Admission Billing</a></li>-->
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-9">
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Diagnosis</a></li>

                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
											<?php if (!$canEditClinical) { ?>
												<div class="alert alert-info">Read-only — Doctor access only</div>
											<?php } ?>
                                    <?php if ($this->session->userdata('emr_viewing') == "") { ?>
                                        <?php if ($canEditClinical && $getOPDPatient->nStatus == "Pending") { ?>
                                            <a href="#" class="btn btn-primary btn-lg" data-toggle="modal" data-target="#multiDiagnosisModal"><i class="fa fa-plus-circle"></i> Add Diagnoses</a>
                                        <?php } ?>
                                    <?php } ?>
                                    <a href="<?php echo base_url() ?>app/ipd_print/print_diagnosis/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                                    <a href="<?php echo base_url() ?>app/ipd_print/pdf_diagnosis/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>" class="btn btn-success" target="_blank"><i class="fa fa-print"></i> PDF</a>

                                    <table class="table table-hover table-striped table-condensed">
                                        <thead>
                                            <tr>
                                                <th style="width:90px;">ICD-10</th>
                                                <th>Diagnosis</th>
                                                <th style="width:140px;">Category</th>
                                                <th>Free-text / Custom</th>
                                                <th>Remarks</th>
                                                <th style="width:60px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($patientDiagnosis as $diagnosisList2) { ?>
                                                <tr>
                                                    <td>
                                                        <?php if (!empty($diagnosisList2->icd_code)): ?>
                                                            <span class="label label-primary" style="font-size:12px;letter-spacing:0.5px;"><?php echo htmlspecialchars($diagnosisList2->icd_code); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars((string)$diagnosisList2->diagnosis_name); ?></td>
                                                    <td><small class="text-muted"><?php echo htmlspecialchars((string)$diagnosisList2->category); ?></small></td>
                                                    <td><?php echo htmlspecialchars((string)$diagnosisList2->diagnosis_text); ?></td>
                                                    <td><?php echo htmlspecialchars((string)$diagnosisList2->remarks); ?></td>
                                                    <td>
                                                        <?php if ($this->session->userdata('emr_viewing') == "") { ?>
                                                            <?php if ($canEditClinical && $getOPDPatient->nStatus == "Pending") { ?>
                                                                <form method="post" action="<?php echo base_url() ?>app/opd/delete_diagnos/<?php echo $diagnosisList2->iop_diag_id ?>/<?php echo url_safe_id($getOPDPatient->IO_ID) ?>/<?php echo $getOPDPatient->patient_no ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove?');">
                                                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                                    <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></button>
                                                                </form>
                                                        <?php }
                                                        } ?>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>

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


    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <?php require_once(APPPATH.'views/app/opd/_detain_admit_modals.php'); ?>

    <!-- BDAY -->
    <script src="<?php echo base_url(); ?>public/datepicker/js/bootstrap-datepicker.js"></script>
    <script type="text/javascript">
        // When the document is ready
        $(document).ready(function() {

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

    <!-- Modal -->
    <form method="post" action="<?php echo base_url() ?>app/opd/save_diagnosis" onSubmit="return confirm('Are you sure you want to save?');">
        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
        <input type="hidden" name="opd_no" value="<?php echo $getOPDPatient->IO_ID ?>">
        <input type="hidden" name="patient_no" value="<?php echo $getOPDPatient->patient_no ?>">
        <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <h4 class="modal-title" id="myModalLabel">Diagnosis</h4>
                    </div>
                    <div class="modal-body">
                        <table class="table table-condensed" style="margin-bottom:0;">
                            <tbody>
                                <tr>
                                    <td style="width:130px;vertical-align:top;padding-top:10px;"><strong>Search ICD-10</strong></td>
                                    <td>
                                        <input type="text" id="diagnosis_search" placeholder="Type code or name: e.g. B54, Malaria, Hypertension, I10..." class="form-control input-sm" style="width:100%;" autocomplete="off">
                                        <small class="text-muted"><i class="fa fa-info-circle"></i> Search by ICD-10 code, diagnosis name, or category. Type freely if not found.</small>
                                        <div id="diagnosis_search_info" style="display:none; margin-top:4px;"></div>
                                        <input type="hidden" name="diagnosis" id="diagnosis_id_hidden" value="">
                                        <div id="icd_code_badge" style="margin-top:5px;display:none;">
                                            <span class="label label-primary" id="selected_icd_display" style="font-size:13px;padding:4px 8px;"></span>
                                            <span id="selected_diag_name" style="margin-left:6px;font-weight:bold;"></span>
                                            <span id="selected_diag_cat" class="text-muted" style="margin-left:6px;font-size:11px;"></span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align:top;padding-top:10px;">Category Filter</td>
                                    <td>
                                        <select id="diag_category_filter" class="form-control input-sm" style="width:100%;" onchange="filterDiagnosisByCategory(this.value)">
                                            <option value="">— All Categories —</option>
                                            <?php
                                            $cats = array();
                                            foreach ($diagnosisList as $d2) {
                                                $cat = isset($d2->category) ? $d2->category : '';
                                                if ($cat !== '' && !in_array($cat, $cats)) $cats[] = $cat;
                                            }
                                            sort($cats);
                                            foreach ($cats as $cat) { ?>
                                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align:top;padding-top:10px;">Select Diagnosis</td>
                                    <td>
                                        <select name="diagnosis_select" id="diagnosis_select" style="width:100%;" class="form-control input-sm" onchange="onDiagnosisSelect(this.value)">
                                            <option value="">— Select from list —</option>
                                            <option value="others">✏ Others (type custom free-text)</option>
                                            <?php foreach ($diagnosisList as $d2) {
                                                $icd  = isset($d2->icd_code)  ? $d2->icd_code  : '';
                                                $cat  = isset($d2->category)  ? $d2->category  : '';
                                                $label = ($icd ? '[' . $icd . '] ' : '') . $d2->diagnosis_name;
                                            ?>
                                                <option value="<?php echo $d2->diagnosis_id; ?>"
                                                    data-icd="<?php echo htmlspecialchars($icd); ?>"
                                                    data-cat="<?php echo htmlspecialchars($cat); ?>"
                                                    data-cat-lower="<?php echo htmlspecialchars(strtolower($cat)); ?>">
                                                    <?php echo htmlspecialchars($label); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr id="diagnosis_txt" style="display:none;">
                                    <td style="vertical-align:top;padding-top:10px;">Custom Diagnosis</td>
                                    <td><input id="autouser" name="diagnosis_text" placeholder="Type custom diagnosis here..." class="form-control input-sm" style="width:100%;" /></td>
                                </tr>
                                <tr>
                                    <td style="vertical-align:top;padding-top:10px;">Remarks</td>
                                    <td><textarea name="remarks" placeholder="Remarks" class="form-control input-sm" style="width:100%;" rows="2"></textarea></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        <button name="btnSubmit" class="btn btn-primary" id="btnSubmit" type="submit" style="font-size:12px;">Save</button>
                    </div>

                </div>
                <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
        </div>
    </form>
    <!-- /.modal -->


<!-- Multi-Entry Diagnosis Modal -->
<div class="modal fade multi-entry-modal" id="multiDiagnosisModal" tabindex="-1" role="dialog" aria-labelledby="multiDiagnosisModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border-radius: 4px 4px 0 0;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff;opacity:0.8;"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="multiDiagnosisModalLabel"><i class="fa fa-stethoscope"></i> Add Multiple Diagnoses</h4>
            </div>
            <div class="modal-body" style="background: #f5f7fa;">
                <?php if (function_exists('form_hidden') && isset($this->security)): ?>
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                <?php endif; ?>
                <div class="multi-entry-summary">
                    <div>
                        <span class="count" id="entry-count">0</span>
                        <span class="label-text">diagnosis(es) to add</span>
                    </div>
                    <button type="button" class="btn btn-add-entry" id="btn-add-diagnosis">
                        <i class="fa fa-plus"></i> Add Diagnosis
                    </button>
                </div>
                <div id="multi-entry-container">
                    <div class="empty-state">
                        <i class="fa fa-plus-circle"></i>
                        <p>Click "Add Diagnosis" button to add items</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background: #f5f7fa; border-top: 1px solid #e0e0e0;">
                <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
                <button type="button" class="btn btn-primary btn-save-all" id="btn-save-all" disabled>
                    <i class="fa fa-check-circle"></i> Save All Diagnoses
                </button>
            </div>
        </div>
    </div>
</div>

</body>


<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
<!-- jQuery UI -->
<script src="<?php echo base_url(); ?>public/js/jquery-ui-1.10.3.min.js"></script>


<!-- Smart Medical Autocomplete -->
<script src="<?php echo base_url(); ?>public/js/smart-medical-autocomplete.js"></script>
<script>
    function otherOptions(val) {
        if (val == 'others') {
            $('#diagnosis_txt').show();
        } else {
            $('#diagnosis_txt').hide();
        }
    }

    function onDiagnosisSelect(val) {
        if (val == 'others') {
            $('#diagnosis_txt').show();
            $('#diagnosis_id_hidden').val('');
            $('#icd_code_badge').hide();
        } else if (val !== '') {
            $('#diagnosis_txt').hide();
            $('#diagnosis_id_hidden').val(val);
            var $opt = $('#diagnosis_select option:selected');
            var icd  = $opt.data('icd')  || '';
            var cat  = $opt.data('cat')  || '';
            var name = $opt.text().replace(/^\[[A-Z0-9.]+\] /, '');
            $('#diagnosis_search').val($opt.text());
            $('#diagnosis_search_info').hide();
            if (icd) {
                $('#selected_icd_display').text(icd);
                $('#selected_diag_name').text(name);
                $('#selected_diag_cat').text(cat ? '(' + cat + ')' : '');
                $('#icd_code_badge').show();
            } else {
                $('#icd_code_badge').hide();
            }
        } else {
            $('#diagnosis_txt').hide();
            $('#diagnosis_id_hidden').val('');
            $('#icd_code_badge').hide();
        }
    }

    function filterDiagnosisByCategory(cat) {
        var catLower = cat.toLowerCase();
        $('#diagnosis_select option').each(function() {
            var v = $(this).val();
            if (v === '' || v === 'others') { $(this).show(); return; }
            if (cat === '') { $(this).show(); return; }
            if ($(this).data('cat-lower') === catLower) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        $('#diagnosis_select').val('');
        $('#diagnosis_id_hidden').val('');
        $('#icd_code_badge').hide();
    }
</script>

<script type='text/javascript'>
    $(document).ready(function() {
        SmartMedical.init('<?= base_url() ?>');
        SmartMedical.injectStyles();

        // Smart diagnosis search
        SmartMedical.initDiagnosisAutocomplete(
            '#diagnosis_search',
            '#diagnosis_id_hidden',
            '#diagnosis_search_info',
            {
                minLength: 2,
                allowCustom: true,
                onSelect: function(item) {
                    if (item.isCustom || item.id === 0) {
                        $('#diagnosis_select').val('others');
                        otherOptions('others');
                        $('#autouser').val(item.value);
                        $('#icd_code_badge').hide();
                    } else {
                        // Try to select in dropdown too
                        var $opt = $('#diagnosis_select option[value="' + item.id + '"]');
                        if ($opt.length) {
                            $('#diagnosis_select').val(item.id);
                        }
                        $('#diagnosis_txt').hide();
                        // Show ICD badge
                        var icd = item.icd_code || ($opt.length ? $opt.data('icd') : '');
                        var cat = item.category || ($opt.length ? $opt.data('cat') : '');
                        if (icd) {
                            $('#selected_icd_display').text(icd);
                            $('#selected_diag_name').text(item.label || item.value);
                            $('#selected_diag_cat').text(cat ? '(' + cat + ')' : '');
                            $('#icd_code_badge').show();
                        } else {
                            $('#icd_code_badge').hide();
                        }
                    }
                }
            }
        );

        // Legacy custom text autocomplete
        $("#autouser").autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: "<?= base_url() ?>app/medical_data/search_diagnoses",
                    type: 'get',
                    dataType: "json",
                    data: { term: request.term },
                    success: function(data) {
                        response($.map(data, function(item) {
                            var desc = item.label;
                            if (item.icd_code) desc += ' [' + item.icd_code + ']';
                            return { label: desc, value: item.label, id: item.id };
                        }));
                    }
                });
            },
            minLength: 2,
            select: function(event, ui) {
                $('#autouser').val(ui.item.value);
                return false;
            }
        });
    });
</script>

<!-- Multi-Entry Manager -->
<script src="<?php echo base_url(); ?>public/js/multi-entry-manager.js"></script>
<script>
$(document).ready(function() {
    // Diagnosis list for autocomplete
    var diagnosisList = <?php echo json_encode(array_map(function($d) {
        return array(
            'diagnosis_id' => $d->diagnosis_id,
            'diagnosis_name' => $d->diagnosis_name,
            'icd_code' => isset($d->icd_code) ? $d->icd_code : '',
            'category' => isset($d->category) ? $d->category : ''
        );
    }, $diagnosisList)); ?>;

    var opdNo = '<?php echo url_safe_id($getOPDPatient->IO_ID); ?>';
    var patientNo = '<?php echo $getOPDPatient->patient_no; ?>';

    // Initialize Multi-Entry Manager
    var diagManager = new MultiEntryManager();
    diagManager.init('<?php echo base_url(); ?>', {
        module: 'diagnosis',
        containerId: 'multi-entry-container',
        onSaveSuccess: function(response) {
            if (response.redirect) {
                window.location.href = response.redirect;
            }
        }
    });

    // Add diagnosis button
    $('#btn-add-diagnosis').on('click', function() {
        diagManager.addEntry('diagnosis', { diagnosisList: diagnosisList });
    });

    // Save all button
    $('#btn-save-all').on('click', function() {
        diagManager.saveAll('diagnosis', opdNo, patientNo);
    });

    // Reset modal on close
    $('#multiDiagnosisModal').on('hidden.bs.modal', function() {
        diagManager.resetModal();
    });

    // Auto-add first row when modal opens
    $('#multiDiagnosisModal').on('shown.bs.modal', function() {
        if ($('#multi-entry-container .multi-entry-row').length === 0) {
            diagManager.addEntry('diagnosis', { diagnosisList: diagnosisList });
        }
    });
});
</script>

</html>