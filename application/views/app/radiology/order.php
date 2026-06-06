<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $title ?? 'Order Test'; ?> | HMS</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/plugins/select2/select2.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php'); ?>
    
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php'); ?>
        
        <aside class="right-side">
            <section class="content-header">
            <h1><i class="fa fa-plus"></i> <?php echo $title; ?></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url('app/dashboard'); ?>"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="<?php echo base_url('app/radiology'); ?>">Radiology</a></li>
                <li class="active">Order Test</li>
            </ol>
        </section>
        
        <section class="content">
            <div class="row">
                <div class="col-md-8">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">Order Radiology Test</h3>
                        </div>
                        <form action="" method="post">
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                            <div class="box-body">
                                <?php if(isset($patient) && $patient): ?>
                                <?php
                                    $ptName = isset($patient->name) ? $patient->name : (
                                        (isset($patient->firstname) ? $patient->firstname : '') . ' ' .
                                        (isset($patient->lastname) ? $patient->lastname : '')
                                    );
                                    $ptNo = isset($patient->patient_no) ? $patient->patient_no : '';
                                ?>
                                <div class="alert alert-info">
                                    <strong>Patient:</strong> <?php echo htmlspecialchars(trim($ptName), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($ptNo !== '') { ?>(<?php echo htmlspecialchars($ptNo, ENT_QUOTES, 'UTF-8'); ?>)<?php } ?>
                                    <input type="hidden" name="iop_id" value="<?php echo htmlspecialchars((string)$iop_id, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="patient_no" value="<?php echo htmlspecialchars($ptNo, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <?php else: ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Patient Number <span class="text-danger">*</span></label>
                                            <input type="text" name="patient_no" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Visit ID (IOP ID)</label>
                                            <input type="text" name="iop_id" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label>Select Test <span class="text-danger">*</span></label>
                                            <select name="test_id" class="form-control select2" required>
                                                <option value="">-- Select Test --</option>
                                                <?php if(!empty($tests)): ?>
                                                <?php foreach($tests as $test): ?>
                                                <option value="<?php echo $test->id; ?>" 
                                                        data-price="<?php echo $test->price; ?>"
                                                        data-nhis="<?php echo $test->is_nhis_covered; ?>">
                                                    <?php echo $test->test_name; ?> 
                                                    (GHS <?php echo number_format($test->price, 2); ?>)
                                                    <?php if($test->is_nhis_covered): ?> [NHIS]<?php endif; ?>
                                                </option>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Priority</label>
                                            <select name="priority" class="form-control">
                                                <option value="normal">Normal</option>
                                                <option value="urgent">Urgent</option>
                                                <option value="stat">STAT (Emergency)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Clinical Notes</label>
                                    <textarea name="clinical_notes" class="form-control" rows="3" 
                                              placeholder="Reason for test, clinical history, etc."></textarea>
                                </div>
                            </div>
                            <div class="box-footer">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-check"></i> Create Order
                                </button>
                                <a href="<?php echo base_url('app/radiology'); ?>" class="btn btn-default">
                                    <i class="fa fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            </section>
        </aside>
    </div>
    
    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url(); ?>public/plugins/select2/select2.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/app.min.js"></script>
    <script>
    $(function() {
        $('.select2').select2();
    });
    </script>
</body>
</html>
