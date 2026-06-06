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
        <link href="<?php echo base_url();?>public/css/highlight.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/wysiwyg.min.css" rel="stylesheet" type="text/css" />
        
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
                    <h1>Add Lab Results</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Admin</a></li>
                        <!-- <li><a href="<?php echo base_url()?>app/complain">Complain Master</a></li> -->
                        <li class="active">Lab</li>
                    </ol>
                </section>
				
                <!-- Main content -->
                <section class="content">
                 
                 
                 <div class="row">
                 	<div class="col-md-12">
                    
                    	 <div class="box">
                         		
                         		<div class="box-header">
                                    <h3 class="box-title"><?php echo $lab_request_name; ?></h3>
                                    
                                </div>
                        	<div class="box-body table-responsive">
                            	<form role="form" method="post" action="<?php echo base_url()?>app/laboratory/save_result" enctype="multipart/form-data" onSubmit="return confirm('Are you sure you want to save?');">
                                
                                		<?php echo validation_errors(); ?>   
                                        <input type="text" name="io_lab_id" hidden value="<?php echo $lab;?>">
                                        <input type="text" name="iop_id" hidden value="<?php echo $lab_patient;?>">
                                        <input type="text" name="lab_name" hidden value="<?php echo $lab_request_name;?>">

                                		<div class="form-group">
                                            <label class="control-label" for="findings">Findings:</label>
                                            <textarea id="findings" name="findings" class="form-control" rows="3"></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="control-label" for="result">Result:</label>
                                            <textarea id="result" name="result" class="form-control" rows="3"></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="control-label" for="result_upload">Result:</label>
                                            <input type="file" id="result_upload" name="result_upload" class="form-control" >
                                        </div>
                                        
                                        <div class="form-group">
                                            <a href="<?php echo base_url();?>app/laboratory/request/" class="btn btn-default">Cancel</a>
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
        <script src="<?php echo base_url();?>public/js/highlight.min.js" type="text/javascript"></script>
        <script src="<?php echo base_url();?>public/js/wysiwyg.min.js" type="text/javascript"></script>

        <script type="text/javascript">
    $(document).ready(function () {
    
        // For init plugin use:
        $('#findings').wysiwyg({
            toolbar: [
                ['mode'],
                ['operations', ['undo', 'rendo', 'cut', 'copy', 'paste']],
                ['styles'],
                ['fonts', ['select', 'size']],
                ['text', ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'font-color', 'bg-color']],
                ['align', ['left', 'center', 'right', 'justify']],
                ['lists', ['unordered', 'ordered', 'indent', 'outdent']],
                ['components', ['table']],
                ['intervals', ['line-height', 'letter-spacing']],
                ['insert', ['emoji', 'link', 'image', 'video', 'symbol']],
                ['special', ['print', 'unformat', 'visual', 'clean']],
            ],
            fontSizes: ['8px', ... '48px'],
            fontSizeDefault: '12px',
            fontFamilies: ['Open Sans', 'Arial', ... 'Times New Roman', 'Verdana'],
            fontFamilyDefault: 'Open Sans',
            mode: 'editor',
            highlight: true,
            debug: false
        });
        
        $('#result').wysiwyg({
            toolbar: [
                ['mode'],
                ['operations', ['undo', 'rendo', 'cut', 'copy', 'paste']],
                ['styles'],
                ['fonts', ['select', 'size']],
                ['text', ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'font-color', 'bg-color']],
                ['align', ['left', 'center', 'right', 'justify']],
                ['lists', ['unordered', 'ordered', 'indent', 'outdent']],
                ['components', ['table']],
                ['intervals', ['line-height', 'letter-spacing']],
                ['insert', ['emoji', 'link', 'image', 'video', 'symbol']],
                ['special', ['print', 'unformat', 'visual', 'clean']],
            ],
            fontSizes: ['8px', ... '48px'],
            fontSizeDefault: '12px',
            fontFamilies: ['Open Sans', 'Arial', ... 'Times New Roman', 'Verdana'],
            fontFamilyDefault: 'Open Sans',
            mode: 'editor',
            highlight: true,
            debug: false
        });
        
        // To destroy plugin use:
        // $('#editor').wysiwyg('destroy');
        
    });
</script>
        
        
    </body>
</html>