<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Financial Ledger</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .debit { color: #dd4b39; }
        .credit { color: #00a65a; }
        .ledger-row:hover { background-color: #f5f5f5; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1>Financial Ledger <small>Double-entry accounting records</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url();?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="<?php echo base_url();?>app/cashier/payments">Cashier</a></li>
                    <li class="active">Ledger</li>
                </ol>
            </section>
            <section class="content">
                <?php echo isset($message) ? $message : ''; ?>

                <!-- Date Filter -->
                <div class="box box-primary">
                    <div class="box-body">
                        <form method="get" class="form-inline">
                            <div class="form-group" style="margin-right:15px">
                                <label>From:</label>
                                <input type="date" name="from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="form-group" style="margin-right:15px">
                                <label>To:</label>
                                <input type="date" name="to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
                            <a href="<?php echo base_url();?>app/cashier/ledger" class="btn btn-default">This Month</a>
                        </form>
                    </div>
                </div>

                <!-- Ledger Table -->
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-book"></i> Ledger Entries</h3>
                        <div class="box-tools pull-right">
                            <span class="label label-info"><?php echo count($entries); ?> entries</span>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr class="bg-primary">
                                    <th>Date</th>
                                    <th>Transaction ID</th>
                                    <th>Account</th>
                                    <th class="text-right">Debit</th>
                                    <th class="text-right">Credit</th>
                                    <th>Reference</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_debit = 0;
                                $total_credit = 0;
                                $account_map = array();
                                foreach ($accounts as $acct) {
                                    $account_map[$acct->account_id] = $acct->account_code . ' - ' . $acct->account_name;
                                }
                                
                                if (isset($entries) && count($entries) > 0) { 
                                    foreach ($entries as $entry) { 
                                        $total_debit += (float)$entry->debit_amount;
                                        $total_credit += (float)$entry->credit_amount;
                                ?>
                                <tr class="ledger-row">
                                    <td><?php echo date('Y-m-d', strtotime($entry->transaction_date)); ?></td>
                                    <td><small><?php echo $entry->transaction_id; ?></small></td>
                                    <td><?php echo isset($account_map[$entry->account_id]) ? $account_map[$entry->account_id] : 'Unknown'; ?></td>
                                    <td class="text-right debit">
                                        <?php echo (float)$entry->debit_amount > 0 ? number_format($entry->debit_amount, 2) : '-'; ?>
                                    </td>
                                    <td class="text-right credit">
                                        <?php echo (float)$entry->credit_amount > 0 ? number_format($entry->credit_amount, 2) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php if ($entry->reference_type && $entry->reference_no) { ?>
                                            <span class="label label-default"><?php echo $entry->reference_type; ?></span>
                                            <?php echo $entry->reference_no; ?>
                                        <?php } else { ?>
                                            -
                                        <?php } ?>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($entry->description, ENT_QUOTES, 'UTF-8'); ?></small></td>
                                </tr>
                                <?php } } else { ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No ledger entries found for this period</td>
                                </tr>
                                <?php } ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray">
                                    <th colspan="3" class="text-right">TOTALS:</th>
                                    <th class="text-right debit"><?php echo number_format($total_debit, 2); ?></th>
                                    <th class="text-right credit"><?php echo number_format($total_credit, 2); ?></th>
                                    <th colspan="2">
                                        <?php if (abs($total_debit - $total_credit) < 0.01) { ?>
                                            <span class="label label-success"><i class="fa fa-check"></i> Balanced</span>
                                        <?php } else { ?>
                                            <span class="label label-danger"><i class="fa fa-warning"></i> Unbalanced: <?php echo number_format($total_debit - $total_credit, 2); ?></span>
                                        <?php } ?>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Chart of Accounts -->
                <div class="box box-default collapsed-box">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-sitemap"></i> Chart of Accounts</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Account Name</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $acct) { ?>
                                <tr>
                                    <td><code><?php echo $acct->account_code; ?></code></td>
                                    <td><?php echo $acct->account_name; ?></td>
                                    <td>
                                        <?php
                                        $typeColors = array(
                                            'ASSET' => 'primary',
                                            'LIABILITY' => 'warning',
                                            'EQUITY' => 'info',
                                            'REVENUE' => 'success',
                                            'EXPENSE' => 'danger'
                                        );
                                        $color = isset($typeColors[$acct->account_type]) ? $typeColors[$acct->account_type] : 'default';
                                        ?>
                                        <span class="label label-<?php echo $color; ?>"><?php echo $acct->account_type; ?></span>
                                    </td>
                                    <td><small><?php echo $acct->description; ?></small></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
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
