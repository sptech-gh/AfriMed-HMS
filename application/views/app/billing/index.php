<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Billing Dashboard | HMS</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>assets/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>assets/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>assets/dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>assets/dist/css/skins/skin-blue.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>assets/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue sidebar-mini">
<div class="wrapper">
    <?php require_once(APPPATH.'views/include/header.php'); ?>
    <?php require_once(APPPATH.'views/include/sidebar.php'); ?>
    
    <div class="content-wrapper">
        <section class="content-header">
            <h1><i class="fa fa-money"></i> Billing Dashboard</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url('app/dashboard'); ?>"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li class="active">Billing</li>
            </ol>
        </section>
        
        <section class="content">
            <?php if($this->session->flashdata('success')): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?php echo $this->session->flashdata('success'); ?>
            </div>
            <?php endif; ?>
            
            <?php if($this->session->flashdata('error')): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?php echo $this->session->flashdata('error'); ?>
            </div>
            <?php endif; ?>
            
            <!-- Summary Cards -->
            <div class="row">
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-aqua">
                        <div class="inner">
                            <h3>GHS <?php echo number_format($total_billing_today ?? 0, 2); ?></h3>
                            <p>Total Billing Today</p>
                        </div>
                        <div class="icon"><i class="fa fa-money"></i></div>
                        <a href="<?php echo base_url('app/billing/searchPatient'); ?>" class="small-box-footer">
                            View Details <i class="fa fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-yellow">
                        <div class="inner">
                            <h3><?php echo $pending_payments ?? 0; ?></h3>
                            <p>Pending Payments</p>
                        </div>
                        <div class="icon"><i class="fa fa-clock-o"></i></div>
                        <a href="<?php echo base_url('app/pos'); ?>" class="small-box-footer">
                            Collect Payment <i class="fa fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-green">
                        <div class="inner">
                            <h3><?php echo $nhis_claims_today ?? 0; ?></h3>
                            <p>NHIS Claims Today</p>
                        </div>
                        <div class="icon"><i class="fa fa-shield"></i></div>
                        <a href="<?php echo base_url('app/nhis_claims/claimit'); ?>" class="small-box-footer">
                            View Claims <i class="fa fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-xs-6">
                    <div class="small-box bg-red">
                        <div class="inner">
                            <h3><?php echo $refunds_pending ?? 0; ?></h3>
                            <p>Pending Refunds</p>
                        </div>
                        <div class="icon"><i class="fa fa-undo"></i></div>
                        <a href="<?php echo base_url('app/ebilling/refund_management'); ?>" class="small-box-footer">
                            Process Refunds <i class="fa fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-bolt"></i> Quick Actions</h3>
                        </div>
                        <div class="box-body">
                            <a href="<?php echo base_url('app/billing/searchPatient'); ?>" class="btn btn-primary btn-lg">
                                <i class="fa fa-search"></i> Search Patient
                            </a>
                            <a href="<?php echo base_url('app/pos'); ?>" class="btn btn-success btn-lg">
                                <i class="fa fa-credit-card"></i> Point of Sale
                            </a>
                            <a href="<?php echo base_url('app/billing/smart_billing'); ?>" class="btn btn-info btn-lg">
                                <i class="fa fa-magic"></i> Smart Billing
                            </a>
                            <a href="<?php echo base_url('app/nhis_claims/claimit'); ?>" class="btn btn-warning btn-lg">
                                <i class="fa fa-shield"></i> NHIS Claims
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Revenue Summary -->
            <div class="row">
                <div class="col-md-6">
                    <div class="box box-success">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-bar-chart"></i> Revenue by Payment Type</h3>
                        </div>
                        <div class="box-body">
                            <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Payment Type</th>
                                        <th>Amount (GHS)</th>
                                        <th>Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="label label-success">Cash</span></td>
                                        <td><?php echo number_format($cash_revenue ?? 0, 2); ?></td>
                                        <td><?php echo $cash_count ?? 0; ?></td>
                                    </tr>
                                    <tr>
                                        <td><span class="label label-info">NHIS</span></td>
                                        <td><?php echo number_format($nhis_revenue ?? 0, 2); ?></td>
                                        <td><?php echo $nhis_count ?? 0; ?></td>
                                    </tr>
                                    <tr>
                                        <td><span class="label label-warning">Insurance</span></td>
                                        <td><?php echo number_format($insurance_revenue ?? 0, 2); ?></td>
                                        <td><?php echo $insurance_count ?? 0; ?></td>
                                    </tr>
                                    <tr class="info">
                                        <th>Total</th>
                                        <th><?php echo number_format(($cash_revenue ?? 0) + ($nhis_revenue ?? 0) + ($insurance_revenue ?? 0), 2); ?></th>
                                        <th><?php echo ($cash_count ?? 0) + ($nhis_count ?? 0) + ($insurance_count ?? 0); ?></th>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-pie-chart"></i> Revenue by Department</h3>
                        </div>
                        <div class="box-body">
                            <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Amount (GHS)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(!empty($department_revenue)): ?>
                                    <?php foreach($department_revenue as $dept): ?>
                                    <tr>
                                        <td><?php echo $dept->department ?? 'General'; ?></td>
                                        <td><?php echo number_format($dept->amount ?? 0, 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td>OPD</td>
                                        <td><?php echo number_format($opd_revenue ?? 0, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Laboratory</td>
                                        <td><?php echo number_format($lab_revenue ?? 0, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Pharmacy</td>
                                        <td><?php echo number_format($pharmacy_revenue ?? 0, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Radiology</td>
                                        <td><?php echo number_format($radiology_revenue ?? 0, 2); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Transactions -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-list"></i> Recent Transactions</h3>
                        </div>
                        <div class="box-body">
                            <div class="table-responsive">
                            <table id="transactionsTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Patient</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>NHIS Covered</th>
                                        <th>Patient Pays</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(!empty($recent_transactions)): ?>
                                    <?php foreach($recent_transactions as $txn): ?>
                                    <tr>
                                        <td><?php echo $txn->invoice_no; ?></td>
                                        <td><?php echo ($txn->firstname ?? '') . ' ' . ($txn->lastname ?? ''); ?></td>
                                        <td>
                                            <?php if(($txn->payer_type ?? 'CASH') == 'NHIS'): ?>
                                            <span class="label label-info">NHIS</span>
                                            <?php else: ?>
                                            <span class="label label-default">CASH</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>GHS <?php echo number_format($txn->total_amount ?? 0, 2); ?></td>
                                        <td>GHS <?php echo number_format($txn->nhis_covered_amount ?? 0, 2); ?></td>
                                        <td>GHS <?php echo number_format($txn->patient_payable_amount ?? $txn->total_amount ?? 0, 2); ?></td>
                                        <td>
                                            <?php 
                                            $status = strtolower($txn->payment_type ?? 'pending');
                                            $status_class = 'default';
                                            if($status == 'paid') $status_class = 'success';
                                            elseif($status == 'partial') $status_class = 'warning';
                                            ?>
                                            <span class="label label-<?php echo $status_class; ?>"><?php echo strtoupper($status); ?></span>
                                        </td>
                                        <td><?php echo date('d M Y H:i', strtotime($txn->dDate)); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    
    <?php require_once(APPPATH.'views/include/footer.php'); ?>
</div>

<script src="<?php echo base_url(); ?>assets/jquery/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>assets/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>assets/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo base_url(); ?>assets/datatables/dataTables.bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>assets/dist/js/app.min.js"></script>
<script>
$(function() {
    $('#transactionsTable').DataTable({
        order: [[7, 'desc']],
        pageLength: 10,
        language: { emptyTable: "No transactions found" }
    });
});
</script>
</body>
</html>
