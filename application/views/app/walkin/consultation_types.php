<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Walk-In Consultation Types</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/font-awesome.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/AdminLTE.css" rel="stylesheet">
    <style>
        .page-card{background:#fff;border:1px solid #e9ecef;border-radius:10px;box-shadow:0 2px 14px rgba(0,0,0,.06);}
        .page-card .box-header{border-bottom:1px solid #eef2f7;}
        .form-control{border-radius:6px;height:38px;font-size:13px;}
        .label-muted{color:#6c757d;font-size:12px;font-weight:600;}
        .table td,.table th{vertical-align:middle;}
        .badge-inactive{background:#f3f4f6;color:#374151;}
        .badge-active{background:#dcfce7;color:#166534;}
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php'); ?>

    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-user-md"></i> Walk-In Consultation Types</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li><a href="<?php echo base_url()?>app/walkin">Walk-In</a></li>
                <li class="active">Consultation Types</li>
            </ol>
        </section>

        <section class="content">
            <?php if(isset($message)) echo $message; ?>

            <div class="row">
                <div class="col-md-4">
                    <div class="box box-primary page-card">
                        <div class="box-header with-border">
                            <h3 class="box-title">
                                <i class="fa fa-plus-circle"></i>
                                <?php echo (!empty($edit) && isset($edit->id)) ? 'Edit Type' : 'Add Type'; ?>
                            </h3>
                        </div>
                        <div class="box-body">
                            <form method="post" action="<?php echo base_url()?>app/walkin/save_consultation_type">
                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                <input type="hidden" name="id" value="<?php echo (!empty($edit) && isset($edit->id)) ? (int)$edit->id : 0; ?>">

                                <div class="form-group">
                                    <label class="label-muted">Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="<?php echo (!empty($edit) && isset($edit->name)) ? htmlspecialchars($edit->name) : ''; ?>" placeholder="e.g. General Consultation" required>
                                </div>

                                <div class="form-group">
                                    <label class="label-muted">Cash Price (GHS)</label>
                                    <input type="number" name="price_cash" class="form-control" step="0.01" min="0" value="<?php echo (!empty($edit) && isset($edit->price_cash)) ? htmlspecialchars((string)$edit->price_cash) : '0.00'; ?>">
                                </div>

                                <div class="form-group">
                                    <label class="label-muted">NHIS Price (GHS)</label>
                                    <input type="number" name="price_nhis" class="form-control" step="0.01" min="0" value="<?php echo (!empty($edit) && isset($edit->price_nhis)) ? htmlspecialchars((string)$edit->price_nhis) : '0.00'; ?>">
                                    <span class="help-block" style="font-size:11px;">If NHIS consultation is free, set this to 0.00</span>
                                </div>

                                <div class="checkbox" style="margin-top:6px;">
                                    <label>
                                        <input type="checkbox" name="InActive" value="1" <?php echo (!empty($edit) && isset($edit->InActive) && (int)$edit->InActive === 1) ? 'checked' : ''; ?>>
                                        Disable (Inactive)
                                    </label>
                                </div>

                                <div class="form-group" style="margin-top:12px;">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fa fa-save"></i> Save
                                    </button>
                                </div>

                                <?php if (!empty($edit) && isset($edit->id)) { ?>
                                <div class="form-group" style="margin-bottom:0;">
                                    <a class="btn btn-default btn-block" href="<?php echo base_url()?>app/walkin/consultation_types">
                                        <i class="fa fa-times"></i> Cancel Edit
                                    </a>
                                </div>
                                <?php } ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="box box-info page-card">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-list"></i> Existing Consultation Types</h3>
                        </div>
                        <div class="box-body no-padding">
                            <table class="table table-hover table-condensed" style="margin:0;">
                                <thead>
                                    <tr style="background:#f8f9fa;">
                                        <th>Name</th>
                                        <th style="width:120px;">Cash (GHS)</th>
                                        <th style="width:120px;">NHIS (GHS)</th>
                                        <th style="width:90px;">Status</th>
                                        <th style="width:90px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($rows)) { ?>
                                    <tr><td colspan="5" class="text-center text-muted" style="padding:22px;">No consultation types found.</td></tr>
                                <?php } else { ?>
                                    <?php foreach ((array)$rows as $r) { ?>
                                        <tr class="<?php echo (isset($r->InActive) && (int)$r->InActive === 1) ? 'text-muted' : ''; ?>">
                                            <td><strong><?php echo htmlspecialchars(isset($r->name) ? $r->name : ''); ?></strong></td>
                                            <td><?php echo number_format((float)(isset($r->price_cash) ? $r->price_cash : 0), 2); ?></td>
                                            <td><?php echo number_format((float)(isset($r->price_nhis) ? $r->price_nhis : 0), 2); ?></td>
                                            <td>
                                                <?php if (isset($r->InActive) && (int)$r->InActive === 1) { ?>
                                                    <span class="label badge-inactive">Inactive</span>
                                                <?php } else { ?>
                                                    <span class="label badge-active">Active</span>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <a class="btn btn-xs btn-default" href="<?php echo base_url()?>app/walkin/consultation_types?edit_id=<?php echo (int)$r->id; ?>">
                                                    <i class="fa fa-pencil"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="box-footer" style="background:#fff;border-top:1px solid #eef2f7;">
                            <small class="text-muted">
                                Note: Walk-In Consultation uses these types for billing (not bill_particulars).
                            </small>
                        </div>
                    </div>
                </div>
            </div>

        </section>
    </aside>
</div>

<script src="<?php echo base_url()?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url()?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url()?>public/js/AdminLTE/app.js"></script>
</body>
</html>
