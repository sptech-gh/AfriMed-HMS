<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <?php $is_edit = isset($test); ?>
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — <?php echo $is_edit ? 'Edit' : 'Add'; ?> Lab Test</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url()?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url()?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url()?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-flask"></i> <?php echo $is_edit ? 'Edit' : 'Add'; ?> Laboratory Test <small>GHS/NHIS Standard</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="<?php echo base_url(); ?>app/test_catalog/lab_tests">Lab Test Catalog</a></li>
                    <li class="active"><?php echo $is_edit ? 'Edit' : 'Add'; ?></li>
                </ol>
            </section>

            <section class="content">
                <?php
                $form_action = $is_edit ? base_url('app/test_catalog/edit_lab_test/' . $test->test_id) : base_url('app/test_catalog/add_lab_test');
                ?>
                <form action="<?php echo $form_action; ?>" method="post">
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                    <div class="row">
                        <!-- Basic Information -->
                        <div class="col-md-8">
                            <div class="box box-primary">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-info-circle"></i> Test Information</h3>
                                </div>
                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Test Code <span class="text-danger">*</span></label>
                                                <input type="text" name="test_code" class="form-control" required
                                                       value="<?php echo $is_edit ? htmlspecialchars($test->test_code) : ''; ?>"
                                                       placeholder="e.g., HEM001">
                                                <small class="text-muted">Unique GHS/NHIS identifier</small>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="form-group">
                                                <label>Test Name <span class="text-danger">*</span></label>
                                                <input type="text" name="test_name" class="form-control" required
                                                       value="<?php echo $is_edit ? htmlspecialchars($test->test_name) : ''; ?>"
                                                       placeholder="e.g., Full Blood Count (FBC)">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Category <span class="text-danger">*</span></label>
                                                <select name="category" class="form-control" required>
                                                    <option value="">-- Select Category --</option>
                                                    <?php 
                                                    $cat_list = ['Hematology', 'Biochemistry', 'Microbiology', 'Serology', 
                                                                'Urinalysis', 'Parasitology', 'Clinical Chemistry', 
                                                                'Immunology', 'Hormones', 'Coagulation'];
                                                    foreach ($cat_list as $cat): 
                                                        $selected = ($is_edit && $test->category === $cat) ? 'selected' : '';
                                                    ?>
                                                    <option value="<?php echo $cat; ?>" <?php echo $selected; ?>><?php echo $cat; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Specimen Type</label>
                                                <select name="specimen_type" class="form-control">
                                                    <option value="">-- Select --</option>
                                                    <?php 
                                                    $specimens = ['Blood', 'Serum', 'Plasma', 'Urine', 'Stool', 'Sputum', 
                                                                 'Swab', 'CSF', 'Tissue', 'Other'];
                                                    foreach ($specimens as $spec): 
                                                        $selected = ($is_edit && isset($test->specimen_type) && $test->specimen_type === $spec) ? 'selected' : '';
                                                    ?>
                                                    <option value="<?php echo $spec; ?>" <?php echo $selected; ?>><?php echo $spec; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Turnaround Time</label>
                                                <input type="text" name="turnaround_time" class="form-control"
                                                       value="<?php echo $is_edit ? htmlspecialchars($test->turnaround_time ?? '') : ''; ?>"
                                                       placeholder="e.g., 2 hours, Same day">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>Special Instructions</label>
                                                <textarea name="special_instructions" class="form-control" rows="2"
                                                          placeholder="Sample collection or patient preparation notes"><?php echo $is_edit ? htmlspecialchars($test->special_instructions ?? '') : ''; ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="checkbox">
                                                <label>
                                                    <input type="checkbox" name="requires_fasting" value="1"
                                                           <?php echo ($is_edit && $test->requires_fasting) ? 'checked' : ''; ?>>
                                                    <strong>Requires Fasting</strong>
                                                </label>
                                            </div>
                                        </div>
                                        <?php if ($is_edit): ?>
                                        <div class="col-md-4">
                                            <div class="checkbox">
                                                <label>
                                                    <input type="checkbox" name="is_active" value="1"
                                                           <?php echo $test->is_active ? 'checked' : ''; ?>>
                                                    <strong>Active</strong>
                                                </label>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pricing, NHIS & Billing Bridge -->
                        <div class="col-md-4">
                            <div class="box box-success">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-money"></i> Pricing & NHIS</h3>
                                </div>
                                <div class="box-body">
                                    <div class="form-group">
                                        <label>Cash Price (GH₵)</label>
                                        <div class="input-group">
                                            <span class="input-group-addon">GH₵</span>
                                            <input type="number" name="price" class="form-control" step="0.01" min="0"
                                                   value="<?php echo $is_edit ? $test->price : '0.00'; ?>">
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="form-group">
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" name="is_nhis_covered" value="1" id="nhisCheck"
                                                       <?php echo ($is_edit && $test->is_nhis_covered) ? 'checked' : ''; ?>>
                                                <strong><i class="fa fa-medkit"></i> NHIS Covered</strong>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div id="nhisFields" style="<?php echo ($is_edit && $test->is_nhis_covered) ? '' : 'display:none;'; ?>">
                                        <div class="form-group">
                                            <label>NHIS Code</label>
                                            <input type="text" name="nhis_code" class="form-control"
                                                   value="<?php echo $is_edit ? htmlspecialchars($test->nhis_code ?? '') : ''; ?>"
                                                   placeholder="e.g., NHIS-HEM-001">
                                        </div>
                                        <div class="form-group">
                                            <label>NHIS Price (GH₵)</label>
                                            <div class="input-group">
                                                <span class="input-group-addon">GH₵</span>
                                                <input type="number" name="nhis_price" class="form-control" step="0.01" min="0"
                                                       value="<?php echo $is_edit ? $test->nhis_price : '0.00'; ?>">
                                            </div>
                                            <small class="text-muted">Amount reimbursed by NHIS</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Billing Bridge -->
                            <div class="box box-warning">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-link"></i> Billing Link</h3>
                                </div>
                                <div class="box-body">
                                    <div class="form-group">
                                        <label>Linked Bill Particular ID</label>
                                        <input type="number" name="particular_id" class="form-control" min="0"
                                               value="<?php echo ($is_edit && isset($test->particular_id)) ? (int)$test->particular_id : ''; ?>"
                                               placeholder="Auto-linked or enter manually">
                                        <small class="text-muted">
                                            Links this test to <code>bill_particular</code> for billing.
                                            <?php if ($is_edit && isset($test->particular_id) && $test->particular_id): ?>
                                            <br><span class="text-success"><i class="fa fa-check"></i> Currently linked to #<?php echo $test->particular_id; ?></span>
                                            <?php else: ?>
                                            <br><span class="text-warning"><i class="fa fa-warning"></i> Not linked — billing may not auto-charge.</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="box box-default">
                                <div class="box-body">
                                    <button type="submit" class="btn btn-success btn-block btn-lg">
                                        <i class="fa fa-save"></i> <?php echo $is_edit ? 'Update Test' : 'Save Test'; ?>
                                    </button>
                                    <a href="<?php echo base_url(); ?>app/test_catalog/lab_tests" class="btn btn-default btn-block">
                                        <i class="fa fa-arrow-left"></i> Back to List
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script>
    $(document).ready(function() {
        $('#nhisCheck').on('change', function() {
            if ($(this).is(':checked')) { $('#nhisFields').slideDown(); } 
            else { $('#nhisFields').slideUp(); }
        });
    });
    </script>
</body>
</html>
