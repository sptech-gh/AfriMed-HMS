<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Register Walk-In Client</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/font-awesome.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/AdminLTE.css" rel="stylesheet">
    <style>
        .step-indicator{display:flex;align-items:center;margin-bottom:28px;gap:0;}
        .step-indicator .step{flex:1;text-align:center;position:relative;}
        .step-indicator .step-circle{width:36px;height:36px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;border:2px solid #dee2e6;background:#fff;color:#adb5bd;}
        .step-indicator .step.active .step-circle{background:#1a6fa5;border-color:#1a6fa5;color:#fff;}
        .step-indicator .step.done .step-circle{background:#27a063;border-color:#27a063;color:#fff;}
        .step-indicator .step-line{flex:1;height:2px;background:#dee2e6;align-self:center;}
        .step-indicator .step-label{font-size:11px;color:#6c757d;margin-top:4px;font-weight:600;}
        .step-indicator .step.active .step-label{color:#1a6fa5;}
        .register-card{max-width:560px;margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 2px 16px rgba(0,0,0,.08);padding:32px 36px;}
        .register-card .form-control{border-radius:6px;height:42px;font-size:14px;}
        .register-card select.form-control{height:42px;}
        .register-card label{font-weight:600;font-size:13px;color:#374151;}
        .btn-register{width:100%;height:46px;font-size:16px;font-weight:700;border-radius:6px;background:#1a6fa5;border:none;color:#fff;}
        .btn-register:hover{background:#155d8a;}
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php'); ?>

    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-user-plus"></i> Register Walk-In Client</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li><a href="<?php echo base_url()?>app/walkin">Walk-In</a></li>
                <li class="active">Register Client</li>
            </ol>
        </section>

        <section class="content">
            <?php if(isset($message)) echo $message; ?>

            <!-- Step Indicator -->
            <div class="register-card">
                <div class="step-indicator">
                    <div class="step active">
                        <div class="step-circle">1</div>
                        <div class="step-label">Register</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step">
                        <div class="step-circle">2</div>
                        <div class="step-label">Add Service</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step">
                        <div class="step-circle">3</div>
                        <div class="step-label">Payment</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step">
                        <div class="step-circle">4</div>
                        <div class="step-label">Receipt</div>
                    </div>
                </div>

                <h4 style="margin:0 0 20px;color:#1a6fa5;font-weight:700;border-bottom:1px solid #e9ecef;padding-bottom:12px;">
                    <i class="fa fa-id-card-o"></i> Step 1 — Client Details
                </h4>

                <form method="post" action="<?php echo base_url()?>app/walkin/save_client" id="frmRegister">
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">

                    <div class="form-group">
                        <label for="client_name">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="client_name" id="client_name" class="form-control" placeholder="e.g. Kwame Mensah" required autofocus>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" name="phone" id="phone" class="form-control" placeholder="e.g. 0244123456">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select name="gender" id="gender" class="form-control">
                                    <option value="">— Select —</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="referral">Referred By <small class="text-muted">(hospital or doctor name)</small></label>
                        <input type="text" name="referral" id="referral" class="form-control" placeholder="e.g. Dr. Boateng / KATH">
                    </div>

                    <button type="submit" class="btn btn-register" id="btnRegister">
                        <i class="fa fa-arrow-right"></i> Register & Add Service
                    </button>
                </form>

                <div style="text-align:center;margin-top:16px;">
                    <a href="<?php echo base_url()?>app/walkin" class="text-muted" style="font-size:13px;">
                        <i class="fa fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </section>
    </aside>
</div>

<script src="<?php echo base_url()?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url()?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url()?>public/js/AdminLTE/app.js"></script>
<script>
$('#frmRegister').on('submit', function(){
    $('#btnRegister').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Registering...');
});
</script>
</body>
</html>
