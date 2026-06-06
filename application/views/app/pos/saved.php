<!DOCTYPE html>
<html>

<head>

	<head>

		<meta charset="UTF-8">
		<title>Hebrew Medical Center</title>
		<meta content="width=device-width, initial-scale=1.0" name="viewport">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">



		<link href="<?php echo base_url() ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
		<link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
		<link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
		<link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />

		<link href="<?php echo base_url(); ?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />

		<!----------BOOTSTRAP DATEPICKER----------------------------->
		<link rel="stylesheet" href="<?php echo base_url(); ?>public/datepicker/css/datepicker.css">
		<!---------------------------------------------------------->


		<!-- jQuery UI CSS -->
		<link rel="stylesheet" href="<?php echo base_url(); ?>public/css/jQueryUI/jquery-ui-1.10.3.custom.min.css">

		<style>
			.ui-autocomplete {
				position: absolute;
				cursor: default;
				z-index: 999999999 !important;
			}
			/* Navigation improvements */
			.pos-nav-header {
				background: linear-gradient(135deg, #00a65a 0%, #008d4c 100%);
				padding: 12px 20px;
				margin-bottom: 15px;
				border-radius: 0 0 8px 8px;
				box-shadow: 0 2px 8px rgba(0,0,0,0.15);
			}
			.pos-nav-header .breadcrumb {
				background: transparent;
				margin: 0;
				padding: 0;
			}
			.pos-nav-header .breadcrumb > li > a {
				color: rgba(255,255,255,0.85);
				text-decoration: none;
			}
			.pos-nav-header .breadcrumb > li > a:hover {
				color: #fff;
			}
			.pos-nav-header .breadcrumb > .active {
				color: #fff;
				font-weight: 600;
			}
			.pos-nav-header .breadcrumb > li + li:before {
				color: rgba(255,255,255,0.5);
				content: "\f105";
				font-family: FontAwesome;
			}
			.quick-nav-btn {
				margin-right: 8px;
				border-radius: 20px;
				padding: 6px 15px;
				font-size: 13px;
				transition: all 0.2s ease;
			}
			.quick-nav-btn:hover {
				transform: translateY(-2px);
				box-shadow: 0 4px 12px rgba(0,0,0,0.2);
			}
			.page-title-pos {
				color: #fff;
				font-size: 18px;
				font-weight: 600;
				margin: 0 0 5px 0;
			}
			.invoice-badge {
				background: rgba(255,255,255,0.2);
				padding: 4px 12px;
				border-radius: 15px;
				color: #fff;
				font-size: 13px;
				margin-left: 10px;
			}
			.back-float-btn {
				position: fixed;
				bottom: 25px;
				left: 25px;
				z-index: 9999;
				width: 50px;
				height: 50px;
				border-radius: 50%;
				background: #00a65a;
				color: white;
				border: none;
				box-shadow: 0 4px 15px rgba(0,166,90,0.4);
				cursor: pointer;
				transition: all 0.3s ease;
				display: flex;
				align-items: center;
				justify-content: center;
				text-decoration: none;
			}
			.back-float-btn:hover {
				background: #008d4c;
				transform: scale(1.1);
				box-shadow: 0 6px 20px rgba(0,166,90,0.5);
				color: white;
			}
			.back-float-btn i {
				font-size: 20px;
			}
		</style>


		<!-- scrollbar -->
		<link rel="stylesheet" href="<?php echo base_url() ?>public/scrollbar/jquery.mCustomScrollbar.css">
		<!-- Google CDN jQuery with fallback to local -->
		<script src="<?php echo base_url() ?>public/scrollbar/jquery.min.js"></script>
		<script>
			window.jQuery || document.write('<script src="<?php echo base_url() ?>public/scrollbar/js/minified/jquery-1.11.0.min.js"><\/script>')
		</script>

		<!-- custom scrollbar plugin -->
		<link rel="stylesheet" href="<?php echo base_url() ?>public/scrollbar/style.css">
		<script src="<?php echo base_url() ?>public/scrollbar/jquery.mCustomScrollbar.concat.min.js"></script>

		<script>
			(function($) {
				$(window).load(function() {

					$("#content-1").mCustomScrollbar({
						autoHideScrollbar: true,
						theme: "rounded"
					});

				});
			})(jQuery);
		</script>
		<!-- scrollbar -->


		<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
		<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
		<!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
          <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
        <![endif]-->
	</head>

<body class="skin-blue">
	<!-- header logo: style can be found in header.less -->
	<?php require_once(APPPATH . 'views/include/header.php'); ?>
	
	<!-- Navigation Header with Breadcrumbs -->
	<div class="pos-nav-header">
		<div class="container-fluid">
			<div class="row">
				<div class="col-md-6">
					<h4 class="page-title-pos">
						<i class="fa fa-check-circle"></i> Invoice Saved
						<span class="invoice-badge">
							<i class="fa fa-file-text-o"></i> <?php echo isset($headerInv->invoice_no) ? $headerInv->invoice_no : ''; ?>
						</span>
					</h4>
					<ol class="breadcrumb">
						<li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
						<li><a href="<?php echo base_url(); ?>app/unified_billing"><i class="fa fa-money"></i> Billing</a></li>
						<li><a href="<?php echo base_url(); ?>app/pos"><i class="fa fa-shopping-cart"></i> POS</a></li>
						<li class="active"><?php echo isset($patientInfo->name) ? $patientInfo->name : 'Invoice'; ?></li>
					</ol>
				</div>
				<div class="col-md-6 text-right" style="padding-top: 8px;">
					<a href="<?php echo base_url(); ?>app/dashboard" class="btn btn-default quick-nav-btn">
						<i class="fa fa-home"></i> Dashboard
					</a>
					<a href="<?php echo base_url(); ?>app/pos" class="btn btn-primary quick-nav-btn">
						<i class="fa fa-plus"></i> New Invoice
					</a>
					<a href="<?php echo base_url(); ?>app/unified_billing" class="btn btn-info quick-nav-btn">
						<i class="fa fa-list"></i> All Invoices
					</a>
					<?php if (isset($patientInfo->patient_no)): ?>
					<a href="<?php echo base_url(); ?>app/patient_history/<?php echo $patientInfo->patient_no; ?>" class="btn btn-warning quick-nav-btn">
						<i class="fa fa-folder-open"></i> Patient File
					</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	
	<form method="post" action="<?php echo base_url() ?>app/billing/update_invoice" onSubmit="return validate_form();">

		<section class="content" style="padding-top: 5px;">

<?php if (!isset($patientDetials) || $patientDetials === null || !isset($headerInv) || $headerInv === null): ?>
			<div class="row">
				<div class="col-md-12">
					<div class="alert alert-danger">
						<i class="fa fa-exclamation-triangle"></i> <strong>Error:</strong> Invoice or patient data not found. 
						<a href="<?php echo base_url();?>app/pos" class="btn btn-primary btn-sm">Go to POS</a>
					</div>
				</div>
			</div>
		</section>
	</form>
</body>
</html>
<?php return; endif; ?>

			<div class="row">
				<div class="col-md-3">
					<div class="box box-primary">
						<div class="box-header">
						</div>

						<div class="box-content">
							<div class="box-body table-responsive no-padding">
								<table width="100%" cellpadding="3" cellspacing="3">
									<tr>
										<td width="15%" valign="top" align="center">
											<?php
											$picture = "avatar.png";
											if (isset($patientDetials->picture) && $patientDetials->picture) {
												$picture = $patientDetials->picture;
											}
											?>
											<img src="<?php echo base_url(); ?>public/patient_picture/<?php echo $picture; ?>" class="img-rounded" width="86" height="81">
										</td>
										<td>
											<table cellpadding="2" width="100%">
												<tr>
													<td><strong>Patient No.</strong></td>
													<td><?php echo $patientDetials->patient_no ?></td>
												</tr>
												<tr>
													<td><strong>IOP No.</strong></td>
													<td><?php echo $patientDetials->IO_ID ?></td>
												</tr>
												<tr>
													<td colspan="2"><strong>Patient Name.</strong></td>
												</tr>
												<tr>
													<td colspan="2"><?php echo $patientDetials->patient ?></td>
												</tr>
											</table>
										</td>
									</tr>
								</table>
								<input type="hidden" name="opd_no" id="opd_no" value="<?php echo $patientDetials->IO_ID ?>">
								<input type="hidden" name="patient_no" id="patient_no" value="<?php echo $patientDetials->patient_no ?>">
							</div>
						</div>
						<div class="box-footer">
							<script>
								function setTitle(val) {
									if (val == "cash") {
										document.getElementById("credit").style.display = "none";
										document.getElementById("insurance").style.display = "none";
										document.getElementById("totalAmount").style.display = "inline";
										document.getElementById("amountPaid").style.display = "inline";
										document.getElementById("change").style.display = "inline";
									} else if (val == "credit") {
										document.getElementById("credit").style.display = "inline";
										document.getElementById("insurance").style.display = "none";
										document.getElementById("totalAmount").style.display = "none";
										document.getElementById("amountPaid").style.display = "none";
										document.getElementById("change").style.display = "none";
									} else if (val == "insurance") {
										document.getElementById("credit").style.display = "none";
										document.getElementById("insurance").style.display = "inline";
										document.getElementById("totalAmount").style.display = "none";
										document.getElementById("amountPaid").style.display = "none";
										document.getElementById("change").style.display = "none";
									}
								}
							</script>

							<?php

							$receipt_no = $receipt_no2->receipt_no;
							$receipt_no2 = $receipt_no2->receipt_no;
							if (strlen($receipt_no) == 1) {
								$receipt_no = "00000" . $receipt_no;
							} else if (strlen($receipt_no) == 2) {
								$receipt_no = "0000" . $receipt_no;
							} else if (strlen($receipt_no) == 3) {
								$receipt_no = "000" . $receipt_no;
							} else if (strlen($receipt_no) == 4) {
								$receipt_no = "00" . $receipt_no;
							} else if (strlen($receipt_no) == 5) {
								$receipt_no = "0" . $receipt_no;
							} else if (strlen($receipt_no) == 6) {
								$receipt_no = $receipt_no;
							}
							?>
							<div class="form-group">
								<label for="exampleInputEmail1">Date</label>
								<input type="text" value="<?php echo $headerInv->dDate; ?>" readonly name="dDate22222" id="dDate22222" class="form-control input-sm">
							</div>
							<div class="form-group">
								<label for="exampleInputEmail1">Invoice No.</label>
								<input type="text" value="<?php echo $headerInv->invoice_no; ?>" readonly name="invoiceno" id="invoiceno" class="form-control input-sm">
							</div>

							<input type="hidden" value="<?php echo $OR_number; ?>" readonly name="receipt_no" id="receipt_no" class="form-control input-sm">


							<?php
							// Calculate actual item count from rendered items
							$itemCount = 0;
							if (isset($detailsInv) && is_array($detailsInv)) {
								foreach ($detailsInv as $item) {
									$itemCount++;
								}
							}
							?>
							<div class="form-group">
								<label for="exampleInputEmail1">Total Items</label>
								<input type="text" readonly name="hdnrowcnt" id="hdnrowcnt" value="<?php echo $itemCount; ?>" class="form-control input-sm">
							</div>

							<div class="form-group">
								<label for="exampleInputEmail1">Sub Total</label>
								<input type="text" readonly name="nGross" id="nGross" placeholder="0.00" value="<?php echo $headerInv->sub_total; ?>" class="form-control input-sm">
							</div>

							<script>
								function validate_discount(val) {
									if (val == "") {
										alert('Invalid discount');
										document.getElementById("discount").value = "0";
									}
									getGross();
								}
							</script>

							<div class="form-group">
								<label for="exampleInputEmail1">Discount</label>
								<input type="text" name="discount" id="discount" value="<?php echo $headerInv->discount; ?>" onKeyUp="validate_discount(this.value)" class="form-control input-sm" onkeypress="return isNumberKey(event)">
							</div>

							<div class="form-group">
								<label for="exampleInputEmail1">TOTAL AMOUNT</label>
								<input type="text" value="<?php echo $headerInv->total_amount; ?>" readonly name="total_amount" id="total_amount" class="form-control input-sm">
							</div>

							<div class="form-group">
								<label for="exampleInputEmail1">Reason for Discount</label>
								<select name="reason_dicount" id="reason_dicount" class="form-control input-sm">
									<option value="">- Reason for Discount -</option>
									<?php foreach ($reason_dicount as $reason_dicount) { ?>
										<option value="<?php echo $reason_dicount->param_id ?>" <?php if ($headerInv->reason_discount == $reason_dicount->param_id) {
																									echo "selected";
																								} ?>><?php echo $reason_dicount->cValue ?></option>
									<?php } ?>
								</select>
							</div>

							<div class="form-group">
								<label for="exampleInputEmail1">Remarks</label>
								<textarea placeholder="Remarks" class="form-control input-sm" name="remarks" id="remarks" rows="3"><?php echo $headerInv->remarks; ?></textarea>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-9">
				<?php
				$_posStatus = isset($pos_payment_status) ? $pos_payment_status : 'UNPAID';
				$_posPaid = isset($pos_paid_amount) ? (float)$pos_paid_amount : 0;
				$_posBal = isset($pos_balance_due) ? (float)$pos_balance_due : 0;
				if ($_posStatus === 'PAID') {
					$_posBadge = '<span class="label label-success" style="font-size:13px;"><i class="fa fa-check-circle"></i> PAID</span>';
				} else if ($_posStatus === 'PARTIAL') {
					$_posBadge = '<span class="label label-warning" style="font-size:13px;"><i class="fa fa-adjust"></i> PARTIAL — Balance: '.number_format($_posBal,2).'</span>';
				} else {
					$_posBadge = '<span class="label label-danger" style="font-size:13px;"><i class="fa fa-exclamation-circle"></i> UNPAID</span>';
				}
				?>
				<div style="margin-bottom:8px;">
					<strong>Payment Status:</strong> <?php echo $_posBadge; ?>
					<?php if ($_posPaid > 0) { ?>
					&nbsp; <span class="text-muted">Paid: <?php echo number_format($_posPaid, 2); ?></span>
					<?php } ?>
				</div>
				<div class="nav-tabs-custom">
					<ul class="nav nav-tabs">
						<li class="active"><a href="#tab_1" data-toggle="tab"><strong>Billing List</strong></a></li>
						<!--<li><a href="#tab_2" data-toggle="tab">Header Details</a></li>-->
					</ul>
					<div class="tab-content">
						<div class="tab-pane active" id="tab_1">
							<?php echo $message; ?>
								<div class="alt2" dir="ltr" style="
											margin: 0px;
											padding: 0px;
											border: 0px solid #919b9c;
											width: 100%;
											height: 390px;
											text-align: left;
											overflow: auto">
									<table id="myTable" width="100%" cellpadding="2" cellspacing="2">
										<thead>
											<tr style="border-bottom:1px #999 solid; border-collapse:collapse; white-space:nowrap;">
												<th width="3%">No.</th>
												<th width="30%">Particular Name</th>
												<th width="5%">Type</th>
												<th width="7%">Qty</th>
												<th width="8%">Rate</th>
												<th width="10%">Amount</th>
												<th width="15%">Note</th>
												<th width="15%">Med Info</th>
												<th width="3%"></th>
											</tr>
										</thead>
										<tbody>
											<?php
											$num = 0;
											foreach ($detailsInv as $detailsInv) {
												$num = $num + 1;
												$_dosage      = isset($detailsInv->dosage) ? htmlspecialchars($detailsInv->dosage) : '';
												$_advice      = isset($detailsInv->advice) ? htmlspecialchars($detailsInv->advice) : '';
												$_instruction = isset($detailsInv->instruction) ? htmlspecialchars($detailsInv->instruction) : '';
												$_frequency   = isset($detailsInv->frequency) ? htmlspecialchars($detailsInv->frequency) : '';
												$_days        = isset($detailsInv->days) ? (int)$detailsInv->days : 0;
												$_itemType    = isset($detailsInv->item_type) ? (string)$detailsInv->item_type : '';
												$_srcModule   = isset($detailsInv->source_module) ? strtoupper(trim((string)$detailsInv->source_module)) : '';
												$_srcRef      = isset($detailsInv->source_ref) ? (string)$detailsInv->source_ref : '';
												// Determine type primarily from authoritative billing line meta
												$_isMedicine = ($_itemType === 'medicine' || !empty($_dosage) || !empty($_frequency) || !empty($_advice));
												$_typeLabel = '<span class="label label-info">Service</span>';
												if ($_srcModule === 'PHARMACY') {
													$_typeLabel = '<span class="label label-success">Medicine</span>';
												} elseif (in_array($_srcModule, array('LABORATORY','LAB_TEST'), true) || strpos($_srcRef, 'iop_laboratory:') === 0) {
													$_typeLabel = '<span class="label label-warning">Lab</span>';
												} elseif (in_array($_srcModule, array('PROCEDURE','SONOGRAPHY','IPD_ROOM'), true)) {
													$_typeLabel = '<span class="label label-primary">Service</span>';
												} elseif ($_isMedicine) {
													$_typeLabel = '<span class="label label-success">Medicine</span>';
												}
												// Build Med Info display
												$_medInfoParts = [];
												if ($_isMedicine) {
													$_rxParts = array_filter([$_dosage, $_frequency, $_days > 0 ? $_days.' days' : '']);
													if (!empty($_rxParts)) $_medInfoParts[] = '<strong>Rx:</strong> ' . implode(' | ', $_rxParts);
													if (!empty($_advice)) $_medInfoParts[] = '<strong>Advice:</strong> ' . $_advice;
													if (!empty($_instruction)) $_medInfoParts[] = '<strong>Note:</strong> ' . $_instruction;
												}
												$_medInfoHtml = $_isMedicine ? (empty($_medInfoParts) ? '<span class="text-muted">See Rx</span>' : '<small>' . implode('<br>', $_medInfoParts) . '</small>') : '<span class="text-muted">N/A</span>';
											?>
												<tr>
													<td><input type="hidden" name="isPackage<?php echo $num; ?>" id="isPackage<?php echo $num; ?>" value="<?php echo $detailsInv->isPackage ?>"><input type="hidden" name="item_type<?php echo $num; ?>" id="item_type<?php echo $num; ?>" value="<?php echo $_isMedicine ? 'medicine' : 'particular'; ?>"><input type="text" size="7" style="width:98%; background-color:#F9F9f9; border:1px solid #ccc; text-align:right" name="id<?php echo $num; ?>" id="id<?php echo $num; ?>" value="<?php echo $num; ?>. " readonly="true"></td>
													<td><input type="text" size="7" style="width:98%; background-color:#F9F9f9; border:1px solid #ccc;" name="bill_name<?php echo $num; ?>" id="bill_name<?php echo $num; ?>" value="<?php echo htmlspecialchars($detailsInv->bill_name) ?>" readonly="true"></td>
													<td><?php echo $_typeLabel; ?></td>
													<td><input type="text" size="7" style="width:98%; text-align:right" name="qty<?php echo $num; ?>" id="qty<?php echo $num; ?>" class="<?php echo $num; ?>" value="<?php echo $detailsInv->qty ?>" onBlur="return validate_input(this.className,'qty')" onkeyup="validate_gross(this.className,'qty')" onkeypress="return isNumberKey(event)"></td>
													<td><input type="text" size="7" style="width:98%; text-align:right" name="rate<?php echo $num; ?>" id="rate<?php echo $num; ?>" class="<?php echo $num; ?>" value="<?php echo $detailsInv->rate ?>" onBlur="return validate_input(this.className,'rate')" onkeyup="validate_gross(this.className,'rate')" onkeypress="return isNumberKey(event)"></td>
													<td><input type="text" size="7" style="width:98%; background-color:#F9F9f9; border:1px solid #ccc; text-align:right" name="amount<?php echo $num; ?>" id="amount<?php echo $num; ?>" value="<?php echo $detailsInv->amount ?>" readonly="true"></td>
													<td><input type="text" size="7" style="width:98%;" name="note<?php echo $num; ?>" id="note<?php echo $num; ?>" value="<?php echo htmlspecialchars($detailsInv->note) ?>"></td>
													<td><?php echo $_medInfoHtml; ?><input type="hidden" name="dosage<?php echo $num; ?>" id="dosage<?php echo $num; ?>" value="<?php echo $_dosage; ?>"><input type="hidden" name="advice<?php echo $num; ?>" id="advice<?php echo $num; ?>" value="<?php echo $_advice; ?>"><input type="hidden" name="instruction<?php echo $num; ?>" id="instruction<?php echo $num; ?>" value="<?php echo $_instruction; ?>"><input type="hidden" name="frequency<?php echo $num; ?>" value="<?php echo $_frequency; ?>"><input type="hidden" name="days<?php echo $num; ?>" value="<?php echo $_days; ?>"></td>
													<td><img src="<?php echo base_url() ?>public/img/b_drop.png" onclick="deleteRow(this)" style="cursor:pointer;"></td>
												</tr>
											<?php } ?>
										</tbody>
									</table>


								</div>
							</div>
							<!-- <div class="tab-pane" id="tab_2">
                                aaa
                                </div>-->
						</div>
					</div>
				</div>
				<div class="col-md-9">
					<div class="box box-primary">
						<div class="box-body">
							<a class="btn btn-app" href="<?php echo base_url() ?>app/pos/saved/<?php echo url_safe_id($patientDetials->IO_ID) ?>/<?php echo $patientDetials->patient_no ?>/<?php echo url_safe_id($headerInv->invoice_no); ?>"><i class="fa fa-refresh"></i> Refresh</a>
							<a class="btn btn-app" href="<?php echo base_url() ?>app/pos/"><i class="fa fa-file-o"></i> New Invoice</a>
							<a class="btn btn-app" href="<?php echo base_url() ?>app/billing/final_clearance/<?php echo url_safe_id($patientDetials->IO_ID) ?>/<?php echo $patientDetials->patient_no ?>" onClick="return confirm('Perform Final System Clearance? This will only succeed if Clinical + Medication clearance are completed and billing is settled.');"><i class="fa fa-check-circle"></i> Final Clearance</a>
							<a class="btn btn-app" data-toggle="modal" data-target="#doctorFeeModal"><i class="fa fa-user-md"></i> Doctor's Fee</a>
							<?php if ($OR_number == "-") { ?>
								<a class="btn btn-app" data-toggle="modal" data-target="#myModal"><i class="fa fa-plus"></i> Add Item</a>
								<a href="#" class="btn btn-app" onClick="return getPatientMedication()"><i class="fa fa-hand-o-down"></i> 1-Click Billed</a>
								<button type="submit" class="btn btn-app"><i class="fa fa-save"></i> Save</button>
								<a class="btn btn-app" data-toggle="modal" data-target="#paymentModal"><i class="fa fa-credit-card"></i> Payment</a>
								<a class="btn btn-app" href="<?php echo base_url() ?>app/opd/printInv/<?php echo $getOPDPatient->IO_ID ?>/<?php echo $patientInfo->patient_no ?>/<?php echo $headerInv->invoice_no ?>" target="_blank"><i class="fa fa-print"></i> Print Invoice</a>
								<a class="btn btn-app" onClick="alert('Invoice not already paid. Print Receipt disable.');"><i class="fa fa-print"></i> Print Receipt</a>
							<?php } else { ?>
								<a class="btn btn-app" onClick="alert('Invoice has already receipt.You cannot modify this transaction.');"><i class="fa fa-plus"></i> Add Item</a>
								<a class="btn btn-app" onClick="alert('Invoice has already receipt.You cannot modify this transaction.');"><i class="fa fa-hand-o-down"></i> 1-Click Billed</a>
								<a class="btn btn-app" onClick="alert('Invoice has already receipt.You cannot modify this transaction.');"><i class="fa fa-save"></i> Save</a>
								<?php if (isset($pos_payment_status) && strtoupper(trim((string)$pos_payment_status)) !== 'PAID') { ?>
									<a class="btn btn-app" data-toggle="modal" data-target="#paymentModal"><i class="fa fa-credit-card"></i> Payment</a>
								<?php } else { ?>
									<a class="btn btn-app" onClick="alert('Invoice is already fully paid.');"><i class="fa fa-credit-card"></i> Payment</a>
								<?php } ?>
								<a class="btn btn-app" href="<?php echo base_url() ?>app/opd/printInv/<?php echo $getOPDPatient->IO_ID ?>/<?php echo $patientInfo->patient_no ?>/<?php echo $headerInv->invoice_no ?>" target="_blank"><i class="fa fa-print"></i> Print Invoice</a>
								<a class="btn btn-app" href="<?php echo base_url() ?>app/opd/printOR/<?php echo $getOPDPatient->IO_ID ?>/<?php echo $patientInfo->patient_no ?>/<?php echo $headerInv->invoice_no ?>" target="_blank"><i class="fa fa-print"></i> Print Receipt</a>
							<?php } ?>



						</div>
					</div>
				</div>
			</div>


		</section><!-- /.content -->
	</form>


	<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
	<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js" type="text/javascript"></script>
	<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js" type="text/javascript"></script>

	<!-- BDAY -->
	<script src="<?php echo base_url(); ?>public/datepicker/js/jquery-1.9.1.min.js"></script>
	<script src="<?php echo base_url(); ?>public/datepicker/js/bootstrap-datepicker.js"></script>


	<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
	<!-- jQuery UI -->
	<script src="<?php echo base_url(); ?>public/js/jquery-ui-1.10.3.min.js"></script>

	<script type="text/javascript">
		// When the document is ready
		$(document).ready(function() {

			$('#cFrom').datepicker({
				//format: "dd/mm/yyyy"
				format: "yyyy-mm-dd"
			});

			$('#cTo').datepicker({
				//format: "dd/mm/yyyy"
				format: "yyyy-mm-dd"
			});

		});
	</script>
	<!-- END BDAY -->








	<!-- / patientListModal modal -->
	<div class="modal fade" id="patientListModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
					<h4 class="modal-title" id="myModalLabel">Search Patient</h4>
				</div>
				<div class="modal-body">

					<script language="javascript">
						function addPatient() {
							var patient;
							patient = document.getElementById("patient").value;

							if (window.XMLHttpRequest) {
								xmlhttp2 = new XMLHttpRequest();
							} else { // code for IE6, IE5
								xmlhttp2 = new ActiveXObject("Microsoft.XMLHTTP");
							}
							xmlhttp2.onreadystatechange = function() {
								if (xmlhttp2.readyState == 4 && xmlhttp2.status == 200) {

									document.getElementById("patientDetials").innerHTML = xmlhttp2.responseText;
								}
							}
							var supp;

							xmlhttp2.open("GET", "<?php echo base_url(); ?>app/pos/patientDetials/" + patient, true);
							xmlhttp2.send();

							$('#patientListModal').modal('hide');
							return true;
						}


						function getPatientList() {

							var cFrom, cTo;
							cFrom = document.getElementById("cFrom").value;
							cTo = document.getElementById("cTo").value;


							if (window.XMLHttpRequest) {
								xmlhttp = new XMLHttpRequest();
							} else { // code for IE6, IE5
								xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
							}
							xmlhttp.onreadystatechange = function() {
								if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {

									document.getElementById("showPatients").innerHTML = xmlhttp.responseText;
								}
							}
							var supp;

							xmlhttp.open("GET", "<?php echo base_url(); ?>app/pos/showPatients/" + cFrom + "/" + cTo, true);
							xmlhttp.send();

						}
					</script>

					<div class="form-group">
						<label for="exampleInputEmail1">From Date Visit</label>
						<input onMouseMove="getPatientList()" class="form-control input-sm" name="cFrom" id="cFrom" type="text" placeholder="From Date Visit" readonly required>
					</div>

					<div class="form-group">
						<label for="exampleInputEmail1">To Date Visit</label>
						<input onMouseMove="getPatientList()" class="form-control input-sm" name="cTo" id="cTo" type="text" placeholder="From Date Visit" readonly required>
					</div>

					<div class="form-group">
						<label for="exampleInputEmail1">Patient List</label>
						<span id="showPatients">
							<select name="patient" id="patient" class="form-control input-sm" required>
								<option value="">- Patient List -</option>
							</select>
						</span>
					</div>


				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
					<button type="button" class="btn btn-primary" onClick="return addPatient()">Proceed</button>
				</div>

			</div>
			<!-- /.modal-content -->
		</div>
		<!-- /.modal-dialog -->
	</div>

	<!-- / payment modal -->

	<!-- / payment modal -->
	<script language="javascript">
		function validate_payment() {
			var amountPaid, totalAmount
			amountPaid = document.getElementById("amountPaid").value;
			totalAmount = document.getElementById("totalAmount").value;
			if (amountPaid == "") {
				alert('Enter a valid Amount Paid.');
				return false;
			} else if (eval(amountPaid) <= 0) {
				alert('Enter a valid Amount Paid.');
				return false;
			} else if (eval(amountPaid) > eval(totalAmount)) {
				alert('Invalid Input!\nAmount Paid must be less than or equal to Total Amount.');
				return false;
			} else {
				if (confirm('Are you sure you want to save?')) {
					return true;
				} else {
					return false;
				}
			}
		}
	</script>
	<form method="post" action="<?php echo base_url() ?>app/pos/save_payment" onSubmit="return validate_payment()">
		<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
		<input type="hidden" name="opd_no" id="opd_no" value="<?php echo $patientDetials->IO_ID ?>">
		<input type="hidden" name="patient_no" id="patient_no" value="<?php echo $patientDetials->patient_no ?>">
		<input type="hidden" value="<?php echo $headerInv->invoice_no; ?>" readonly name="invoiceno" id="invoiceno" class="form-control input-sm">
		<input type="hidden" name="totalItem" id="totalItem" value="<?php echo isset($itemCount) ? $itemCount : $headerInv->total_purchased; ?>" class="form-control input-sm">
		<input type="hidden" name="hasOR" id="hasOR" value="<?php echo $hasOR ?>">
		<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
						<h4 class="modal-title" id="myModalLabel">Payment</h4>
					</div>
					<div class="modal-body">

						<div class="form-group">
							<label for="exampleInputEmail1">Receipt No.</label>
							<input type="text" value="OR-<?php echo $receipt_no; ?>" readonly name="receiptno" id="receiptno" class="form-control input-sm">
							<input type="hidden" name="receipt_no2" id="receipt_no2" value="<?php echo $receipt_no2 ?>">
						</div>

						<div class="form-group">
							<label for="exampleInputEmail1">Mode of Payment</label>
							<input type="text" value="cash" placeholder="Mode of Payment" readonly name="paymentType" id="paymentType" class="form-control input-sm">
							<!--<select name="paymentType" id="paymentType" class="form-control input-sm" onChange="setTitle(this.value)" readonly>
                                     					<option value="">- Mode of Payment -</option>
                                     					<option value="cash">Cash</option>
                                     					<option value="credit">Credit</option>
                                     					<option value="insurance">Insurance Company</option>
                                     				</select>-->
						</div>

						<script language="javascript">
							function compute() {
								var change, totalAmount, amountPaid;
								amountPaid = parseFloat(document.getElementById("amountPaid").value) || 0;
								totalAmount = parseFloat(document.getElementById("totalAmount").value) || 0;
								change = amountPaid - totalAmount;
								if (change < 0) { change = 0; }
								document.getElementById("change").value = change.toFixed(2);
							}
						</script>

						<div class="form-group" id="lbltotalAmount">
							<label for="exampleInputEmail1">Total Amount</label>
							<input type="text" value="<?php echo isset($pos_balance_due) ? round((float)$pos_balance_due, 2) : $headerInv->total_amount; ?>" placeholder="Total Amount" readonly name="totalAmount" id="totalAmount" class="form-control input-sm">
							<input type="hidden" name="discount" id="discount" value="<?php echo $headerInv->discount; ?>">
							<input type="hidden" name="nGross" id="nGross" value="<?php echo $headerInv->sub_total; ?>">
						</div>



						<div class="form-group" id="lblchange">
							<label for="exampleInputEmail1">Change</label>
							<input type="text" placeholder="0.00" name="change" readonly id="change" class="form-control input-sm">
						</div>

						<div class="form-group" id="lblamountPaid">
							<label for="exampleInputEmail1">Amount Paid</label>
							<input type="text" style=" font-size:24px; height:auto;" onKeyUp="compute()" onkeypress="return isNumberKey(event)" placeholder="0.00" name="amountPaid" id="amountPaid" class="form-control input-sm">
						</div>

						<div class="form-group" id="credit" style=" display:none;">
							<label for="exampleInputEmail1">Credit Card No.</label>
							<input type="text" placeholder="Credit Card No." name="creditCardNo" id="creditCardNo" class="form-control input-sm">
						</div>

						<div class="form-group" id="insurance" style=" display:none;">
							<label for="exampleInputEmail1">Insurance Company</label>
							<select name="insurance_company" id="insurance_company" class="form-control input-sm">
								<option value="">- Insurance Company -</option>
								<?php foreach ($insurance_company as $insurance_company) { ?>
									<option value="<?php echo $insurance_company->in_com_id; ?>"><?php echo $insurance_company->company_name; ?></option>
								<?php } ?>
							</select>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
						<button type="submit" class="btn btn-primary">Save</button>
					</div>

				</div>
				<!-- /.modal-content -->
			</div>
			<!-- /.modal-dialog -->
		</div>
	</form>
	<!-- / payment modal -->






	<!-- Modal -->


	<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
					<h4 class="modal-title" id="myModalLabel">Add Item</h4>
				</div>

				<script language="javascript">
					function showDrugList(category_id) {
						if (window.XMLHttpRequest) {
							xmlhttp3 = new XMLHttpRequest();
						} else { // code for IE6, IE5
							xmlhttp3 = new ActiveXObject("Microsoft.XMLHTTP");
						}
						xmlhttp3.onreadystatechange = function() {
							if (xmlhttp3.readyState == 4 && xmlhttp3.status == 200) {

								document.getElementById("showDrugListItem").innerHTML = xmlhttp3.responseText;
							}
						}
						var supp;
						xmlhttp3.open("GET", "<?php echo base_url(); ?>app/billing/drug_list/" + category_id, true);
						xmlhttp3.send();

					}

					function showDrugName(category_id) {
						if (window.XMLHttpRequest) {
							xmlhttp = new XMLHttpRequest();
						} else { // code for IE6, IE5
							xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
						}
						xmlhttp.onreadystatechange = function() {
							if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {

								document.getElementById("showCategories").innerHTML = xmlhttp.responseText;
							}
						}
						var supp;

						xmlhttp.open("GET", "<?php echo base_url(); ?>app/billing/getItem/" + category_id, true);
						xmlhttp.send();

					}

					function getDrugRate(category_id) {
						if (window.XMLHttpRequest) {
							xmlhttp5 = new XMLHttpRequest();
						} else { // code for IE6, IE5
							xmlhttp5 = new ActiveXObject("Microsoft.XMLHTTP");
						}
						xmlhttp5.onreadystatechange = function() {
							if (xmlhttp5.readyState == 4 && xmlhttp5.status == 200) {

								document.getElementById("showDrugRate").innerHTML = xmlhttp5.responseText;
							}
						}

						xmlhttp5.open("GET", "<?php echo base_url(); ?>app/billing/getDrugRate/" + category_id, true);
						xmlhttp5.send();

					}


					function showDrugName(category_id) {
						if (window.XMLHttpRequest) {
							xmlhttp = new XMLHttpRequest();
						} else { // code for IE6, IE5
							xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
						}
						xmlhttp.onreadystatechange = function() {
							if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {

								document.getElementById("showCategories").innerHTML = xmlhttp.responseText;
							}
						}
						var supp;

						xmlhttp.open("GET", "<?php echo base_url(); ?>app/billing/getItem/" + category_id, true);
						xmlhttp.send();

					}

					function getItemRate(category_id) {
						if (window.XMLHttpRequest) {
							xmlhttp2 = new XMLHttpRequest();
						} else { // code for IE6, IE5
							xmlhttp2 = new ActiveXObject("Microsoft.XMLHTTP");
						}
						xmlhttp2.onreadystatechange = function() {
							if (xmlhttp2.readyState == 4 && xmlhttp2.status == 200) {

								document.getElementById("showRate").innerHTML = xmlhttp2.responseText;
							}
						}

						xmlhttp2.open("GET", "<?php echo base_url(); ?>app/billing/getRate/" + category_id, true);
						xmlhttp2.send();

					}

					function showBills(val) {
						if (val == "particular") {
							document.getElementById("particular").style.display = "inline";
							document.getElementById("particular_item").style.display = "inline";
							document.getElementById("category").style.display = "inline";
							document.getElementById("showCategories").style.display = "inline";
							document.getElementById("showRate").style.display = "inline";
							document.getElementById("medicine").style.display = "none";
							document.getElementById("drug_name").style.display = "none";
							document.getElementById("medicine_cat").style.display = "none";
							document.getElementById("showDrugListItem").style.display = "none";
							document.getElementById("showDrugRate").style.display = "none";
							document.getElementById("buttonMedication").style.display = "none";
						} else if (val == "medicine") {
							document.getElementById("particular").style.display = "none";
							document.getElementById("particular_item").style.display = "none";
							document.getElementById("category").style.display = "none";
							document.getElementById("showCategories").style.display = "none";
							document.getElementById("showRate").style.display = "none";
							document.getElementById("medicine").style.display = "inline";
							document.getElementById("drug_name").style.display = "inline";
							document.getElementById("medicine_cat").style.display = "inline";
							document.getElementById("showDrugListItem").style.display = "inline";
							document.getElementById("showDrugRate").style.display = "inline";
							document.getElementById("buttonMedication").style.display = "inline";
						}
					}
				</script>
				<div class="modal-body">
					<table class="table table-hover">
						<tbody>
							<tr>
								<td>Type <font color="#FF0000">*</font></td>
								<td>
									<select name="bill_category" onChange="showBills(this.value);" id="bill_category" class="form-control input-sm" style="width: 250px;">
										<option value="particular">Particular Bills</option>
										<option value="medicine">Medicine Bills</option>
									</select>
								</td>
							</tr>
							<tr>
								<td>
									<span id="particular">Paricular Category <font color="#FF0000">*</font></span>
									<span id="medicine" style="display: none">Medicine Category <font color="#FF0000">*</font></span>
								</td>
								<td>
									<select name="category" onChange="showDrugName(this.value);" id="category" class="form-control input-sm" style="width: 250px;" required>
										<option value="">- Paricular Category -</option>
										<?php
										foreach ($particular_cat as $particular_cat) { ?>
											<option value="<?php echo $particular_cat->group_id; ?>"><?php echo $particular_cat->group_name; ?></option>
										<?php } ?>
									</select>

									<select name="medicine_cat" onChange="showDrugList(this.value); otherOptions(this.value)" id="medicine_cat" class="form-control input-sm" style="width: 250px; display: none;" required>
										<option value="">- Medicine Category -</option>
										<option value="others">Others</option>
										<?php
										foreach ($medicine_cat as $medicine_cat) { ?>
											<option value="<?php echo $medicine_cat->cat_id; ?>"><?php echo $medicine_cat->med_category_name; ?></option>
										<?php } ?>
									</select>
								</td>
							</tr>
							<tr id="drug_block">
								<td>
									<span id="particular_item">Paricular Item <font color="#FF0000">*</font></span>
									<span id="drug_name" style="display: none">Drug Name <font color="#FF0000">*</font></span>
								</td>
								<td>
									<span id="showCategories">
										<select name="item" id="item" class="form-control input-sm" style="width: 250px;" required>
											<option value="">- Paricular Item -</option>
										</select>
									</span>

									<span id="showDrugListItem" style="display: none;">
										<select name="item2" id="item2" class="form-control input-sm" style="width: 250px;" required>
											<option value="">- Drug Name List -</option>
										</select>
									</span>
								</td>
							</tr>
							<!-- <tr id="drug_block">
                                        	<td>Drug Name</td>
                                            <td>
                                            <label id="showCategories">
                        					<select name="drug_name" id="drug_name" class="form-control input-sm" style="width: 250px;" >
                        						<option value="">- select -</option>
                        					</select>
                                            </td>
                                        </tr> -->
							<tr id="medicine_txt" style="display: none;">
								<td>Medicine Name <font color="#FF0000">*</font></td>
								<td><input id="autouser" name="medicine_text" placeholder="type medicine here" class="form-control input-sm" style="width: 100%;" /></td>
							</tr>
							<tr>
								<td>Days <font color="#FF0000">*</font></td>
								<td><input type="text" name="nDays" id="nDays" placeholder="Days" class="form-control input-sm" style="width: 250px;" required></td>
							</tr>
							<tr>
								<td>Qty <font color="#FF0000">*</font></td>
								<td><input type="text" onkeypress="return isNumberKey(event)" name="qty" id="qty" value="1" placeholder="Qty" class="form-control input-sm" style="width: 250px;" required></td>
							</tr>
							<tr>
								<td>Rate <font color="#FF0000">*</font></td>
								<td>
									<label id="showRate">
										<input type="text" onkeypress="return isNumberKey(event)" name="rate" id="rate" placeholder="rate" class="form-control input-sm" style="width: 250px;" required>
									</label>

									<label id="showDrugRate" style="display:none">
										<input type="text" onkeypress="return isNumberKey(event)" name="drugrate" id="drugrate" placeholder="rate" class="form-control input-sm" style="width: 250px;" required>
									</label>
								</td>
							</tr>
							<tr>
								<td>Note</td>
								<td><textarea name="note" id="note" placeholder="note" class="form-control input-sm" style="width: 100%;"></textarea></td>
							</tr>
							<tr>
								<td>Dosage</td>
								<td><textarea name="dosage" id="dosage" placeholder="note" class="form-control input-sm" style="width: 100%;"></textarea></td>
							</tr>
							<tr>
								<td>Instruction</td>
								<td><textarea name="instruction" id="instruction" placeholder="instruction" class="form-control input-sm" style="width: 100%;"></textarea></td>
							</tr>
							<tr>
								<td>Advice</td>
								<td><textarea name="advice" id="advice" placeholder="advice" class="form-control input-sm" style="width: 100%;"></textarea></td>
							</tr>
							<script language="javascript">
								function getPatientMedication() {
									if (!confirm('Are you sure you want to get the Bills?')) {
										return false;
									} else {

										var patientNo, iopNo;
										patientNo = document.getElementById("patient_no").value;
										iopNo = document.getElementById("opd_no").value;

										var left = (screen.width / 2) - (500 / 2);
										var top = (screen.height / 2) - (400 / 2);
										var sFeatures = "dialogHeight: 420px;  dialogWidth: 600px; dialogTop: " + top + "px; dialogLeft: " + left + "px;";

										window.showModalDialog("<?php echo base_url() ?>app/pos/getPatientMedication/" + patientNo + "/" + iopNo, sFeatures);
										return true;
									}
								}
							</script>
						</tbody>
					</table>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
					<button type="button" class="btn btn-primary" onClick="return addItem()">Add</button>
				</div>

			</div>
			<!-- /.modal-content -->
		</div>
		<!-- /.modal-dialog -->
	</div>

	<!-- /.modal -->

	<script>
		function otherOptions(val) {
			if (val == 'others') {
				$('#medicine_txt').show();
				// $('#showDrugListItem').hide();
				var drugSel = document.querySelector('#showDrugListItem select[name="drug_name"]');
				if (drugSel) {
					drugSel.disabled = true;
					drugSel.value = '';
				}
			} else {
				$('#medicine_txt').hide();
				var drugSel2 = document.querySelector('#showDrugListItem select[name="drug_name"]');
				if (drugSel2) {
					drugSel2.disabled = false;
				}
			}
		}
	</script>


	<script type='text/javascript'>
		var csrfNameAuto = '<?php echo $this->security->get_csrf_token_name(); ?>';
		var csrfHashAuto = '<?php echo $this->security->get_csrf_hash(); ?>';
		$(document).ready(function() {

			// Initialize 
			$("#autouser").autocomplete({
				source: function(request, response) {
					// Fetch data
					var searchData = { search: request.term };
					searchData[csrfNameAuto] = csrfHashAuto;
					$.ajax({
						url: "<?= base_url() ?>app/opd/getMeds",
						type: 'post',
						dataType: "json",
						data: searchData,
						success: function(data) {
							response(data);
						}
					});
				},
				minLength: 3,
				select: function(event, ui) {
					// Set selection
					$('#autouser').val(ui.item.label); // display the selected text
					$('#medicine_name').val($('#autouser').val());
					$('#drug_name_a').val($('#autouser').val());
					//   console.log($('#medicine_name').val());
					//   $('#userid').val(ui.item.value); // save selected id to input
					return false;
				}
			});

		});
	</script>



	<script language="javascript">
		function isNumberKey(evt) {
			var charCode = (evt.which) ? evt.which : event.keyCode;
			if (charCode != 46 && charCode > 31 &&
				(charCode < 48 || charCode > 57))
				return false;

			return true;
		}

		function addItem() {

			if (document.getElementById("bill_category").value == "particular") {
				var catEl = document.getElementById("category");
				var catVal = catEl ? catEl.value : '';
				if (catVal == "") {
					alert("Please select Paricular Category");
					return false;
				}
				var partSelect = document.querySelector('#showCategories select[name="particular"]');
				var partVal = partSelect ? partSelect.value : '';
				if (partVal == "") {
					alert("Please select Paricular Item");
					return false;
				} else if (document.getElementById("qty").value == "") {
					alert("Please enter a valid Qty");
					return false;
				} else if (document.getElementById("rate").value == "") {
					alert("Please enter a valid Rate");
					return false;
				}
			} else if (document.getElementById("bill_category").value == "medicine") {
				var medCatEl = document.getElementById("medicine_cat");
				var medCatVal = medCatEl ? medCatEl.value : '';
				if (medCatVal == "") {
					alert("Please select Medicine Category");
					return false;
				}
				if (medCatVal == "others") {
					if (document.getElementById("autouser").value == "") {
						alert("Please type medicine name");
						return false;
					}
				} else {
					var drugSelect = document.querySelector('#showDrugListItem select[name="drug_name"]');
					var drugVal = drugSelect ? drugSelect.value : '';
					if (drugVal == "") {
						alert("Please select Drug Name");
						return false;
					}
				}
				if (document.getElementById("qty").value == "") {
					alert("Please enter a valid Qty");
					return false;
				}
				if (medCatVal != "others") {
					var isExpiredEl = document.getElementById('drug_is_expired');
					var isExpired = isExpiredEl ? (isExpiredEl.value || '') : '';
					if (isExpired == '1') {
						alert('Selected drug is expired. Please choose another item.');
						return false;
					}
					var stockEl = document.getElementById('drug_stock');
					var stockVal = stockEl ? (stockEl.value || '') : '';
					var qtyVal = document.getElementById('qty').value;
					var stockNum = parseFloat(stockVal);
					var qtyNum = parseFloat(qtyVal);
					if (!isNaN(stockNum) && !isNaN(qtyNum) && stockNum >= 0 && qtyNum > stockNum) {
						alert('Insufficient stock. Available: ' + stockNum);
						return false;
					}
				}
				var drugRateEl = document.getElementById("drugrate") || document.getElementById("drug_rate");
				var drugRateVal = drugRateEl ? drugRateEl.value : '';
				if (drugRateVal == "") {
					alert("Please enter a valid Rate");
					return false;
				}
			}



			var tbl = document.getElementById('myTable').getElementsByTagName('tr');
			var lastRow = tbl.length;

			var category, particular, qty, rate, note, amount, dosage, advice, instruction;

			qty = document.getElementById("qty").value;
			note = document.getElementById("note").value;

			if (document.getElementById("bill_category").value == "particular") {
				var catEl2 = document.getElementById("category");
				if (catEl2 && catEl2.options && catEl2.selectedIndex >= 0) {
					category = catEl2.options[catEl2.selectedIndex].text;
				} else {
					category = '';
				}
				var partSelect2 = document.querySelector('#showCategories select[name="particular"]');
				if (partSelect2 && partSelect2.options && partSelect2.selectedIndex >= 0) {
					particular = partSelect2.options[partSelect2.selectedIndex].text;
				} else {
					particular = '';
				}
				rate = document.getElementById("rate").value;
			} else if (document.getElementById("bill_category").value == "medicine") {
				var medCatEl2 = document.getElementById("medicine_cat");
				var medCatVal2 = medCatEl2 ? medCatEl2.value : '';
				if (medCatVal2 == 'others') {
					category = 'Others';
					particular = document.getElementById("autouser").value;
				} else {
					var medNameEl = document.getElementById("medicine_name");
					if (medNameEl && medNameEl.value) {
						category = medNameEl.value;
					} else if (medCatEl2 && medCatEl2.options && medCatEl2.selectedIndex >= 0) {
						category = medCatEl2.options[medCatEl2.selectedIndex].text;
					} else {
						category = '';
					}
					var drugSelect2 = document.querySelector('#showDrugListItem select[name="drug_name"]');
					if (drugSelect2 && drugSelect2.options && drugSelect2.selectedIndex >= 0) {
						particular = drugSelect2.options[drugSelect2.selectedIndex].text;
					} else {
						particular = '';
					}
				}
				var drugRateEl2 = document.getElementById("drugrate") || document.getElementById("drug_rate");
				rate = drugRateEl2 ? drugRateEl2.value : '';
				dosage = document.getElementById("dosage").value;
				advice = document.getElementById("advice").value;
				instruction = document.getElementById("instruction").value;
			}


			amount = eval(qty) * eval(rate);
			amount = amount.toFixed(2);

			// Determine item type for display
			var itemType = document.getElementById("bill_category").value;
			var itemTypeLabel = (itemType === 'medicine') ? '<span class="label label-success">Medicine</span>' : '<span class="label label-info">Service</span>';
			var isMedicine = (itemType === 'medicine');
			
			// Build medication info display (only for medicines)
			var medInfoHtml = '';
			if (isMedicine && (dosage || advice || instruction)) {
				medInfoHtml = '<small>';
				if (dosage) medInfoHtml += '<strong>Dosage:</strong> ' + dosage + '<br>';
				if (advice) medInfoHtml += '<strong>Advice:</strong> ' + advice + '<br>';
				if (instruction) medInfoHtml += '<strong>Instr:</strong> ' + instruction;
				medInfoHtml += '</small>';
			} else if (!isMedicine) {
				medInfoHtml = '<span class="text-muted">N/A</span>';
			}

			var a = document.getElementById('myTable').insertRow(-1);
			var b = a.insertCell(0);
			var c = a.insertCell(1);
			var cType = a.insertCell(2);
			var d = a.insertCell(3);
			var e = a.insertCell(4);
			var f = a.insertCell(5);
			var g = a.insertCell(6);
			var h = a.insertCell(7);
			var kk = a.insertCell(8);

			b.innerHTML = "<input type=\"hidden\" name=\"isPackage" + lastRow + "\" id=\"isPackage" + lastRow + "\" value=\"0\"><input type=\"hidden\" name=\"item_type" + lastRow + "\" id=\"item_type" + lastRow + "\" value=\"" + itemType + "\"><input type=\"text\" size = \"7\" style=\"width:98%; background-color:#F9F9f9; border:1px solid #ccc; text-align:right\" name=\"id" + lastRow + "\" id=\"id" + lastRow + "\" value=\"" + lastRow + ". \" readonly=\"true\">";
			c.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; background-color:#F9F9f9; border:1px solid #ccc;\" name=\"bill_name" + lastRow + "\" id=\"bill_name" + lastRow + "\" value=\"" + particular + "\" readonly=\"true\">";
			cType.innerHTML = itemTypeLabel;
			d.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; text-align:right\" name=\"qty" + lastRow + "\" id=\"qty" + lastRow + "\" class=\"" + lastRow + "\" value=\"" + qty + "\" onBlur=\"return validate_input(this.className,'qty')\" onkeyup=\"validate_gross(this.className,'qty')\" onkeypress=\"return isNumberKey(event)\" >";
			e.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; text-align:right\" name=\"rate" + lastRow + "\" id=\"rate" + lastRow + "\" class=\"" + lastRow + "\" value=\"" + rate + "\" onBlur=\"return validate_input(this.className,'rate')\" onkeyup=\"validate_gross(this.className,'rate')\" onkeypress=\"return isNumberKey(event)\">";
			f.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; background-color:#F9F9f9; border:1px solid #ccc; text-align:right\" name=\"amount" + lastRow + "\" id=\"amount" + lastRow + "\" value=\"" + amount + "\" readonly=\"true\">";
			g.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%;\" name=\"note" + lastRow + "\" id=\"note" + lastRow + "\" value=\"" + note + "\">";
			// Combined medication info column with hidden fields for form submission
			h.innerHTML = medInfoHtml + "<input type=\"hidden\" name=\"dosage" + lastRow + "\" id=\"dosage" + lastRow + "\" value=\"" + (dosage || '') + "\"><input type=\"hidden\" name=\"advice" + lastRow + "\" id=\"advice" + lastRow + "\" value=\"" + (advice || '') + "\"><input type=\"hidden\" name=\"instruction" + lastRow + "\" id=\"instruction" + lastRow + "\" value=\"" + (instruction || '') + "\">";
			kk.innerHTML = "<img src=\"<?php echo base_url() ?>public/img/b_drop.png\" onclick=\"deleteRow(this)\" style=\"cursor:pointer;\">";

			document.getElementById("hdnrowcnt").value = lastRow;

			getGross();

			$('#myModal').modal('hide');
			return true;


		}

		function closeModal() {
			$('#myModal').modal('hide');
		}

		function deleteRow(r) {
			var tbl = document.getElementById('myTable').getElementsByTagName('tr');
			var lastRow = tbl.length;

			var i = r.parentNode.parentNode.rowIndex;
			if (lastRow > 2) {
				document.getElementById('myTable').deleteRow(i);
				document.getElementById('hdnrowcnt').value = lastRow - 2;
				var lastRow = tbl.length;
				var z;
				for (z = i + 1; z <= lastRow; z++) {

					var id = document.getElementById('id' + z);
					var isPackage = document.getElementById('isPackage' + z);
					var itemTypeEl = document.getElementById('item_type' + z);
					var bill_name = document.getElementById('bill_name' + z);
					var qty = document.getElementById('qty' + z);
					var rate = document.getElementById('rate' + z);
					var amount = document.getElementById('amount' + z);
					var note = document.getElementById('note' + z);
					var dosageEl = document.getElementById('dosage' + z);
					var adviceEl = document.getElementById('advice' + z);
					var instructionEl = document.getElementById('instruction' + z);


					var x = z - 1;

					id.value = x;
					id.id = "id" + x;
					id.name = "id" + x;

					isPackage.id = "isPackage" + x;
					isPackage.name = "isPackage" + x;

					if (itemTypeEl) { itemTypeEl.id = "item_type" + x; itemTypeEl.name = "item_type" + x; }

					bill_name.id = "bill_name" + x;
					bill_name.name = "bill_name" + x;

					qty.id = "qty" + x;
					qty.name = "qty" + x;
					qty.className = x;

					rate.id = "rate" + x;
					rate.name = "rate" + x;
					rate.className = x;

					amount.id = "amount" + x;
					amount.name = "amount" + x;

					note.id = "note" + x;
					note.name = "note" + x;

					if (dosageEl) { dosageEl.id = "dosage" + x; dosageEl.name = "dosage" + x; }
					if (adviceEl) { adviceEl.id = "advice" + x; adviceEl.name = "advice" + x; }
					if (instructionEl) { instructionEl.id = "instruction" + x; instructionEl.name = "instruction" + x; }

					//alert(bill_name.name + " - " + rate.value);
				}
				getGross();
			} else {
				alert("Minimum of one row per transaction.");
			}
		}

		function getGross() {
			var len;
			var nGross = 0;
			var nTotal = 0;
			len = document.getElementById("hdnrowcnt").value;
			for (i = 1; i <= len; i++) {
				nGross += parseFloat(document.getElementById("amount" + i).value - 0);
			}
			nGross = nGross.toFixed(2);
			document.getElementById("nGross").value = nGross;
			nTotal = eval(nGross) - eval(document.getElementById("discount").value);
			nTotal = nTotal.toFixed(2);
			document.getElementById("total_amount").value = nTotal;
			document.getElementById("total_amount").value = nTotal;
			$("#totalAmount").val(nTotal);
		}

		function validate_gross(id, nName) {
			var qty, rate, amount;
			qty = document.getElementById("qty" + id).value;
			rate = document.getElementById("rate" + id).value;

			amount = eval(qty) * eval(rate);
			amount = amount.toFixed(2);

			document.getElementById("amount" + id).value = amount;

			getGross();
		}

		function validate_input(id, name) {
			//alert(document.getElementById(name+""+id).value);
			if (document.getElementById(name + "" + id).value == "" || eval(document.getElementById(name + "" + id).value) <= 0) {
				alert("Please enter a valid " + name + ".");
				document.getElementById(name + "" + id).value = "0";
				validate_gross(id, name)
				getGross();
				return false;
			} else {
				validate_gross(id, name)
				getGross();
			}
		}

		function validate_form() {


			if (document.getElementById("hdnrowcnt").value == "0") {
				alert('Minimum of one row per transaction.');
				return false;
			} else {
				var len;
				len = document.getElementById("hdnrowcnt").value;
				for (i = 1; i <= len; i++) {
					if (eval(document.getElementById("amount" + i).value) <= 0) {
						alert("Transaction cannot be saved. There are still some items without amount.");
						return false;
					} else {
						if (confirm('Are you sure you want to save?')) {
							return true;
						} else {
							return false;
						}
					}
				}
			}


		}

		function stopEnterKey(evt) {
			var evt = (evt) ? evt : ((event) ? event : null);
			var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null);
			if ((evt.keyCode == 13) && (node.type == "text")) {
				return false;
			}
		}
		document.onkeypress = stopEnterKey;
	</script>

	<!-- Doctor's Fee Modal -->
	<div class="modal fade" id="doctorFeeModal" tabindex="-1" role="dialog" aria-labelledby="doctorFeeModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
					<h4 class="modal-title" id="doctorFeeModalLabel">Doctor's Fee (Commission Tracking)</h4>
				</div>
				<div class="modal-body">
					<div class="alert alert-info">
						<i class="fa fa-info-circle"></i> This records the doctor's commission from this invoice for internal accounting.
					</div>
					<form name="frmDoctorFee" id="frmDoctorFee" method="post">
						<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
						<table class="table table-striped">
							<tr>
								<td>Select Doctor <font color="#FF0000">*</font></td>
								<td>
									<select name="doctor" id="doctorC" class="form-control input-sm" required onchange="clearDoctorFields()">
										<option value="">- Select Doctor -</option>
										<?php if (isset($doctorList) && is_array($doctorList)): foreach ($doctorList as $doc): ?>
											<option value="<?php echo trim($doc->user_id); ?>"><?php echo $doc->name; ?></option>
										<?php endforeach; endif; ?>
									</select>
								</td>
							</tr>
							<tr>
								<td>Fee Type <font color="#FF0000">*</font></td>
								<td>
									<select name="cType" id="cType" class="form-control input-sm" required>
										<option value="">- Select Fee Type -</option>
										<option value="percentage">Percentage of Invoice</option>
										<option value="actual">Fixed Amount</option>
									</select>
								</td>
							</tr>
							<tr>
								<td>Value <font color="#FF0000">*</font></td>
								<td>
									<input type="text" class="form-control input-sm" required placeholder="Enter % or Amount" onkeyup="computeDoctorFee(this.value)" name="valueFee" id="valueFee">
								</td>
							</tr>
							<tr>
								<td>Invoice Total</td>
								<td>
									<input type="text" class="form-control input-sm" readonly id="invoiceTotalDisplay" value="<?php echo isset($headerInv->total_amount) ? number_format($headerInv->total_amount, 2) : '0.00'; ?>">
									<input type="hidden" id="invoiceTotalValue" value="<?php echo isset($headerInv->total_amount) ? $headerInv->total_amount : 0; ?>">
								</td>
							</tr>
							<tr>
								<td>Doctor's Fee <font color="#FF0000">*</font></td>
								<td>
									<input type="text" style="font-size:20px; background-color:rgba(243, 215, 16, 0.27);" readonly name="totalFee" id="totalFee" class="form-control">
								</td>
							</tr>
							<tr>
								<td>Notes</td>
								<td>
									<textarea class="form-control" name="notes" id="doctorFeeNotes" rows="2" placeholder="Optional notes"></textarea>
								</td>
							</tr>
						</table>
					</form>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
					<button type="button" class="btn btn-primary" onclick="saveDoctorFee()" id="btnSaveDoctorFee">Save Doctor's Fee</button>
				</div>
			</div>
		</div>
	</div>

	<script type="text/javascript">
		var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
		var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
		// Doctor's Fee Functions
		$(document).ready(function() {
			// Load existing doctor fee if any
			var invoiceno = '<?php echo isset($headerInv->invoice_no) ? $headerInv->invoice_no : ""; ?>';
			if (invoiceno) {
				var fetchData = {};
				fetchData[csrfName] = csrfHash;
				$.ajax({
					url: "<?php echo base_url(); ?>app/pos/getDoctorFee/" + invoiceno,
					type: "POST",
					data: fetchData,
					dataType: "json",
					success: function(result) {
						if (result && result.user_id) {
							$('#doctorC').val(result.user_id);
							$('#cType').val(result.feeType);
							$('#valueFee').val(result.value);
							$('#totalFee').val(result.totalFee);
							$('#doctorFeeNotes').val(result.notes);
						}
					}
				});
			}
		});

		function clearDoctorFields() {
			$('#cType').val('');
			$('#valueFee').val('');
			$('#totalFee').val('');
			$('#doctorFeeNotes').val('');
		}

		function computeDoctorFee(val) {
			var cType = $('#cType').val();
			var invoiceTotal = parseFloat($('#invoiceTotalValue').val()) || 0;
			var totalFee = 0;

			if (cType == "percentage") {
				var percentageValue = parseFloat(val) / 100;
				totalFee = invoiceTotal * percentageValue;
			} else if (cType == "actual") {
				totalFee = parseFloat(val) || 0;
			}

			$('#totalFee').val(totalFee.toFixed(2));
		}

		function saveDoctorFee() {
			var doctor = $('#doctorC').val();
			var cType = $('#cType').val();
			var valueFee = $('#valueFee').val();

			if (!doctor || !cType || !valueFee) {
				alert('Please fill in all required fields.');
				return;
			}

			var formdata = $('#frmDoctorFee').serialize();
			var invoiceno = '<?php echo isset($headerInv->invoice_no) ? $headerInv->invoice_no : ""; ?>';

			$.ajax({
				url: "<?php echo base_url(); ?>app/pos/saveDoctorFee/" + invoiceno,
				type: "POST",
				data: formdata,
				success: function(result) {
					$('#btnSaveDoctorFee').removeClass("disabled").text('Save Doctor\'s Fee');
					alert("Doctor's Fee has been saved successfully.");
					$('#doctorFeeModal').modal('hide');
				},
				beforeSend: function() {
					$('#btnSaveDoctorFee').addClass("disabled").text('Saving...');
				},
				error: function() {
					$('#btnSaveDoctorFee').removeClass("disabled").text('Save Doctor\'s Fee');
					alert("Error saving Doctor's Fee. Please try again.");
				}
			});
		}
	</script>

	<!-- Required for sidebar toggle and Bootstrap components -->
	<script>window.jQuery || document.write('<script src="<?php echo base_url(); ?>public\/js\/jquery.min.js"><\/script>')</script>
	<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
	<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
	
	<!-- Floating Back to Dashboard Button -->
	<a href="<?php echo base_url(); ?>app/dashboard" class="back-float-btn" title="Back to Dashboard">
		<i class="fa fa-home"></i>
	</a>
</body>

</html>