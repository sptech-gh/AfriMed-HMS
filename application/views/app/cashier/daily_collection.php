<?php
// Prevent browser caching to avoid stale flash messages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Daily Collection Report</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
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
                <h1>Daily Collection Report <small><?php echo isset($date) ? date('l, M d, Y', strtotime($date)) : date('l, M d, Y'); ?></small></h1>
            </section>
            <section class="content">
                <?php echo isset($message) ? $message : ''; ?>

                <!-- Filter -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-filter"></i> Filter</h3>
                    </div>
                    <div class="box-body">
                        <form method="get" action="<?php echo base_url();?>app/cashier/daily_collection" class="form-inline">
                            <div class="form-group" style="margin-right:15px">
                                <label style="margin-right:5px">Date:</label>
                                <input type="date" name="date" class="form-control" value="<?php echo isset($date) ? $date : date('Y-m-d'); ?>">
                            </div>
                            <?php if (isset($cashiers) && count($cashiers) > 0) { ?>
                            <div class="form-group" style="margin-right:15px">
                                <label style="margin-right:5px">Cashier:</label>
                                <select name="cashier_id" class="form-control">
                                    <option value="">All Cashiers</option>
                                    <?php foreach ($cashiers as $c) { ?>
                                        <option value="<?php echo $c->user_id; ?>" <?php echo (isset($cashier_id) && $cashier_id == $c->user_id) ? 'selected' : ''; ?>><?php echo isset($c->fullname) && $c->fullname ? $c->fullname : $c->username; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <?php } ?>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> View Report</button>
                            <a href="<?php echo base_url();?>app/cashier/export_daily_pdf?date=<?php echo isset($date) ? $date : date('Y-m-d'); ?><?php echo (isset($cashier_id) && $cashier_id) ? '&cashier_id='.$cashier_id : ''; ?>" class="btn btn-default" target="_blank"><i class="fa fa-file-pdf-o"></i> Export PDF</a>
                        </form>
                    </div>
                </div>

                <!-- Summary -->
                <div class="row">
                    <div class="col-lg-4 col-xs-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3><?php echo number_format(isset($summary->total) ? $summary->total : 0, 2); ?></h3>
                                <p>Total Collections</p>
                            </div>
                            <div class="icon"><i class="ion ion-cash"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-xs-6">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?php echo isset($summary->count) ? $summary->count : 0; ?></h3>
                                <p>Transactions</p>
                            </div>
                            <div class="icon"><i class="ion ion-document-text"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-xs-6">
                        <div class="small-box bg-purple">
                            <div class="inner">
                                <h3><?php echo (isset($summary->count) && $summary->count > 0) ? number_format($summary->total / $summary->count, 2) : '0.00'; ?></h3>
                                <p>Average Transaction</p>
                            </div>
                            <div class="icon"><i class="ion ion-calculator"></i></div>
                        </div>
                    </div>
                </div>

                <!-- By Payment Method -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-pie-chart"></i> By Payment Method</h3>
                            </div>
                            <div class="box-body no-padding">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Method</th>
                                            <th class="text-right">Count</th>
                                            <th class="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($by_method) && count($by_method) > 0) { ?>
                                            <?php foreach ($by_method as $m) { ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    $icon = 'fa-money';
                                                    $pt = strtoupper($m->payment_type);
                                                    if (strpos($pt, 'MOMO') !== false || strpos($pt, 'MOBILE') !== false) $icon = 'fa-mobile';
                                                    elseif (strpos($pt, 'CARD') !== false) $icon = 'fa-credit-card';
                                                    elseif (strpos($pt, 'BANK') !== false) $icon = 'fa-university';
                                                    elseif (strpos($pt, 'CHEQUE') !== false) $icon = 'fa-file-text-o';
                                                    elseif (strpos($pt, 'NHIS') !== false) $icon = 'fa-shield';
                                                    ?>
                                                    <i class="fa <?php echo $icon; ?>"></i> <?php echo $m->payment_type; ?>
                                                </td>
                                                <td class="text-right"><?php echo $m->count; ?></td>
                                                <td class="text-right"><?php echo number_format($m->total, 2); ?></td>
                                            </tr>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr><td colspan="3" class="text-center text-muted">No data</td></tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions List -->
                    <div class="col-md-8">
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-list"></i> Transactions</h3>
                                <span class="label label-success pull-right"><?php echo isset($summary->count) ? $summary->count : 0; ?> transactions</span>
                            </div>
                            <div class="box-body table-responsive no-padding" style="max-height:500px; overflow-y:auto">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>Receipt #</th>
                                            <th>Time</th>
                                            <th>Invoice #</th>
                                            <th>Patient</th>
                                            <th>Method</th>
                                            <th class="text-right">Amount</th>
                                            <th>Cashier</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($collections) && count($collections) > 0) { ?>
                                            <?php foreach ($collections as $c) { ?>
                                            <tr>
                                                <td>
                                                    <a href="<?php echo base_url();?>app/cashier/print_receipt/<?php echo $c->receipt_no; ?>" target="_blank"><?php echo $c->receipt_no; ?></a>
                                                    <a href="<?php echo base_url();?>app/cashier/pdf_receipt/<?php echo $c->receipt_no; ?>" target="_blank" style="margin-left:6px;"><i class="fa fa-file-pdf-o"></i></a>
                                                </td>
                                                <td><?php echo date('H:i', strtotime($c->dDate)); ?></td>
                                                <td><a href="<?php echo base_url();?>app/cashier/invoice/<?php echo $c->invoice_no; ?>"><?php echo $c->invoice_no; ?></a></td>
                                                <td><?php echo isset($c->patient_name) ? $c->patient_name : $c->patient_no; ?></td>
                                                <td><?php echo $c->payment_type; ?></td>
                                                <td class="text-right"><strong><?php echo number_format($c->amountPaid, 2); ?></strong></td>
                                                <td><?php echo isset($c->cashier_name) ? $c->cashier_name : '-'; ?></td>
                                            </tr>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr><td colspan="7" class="text-center text-muted">No transactions for this date</td></tr>
                                        <?php } ?>
                                    </tbody>
                                    <?php if (isset($collections) && count($collections) > 0) { ?>
                                    <tfoot>
                                        <tr class="success">
                                            <th colspan="5" class="text-right">Total:</th>
                                            <th class="text-right"><?php echo number_format(isset($summary->total) ? $summary->total : 0, 2); ?></th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                    <?php } ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
</body>
</html>
