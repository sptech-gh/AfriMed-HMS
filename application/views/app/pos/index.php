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
                background: linear-gradient(135deg, #3c8dbc 0%, #2c6d9c 100%);
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
            .back-float-btn {
                position: fixed;
                bottom: 25px;
                left: 25px;
                z-index: 9999;
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background: #3c8dbc;
                color: white;
                border: none;
                box-shadow: 0 4px 15px rgba(60,141,188,0.4);
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .back-float-btn:hover {
                background: #2c6d9c;
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(60,141,188,0.5);
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

<body class="skin-blue" onLoad="autoload();">
    <!-- header logo: style can be found in header.less -->
    <?php require_once(APPPATH . 'views/include/header.php'); ?>
    
    <!-- Navigation Header with Breadcrumbs -->
    <div class="pos-nav-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <h4 class="page-title-pos"><i class="fa fa-shopping-cart"></i> Point of Sale - Billing</h4>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                        <li><a href="<?php echo base_url(); ?>app/unified_billing"><i class="fa fa-money"></i> Billing</a></li>
                        <li class="active"><i class="fa fa-shopping-cart"></i> POS <?php echo isset($direct) && isset($patient_rows) ? '- ' . $patient_rows->name : ''; ?></li>
                    </ol>
                </div>
                <div class="col-md-6 text-right" style="padding-top: 8px;">
                    <a href="<?php echo base_url(); ?>app/dashboard" class="btn btn-default quick-nav-btn">
                        <i class="fa fa-home"></i> Dashboard
                    </a>
                    <a href="<?php echo base_url(); ?>app/unified_billing" class="btn btn-info quick-nav-btn">
                        <i class="fa fa-list"></i> All Invoices
                    </a>
                    <a href="<?php echo base_url(); ?>app/opd" class="btn btn-success quick-nav-btn">
                        <i class="fa fa-user-md"></i> OPD
                    </a>
                    <?php if (isset($direct) && isset($patient_rows)): ?>
                    <a href="<?php echo base_url(); ?>app/patient_history/<?php echo $patient_rows->patient_no; ?>" class="btn btn-warning quick-nav-btn">
                        <i class="fa fa-folder-open"></i> Patient File
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <form method="post" action="<?php echo base_url() ?>app/billing/save_invoice" onSubmit="return validate_form();">
        <input type="hidden" name="patient" id="patient" value="<?php echo (isset($direct)) ? $patient_rows->patient_no : ""; ?>">

        <section class="content" style="padding-top: 5px;">

            <?php if ($this->session->flashdata('message')) { ?>
                <div class="row">
                    <div class="col-md-12">
                        <?php echo $this->session->flashdata('message'); ?>
                    </div>
                </div>
            <?php } ?>

            <div class="row">
                <div class="col-md-3">
                    <div class="box box-primary">
                        <div class="box-header">
                        </div>

                        <div class="box-content">
                            <div class="box-body table-responsive no-padding">
                                <?php
                                if (isset($direct)) {
                                ?>
                                    <table width="100%" cellpadding="3" cellspacing="3">
                                        <tr>
                                            <td width="15%" valign="top" align="center">
                                                <img src="<?php echo base_url(); ?>public/patient_picture/avatar.png" class="img-rounded" width="86" height="81">
                                            </td>
                                            <td>
                                                <table cellpadding="2" width="100%">
                                                    <tr>
                                                        <td><strong>Patient No.</strong></td>
                                                        <td><?php echo $patient_rows->patient_no; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>IOP No.</strong></td>
                                                        <td><?php echo (isset($iop_info) && isset($iop_info->IO_ID)) ? $iop_info->IO_ID : ((isset($auto_load_io_id) && $auto_load_io_id !== '') ? $auto_load_io_id : (isset($iop_no) ? $iop_no : '-')); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Patient Name.</strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td><?php echo $patient_rows->name; ?></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    <input type="hidden" name="opd_no" id="opd_no" value="<?php echo (isset($iop_info) && isset($iop_info->IO_ID)) ? $iop_info->IO_ID : ((isset($auto_load_io_id) && $auto_load_io_id !== '') ? $auto_load_io_id : ((isset($iop_no) && $iop_no !== '') ? $iop_no : '0')); ?>">
                                    <input type="hidden" name="patient_no" id="patient_no" value="<?php echo $patient_rows->patient_no ?>">
                                <?php } else { ?>
                                    <span id="patientDetials">
                                        <table width="100%" cellpadding="3" cellspacing="3">
                                            <tr>
                                                <td width="15%" valign="top" align="center">
                                                    <img src="<?php echo base_url(); ?>public/patient_picture/avatar.png" class="img-rounded" width="86" height="81">
                                                </td>
                                                <td>
                                                    <table cellpadding="2" width="100%">
                                                        <tr>
                                                            <td><strong>Patient No.</strong></td>
                                                            <td>-</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>IOP No.</strong></td>
                                                            <td>-</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Patient Name.</strong></td>
                                                        </tr>
                                                        <tr>
                                                            <td>-</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </span>
                                <?php } ?>
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
                            $userID = $invoice_no->invoice_no;
                            $userID2 = $invoice_no->invoice_no;
                            if (strlen($userID) == 1) {
                                $userID = "00000" . $userID;
                            } else if (strlen($userID) == 2) {
                                $userID = "0000" . $userID;
                            } else if (strlen($userID) == 3) {
                                $userID = "000" . $userID;
                            } else if (strlen($userID) == 4) {
                                $userID = "00" . $userID;
                            } else if (strlen($userID) == 5) {
                                $userID = "0" . $userID;
                            } else if (strlen($userID) == 6) {
                                $userID = $userID;
                            }

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
                            <input type="hidden" name="invoiceno2" value="<?php echo $userID2; ?>">

                            <div class="form-group">
                                <label for="exampleInputEmail1">Date</label>
                                <input type="text" value="<?php echo date("m/d/Y"); ?>" readonly name="dDate22222" id="dDate22222" class="form-control input-sm">
                            </div>

                            <div class="form-group">
                                <label for="exampleInputEmail1">Invoice No.</label>
                                <input type="text" value="SI-<?php echo $userID; ?>" readonly name="invoiceno" id="invoiceno" class="form-control input-sm">
                            </div>

                            <div class="form-group">
                                <label for="exampleInputEmail1">Total Items</label>
                                <input type="text" readonly name="hdnrowcnt" id="hdnrowcnt" value="0" class="form-control input-sm">
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
                                <label for="exampleInputEmail1">Sub Total</label>
                                <input type="text" readonly name="nGross" id="nGross" placeholder="0.00" class="form-control input-sm">
                            </div>

                            <div class="form-group">
                                <label for="exampleInputEmail1">Discount</label>
                                <input type="text" name="discount" id="discount" value="0" onKeyUp="validate_discount(this.value)" class="form-control input-sm" onkeypress="return isNumberKey(event)">
                            </div>

                            <div class="form-group">
                                <label for="exampleInputEmail1">TOTAL AMOUNT</label>
                                <input type="text" placeholder="0.00" readonly name="total_amount" id="total_amount" class="form-control input-sm">
                            </div>

                            <div class="form-group">
                                <label for="exampleInputEmail1">Reason for Discount</label>
                                <select name="reason_dicount" id="reason_dicount" class="form-control input-sm">
                                    <option value="">- Reason for Discount -</option>
                                    <?php foreach ($reason_dicount as $reason_dicount) { ?>
                                        <option value="<?php echo $reason_dicount->param_id ?>"><?php echo $reason_dicount->cValue ?></option>
                                    <?php } ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="exampleInputEmail1">Remarks</label>
                                <textarea placeholder="Remarks" class="form-control input-sm" name="remarks" id="remarks" rows="5"></textarea>
                            </div>
                        </div>
                    </div>
                </div>



                <div class="col-md-9">
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab"><strong>Billing List</strong></a></li>
                            <!--<li><a href="#tab_2" data-toggle="tab">Header Details</a></li>-->
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">

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
                                            <tr style="border-bottom:1px #999 solid; border-collapse:collapse">
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
                            <a class="btn btn-app" href="<?php echo base_url() ?>app/pos/"><i class="fa fa-refresh"></i> Refresh</a>

                            <!-- Doctor's Fee moved to saved.php - only available after invoice is saved -->
                            <a class="btn btn-app" data-toggle="modal" data-target="#patientListModal" style="display: <?php echo (isset($direct)) ? "none" : "inline-block"; ?>"><i class="fa fa-user"></i> Patient</a>

                            <a class="btn btn-app" data-toggle="modal" data-target="#myModal"><i class="fa fa-plus"></i> Add Item</a>
                            <a href="#" class="btn btn-app" onClick="return getPatientMedication()"><i class="fa fa-hand-o-down"></i> 1-Click Billed</a>
                            <button type="submit" class="btn btn-app"><i class="fa fa-save"></i> Save</button>
                            <a class="btn btn-app" onClick="alert('Please save current transaction to make Payment');"><i class="fa fa-credit-card"></i> Payment</a>
                            <!--<a class="btn btn-app" data-toggle="modal" data-target="#paymentModal"><i class="fa fa-credit-card"></i> Payment</a>-->
                            <a class="btn btn-app" onClick="alert('Please save current transaction to print Invoice');"><i class="fa fa-print"></i> Print Invoice</a>
                            <a class="btn btn-app" onClick="alert('Please save current transaction to print Receipt');"><i class="fa fa-print"></i> Print Receipt</a>


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
                        function addPatient(patient, patient_no) {
                            //var patient;
                            //patient = document.getElementById("patient").value;

                            if (window.XMLHttpRequest) {
                                xmlhttp2 = new XMLHttpRequest();
                            } else { // code for IE6, IE5
                                xmlhttp2 = new ActiveXObject("Microsoft.XMLHTTP");
                            }
                            xmlhttp2.onreadystatechange = function() {
                                if (xmlhttp2.readyState == 4 && xmlhttp2.status == 200) {

                                    document.getElementById("patientDetials").innerHTML = xmlhttp2.responseText;
									var stEl = document.getElementById('insurance_card_status');
									if (stEl && typeof applyInsuranceCardStatus === 'function') {
										applyInsuranceCardStatus(stEl.value);
									}
                                }
                            }
                            document.getElementById("patient").value = patient_no;

                            xmlhttp2.open("GET", "<?php echo base_url(); ?>app/pos/patientDetials/" + patient, true);
                            xmlhttp2.send();

                            $('#patientListModal').modal('hide');
                            return true;
                        }

                        function autoload() {
                            getPatientList('');
                        }

                        function getPatientList(val) {


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
                            xmlhttp.open("GET", "<?php echo base_url(); ?>app/pos/showPatients/" + val, true);
                            xmlhttp.send();

                        }
                    </script>
					<script type="text/javascript">
						function applyInsuranceCardStatus(status) {
							status = (status || '').toString().toUpperCase().trim();
							var paymentEl = document.getElementById('paymentType');
							if (!paymentEl) {
								return;
							}
							var insuranceOpt = null;
							for (var i = 0; i < paymentEl.options.length; i++) {
								if (paymentEl.options[i].value === 'insurance') {
									insuranceOpt = paymentEl.options[i];
									break;
								}
							}
							if (!insuranceOpt) {
								return;
							}
							if (status === 'INACTIVE') {
								insuranceOpt.disabled = true;
								if (paymentEl.value === 'insurance') {
									paymentEl.value = 'cash';
									if (typeof setTitle === 'function') {
										setTitle('cash');
									}
								}
							} else {
								insuranceOpt.disabled = false;
							}
						}
					</script>
                    <input onKeyUp="getPatientList(this.value)" class="form-control input-sm" name="cSearch" id="cSearch" type="text" placeholder="Search here">
                    <span id="showPatients">

                    </span>


                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <!-- <button type="button" class="btn btn-primary" onClick="return addPatient()">Proceed</button>-->
                </div>

            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>














    <!-- / patientListModal modal -->
    <div class="modal fade" id="doctorListModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="myModalLabel">Doctor's Fee</h4>
                </div>
                <div class="modal-body">

                    <div id="msgNotif"></div>

                    <form name="frmDoctorFee" id="frmDoctorFee" method="post">
                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                        <table class="table table-striped">
                            <tr>
                                <td>Select Doctor <font color="#FF0000">*</font>
                                </td>
                                <td>
                                    <select name="doctor" id="doctorC" class="form-control input-sm" style="width: 100%;" required onchange="clearFields()">
                                        <option value="">- Select Doctor -</option>
                                        <?php
                                        foreach ($doctorList as $doctorList) {

                                        ?>
                                            <option value="<?php echo trim($doctorList->user_id); ?>"><?php echo $doctorList->name; ?></option>
                                        <?php } ?>
                                    </select>

                                </td>
                            </tr>
                            <tr>
                                <td>Fee Type <font color="#FF0000">*</font>
                                </td>
                                <td>
                                    <select name="cType" id="cType" class="form-control input-sm" style="width: 100%;" required>
                                        <option value="">- Select Fee Type -</option>
                                        <option value="percentage">Percentage</option>
                                        <option value="actual">Actual Fee</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>Value <font color="#FF0000">*</font>
                                </td>
                                <td>
                                    <input type="text" class="form-control input-sm" style="width: 100%;" required placeholder="Value" onkeyup="compute(this.value)" name="valueFee" id="valueFee">
                                </td>
                            </tr>
                            <tr>
                                <td>Total Fee <font color="#FF0000">*</font>
                                </td>
                                <td>
                                    <input type="text" style="font-size:26px; width:100%; background-color:rgba(243, 215, 16, 0.27);;" readonly="" name="totalFee" id="totalFee">
                                </td>
                            </tr>
                            <tr>
                                <td>Notes <font color="#FF0000">*</font>
                                </td>
                                <td>
                                    <textarea style="width:100%;" name="notes" id="notes" rows="4"></textarea>
                                </td>
                            </tr>
                        </table>
                    </form>


                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onClick="saveDoctorFee()" name="btnSaveDoctorFee" id="btnSaveDoctorFee">Save</button>
                </div>

            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>



    <script type="text/javascript">
        var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
        var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
        $(document).ready(function() {
            var invoiceno = $('#invoiceno').val();
            // alert(invoiceno);
            var fetchData = {};
            fetchData[csrfName] = csrfHash;
            $.ajax({
                url: "<?php echo base_url(); ?>app/pos/getDoctorFee/" + invoiceno,
                type: "POST",
                data: fetchData,
                dataType: "json",
                success: function(result) {
                    // alert(result.user_id);
                    $('#doctorC').val(result.user_id);
                    $('#cType').val(result.feeType);
                    $('#valueFee').val(result.value);
                    $('#totalFee').val(result.totalFee);
                    $('#notes').val(result.notes);
                }
            });
        });


        function compute(val) {
            var cType = $('#cType').val();
            var total_amount = $('#total_amount').val();


            if (cType == "percentage") {
                var percentageValue = 0;
                percentageValue = val / 100;

                totalFee = total_amount * percentageValue;
            } else if (cType == "actual") {
                totalFee = val;
            }

            $('#totalFee').val(totalFee);

        }

        function saveDoctorFee() {
            var formdata = $('#frmDoctorFee').serialize();
            var invoiceno = $('#invoiceno').val();

            $.ajax({
                url: "<?php echo base_url(); ?>app/pos/saveDoctorFee/" + invoiceno,
                type: "POST",
                data: formdata,
                success: function(result) {
                    // alert(result);
                    $('#btnSaveDoctorFee').removeClass("disabled");
                    $('#btnSaveDoctorFee').text('Save');

                    alert("Doctor's Fee has been saved.");

                },
                beforeSend: function() {
                    $('#btnSaveDoctorFee').addClass("disabled");
                    $('#btnSaveDoctorFee').text('Saving...');
                }
            });


        }

        function clearFields() {
            $('#valueFee').val("");
            $('#totalFee').val("");
        }
    </script>

































    <!-- / payment modal -->

    <!-- / payment modal -->
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
                    </div>

                    <div class="form-group">
                        <label for="exampleInputEmail1">Mode of Payment</label>
                        <select name="paymentType" id="paymentType" class="form-control input-sm" onChange="setTitle(this.value)" readonly>
                            <!--<option value="">- Mode of Payment -</option>-->
                            <option value="cash">Cash</option>
                            <option value="credit">Credit</option>
                            <option value="insurance">Insurance Company</option>
                        </select>
                    </div>

                    <div class="form-group" id="totalAmountz">
                        <label for="exampleInputEmail1">Total Amount</label>
                        <input type="text" placeholder="Total Amount" readonly name="totalAmount" id="totalAmount" class="form-control input-sm totalAmount">
                    </div>

                    <div class="form-group" id="amountPaid">
                        <label for="exampleInputEmail1">Amount Paid</label>
                        <input type="text" placeholder="Amount Paid" name="amountPaid" id="amountPaid" class="form-control input-sm">
                    </div>

                    <div class="form-group" id="change">
                        <label for="exampleInputEmail1">Change</label>
                        <input type="text" placeholder="Change" name="change" readonly id="change" class="form-control input-sm">
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
                    <button type="button" class="btn btn-primary" onClick="return addItem()">Save</button>
                </div>

            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>

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
                                if (xmlhttp3.responseText) {
                                    document.getElementById("showDrugListItem").innerHTML = xmlhttp3.responseText;
                                }

                            }
                        }
                        var supp;
                        xmlhttp3.open("GET", "<?php echo base_url(); ?>app/billing/drug_list/" + category_id, true);
                        xmlhttp3.send();

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
                                if (xmlhttp.responseText) {
                                    document.getElementById("showCategories").innerHTML = xmlhttp.responseText;
                                }

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
                                    <select name="bill_category" onChange="showBills(this.value);" id="bill_category" class="form-control input-sm" style="width: 100%;">
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
                                    <select name="category" onChange="showDrugName(this.value);" id="category" class="form-control input-sm" style="width: 100%;" required>
                                        <option value="">- Paricular Category -</option>
                                        <?php
                                        foreach ($particular_cat as $particular_cat) { ?>
                                            <option value="<?php echo $particular_cat->group_id; ?>"><?php echo $particular_cat->group_name; ?></option>
                                        <?php } ?>
                                    </select>

                                    <select name="medicine_cat" onChange="showDrugList(this.value); otherOptions(this.value)" id="medicine_cat" class="form-control input-sm" style="width: 100%; display: none;" required>
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
                                        <select name="item" id="item" class="form-control input-sm" style="width: 100%;" required>
                                            <option value="">- Paricular Item -</option>
                                        </select>
                                    </span>

                                    <span id="showDrugListItem" style="display: none;">
                                        <select name="item2" id="item2" class="form-control input-sm" style="width: 100%;" required>
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
                                <td><input type="text" onkeypress="return isNumberKey(event)" name="qty" id="qty" value="1" placeholder="Qty" class="form-control input-sm" style="width: 100%;" required></td>
                            </tr>
                            <tr>
                                <td>Rate <font color="#FF0000">*</font></td>
                                <td>
                                    <label id="showRate">
                                        <input type="text" onkeypress="return isNumberKey(event)" name="rate" id="rate" placeholder="rate" class="form-control input-sm" style="width: 100%;" required>
                                    </label>

                                    <label id="showDrugRate" style="display:none">
                                        <input type="text" onkeypress="return isNumberKey(event)" name="drugrate" id="drugrate" placeholder="rate" class="form-control input-sm" style="width: 100%;" required>
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
                                function fetchPatientBills(askConfirm) {
                                    if (askConfirm && !confirm('Are you sure you want to get the Bills?')) {
                                        return false;
                                    }

                                    var patientNoEl = document.getElementById("patient_no");
                                    var iopNoEl = document.getElementById("opd_no");
                                    var patientNo = patientNoEl ? (patientNoEl.value || '') : '';
                                    var iopNo = iopNoEl ? (iopNoEl.value || '') : '';

                                    if (!patientNo || !iopNo) {
                                        alert('Patient/Visit not loaded. Please select a patient/visit first.');
                                        return false;
                                    }

                                    var baseUrl = "<?php echo base_url(); ?>";

                                    function esc(v) {
                                        v = (v === null || typeof v === 'undefined') ? '' : String(v);
                                        return v
                                            .replace(/&/g, '&amp;')
                                            .replace(/</g, '&lt;')
                                            .replace(/>/g, '&gt;')
                                            .replace(/"/g, '&quot;')
                                            .replace(/'/g, '&#039;');
                                    }

                                    function mapType(sourceModule) {
                                        var src = (sourceModule || '').toString().toUpperCase();
                                        if (src === 'PHARMACY') return { itemType: 'medicine', labelClass: 'label-success', labelText: 'Medicine', isMedicine: true };
                                        if (src === 'LAB') return { itemType: 'laboratory', labelClass: 'label-primary', labelText: 'Laboratory', isMedicine: false };
                                        if (src === 'SONOGRAPHY') return { itemType: 'sonography', labelClass: 'label-warning', labelText: 'Sonography', isMedicine: false };
                                        if (src === 'IPD_ROOM') return { itemType: 'room', labelClass: 'label-default', labelText: 'Room', isMedicine: false };
                                        if (src === 'IPD_OT') return { itemType: 'surgery', labelClass: 'label-danger', labelText: 'Surgery', isMedicine: false };
                                        if (src === 'IPD_BED_SIDE') return { itemType: 'procedure', labelClass: 'label-info', labelText: 'Procedure', isMedicine: false };
                                        if (src === 'REGISTRATION') return { itemType: 'registration', labelClass: 'label-default', labelText: 'Registration', isMedicine: false };
                                        if (src === 'CONSULTATION') return { itemType: 'consultation', labelClass: 'label-info', labelText: 'Consult', isMedicine: false };
                                        return { itemType: 'particular', labelClass: 'label-info', labelText: 'Service', isMedicine: false };
                                    }

                                    function rowExists(sourceRef, particular) {
                                        var table = document.getElementById('myTable');
                                        if (!table) return false;
                                        var rows = table.getElementsByTagName('tr');
                                        if (sourceRef) {
                                            for (var r = 0; r < rows.length; r++) {
                                                var refInput = rows[r].querySelector('input[name^="source_ref"]');
                                                if (refInput && refInput.value === sourceRef) return true;
                                            }
                                        }
                                        if (!sourceRef && particular) {
                                            for (var r2 = 0; r2 < rows.length; r2++) {
                                                var nameInput = rows[r2].querySelector('input[name^="bill_name"]');
                                                if (nameInput && nameInput.value === particular) return true;
                                            }
                                        }
                                        return false;
                                    }

                                    function addBillRow(item) {
                                        var table = document.getElementById('myTable');
                                        if (!table) return;
                                        var tbl = table.getElementsByTagName('tr');
                                        var lastRow = tbl.length;

                                        var sourceModule = item.source_module || '';
                                        var sourceRef = item.source_ref || '';
                                        var isPackage = item.isPackage || '0';
                                        var typeInfo = mapType(sourceModule);

                                        var particular = item.drug_name || item.particular_name || item.medicine_text || '';
                                        var qty = parseFloat(item.total_qty || item.qty || 1);
                                        if (isNaN(qty) || qty <= 0) qty = 1;
                                        var rate = parseFloat(item.nPrice || item.charge_amount || 0);
                                        if (isNaN(rate) || rate < 0) rate = 0;
                                        var amount = (qty * rate).toFixed(2);

                                        if (rowExists(sourceRef, particular)) {
                                            return;
                                        }

                                        var dosage = item.dosage || '';
                                        var advice = item.advice || '';
                                        var instruction = item.instruction || '';
                                        var frequency = item.frequency || '';
                                        var days = parseInt(item.days || 0, 10);
                                        if (isNaN(days) || days < 0) days = 0;

                                        var prescriptionInfo = [];
                                        if (typeInfo.isMedicine) {
                                            if (dosage) prescriptionInfo.push(dosage);
                                            if (frequency) prescriptionInfo.push(frequency);
                                            if (days && days > 0) prescriptionInfo.push(days + ' days');
                                        }
                                        var prescriptionStr = prescriptionInfo.join(' | ');
                                        if (!prescriptionStr && instruction && typeInfo.isMedicine) prescriptionStr = instruction;

                                        var medInfoHtml = '';
                                        if (typeInfo.isMedicine) {
                                            medInfoHtml = '<small>';
                                            if (prescriptionStr) medInfoHtml += '<strong>Rx:</strong> ' + esc(prescriptionStr) + '<br>';
                                            if (advice) medInfoHtml += '<strong>Advice:</strong> ' + esc(advice) + '<br>';
                                            if (instruction && instruction !== prescriptionStr) medInfoHtml += '<strong>Note:</strong> ' + esc(instruction);
                                            medInfoHtml += '</small>';
                                            if (medInfoHtml === '<small></small>') medInfoHtml = '<span class="text-muted">See Rx</span>';
                                        } else {
                                            medInfoHtml = '<span class="text-muted">N/A</span>';
                                        }

                                        var a = table.insertRow(-1);
                                        var b = a.insertCell(0);
                                        var c = a.insertCell(1);
                                        var cType = a.insertCell(2);
                                        var d = a.insertCell(3);
                                        var e = a.insertCell(4);
                                        var f = a.insertCell(5);
                                        var g = a.insertCell(6);
                                        var h = a.insertCell(7);
                                        var k = a.insertCell(8);

                                        b.innerHTML = "<input type=\"hidden\" name=\"isPackage" + lastRow + "\" id=\"isPackage" + lastRow + "\" value=\"" + esc(isPackage) + "\"><input type=\"hidden\" name=\"item_type" + lastRow + "\" id=\"item_type" + lastRow + "\" value=\"" + esc(typeInfo.itemType) + "\"><input type=\"hidden\" name=\"source_module" + lastRow + "\" id=\"source_module" + lastRow + "\" value=\"" + esc(sourceModule) + "\"><input type=\"hidden\" name=\"source_ref" + lastRow + "\" id=\"source_ref" + lastRow + "\" value=\"" + esc(sourceRef) + "\"><input type=\"text\" size = \"7\" style=\"width:98%; background-color:#F9F9f9; border:1px solid #ccc; text-align:right\" name=\"id" + lastRow + "\" id=\"id" + lastRow + "\" value=\"" + lastRow + ". \" readonly=\"true\">";
                                        c.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; background-color:#F9F9f9; border:1px solid #ccc;\" name=\"bill_name" + lastRow + "\" id=\"bill_name" + lastRow + "\" value=\"" + esc(particular) + "\" readonly=\"true\">";
                                        cType.innerHTML = '<span class="label ' + esc(typeInfo.labelClass) + '">' + esc(typeInfo.labelText) + '</span>';
                                        d.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; text-align:right\" name=\"qty" + lastRow + "\" id=\"qty" + lastRow + "\" class=\"" + lastRow + "\" value=\"" + esc(qty) + "\" onBlur=\"return validate_input(this.className,'qty')\" onkeyup=\"validate_gross(this.className,'qty')\" onkeypress=\"return isNumberKey(event)\" >";
                                        e.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; text-align:right\" name=\"rate" + lastRow + "\" id=\"rate" + lastRow + "\" class=\"" + lastRow + "\" value=\"" + esc(rate) + "\" onBlur=\"return validate_input(this.className,'rate')\" onkeyup=\"validate_gross(this.className,'rate')\" onkeypress=\"return isNumberKey(event)\">";
                                        f.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; background-color:#F9F9f9; border:1px solid #ccc; text-align:right\" name=\"amount" + lastRow + "\" id=\"amount" + lastRow + "\" value=\"" + esc(amount) + "\" readonly=\"true\">";
                                        g.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%;\" name=\"note" + lastRow + "\" id=\"note" + lastRow + "\" value=\"\">";
                                        h.innerHTML = medInfoHtml + "<input type=\"hidden\" name=\"dosage" + lastRow + "\" id=\"dosage" + lastRow + "\" value=\"" + esc(dosage) + "\"><input type=\"hidden\" name=\"advice" + lastRow + "\" id=\"advice" + lastRow + "\" value=\"" + esc(advice) + "\"><input type=\"hidden\" name=\"instruction" + lastRow + "\" id=\"instruction" + lastRow + "\" value=\"" + esc(instruction) + "\"><input type=\"hidden\" name=\"frequency" + lastRow + "\" value=\"" + esc(frequency) + "\"><input type=\"hidden\" name=\"days" + lastRow + "\" value=\"" + esc(days) + "\">";
                                        k.innerHTML = "<img src=\"<?php echo base_url()?>public/img/b_drop.png\" onclick=\"deleteRow(this)\" style=\"cursor:pointer;\">";

                                        document.getElementById("hdnrowcnt").value = lastRow;
                                    }

                                    $.ajax({
                                        url: baseUrl + 'app/pos/patientMedicationJson/' + encodeURIComponent(patientNo) + '/' + encodeURIComponent(iopNo),
                                        type: 'GET',
                                        dataType: 'json',
                                        success: function(resp) {
                                            if (!resp || !resp.success) {
                                                alert((resp && resp.message) ? resp.message : 'Failed to load bills');
                                                return;
                                            }
                                            var items = resp.items || [];
                                            if (!items.length) {
                                                if (resp.invoice_no) {
                                                    window.location = baseUrl + 'app/pos/saved/' + encodeURIComponent(iopNo) + '/' + encodeURIComponent(patientNo) + '/' + encodeURIComponent(resp.invoice_no);
                                                    return;
                                                }
                                                var stats = resp.stats || {};
                                                var locked = parseInt(stats.locked || 0, 10);
                                                var unverified = parseInt(stats.unverified_meds || 0, 10);
                                                var unavailable = parseInt(stats.unavailable_meds || 0, 10);
                                                var msg = 'No pending bill items found for this visit.';
                                                var reasons = [];
                                                if (locked > 0) reasons.push(locked + ' item(s) already invoiced');
                                                if (unverified > 0) reasons.push(unverified + ' prescription(s) not verified');
                                                if (unavailable > 0) reasons.push(unavailable + ' prescription(s) unavailable');
                                                if (reasons.length) msg += "\n\n" + reasons.join("\n");
                                                alert(msg);
                                                return;
                                            }
                                            for (var i = 0; i < items.length; i++) {
                                                addBillRow(items[i]);
                                            }
                                            if (typeof getGross === 'function') getGross();
                                        },
                                        error: function() {
                                            alert('Failed to load bills. Please try again.');
                                        }
                                    });

                                    return true;
                                }

                                function getPatientMedication() {
                                    return fetchPatientBills(true);
                                }
                            </script>
                            <tr>
                                <td></td>
                                <td>
                                    <!--<span id="buttonMedication" style="display: none;">
                                            <a href="#" class="btn btn-danger" onClick="getPatientMedication()" style="width: 250px;">Get Patient Medication</a>
                                            </span>-->
                                </td>
                            </tr>
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
        $(document).ready(function() {

            // Initialize 
            $("#autouser").autocomplete({
                source: function(request, response) {
                    // Fetch data
                    $.ajax({
                        url: "<?= base_url() ?>app/opd/getMeds",
                        type: 'post',
                        dataType: "json",
                        data: {
                            search: request.term
                        },
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
            var k = a.insertCell(8);

            b.innerHTML = "<input type=\"hidden\" name=\"isPackage" + lastRow + "\" id=\"isPackage" + lastRow + "\" value=\"0\"><input type=\"hidden\" name=\"item_type" + lastRow + "\" id=\"item_type" + lastRow + "\" value=\"" + itemType + "\"><input type=\"text\" size = \"7\" style=\"width:98%; background-color:#F9F9f9; border:1px solid #ccc; text-align:right\" name=\"id" + lastRow + "\" id=\"id" + lastRow + "\" value=\"" + lastRow + ". \" readonly=\"true\">";
            c.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; background-color:#F9F9f9; border:1px solid #ccc;\" name=\"bill_name" + lastRow + "\" id=\"bill_name" + lastRow + "\" value=\"" + particular + "\" readonly=\"true\">";
            cType.innerHTML = itemTypeLabel;
            d.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; text-align:right\" name=\"qty" + lastRow + "\" id=\"qty" + lastRow + "\" class=\"" + lastRow + "\" value=\"" + qty + "\" onBlur=\"return validate_input(this.className,'qty')\" onkeyup=\"validate_gross(this.className,'qty')\" onkeypress=\"return isNumberKey(event)\" >";
            e.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; text-align:right\" name=\"rate" + lastRow + "\" id=\"rate" + lastRow + "\" class=\"" + lastRow + "\" value=\"" + rate + "\" onBlur=\"return validate_input(this.className,'rate')\" onkeyup=\"validate_gross(this.className,'rate')\" onkeypress=\"return isNumberKey(event)\">";
            f.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%; background-color:#F9F9f9; border:1px solid #ccc; text-align:right\" name=\"amount" + lastRow + "\" id=\"amount" + lastRow + "\" value=\"" + amount + "\" readonly=\"true\">";
            g.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%;\" name=\"note" + lastRow + "\" id=\"note" + lastRow + "\" value=\"" + note + "\">";
            // Combined medication info column (only shows data for medicines)
            h.innerHTML = medInfoHtml + "<input type=\"hidden\" name=\"dosage" + lastRow + "\" id=\"dosage" + lastRow + "\" value=\"" + (dosage || '') + "\"><input type=\"hidden\" name=\"advice" + lastRow + "\" id=\"advice" + lastRow + "\" value=\"" + (advice || '') + "\"><input type=\"hidden\" name=\"instruction" + lastRow + "\" id=\"instruction" + lastRow + "\" value=\"" + (instruction || '') + "\">";
            k.innerHTML = "<img src=\"<?php echo base_url() ?>public/img/b_drop.png\" onclick=\"deleteRow(this)\" style=\"cursor:pointer;\">"; 

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
            $(".totalAmount").val(nTotal);
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
            } else if (document.getElementById("patient").value == "") {
                alert('Please select Patient.');
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
        
        <?php if (isset($auto_load_io_id) && $auto_load_io_id !== ''): ?>
        // Auto-load patient from search - uses IO_ID to load patient details via AJAX
        $(document).ready(function() {
            var ioId = '<?php echo addslashes($auto_load_io_id); ?>';
            <?php $autoPatientNo = isset($auto_load_patient_no) ? $auto_load_patient_no : ''; ?>
            var patientNo = '<?php echo addslashes($autoPatientNo); ?>';
            <?php if (!isset($direct)): ?>
            // Only load patient via AJAX if not in direct mode (patient info already loaded server-side)
            if (ioId) {
                // Load patient details via AJAX using IO_ID
                $.ajax({
                    url: '<?php echo base_url(); ?>app/pos/patientDetials/' + ioId,
                    type: 'GET',
                    success: function(html) {
                        $('#patientDetials').html(html);
                        // Extract patient_no from the loaded content and set hidden field
                        var patientNoMatch = html.match(/patient_no['"]\s*value=['"]([\w-]+)['"]/i);
                        if (patientNoMatch && patientNoMatch[1]) {
                            $('#patient').val(patientNoMatch[1]);
                            patientNo = patientNoMatch[1];
                        } else if (patientNo) {
                            $('#patient').val(patientNo);
                        }
                        // Apply insurance card status if present
                        var stEl = document.getElementById('insurance_card_status');
                        if (stEl && typeof applyInsuranceCardStatus === 'function') {
                            applyInsuranceCardStatus(stEl.value);
                        }
                        
                        <?php endif; // End of !isset($direct) check ?>
                        
                        <?php if (isset($auto_load_lab_bill) && $auto_load_lab_bill): ?>
                        // Auto-add the specific lab bill item to the billing form
                        setTimeout(function() {
                            var labItem = {
                                name: '<?php echo addslashes(isset($auto_load_lab_bill->item_name) ? $auto_load_lab_bill->item_name : "Laboratory Test"); ?>',
                                rate: <?php echo (float)(isset($auto_load_lab_bill->rate_amount) ? $auto_load_lab_bill->rate_amount : 0); ?>,
                                lab_bill_id: <?php echo (int)(isset($auto_load_lab_bill->lab_bill_id) ? $auto_load_lab_bill->lab_bill_id : 0); ?>,
                                io_lab_id: <?php echo (int)(isset($auto_load_lab_bill->io_lab_id) ? $auto_load_lab_bill->io_lab_id : 0); ?>
                            };
                            
                            // Add item directly to myTable using existing format
                            var tbl = document.getElementById('myTable').getElementsByTagName('tr');
                            var lastRow = tbl.length;
                            var qty = 1;
                            var rate = labItem.rate;
                            var amount = (qty * rate).toFixed(2);
                            
                            var a = document.getElementById('myTable').insertRow(-1);
                            var b = a.insertCell(0);
                            var c = a.insertCell(1);
                            var cType = a.insertCell(2);
                            var d = a.insertCell(3);
                            var e = a.insertCell(4);
                            var f = a.insertCell(5);
                            var g = a.insertCell(6);
                            var h = a.insertCell(7);
                            var k = a.insertCell(8);
                            
                            b.innerHTML = '<input type="hidden" name="isPackage' + lastRow + '" id="isPackage' + lastRow + '" value="0"><input type="hidden" name="item_type' + lastRow + '" id="item_type' + lastRow + '" value="particular"><input type="hidden" name="lab_bill_id' + lastRow + '" value="' + labItem.lab_bill_id + '"><input type="hidden" name="io_lab_id' + lastRow + '" value="' + labItem.io_lab_id + '"><input type="text" size="7" style="width:98%; background-color:#F9F9f9; border:1px solid #ccc; text-align:right" name="id' + lastRow + '" id="id' + lastRow + '" value="' + lastRow + '. " readonly="true">';
                            c.innerHTML = '<input type="text" size="7" style="width:98%; background-color:#F9F9f9; border:1px solid #ccc;" name="bill_name' + lastRow + '" id="bill_name' + lastRow + '" value="' + labItem.name + '" readonly="true">';
                            cType.innerHTML = '<span class="label label-info">Service</span>';
                            d.innerHTML = '<input type="text" size="7" style="width:98%; text-align:right" name="qty' + lastRow + '" id="qty' + lastRow + '" class="' + lastRow + '" value="' + qty + '" onBlur="return validate_input(this.className,\'qty\')" onkeyup="validate_gross(this.className,\'qty\')" onkeypress="return isNumberKey(event)">';
                            e.innerHTML = '<input type="text" size="7" style="width:98%; text-align:right" name="rate' + lastRow + '" id="rate' + lastRow + '" class="' + lastRow + '" value="' + rate.toFixed(2) + '" onBlur="return validate_input(this.className,\'rate\')" onkeyup="validate_gross(this.className,\'rate\')" onkeypress="return isNumberKey(event)">';
                            f.innerHTML = '<input type="text" size="7" style="width:98%; background-color:#F9F9f9; border:1px solid #ccc; text-align:right" name="amount' + lastRow + '" id="amount' + lastRow + '" value="' + amount + '" readonly="true">';
                            g.innerHTML = '<input type="text" size="7" style="width:98%;" name="note' + lastRow + '" id="note' + lastRow + '" value="">';
                            h.innerHTML = '<span class="text-muted">N/A</span><input type="hidden" name="dosage' + lastRow + '" id="dosage' + lastRow + '" value=""><input type="hidden" name="advice' + lastRow + '" id="advice' + lastRow + '" value=""><input type="hidden" name="instruction' + lastRow + '" id="instruction' + lastRow + '" value="">';
                            k.innerHTML = '<img src="<?php echo base_url() ?>public/img/b_drop.png" onclick="deleteRow(this)" style="cursor:pointer;">';
                            
                            document.getElementById("hdnrowcnt").value = lastRow;
                            if (typeof getGross === 'function') getGross();
                        }, 500);
                        <?php else: ?>
                        // Auto-load pending billing items (medications, lab tests, etc.)
                        if (patientNo && ioId) {
                            setTimeout(function() {
                                if (typeof fetchPatientBills === 'function') {
                                    fetchPatientBills(false);
                                }
                            }, 500);
                        }
                        <?php endif; ?>
                    <?php if (!isset($direct)): ?>
                    }
                });
            }
                    <?php endif; ?>
        });
        <?php endif; ?>
    </script>
    
    <!-- Floating Back to Dashboard Button -->
    <a href="<?php echo base_url(); ?>app/dashboard" class="back-float-btn" title="Back to Dashboard">
        <i class="fa fa-home"></i>
    </a>
</body>

</html>