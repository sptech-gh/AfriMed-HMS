<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?> | HMS</title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css">
    <link href="<?php echo base_url(); ?>public/css/custom.css" rel="stylesheet" type="text/css">
    <style>
        .blocked-card { border-left: 4px solid #dc3545; padding: 15px; margin-bottom: 15px; background: #fff; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .patient-name { font-size: 16px; font-weight: bold; color: #333; }
        .service-name { font-size: 14px; color: #666; margin-top: 5px; }
        .department-badge { background: #3c8dbc; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; }
        .amount { font-size: 18px; font-weight: bold; color: #f56954; }
        .blocked-time { color: #999; font-size: 12px; }
        .unblock-btn { margin-top: 10px; }
    </style>
</head>
<body class="skin-blue">

<?php require_once(APPPATH.'views/include/header.php');?>
<?php require_once(APPPATH.'views/include/sidebar.php');?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <aside class="right-side">
        <section class="content-header">
            <h1><?php echo $page_title; ?> <small>Services Pending Payment</small></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li><a href="<?php echo base_url(); ?>app/unified_billing">Unified Billing</a></li>
                <li class="active">Blocked Services</li>
            </ol>
        </section>

        <section class="content">
            <!-- Notification Messages -->
            <?php if($this->session->flashdata('message')): ?>
                <div class="alert alert-success alert-dismissable">
                    <i class="fa fa-check"></i>
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <?php echo $this->session->flashdata('message');?>
                </div>
            <?php endif; ?>

            <?php if($this->session->flashdata('error')): ?>
                <div class="alert alert-danger alert-dismissable">
                    <i class="fa fa-ban"></i>
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <?php echo $this->session->flashdata('error');?>
                </div>
            <?php endif; ?>

            <!-- Info Box -->
            <div class="row">
                <div class="col-md-12">
                    <div class="callout callout-warning">
                        <h4><i class="fa fa-info-circle"></i> About Blocked Services</h4>
                        <p>These are services (lab tests, medications, procedures) that have been ordered by doctors but are <strong>blocked</strong> until payment is received. 
                        After collecting payment, click "Unblock Service" to allow the service to proceed.</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="box box-danger">
                        <div class="box-header">
                            <h3 class="box-title"><i class="fa fa-lock"></i> Blocked Services List</h3>
                            <div class="box-tools pull-right">
                                <a href="<?php echo base_url(); ?>app/unified_billing" class="btn btn-default btn-sm">
                                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </div>
                        <div class="box-body">
                            <?php if (empty($blocked_items)): ?>
                                <div class="alert alert-success">
                                    <i class="fa fa-check-circle"></i> No blocked services! All services are cleared for processing.
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($blocked_items as $item): ?>
                                        <div class="col-md-6">
                                            <div class="blocked-card" id="item_<?php echo (int)$item->queue_id; ?>">
                                                <div class="row">
                                                    <div class="col-xs-8">
                                                        <div class="patient-name">
                                                            <i class="fa fa-user"></i> 
                                                            <?php echo $item->patient_name ?? 'Unknown'; ?>
                                                        </div>
                                                        <div class="service-name">
                                                            <i class="fa fa-medkit"></i> 
                                                            <?php echo $item->item_name ?? ('Item #' . (int)$item->queue_id); ?>
                                                        </div>
                                                        <div style="margin-top: 8px;">
                                                            <span class="department-badge">
                                                                <?php echo $item->item_type ?? ($item->source_module ?? 'Unknown'); ?>
                                                            </span>
                                                            <span class="blocked-time">
                                                                <i class="fa fa-clock-o"></i> 
                                                                Blocked: <?php echo date('M d, H:i', strtotime($item->created_at)); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="col-xs-4 text-right">
                                                        <div class="amount">
                                                            GH₵<?php echo number_format((float)$item->net_amount, 2); ?>
                                                        </div>
                                                        <small>Amount</small>
                                                        <div class="unblock-btn">
                                                            <?php if ($can_unblock): ?>
                                                                <button class="btn btn-success btn-sm btn-block" onclick="unblockService(<?php echo (int)$item->queue_id; ?>)">
                                                                    <i class="fa fa-unlock"></i> Unblock
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row" style="margin-top: 10px;">
                                                    <div class="col-xs-12">
                                                        <small class="text-muted">
                                                            <?php if (!empty($item->invoice_no)): ?>
                                                                <i class="fa fa-file-text-o"></i> Invoice: <?php echo htmlspecialchars($item->invoice_no); ?> |
                                                            <?php endif; ?>
                                                            <i class="fa fa-hashtag"></i> Patient ID: <?php echo htmlspecialchars($item->patient_no); ?> |
                                                            <i class="fa fa-hospital-o"></i> Visit: <?php echo htmlspecialchars($item->iop_id); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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

<script>
var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
function unblockService(itemId) {
    if (!confirm('Are you sure you want to unblock this service? The patient has paid and the service can now proceed.')) {
        return;
    }
    var postData = { queue_id: itemId };
    postData[csrfName] = csrfHash;
    $.ajax({
        url: '<?php echo base_url(); ?>app/unified_billing/unblock_service',
        type: 'POST',
        data: postData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Service unblocked successfully!');
                // Remove the card from view
                $('#item_' + itemId).fadeOut(300, function() { $(this).remove(); });
            } else {
                alert('Error: ' + (response.error || 'Failed to unblock service'));
            }
        },
        error: function() {
            alert('Network error. Please try again.');
        }
    });
}
</script>

</body>
</html>
