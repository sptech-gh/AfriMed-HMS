<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $title ?? 'Edit Test'; ?> | HMS</title>
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
                    <li class="active">Edit Test</li>
                </ol>
            </section>
            
            <section class="content">
                <?php if($this->session->flashdata('error')): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo $this->session->flashdata('error'); ?>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title">Edit Radiology Test: <?php echo $test->test_name; ?></h3>
                            </div>
                            <form action="" method="post">
                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Test Name <span class="text-danger">*</span></label>
                                                <input type="text" name="test_name" class="form-control" value="<?php echo htmlspecialchars($test->test_name); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Test Code</label>
                                                <input type="text" name="test_code" class="form-control" value="<?php echo htmlspecialchars($test->test_code ?? ''); ?>" placeholder="e.g., XRAY-001">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>NHIS Code</label>
                                                <input type="text" name="nhis_code" class="form-control" value="<?php echo htmlspecialchars($test->nhis_code ?? ''); ?>" placeholder="e.g., RAD001">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Category</label>
                                                <select name="category" class="form-control">
                                                    <option value="">-- Select Category --</option>
                                                    <option value="X-Ray" <?php echo ($test->category ?? '') == 'X-Ray' ? 'selected' : ''; ?>>X-Ray</option>
                                                    <option value="CT Scan" <?php echo ($test->category ?? '') == 'CT Scan' ? 'selected' : ''; ?>>CT Scan</option>
                                                    <option value="MRI" <?php echo ($test->category ?? '') == 'MRI' ? 'selected' : ''; ?>>MRI</option>
                                                    <option value="Ultrasound" <?php echo ($test->category ?? '') == 'Ultrasound' ? 'selected' : ''; ?>>Ultrasound</option>
                                                    <option value="Mammography" <?php echo ($test->category ?? '') == 'Mammography' ? 'selected' : ''; ?>>Mammography</option>
                                                    <option value="Fluoroscopy" <?php echo ($test->category ?? '') == 'Fluoroscopy' ? 'selected' : ''; ?>>Fluoroscopy</option>
                                                    <option value="Nuclear Medicine" <?php echo ($test->category ?? '') == 'Nuclear Medicine' ? 'selected' : ''; ?>>Nuclear Medicine</option>
                                                    <option value="Cardiac" <?php echo ($test->category ?? '') == 'Cardiac' ? 'selected' : ''; ?>>Cardiac</option>
                                                    <option value="Other" <?php echo ($test->category ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Price (GHS) <span class="text-danger">*</span></label>
                                                <input type="number" name="price" class="form-control" step="0.01" value="<?php echo $test->price ?? 0; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>NHIS Price (GHS)</label>
                                                <input type="number" name="nhis_price" class="form-control" step="0.01" value="<?php echo $test->nhis_price ?? 0; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Department</label>
                                                <input type="text" name="department" class="form-control" value="<?php echo htmlspecialchars($test->department ?? 'Radiology'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Status</label>
                                                <select name="status" class="form-control">
                                                    <option value="active" <?php echo ($test->status ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="inactive" <?php echo ($test->status ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" name="is_nhis_covered" value="1" <?php echo ($test->is_nhis_covered ?? 0) ? 'checked' : ''; ?>>
                                                        <strong>NHIS Covered</strong>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="box-footer">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save"></i> Update Test
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
    <script src="<?php echo base_url(); ?>public/js/app.min.js"></script>
</body>
</html>
