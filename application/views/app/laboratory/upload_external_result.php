<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Upload External Lab Result</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet">
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet">
</head>
<body class="skin-blue">
    <?php require_once(APPPATH . 'views/include/header.php'); ?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-upload"></i> Upload External Lab Result</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url(); ?>app/laboratory/lab_queue">Lab Queue</a></li>
                    <li class="active">Upload External Result</li>
                </ol>
            </section>

            <section class="content">
                <?php echo isset($message) ? $message : ''; ?>

                <div class="row">
                    <div class="col-md-6 col-md-offset-3">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-upload"></i> Upload Result for Lab Test #<?php echo (int)(isset($io_lab_id) ? $io_lab_id : 0); ?></h3>
                            </div>
                            <div class="box-body">
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i> This lab test was referred to an external laboratory. Upload the result document (PDF or image) received from the external lab.
                                </div>

                                <form method="post" action="<?php echo base_url(); ?>app/laboratory/upload_external_result/<?php echo (int)(isset($io_lab_id) ? $io_lab_id : 0); ?>" enctype="multipart/form-data">
                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                    <div class="form-group">
                                        <label>Result File <span class="text-danger">*</span></label>
                                        <input type="file" name="result_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                        <small class="text-muted">Accepted formats: PDF, JPG, PNG. Max 10MB.</small>
                                    </div>
                                    <div class="form-group">
                                        <label>Notes / Summary <small class="text-muted">(optional)</small></label>
                                        <textarea name="notes" class="form-control" rows="4" placeholder="e.g. Result received from KATH laboratory, reviewed by Dr. Asante..."></textarea>
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-info btn-block"><i class="fa fa-upload"></i> Upload External Result</button>
                                    </div>
                                    <a href="<?php echo base_url(); ?>app/laboratory/lab_queue" class="btn btn-default btn-block"><i class="fa fa-arrow-left"></i> Back to Lab Queue</a>
                                </form>
                            </div>
                        </div>
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
