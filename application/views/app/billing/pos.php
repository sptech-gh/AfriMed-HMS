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

        <!----------BOOTSTRAP DATEPICKER----------------------------->
        <link rel="stylesheet" href="<?php echo base_url(); ?>public/datepicker/css/datepicker.css">
        <!---------------------------------------------------------->

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

    <div class="wrapper row-offcanvas row-offcanvas-left">

        <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>

        <!-- Right side column. Contains the navbar and content of the page -->
        <aside class="right-side">
            <!-- Content Header (Page header) -->
            <section class="content-header">
                <h1>POS</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="#">Billing</a></li>
                    <li class="active">POS</li>
                </ol>
            </section>

            <!-- Main content -->
            <section class="content">


                <div class="row">
                    <div class="col-md-3">

                        <div class="box">
                            <div class="box-body table-responsive">

                                <table width="100%" cellpadding="3" cellspacing="3">
                                    <tr>
                                        <td width="15%" valign="top" align="center">
                                            <?php
                                            $picture = "avatar.png";
                                            if (isset($patientInfo) && is_object($patientInfo) && !empty($patientInfo->picture)) {
                                                $picture = $patientInfo->picture;
                                            }
                                            ?>
                                            <img src="<?php echo base_url(); ?>public/patient_picture/<?php echo $picture; ?>" class="img-rounded" width="86" height="81">
                                        </td>
                                        <td>
                                            <table width="100%">
                                                <tr>
                                                    <td><u>Patient No.</u></td>
                                                </tr>
                                                <tr>
                                                    <td><?php echo isset($patientInfo) && is_object($patientInfo) ? $patientInfo->patient_no : '-'; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><u>Patient Name</u></td>
                                                </tr>
                                                <tr>
                                                    <td><?php echo isset($patientInfo) && is_object($patientInfo) ? $patientInfo->patient : '-'; ?></td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>

                            </div>
                            <div class="box-footer clearfix">
                                <div class="table-responsive">
                                <table cellpadding="2" cellspacing="2" width="100%">
                                    <tr>
                                        <td><b>IOPD No.</b></td>
                                    </tr>
                                    <tr>
                                        <td><?php echo isset($patientInfo) && is_object($patientInfo) ? $patientInfo->IO_ID : '-'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><b>Patient Type</b></td>
                                    </tr>
                                    <tr>
                                        <td><?php echo isset($patientInfo) && is_object($patientInfo) ? $patientInfo->patient_type : '-'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><b>Age</b></td>
                                    </tr>
                                    <tr>
                                        <td><?php echo isset($patientInfo) && is_object($patientInfo) && isset($patientInfo->age) ? $patientInfo->age . ' yrs. old' : '-'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><b>Date Time Visit</b></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <?php if (isset($patientInfo) && is_object($patientInfo) && isset($patientInfo->date_visit)) { ?>
                                            <?php echo date("M d, Y", strtotime($patientInfo->date_visit)); ?>
                                            <?php echo isset($patientInfo->time_visit) ? date("H:i:s", strtotime($patientInfo->time_visit)) : ''; ?>
                                            <?php } else { echo '-'; } ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <br><br><br><br><br><br><br><br><br>
                                        </td>
                                    </tr>
                                </table>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="col-md-9">

                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Particular Items</a></li>
                            </ul>

                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
                                    <div class="alt2" dir="ltr" style="
									margin: 0px;
									padding: 0px;
									border: 0px solid #919b9c;
									width: 100%;
									height: 350px;
									text-align: left;
									overflow: auto">
                                        <div class="table-responsive">
                                        <table class="table table-striped" id="myTable">
                                            <thead>
                                                <tr>
                                                    <th>No.</th>
                                                    <th>Particulars</th>
                                                    <th>Qty</th>
                                                    <th>Rate</th>
                                                    <th>Amount</th>
                                                    <th>Note</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>
                                </div>
                                <br>
                                <div class="table-responsive">
                                <table width="100%">
                                    <tr>
                                        <td>
                                            <hr>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <button class="btn btn-primary" name="btnSubmit" id="btnSubmit" type="submit"><i class="fa fa-hand-o-down"></i> Payment</button>
                                            <button class="btn btn-default" name="btnSubmit" id="btnSubmit" type="submit" data-toggle="modal" data-target="#myModal"><i class="fa fa-plus"></i> Add Item</button>
                                        </td>
                                    </tr>
                                </table>
                                </div>

                            </div>

                        </div>


                    </div>
                </div>




            </section><!-- /.content -->
        </aside><!-- /.right-side -->
    </div><!-- ./wrapper -->


    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js" type="text/javascript"></script>

    <!-- BDAY -->
    <script src="<?php echo base_url(); ?>public/datepicker/js/jquery-1.9.1.min.js"></script>
    <script src="<?php echo base_url(); ?>public/datepicker/js/bootstrap-datepicker.js"></script>
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



    <!-- Modal -->
    <form method="post" action="<?php echo base_url() ?>app/opd/save_medication" onSubmit="return confirm('Are you sure you want to save?');">
        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
        <input type="hidden" name="opd_no" value="<?php echo isset($patientInfo) && is_object($patientInfo) ? $patientInfo->IO_ID : ''; ?>">
        <input type="hidden" name="patient_no" value="<?php echo isset($patientInfo) && is_object($patientInfo) ? $patientInfo->patient_no : ''; ?>">
        <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <h4 class="modal-title" id="myModalLabel">Add Item</h4>
                    </div>

                    <script language="javascript">
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
                    </script>
                    <div class="modal-body">
                        <div class="table-responsive">
                        <table class="table table-striped">
                            <tbody>
                                <tr>
                                    <td>Paricular Category</td>
                                    <td>
                                        <select name="category" onChange="showDrugName(this.value);" id="category" class="form-control input-sm" style="width: 250px; " required>
                                            <option value="">- Paricular Category -</option>
                                            <?php
                                            foreach ($particular_cat as $particular_cat) { ?>
                                                <option value="<?php echo $particular_cat->group_id; ?>"><?php echo $particular_cat->group_name; ?></option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Paricular Item</td>
                                    <td>
                                        <span id="showCategories">
                                            <select name="item" id="item" class="form-control input-sm" style="width: 250px;" required>
                                                <option value="">- Paricular Item -</option>
                                            </select>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Qty</td>
                                    <td><input type="text" name="qty" id="qty" value="1" placeholder="Qty" class="form-control input-sm" style="width: 250px;" required></td>
                                </tr>
                                <tr>
                                    <td>Rate</td>
                                    <td>
                                        <label id="showRate">
                                            <input type="text" name="rate" id="rate" placeholder="rate" class="form-control input-sm" style="width: 250px;" required>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Note</td>
                                    <td><textarea name="note" id="note" placeholder="note" class="form-control input-sm" style="width: 250px;"></textarea></td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onClick="addItem()">Add</button>
                    </div>

                </div>
                <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
        </div>
    </form>
    <!-- /.modal -->
    <script language="javascript">
        function addItem() {

            var tbl = document.getElementById('myTable').getElementsByTagName('tr');
            var lastRow = tbl.length;

            var category, particular, qty, rate, note;
            category = document.getElementById("particular_name").value;
            particular = document.getElementById("bill_name").value;
            qty = document.getElementById("qty").value;
            rate = document.getElementById("rate").value;
            note = document.getElementById("note").value;


            var a = document.getElementById('myTable').insertRow(-1);
            var b = a.insertCell(0);
            var c = a.insertCell(1);
            var d = a.insertCell(2);
            var e = a.insertCell(3);
            var f = a.insertCell(4);
            var g = a.insertCell(5);

            b.innerHTML = lastRow;
            c.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%;\" name=\"category" + lastRow + "\" id=\"category" + lastRow + "\" value=\"" + category + "\" readonly=\"true\">";
            d.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%;\" name=\"particular" + lastRow + "\" id=\"particular" + lastRow + "\" value=\"" + particular + "\" readonly=\"true\">";
            e.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%;\" name=\"qty" + lastRow + "\" id=\"qty" + lastRow + "\" value=\"" + qty + "\" readonly=\"true\">";
            f.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%;\" name=\"rate" + lastRow + "\" id=\"rate" + lastRow + "\" value=\"" + rate + "\" readonly=\"true\">";
            g.innerHTML = "<input type=\"text\" size = \"7\" style=\"width:98%;\" name=\"note" + lastRow + "\" id=\"note" + lastRow + "\" value=\"" + note + "\" readonly=\"true\">";

            $('#myModal').modal('hide');
            return true;
        }
    </script>
</body>

</html>