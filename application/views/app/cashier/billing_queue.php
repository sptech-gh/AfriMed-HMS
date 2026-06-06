<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Billing Queue</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
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
                <h1>Billing Queue <small>Pending billable items</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url();?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="<?php echo base_url();?>app/cashier/payments">Cashier</a></li>
                    <li class="active">Billing Queue</li>
                </ol>
            </section>
            <section class="content">
                <?php echo isset($message) ? $message : ''; ?>

                <!-- Summary Boxes -->
                <div class="row">
                    <div class="col-lg-4 col-xs-6">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3><?php echo isset($summary['pending_count']) ? $summary['pending_count'] : 0; ?></h3>
                                <p>Pending Items</p>
                            </div>
                            <div class="icon"><i class="ion ion-clock"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-xs-6">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3>GHS <?php echo number_format(isset($summary['pending_amount']) ? $summary['pending_amount'] : 0, 2); ?></h3>
                                <p>Pending Amount</p>
                            </div>
                            <div class="icon"><i class="ion ion-cash"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-xs-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3>GHS <?php echo number_format(isset($summary['billed_today']) ? $summary['billed_today'] : 0, 2); ?></h3>
                                <p>Billed Today</p>
                            </div>
                            <div class="icon"><i class="ion ion-checkmark-circled"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Queue Table -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-list"></i> Pending Billable Items</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
					<?php $mode = isset($mode) ? (string)$mode : 'detail'; ?>
					<?php if ($mode === 'group') { ?>
					<table class="table table-hover table-striped">
						<thead>
							<tr>
								<th>#</th>
								<th>Patient</th>
								<th>Visit ID</th>
								<th>Payer</th>
								<th class="text-right">Items</th>
								<th class="text-right">Total</th>
								<th>Requested</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php if (isset($queue_groups) && count($queue_groups) > 0) { ?>
								<?php foreach ($queue_groups as $idx => $g) { ?>
								<?php
									$pRow = null;
									if (isset($patient_map) && is_array($patient_map) && isset($patient_map[(string)$g->patient_no])) {
										$pRow = $patient_map[(string)$g->patient_no];
									}
									$pName = $pRow && isset($pRow->patient_name) ? (string)$pRow->patient_name : '';
									$pPhone = $pRow && isset($pRow->phone) ? (string)$pRow->phone : '';
								?>
								<tr>
									<td><?php echo $idx + 1; ?></td>
									<td>
										<a href="<?php echo base_url();?>app/cashier/billing_queue?iop_id=<?php echo urlencode((string)$g->iop_id); ?>&patient_no=<?php echo urlencode((string)$g->patient_no); ?>">
											<?php echo htmlspecialchars((string)$g->patient_no, ENT_QUOTES, 'UTF-8'); ?>
										</a>
										<?php if (trim($pName) !== '') { ?>
											<br><small class="text-muted"><?php echo htmlspecialchars($pName, ENT_QUOTES, 'UTF-8'); ?></small>
										<?php } ?>
										<?php if (trim($pPhone) !== '') { ?>
											<br><small class="text-muted"><?php echo htmlspecialchars($pPhone, ENT_QUOTES, 'UTF-8'); ?></small>
										<?php } ?>
									</td>
									<td>
										<a href="<?php echo base_url();?>app/cashier/billing_queue?iop_id=<?php echo urlencode((string)$g->iop_id); ?>&patient_no=<?php echo urlencode((string)$g->patient_no); ?>">
											<?php echo htmlspecialchars((string)$g->iop_id, ENT_QUOTES, 'UTF-8'); ?>
										</a>
									</td>
									<td>
										<?php if ((string)$g->payer_type === 'NHIS') { ?>
											<span class="label label-success"><i class="fa fa-shield"></i> NHIS</span>
										<?php } elseif ((string)$g->payer_type === 'INSURANCE') { ?>
											<span class="label label-info">Insurance</span>
										<?php } elseif ((string)$g->payer_type === 'COMPANY') { ?>
											<span class="label label-primary">Company</span>
										<?php } else { ?>
											<span class="label label-default">Cash</span>
										<?php } ?>
									</td>
									<td class="text-right"><?php echo (int)$g->items_count; ?></td>
									<td class="text-right"><strong><?php echo number_format((float)$g->total_amount, 2); ?></strong></td>
									<td>
										<?php if (!empty($g->first_requested_at)) { echo date('M d H:i', strtotime((string)$g->first_requested_at)); } else { echo '-'; } ?>
									</td>
									<td>
										<a class="btn btn-xs btn-success" href="<?php echo base_url();?>app/cashier/bill_patient/<?php echo urlencode((string)$g->iop_id); ?>/<?php echo urlencode((string)$g->patient_no); ?>" onclick="return confirm('Create invoice for this patient/visit from all pending items?');">
											<i class="fa fa-file-text-o"></i> Bill
										</a>
									</td>
								</tr>
								<?php } ?>
							<?php } else { ?>
								<tr>
									<td colspan="8" class="text-center text-muted">
										<i class="fa fa-check-circle fa-2x"></i><br>
										No pending items in billing queue
									</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
					<?php } else { ?>
					<div style="padding:10px 10px 0 10px;">
						<a href="<?php echo base_url();?>app/cashier/billing_queue" class="btn btn-sm btn-default"><i class="fa fa-arrow-left"></i> Back to Patients</a>
						<?php if (!empty($filter_iop_id) && !empty($filter_patient_no)) { ?>
							<a class="btn btn-sm btn-success" href="<?php echo base_url();?>app/cashier/bill_patient/<?php echo urlencode((string)$filter_iop_id); ?>/<?php echo urlencode((string)$filter_patient_no); ?>" onclick="return confirm('Create invoice for this patient/visit from all pending items?');">
								<i class="fa fa-file-text-o"></i> Bill All Items
							</a>
						<?php } ?>
					</div>
					<table class="table table-hover table-striped">
						<thead>
							<tr>
								<th>#</th>
								<th>Patient</th>
								<th>Visit ID</th>
								<th>Type</th>
								<th>Item</th>
								<th class="text-right">Qty</th>
								<th class="text-right">Rate</th>
								<th class="text-right">Amount</th>
								<th>Payer</th>
								<th>Requested</th>
							</tr>
						</thead>
						<tbody>
							<?php if (isset($queue_items) && count($queue_items) > 0) { ?>
								<?php foreach ($queue_items as $idx => $item) { ?>
								<tr>
									<td><?php echo $idx + 1; ?></td>
									<td><?php echo htmlspecialchars((string)$item->patient_no, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string)$item->iop_id, ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<?php
										$typeColors = array(
											'CONSULTATION' => 'primary',
											'REGISTRATION' => 'info',
											'LAB' => 'warning',
											'PHARMACY' => 'success',
											'SONOGRAPHY' => 'default',
											'RADIOLOGY' => 'danger',
											'PROCEDURE' => 'danger',
											'ADMISSION' => 'primary',
											'SURGERY' => 'danger',
											'ROOM' => 'info',
											'OTHER' => 'default'
										);
										$color = isset($typeColors[$item->item_type]) ? $typeColors[$item->item_type] : 'default';
										?>
										<span class="label label-<?php echo $color; ?>"><?php echo htmlspecialchars((string)$item->item_type, ENT_QUOTES, 'UTF-8'); ?></span>
									</td>
									<td><?php echo htmlspecialchars($item->item_name, ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="text-right"><?php echo number_format((float)$item->quantity, 2); ?></td>
									<td class="text-right"><?php echo number_format((float)$item->unit_price, 2); ?></td>
									<td class="text-right"><strong><?php echo number_format((float)$item->net_amount, 2); ?></strong></td>
									<td>
										<?php if ($item->payer_type === 'NHIS') { ?>
											<span class="label label-success"><i class="fa fa-shield"></i> NHIS</span>
										<?php } elseif ($item->payer_type === 'INSURANCE') { ?>
											<span class="label label-info">Insurance</span>
										<?php } elseif ($item->payer_type === 'COMPANY') { ?>
											<span class="label label-primary">Company</span>
										<?php } else { ?>
											<span class="label label-default">Cash</span>
										<?php } ?>
									</td>
									<td><?php echo !empty($item->requested_at) ? date('M d H:i', strtotime((string)$item->requested_at)) : '-'; ?></td>
								</tr>
								<?php } ?>
							<?php } else { ?>
								<tr>
									<td colspan="10" class="text-center text-muted">
										No pending items for this patient/visit
									</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
					<?php } ?>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="callout callout-info">
                    <h4><i class="fa fa-info-circle"></i> About Billing Queue</h4>
                    <p>
                        The billing queue shows all services that have been ordered but not yet invoiced.
                        Services are automatically added to this queue when doctors order labs, prescribe medications,
                        or request procedures.
                    </p>
                </div>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
</body>
</html>
