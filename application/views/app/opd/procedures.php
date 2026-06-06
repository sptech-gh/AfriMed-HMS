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

    <style>
        .opd-side-nav { margin:0; }
        .opd-side-nav > li > a { padding:10px 12px; border-radius:4px; font-size:13px; }
        .opd-side-nav > li.active > a,
        .opd-side-nav > li.active > a:hover { background:#3c8dbc; color:#fff; }
        .opd-side-nav > li > a:hover { background:#f5f7fa; }
    </style>
</head>

<body class="skin-blue">
<?php require_once(APPPATH . 'views/include/header.php'); ?>
<?php $canEditClinical = ((isset($userInfo) && isset($userInfo->module) && strtolower((string)$userInfo->module) === 'doctor') || (isset($hasAccesstoAdmin) && $hasAccesstoAdmin)); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>

    <aside class="right-side">
        <section class="content-header">
            <h1>OPD Procedures</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li><a href="#">Doctor Module</a></li>
                <li><a href="<?php echo base_url() ?>app/doctor/opd">Out-Patient Master</a></li>
                <li class="active">Procedures</li>
            </ol>
        </section>

        <section class="content">
            <?php echo isset($message) ? $message : ''; ?>

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
                                            <tr><td><u>Patient No.</u></td></tr>
                                            <tr><td><?php echo $patientInfo->patient_no ?></td></tr>
                                            <tr><td><u>Patient Name</u></td></tr>
                                            <tr><td><?php echo $patientInfo->name ?></td></tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="box-footer clearfix">
                            <div style="margin-top: 15px;">
                                <ul class="nav nav-pills nav-stacked opd-side-nav">
                                    <li><a href="<?php echo base_url() ?>app/opd/view/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> General Information</a></li>
                                    <li><a href="<?php echo base_url() ?>app/opd/diagnosis/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Diagnosis</a></li>
                                    <li><a href="<?php echo base_url() ?>app/opd/medication/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Medication</a></li>
                                    <li><a href="<?php echo base_url() ?>app/opd/complain/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Complain</a></li>
                                    <li><a href="<?php echo base_url() ?>app/opd/vitalSign/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Vital Sign</a></li>
                                    <li><a href="<?php echo base_url() ?>app/opd/patientHistory/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Patient History</a></li>
                                    <li><a href="<?php echo base_url() ?>app/opd/laboratory/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Laboratory</a></li>
                                    <li class="active"><a href="<?php echo base_url() ?>app/opd/procedures/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Procedures</a></li>
                                    <li><a href="<?php echo base_url() ?>app/opd/discharge_summary/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Discharge Summary</a></li>
                                    <?php require_once(APPPATH.'views/app/opd/_detain_admit_menu.php'); ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-9">
                    <div class="box">
                        <div class="box-header">
                            <h3 class="box-title">Procedure Requests</h3>
                        </div>
                        <div class="box-body">
                            <?php if (!$canEditClinical) { ?>
                                <div class="alert alert-danger"><i class="fa fa-ban"></i> You do not have permission to order procedures.</div>
                            <?php } ?>

                            <?php if ($canEditClinical) { ?>
                                <form method="post" action="<?php echo base_url(); ?>app/opd/save_procedure" onSubmit="return confirm('Are you sure you want to save?');">
                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                    <input type="hidden" name="opd_no" value="<?php echo url_safe_id($getOPDPatient->IO_ID); ?>">
                                    <input type="hidden" name="patient_no" value="<?php echo $getOPDPatient->patient_no; ?>">

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Category</label>
                                                <select class="form-control" name="category_id">
                                                    <?php if (!empty($procedure_categories)) { foreach ($procedure_categories as $c) { ?>
                                                        <option value="<?php echo (int)$c->group_id; ?>" <?php echo ((int)$procedure_category_id === (int)$c->group_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c->group_name); ?></option>
                                                    <?php } } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Procedure</label>
                                                <select class="form-control" name="procedure_id" required>
                                                    <option value="">-- Select procedure --</option>
                                                    <?php if (!empty($procedure_items)) { foreach ($procedure_items as $p) { ?>
                                                        <option value="<?php echo (int)$p->particular_id; ?>"><?php echo htmlspecialchars($p->particular_name); ?></option>
                                                    <?php } } ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Qty</label>
                                                <input type="number" step="0.01" min="1" class="form-control" name="qty" value="1">
                                            </div>
                                        </div>
                                        <div class="col-md-9">
                                            <div class="form-group">
                                                <label>Notes</label>
                                                <input type="text" class="form-control" name="notes" placeholder="Clinical notes / remarks">
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> Add Procedure</button>
                                </form>
                                <hr>
                            <?php } ?>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Procedure</th>
                                            <th>Qty</th>
                                            <th>Status</th>
                                            <th>Requested By</th>
                                            <th>Notes</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($procedure_requests)) { foreach ($procedure_requests as $r) { ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(isset($r->requested_at) ? $r->requested_at : ''); ?></td>
                                                <td><?php echo htmlspecialchars(isset($r->particular_name) && $r->particular_name !== '' ? $r->particular_name : (isset($r->procedure_name) ? $r->procedure_name : '')); ?></td>
                                                <td><?php echo htmlspecialchars(isset($r->qty) ? $r->qty : '1'); ?></td>
                                                <td><?php echo htmlspecialchars(isset($r->status) ? $r->status : ''); ?></td>
                                                <td><?php echo htmlspecialchars(isset($r->requested_by_name) ? $r->requested_by_name : (isset($r->requested_by) ? $r->requested_by : '')); ?></td>
                                                <td><?php echo htmlspecialchars(isset($r->notes) ? $r->notes : ''); ?></td>
                                                <td>
                                                    <?php if ($canEditClinical) { ?>
                                                        <form method="post" action="<?php echo base_url(); ?>app/opd/delete_procedure/<?php echo (int)$r->request_id; ?>/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>" style="display:inline;" onSubmit="return confirm('Remove this procedure request?');">
                                                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                            <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i> Remove</button>
                                                        </form>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } } else { ?>
                                            <tr><td colspan="7" class="text-center text-muted">No procedure requests yet.</td></tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </section>
    </aside>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js" type="text/javascript"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js" type="text/javascript"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
