<!DOCTYPE html>
<html>
    <head>
<head>

        <meta charset="UTF-8">
        <title>Hebrew Medical Center</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

  

        <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
        
        <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
          <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
        <![endif]-->
    </head>  
    <body class="skin-blue">
        <!-- header logo: style can be found in header.less -->
        <?php require_once(APPPATH.'views/include/header.php');?>
        
        <div class="wrapper row-offcanvas row-offcanvas-left">
            
            <?php require_once(APPPATH.'views/include/sidebar.php');?>

            <!-- Right side column. Contains the navbar and content of the page -->
            <aside class="right-side">                
                <!-- Content Header (Page header) -->
                <section class="content-header">
                    <h1>Add Drug Name</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Admin</a></li>
                        <li><a href="#">Medicine Management</a></li>
                        <li><a href="<?php echo base_url()?>app/drug_name">Drug Name Master</a></li>
                        <li class="active">Add Drug Name</li>
                    </ol>
                </section>
				
                <!-- Main content -->
                <section class="content">
                 
                 
                 <div class="row">
                 	<div class="col-md-12">
                    
                    	 <div class="box">
                         		
                         		<div class="box-header">
                                    <h3 class="box-title"></h3>
                                    
                                </div>
                        	<div class="box-body table-responsive">
                            	<form role="form" method="post" action="<?php echo base_url()?>app/drug_name/save" onSubmit="return confirm('Are you sure you want to save?');">
                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                
                                		<?php echo validation_errors(); ?>   
                                
                                		<div class="form-group">
                                            <label for="exampleInputEmail1">Category Name</label>
                                            <select name="category" id="category" class="form-control input-sm" style="width: 250px;" required>
                                                            	<option value="">- Category Name -</option>
																<?php 
																foreach($category as $category){?>
                                                            	<option value="<?php echo $category->cat_id;?>"><?php echo $category->med_category_name;?></option>
                                                                <?php }?>
                                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="exampleInputEmail1">Type</label>
                                            <select name="cType" id="cType" class="form-control input-sm" style="width: 250px;" required>
                                                            	<option value="">- Type -</option>
																<?php 
																foreach($cType as $cType){
																?>
                                                            	<option value="<?php echo $cType->param_id;?>"><?php echo $cType->cValue;?></option>
                                                                <?php }?>
                                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="exampleInputEmail1">Drug Name</label>
                                            <input class="form-control input-sm" name="drug_name" id="drug_name" type="text" placeholder="Drug Name" style="width: 350px;">
                                        </div>

                                        <div class="form-group">
                                            <label>Generic Name</label>
                                            <input class="form-control input-sm" name="generic_name" id="generic_name" type="text" placeholder="e.g. Amoxicillin" style="width: 350px;">
                                        </div>

                                        <div class="form-group">
                                            <label>Strength</label>
                                            <input class="form-control input-sm" name="strength" id="strength" type="text" placeholder="e.g. 500mg, 250mg/5ml" style="width: 250px;">
                                        </div>
                                        
                                        <?php
                                            $uomByLabel = array();
                                            foreach ((isset($uom) ? $uom : array()) as $uomRow) {
                                                $uomByLabel[strtolower(trim((string)$uomRow->cValue))] = (int)$uomRow->param_id;
                                            }
                                        ?>
                                        <div class="form-group">
                                            <label>Drug Form</label>
                                            <select name="dosage_form" id="dosage_form" class="form-control input-sm" style="width: 250px;">
                                                <option value="">-- Select --</option>
                                                <?php foreach ((isset($drug_forms) ? $drug_forms : array()) as $form) { ?>
                                                    <?php $matchedUom = isset($uomByLabel[strtolower(trim((string)$form))]) ? $uomByLabel[strtolower(trim((string)$form))] : ''; ?>
                                                    <option value="<?php echo htmlspecialchars($form); ?>" data-uom-id="<?php echo $matchedUom; ?>"><?php echo htmlspecialchars($form); ?></option>
                                                <?php } ?>
                                            </select>
                                            <input type="hidden" name="uom" id="uom" value="<?php echo isset($default_uom) ? (int)$default_uom : 0; ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="exampleInputEmail1">Re-order level</label>
                                            <input class="form-control input-sm" name="reorder" id="reorder" value="0.00" type="text" placeholder="Re-order level" style="width: 350px;">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="exampleInputEmail1">Price</label>
                                            <input class="form-control input-sm" name="price" id="price" type="text" value="0.00" placeholder="eg 00.00" style="width: 350px;">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="exampleInputEmail1">Stock-on-Hand</label>
                                            <input class="form-control input-sm" name="stock" id="stock" type="text" value="0.00" placeholder="Stock-on-Hand" style="width: 350px;">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="exampleInputEmail1">Description</label>
                                            <input class="form-control input-sm" name="description" id="description" type="text" placeholder="Description" style="width: 350px;">
                                        </div>

                                        <hr style="border-top:2px solid #3c8dbc;">
                                        <h4 style="color:#3c8dbc;"><i class="fa fa-medkit"></i> NHIS Pricing</h4>

                                        <div class="form-group">
                                            <label>
                                                <input type="checkbox" name="is_nhis_covered" id="is_nhis_covered" value="1"> NHIS Covered Drug
                                            </label>
                                        </div>

                                        <div class="form-group">
                                            <label for="nhis_price">NHIS Price</label>
                                            <input class="form-control input-sm" name="nhis_price" id="nhis_price" type="text" value="0.00" placeholder="NHIS price" style="width: 350px;">
                                        </div>

                                        <div class="form-group">
                                            <label for="cash_price">Cash Price</label>
                                            <input class="form-control input-sm" name="cash_price" id="cash_price" type="text" value="0.00" placeholder="Cash price (defaults to Price above)" style="width: 350px;">
                                        </div>
                                        
                                        <div class="form-group">
                                            <a href="<?php echo base_url();?>app/drug_name" class="btn btn-default">Cancel</a>
                                            <button class="btn btn-primary" name="btnSubmit" id="btnSubmit" type="submit"><i class="fa fa-save"></i> Save</button>
                                        </div>
                                        
                                </form>
                                
                            </div>
                        </div>
                    </div>
                 </div>
                 
                 
                </section><!-- /.content -->
            </aside><!-- /.right-side -->
        </div><!-- ./wrapper -->
  
        
         <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
         <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>     
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
        <script>
            (function () {
                var formSelect = document.getElementById('dosage_form');
                var uomInput = document.getElementById('uom');
                var fallbackUom = uomInput ? uomInput.value : '';
                if (!formSelect || !uomInput) return;
                formSelect.onchange = function () {
                    var opt = formSelect.options[formSelect.selectedIndex];
                    uomInput.value = opt && opt.getAttribute('data-uom-id') ? opt.getAttribute('data-uom-id') : fallbackUom;
                };
            }());
        </script>
        
    </body>
</html>
