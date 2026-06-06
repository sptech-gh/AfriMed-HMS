<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Cashier Dashboard</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
        <style>
            .stat-box { border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.2s; }
            .stat-box:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
            .info-box { border-radius: 8px; }
            .box { border-radius: 8px; border-top: 3px solid; }
            .box-primary { border-top-color: #3c8dbc; }
            .box-warning { border-top-color: #f39c12; }
            .table th { background: #f8f9fa; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
            .badge-pill { border-radius: 50px; padding: 4px 10px; font-size: 11px; }
            .text-amount { font-family: 'Consolas', monospace; font-weight: 600; }
            .btn-action { border-radius: 4px; }
            .patient-link { color: #3c8dbc; font-weight: 500; }
            .patient-link:hover { text-decoration: underline; }
            .status-paid { background: #28a745; color: #fff; }
            .status-partial { background: #ffc107; color: #000; }
            .status-unpaid { background: #dc3545; color: #fff; }
        </style>
    </head>
    <body class="skin-blue">
        <?php require_once(APPPATH.'views/include/header.php');?>
        <div class="wrapper row-offcanvas row-offcanvas-left">
            <?php require_once(APPPATH.'views/include/sidebar.php');?>
            <aside class="right-side">
                <section class="content-header">
                    <h1>Cashier Dashboard <small><?php echo date('l, M d, Y'); ?></small></h1>
                </section>
                <section class="content">

                    <!-- Stat Boxes -->
                    <div class="row">
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-aqua">
                                <div class="inner">
                                    <h3><?php echo isset($today_invoices) ? $today_invoices : 0; ?></h3>
                                    <p>Invoices Today</p>
                                </div>
                                <div class="icon"><i class="ion ion-document-text"></i></div>
                                <a href="<?php echo base_url();?>app/billing_history" class="small-box-footer">View All <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-green">
                                <div class="inner">
                                    <h3><?php echo number_format(isset($payments_today) ? $payments_today : 0, 2); ?></h3>
                                    <p>Payments Received</p>
                                </div>
                                <div class="icon"><i class="ion ion-cash"></i></div>
                                <a href="<?php echo base_url();?>app/billing_history" class="small-box-footer">Details <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-red">
                                <div class="inner">
                                    <h3><?php echo isset($unpaid_invoices) ? $unpaid_invoices : 0; ?></h3>
                                    <p>Unpaid Invoices</p>
                                </div>
                                <div class="icon"><i class="ion ion-alert-circled"></i></div>
                                <a href="<?php echo base_url();?>app/billing_history" class="small-box-footer">View Unpaid <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-yellow">
                                <div class="inner">
                                    <h3><?php echo number_format(isset($outstanding) ? $outstanding : 0, 2); ?></h3>
                                    <p>Outstanding Balance</p>
                                </div>
                                <div class="icon"><i class="ion ion-pie-graph"></i></div>
                                <a href="<?php echo base_url();?>app/billing_history" class="small-box-footer">Details <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Summary Box -->
                    <div class="row">
                        <div class="col-lg-4 col-xs-12">
                            <div class="info-box">
                                <span class="info-box-icon bg-aqua"><i class="fa fa-file-text"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Billed Today</span>
                                    <span class="info-box-number"><?php echo number_format(isset($revenue_today) ? $revenue_today : 0, 2); ?></span>
                                    <small class="text-muted">Total invoice value</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-xs-12">
                            <div class="info-box">
                                <span class="info-box-icon bg-green"><i class="fa fa-money"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Cash Received</span>
                                    <span class="info-box-number"><?php echo number_format(isset($payments_today) ? $payments_today : 0, 2); ?></span>
                                    <small class="text-muted">Actual payments collected</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-xs-12">
                            <div class="info-box">
                                <span class="info-box-icon bg-red"><i class="fa fa-exclamation-triangle"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Outstanding (All Time)</span>
                                    <span class="info-box-number"><?php echo number_format(isset($outstanding) ? $outstanding : 0, 2); ?></span>
                                    <small class="text-muted">Unpaid balances</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Patient Bills Panel - CONSOLIDATED VIEW -->
                    <?php $pendingPatientsCount = isset($pending_patients_count) ? (int)$pending_patients_count : 0; ?>
                    <?php if ($pendingPatientsCount > 0) { ?>
                    <div class="row">
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-orange">
                                <div class="inner">
                                    <h3><?php echo $pendingPatientsCount; ?></h3>
                                    <p>Patients Awaiting Billing</p>
                                </div>
                                <div class="icon"><i class="fa fa-users"></i></div>
                                <a href="#pendingPatientsSection" class="small-box-footer">View Below <i class="fa fa-arrow-circle-down"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="pendingPatientsSection">
                        <section class="col-lg-12">
                            <div class="box box-warning">
                                <div class="box-header">
                                    <div class="pull-right box-tools">
                                        <button class="btn btn-warning btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                    </div>
                                    <i class="fa fa-users"></i>
                                    <h3 class="box-title">Patients Awaiting Payment <span class="badge bg-red"><?php echo $pendingPatientsCount; ?></span></h3>
                                    <small class="text-muted" style="margin-left:10px;">Click "Bill All" to bill all pending items for a patient at once</small>
                                </div>
                                <div class="box-body no-padding">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped">
                                            <thead><tr>
                                                <th>Patient No.</th>
                                                <th>Patient Name</th>
                                                <th class="text-center">Lab/Scan Items</th>
                                                <th class="text-center">Medication Items</th>
                                                <th class="text-right">Total Amount</th>
                                                <th>Services</th>
                                                <th>First Request</th>
                                                <th>Action</th>
                                            </tr></thead>
                                            <tbody>
                                            <?php if (isset($pending_patients) && is_array($pending_patients)) { foreach($pending_patients as $pt) { 
                                                $totalItems = (int)$pt->lab_count + (int)$pt->med_count;
                                            ?>
                                                <tr>
                                                    <td><a href="<?php echo base_url();?>app/patient/view/<?php echo htmlspecialchars($pt->patient_no);?>" class="patient-link"><?php echo htmlspecialchars($pt->patient_no);?></a></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($pt->patient_name ? $pt->patient_name : '—');?></strong>
                                                        <?php if ($pt->phone) { ?><br><small class="text-muted"><i class="fa fa-phone"></i> <?php echo htmlspecialchars($pt->phone);?></small><?php } ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($pt->lab_count > 0) { ?>
                                                            <span class="badge bg-blue" title="Lab/Scan/Radiology tests"><?php echo $pt->lab_count; ?> <i class="fa fa-flask"></i></span>
                                                            <br><small class="text-muted"><?php echo number_format($pt->lab_total, 2);?></small>
                                                        <?php } else { echo '—'; } ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($pt->med_count > 0) { ?>
                                                            <span class="badge bg-green" title="Medications"><?php echo $pt->med_count; ?> <i class="fa fa-medkit"></i></span>
                                                            <br><small class="text-muted"><?php echo number_format($pt->med_total, 2);?></small>
                                                        <?php } else { echo '—'; } ?>
                                                    </td>
                                                    <td class="text-right text-amount">
                                                        <strong class="text-danger"><?php echo number_format($pt->total_amount, 2);?></strong>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $depts = $pt->departments ? explode(', ', $pt->departments) : array();
                                                        foreach (array_slice($depts, 0, 2) as $dept) {
                                                            $deptClass = (stripos($dept, 'RADIO') !== false || stripos($dept, 'SONO') !== false) ? 'label-warning' : 'label-info';
                                                            echo '<span class="label '.$deptClass.'" style="margin-right:2px;">'.htmlspecialchars($dept).'</span>';
                                                        }
                                                        if (count($depts) > 2) echo '<span class="label label-default">+' . (count($depts)-2) . '</span>';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $pt->earliest_request ? date('M d, H:i', strtotime($pt->earliest_request)) : '—'; ?>
                                                    </td>
                                                    <td>
                                                        <a href="<?php echo base_url();?>app/pos/pos_patient/<?php echo htmlspecialchars($pt->patient_no);?>/<?php echo htmlspecialchars($pt->iop_id);?>" class="btn btn-sm btn-success" title="Bill all <?php echo $totalItems; ?> pending items for this patient">
                                                            <i class="fa fa-money"></i> Bill All (<?php echo $totalItems; ?>)
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php } } ?>
                                            <?php if (!isset($pending_patients) || count($pending_patients) == 0) { ?>
                                                <tr><td colspan="8" class="text-center text-muted"><i class="fa fa-check-circle text-success"></i> No patients awaiting payment</td></tr>
                                            <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <?php } ?>

                    <!-- Recent Invoices Table -->
                    <div class="row">
                        <section class="col-lg-12">
                            <div class="box box-primary">
                                <div class="box-header">
                                    <div class="pull-right box-tools">
                                        <a href="<?php echo base_url();?>app/pos" class="btn btn-success btn-sm"><i class="fa fa-plus"></i> New Invoice</a>
                                        <a href="<?php echo base_url();?>app/billing_history" class="btn btn-default btn-sm"><i class="fa fa-list"></i> All Invoices</a>
                                        <button class="btn btn-primary btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                    </div>
                                    <i class="fa fa-file-text-o"></i>
                                    <h3 class="box-title">Today's Invoices</h3>
                                </div>
                                <div class="box-body no-padding">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped">
                                        <thead><tr>
                                            <th>Invoice #</th><th>Patient No.</th><th>Patient Name</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Balance</th><th>Status</th><th>Action</th>
                                        </tr></thead>
                                        <tbody>
                                        <?php if (isset($recent_invoices) && is_array($recent_invoices)) { foreach($recent_invoices as $inv) {
                                            $total = isset($inv->total_amount) ? (float)$inv->total_amount : 0;
                                            $paid = isset($inv->amount_paid) ? (float)$inv->amount_paid : 0;
                                            $balance = $total - $paid;
                                            $rowClass = ($balance > 0.01) ? '' : 'success';
                                            // Determine payment status
                                            if ($balance <= 0.01) {
                                                $statusBadge = '<span class="badge badge-pill status-paid"><i class="fa fa-check"></i> PAID</span>';
                                            } elseif ($paid > 0) {
                                                $statusBadge = '<span class="badge badge-pill status-partial"><i class="fa fa-clock-o"></i> PARTIAL</span>';
                                            } else {
                                                $statusBadge = '<span class="badge badge-pill status-unpaid"><i class="fa fa-exclamation"></i> UNPAID</span>';
                                            }
                                        ?>
                                            <tr class="<?php echo $rowClass;?>">
                                                <td><strong><?php echo $inv->invoice_no;?></strong></td>
                                                <td><a href="<?php echo base_url();?>app/patient/view/<?php echo $inv->patient_no;?>" class="patient-link"><?php echo $inv->patient_no;?></a></td>
                                                <td><?php echo htmlspecialchars($inv->patient_name);?></td>
                                                <td class="text-right text-amount"><?php echo number_format($total, 2);?></td>
                                                <td class="text-right text-amount text-success"><?php echo number_format($paid, 2);?></td>
                                                <td class="text-right text-amount"><?php echo ($balance > 0.01) ? '<span class="text-danger">'.number_format($balance, 2).'</span>' : '<span class="text-success">0.00</span>';?></td>
                                                <td><?php echo $statusBadge;?></td>
                                                <td>
                                                    <a href="<?php echo base_url();?>app/billing_history/view/<?php echo $inv->invoice_no;?>" class="btn btn-xs btn-default btn-action" title="View"><i class="fa fa-eye"></i></a>
                                                    <?php if ($balance > 0.01) { ?>
                                                    <a href="<?php echo base_url();?>app/pos/pos_patient/<?php echo $inv->patient_no;?>" class="btn btn-xs btn-success btn-action" title="Collect Payment"><i class="fa fa-money"></i></a>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } } ?>
                                        <?php if (!isset($recent_invoices) || !is_array($recent_invoices) || count($recent_invoices) == 0) { ?>
                                            <tr><td colspan="8" class="text-center text-muted"><i class="fa fa-inbox"></i> No invoices today</td></tr>
                                        <?php } ?>
                                        </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                </section>
            </aside>
        </div>
        <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
        <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    </body>
</html>
