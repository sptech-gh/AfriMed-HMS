<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Patient Financial Ledger | HMS</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>dist/css/skins/skin-blue.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php'); ?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php'); ?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-book"></i> Patient Financial Ledger <small>Single Source of Truth</small></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url('app/dashboard'); ?>"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="<?php echo base_url('app/billing_reconciliation'); ?>">Reconciliation</a></li>
                <li class="active">Patient Ledger</li>
            </ol>
        </section>

        <section class="content">
            <!-- Search Form -->
            <div class="row">
                <div class="col-md-6">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-search"></i> Search Patient</h3>
                        </div>
                        <div class="box-body">
                            <form method="GET" action="<?php echo base_url('app/billing_reconciliation/patient_ledger'); ?>" class="form-inline">
                                <div class="form-group">
                                    <input type="text" name="patient_no" class="form-control" placeholder="Patient Number" value="<?php echo htmlspecialchars($patient_no); ?>">
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Search</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php if ($patient): ?>
                <div class="col-md-6">
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-user"></i> Patient Info</h3>
                        </div>
                        <div class="box-body">
                            <table class="table table-condensed">
                                <tr>
                                    <td><strong>Patient No:</strong></td>
                                    <td><?php echo htmlspecialchars($patient->patient_no); ?></td>
                                    <td><strong>Name:</strong></td>
                                    <td><?php echo htmlspecialchars(trim($patient->firstname . ' ' . $patient->lastname)); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Phone:</strong></td>
                                    <td><?php echo isset($patient->phone) ? htmlspecialchars($patient->phone) : '-'; ?></td>
                                    <td><strong>Insurance:</strong></td>
                                    <td>
                                        <?php 
                                        $ins = isset($patient->Insurance_comp) ? strtoupper(trim($patient->Insurance_comp)) : '';
                                        if (strpos($ins, 'NHIS') !== false) {
                                            echo '<span class="label label-success">NHIS</span>';
                                        } elseif ($ins && $ins !== 'NONE') {
                                            echo '<span class="label label-info">' . htmlspecialchars($ins) . '</span>';
                                        } else {
                                            echo '<span class="label label-default">CASH</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($patient_no): ?>
            <!-- Balance Summary -->
            <div class="row">
                <div class="col-md-4">
                    <div class="info-box <?php echo $balance > 0 ? 'bg-red' : ($balance < 0 ? 'bg-green' : 'bg-aqua'); ?>">
                        <span class="info-box-icon"><i class="fa fa-<?php echo $balance > 0 ? 'exclamation-triangle' : ($balance < 0 ? 'check' : 'balance-scale'); ?>"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Current Balance</span>
                            <span class="info-box-number">GHS <?php echo number_format(abs($balance), 2); ?></span>
                            <span class="progress-description">
                                <?php 
                                if ($balance > 0) echo 'Amount Owed by Patient';
                                elseif ($balance < 0) echo 'Credit Balance';
                                else echo 'Fully Settled';
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box bg-purple">
                        <span class="info-box-icon"><i class="fa fa-list"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Entries</span>
                            <span class="info-box-number"><?php echo count($ledger); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <a href="<?php echo base_url('app/billing_reconciliation'); ?>" class="btn btn-default btn-lg btn-block" style="margin-top:10px;">
                        <i class="fa fa-arrow-left"></i> Back to Reconciliation
                    </a>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-list-alt"></i> Financial Ledger</h3>
                        </div>
                        <div class="box-body table-responsive">
                            <?php if (empty($ledger)): ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No ledger entries found for this patient.
                            </div>
                            <?php else: ?>
                            <table class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Reference</th>
                                        <th>Description</th>
                                        <th class="text-right">Debit (Charge)</th>
                                        <th class="text-right">Credit (Payment)</th>
                                        <th class="text-right">Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ledger as $entry): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($entry->created_at)); ?></td>
                                        <td>
                                            <?php 
                                            $type_class = 'default';
                                            switch ($entry->reference_type) {
                                                case 'CHARGE': $type_class = 'danger'; break;
                                                case 'PAYMENT': $type_class = 'success'; break;
                                                case 'REFUND': $type_class = 'warning'; break;
                                                case 'ADJUSTMENT': $type_class = 'info'; break;
                                                case 'WAIVER': $type_class = 'primary'; break;
                                                case 'REVERSAL': $type_class = 'default'; break;
                                            }
                                            ?>
                                            <span class="label label-<?php echo $type_class; ?>"><?php echo $entry->reference_type; ?></span>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($entry->reference_no); ?></code></td>
                                        <td><?php echo htmlspecialchars($entry->description); ?></td>
                                        <td class="text-right <?php echo $entry->debit_amount > 0 ? 'text-danger' : ''; ?>">
                                            <?php echo $entry->debit_amount > 0 ? number_format($entry->debit_amount, 2) : '-'; ?>
                                        </td>
                                        <td class="text-right <?php echo $entry->credit_amount > 0 ? 'text-success' : ''; ?>">
                                            <?php echo $entry->credit_amount > 0 ? number_format($entry->credit_amount, 2) : '-'; ?>
                                        </td>
                                        <td class="text-right">
                                            <strong class="<?php echo $entry->running_balance > 0 ? 'text-danger' : ($entry->running_balance < 0 ? 'text-success' : ''); ?>">
                                                <?php echo number_format($entry->running_balance, 2); ?>
                                            </strong>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="active">
                                        <th colspan="6" class="text-right">Current Balance:</th>
                                        <th class="text-right">
                                            <strong class="<?php echo $balance > 0 ? 'text-danger' : ($balance < 0 ? 'text-success' : ''); ?>">
                                                GHS <?php echo number_format($balance, 2); ?>
                                            </strong>
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </section>
    </aside>
</div>

<script src="<?php echo base_url(); ?>jquery/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>dist/js/app.min.js"></script>
</body>
</html>
