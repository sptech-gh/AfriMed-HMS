<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bulk Price Adjustment - HMS</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        
        <aside class="right-side">
            <section class="content-header">
                <h1>
                    <i class="fa fa-percent"></i> Bulk Price Adjustment
                    <small>Increase or decrease prices by percentage</small>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/price_list">Price List</a></li>
                    <li class="active">Bulk Adjust</li>
                </ol>
            </section>
            
            <section class="content">
                <?php if (isset($message) && $message): ?>
                    <?php echo $message; ?>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-sliders"></i> Adjustment Settings</h3>
                            </div>
                            <form method="post" action="<?php echo base_url(); ?>app/price_list/apply_adjustment" onsubmit="return confirmAdjustment();">
                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                
                                <div class="box-body">
                                    <div class="alert alert-warning">
                                        <i class="fa fa-exclamation-triangle"></i> <strong>Warning:</strong> This will modify prices in bulk. Please review carefully before applying.
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Category to Adjust</label>
                                        <select name="category" id="category" class="form-control" required>
                                            <?php foreach ($categories as $key => $label): ?>
                                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Adjustment Type</label>
                                        <div class="btn-group btn-group-justified" data-toggle="buttons">
                                            <label class="btn btn-success active">
                                                <input type="radio" name="adjustment_type" value="increase" checked> 
                                                <i class="fa fa-arrow-up"></i> Increase
                                            </label>
                                            <label class="btn btn-danger">
                                                <input type="radio" name="adjustment_type" value="decrease"> 
                                                <i class="fa fa-arrow-down"></i> Decrease
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Percentage (%)</label>
                                        <div class="input-group">
                                            <input type="number" name="percentage" id="percentage" class="form-control" 
                                                   min="0.01" max="100" step="0.01" value="5" required>
                                            <span class="input-group-addon">%</span>
                                        </div>
                                        <p class="help-block">Enter value between 0.01 and 100</p>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Apply To</label>
                                        <select name="price_type" class="form-control" required>
                                            <option value="cash">Cash Prices Only</option>
                                            <option value="nhis">NHIS Prices Only</option>
                                            <option value="both">Both Cash and NHIS</option>
                                        </select>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="callout callout-info" id="preview">
                                        <h4><i class="fa fa-calculator"></i> Preview</h4>
                                        <p>If current price is <strong>GHS 100.00</strong>:</p>
                                        <p>New price will be: <strong id="previewResult">GHS 105.00</strong></p>
                                    </div>
                                </div>
                                
                                <div class="box-footer">
                                    <a href="<?php echo base_url(); ?>app/price_list" class="btn btn-default">
                                        <i class="fa fa-arrow-left"></i> Back to Price List
                                    </a>
                                    <button type="submit" class="btn btn-warning pull-right">
                                        <i class="fa fa-check"></i> Apply Adjustment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-info-circle"></i> How It Works</h3>
                            </div>
                            <div class="box-body">
                                <h4>Increase Example</h4>
                                <p>If you select <strong>10% increase</strong> on <strong>Medicines</strong>:</p>
                                <ul>
                                    <li>Drug A: GHS 50.00 → <strong>GHS 55.00</strong></li>
                                    <li>Drug B: GHS 120.00 → <strong>GHS 132.00</strong></li>
                                </ul>
                                
                                <hr>
                                
                                <h4>Decrease Example</h4>
                                <p>If you select <strong>15% decrease</strong> on <strong>Services</strong>:</p>
                                <ul>
                                    <li>Service X: GHS 200.00 → <strong>GHS 170.00</strong></li>
                                    <li>Service Y: GHS 80.00 → <strong>GHS 68.00</strong></li>
                                </ul>
                                
                                <hr>
                                
                                <div class="alert alert-info">
                                    <i class="fa fa-lightbulb-o"></i> <strong>Tip:</strong> You can export prices to CSV before adjusting, so you have a backup of original prices.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </section>
        </aside>
    </div>
    
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    
    <script>
    $(document).ready(function() {
        function updatePreview() {
            var percentage = parseFloat($('#percentage').val()) || 0;
            var isIncrease = $('input[name="adjustment_type"]:checked').val() === 'increase';
            var base = 100;
            var result;
            
            if (isIncrease) {
                result = base * (1 + percentage / 100);
            } else {
                result = base * (1 - percentage / 100);
            }
            
            $('#previewResult').text('GHS ' + result.toFixed(2));
        }
        
        $('#percentage').on('input', updatePreview);
        $('input[name="adjustment_type"]').change(updatePreview);
        
        updatePreview();
    });
    
    function confirmAdjustment() {
        var category = $('#category option:selected').text();
        var percentage = $('#percentage').val();
        var type = $('input[name="adjustment_type"]:checked').val();
        
        return confirm('Are you sure you want to ' + type + ' prices in "' + category + '" by ' + percentage + '%?\n\nThis action cannot be undone!');
    }
    </script>
</body>
</html>
