<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $title ?? 'Enter Result'; ?> | HMS</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php'); ?>
    
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php'); ?>
        
        <aside class="right-side">
            <section class="content-header">
            <h1><i class="fa fa-edit"></i> <?php echo $title; ?></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url('app/dashboard'); ?>"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="<?php echo base_url('app/radiology'); ?>">Radiology</a></li>
                <li class="active">Enter Result</li>
            </ol>
        </section>
        
        <section class="content">
            <div class="row">
                <div class="col-md-8">
                    <?php if(isset($order) && $order): ?>
                    <!-- Order Info -->
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title">Order Information</h3>
                        </div>
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Order #:</strong> <?php echo $order->order_no; ?></p>
                                    <p><strong>Patient:</strong> <?php echo ($order->firstname ?? '') . ' ' . ($order->lastname ?? ''); ?></p>
                                    <p><strong>Patient No:</strong> <?php echo $order->pat_no ?? $order->patient_no; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Test:</strong> <?php echo $order->test_name; ?></p>
                                    <p><strong>NHIS Code:</strong> <?php echo $order->nhis_code ?? 'N/A'; ?></p>
                                    <p><strong>Priority:</strong> 
                                        <span class="label label-<?php echo $order->priority == 'stat' ? 'danger' : ($order->priority == 'urgent' ? 'warning' : 'default'); ?>">
                                            <?php echo strtoupper($order->priority); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Result Entry Form -->
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">Enter Result</h3>
                        </div>
                        <form action="" method="post">
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                            <input type="hidden" name="order_id" value="<?php echo $order->id; ?>">
                            <div class="box-body">
                                <div class="form-group">
                                    <label>Findings <span class="text-danger">*</span></label>
                                    <textarea name="findings" class="form-control" rows="5" required
                                              placeholder="Describe the radiological findings..."><?php echo $order->result->findings ?? ''; ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label>Impression <span class="text-danger">*</span></label>
                                    <textarea name="impression" class="form-control" rows="3" required
                                              placeholder="Summary/Impression..."><?php echo $order->result->impression ?? ''; ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label>Recommendations</label>
                                    <textarea name="recommendations" class="form-control" rows="2"
                                              placeholder="Any recommendations for follow-up..."><?php echo $order->result->recommendations ?? ''; ?></textarea>
                                </div>
                            </div>
                            <div class="box-footer">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-save"></i> Save Result
                                </button>
                                <a href="<?php echo base_url('app/radiology'); ?>" class="btn btn-default">
                                    <i class="fa fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </form>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i> Order not found or invalid.
                        <a href="<?php echo base_url('app/radiology'); ?>">Go back to Radiology</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            </section>
        </aside>
    </div>
    
    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
</body>
</html>
