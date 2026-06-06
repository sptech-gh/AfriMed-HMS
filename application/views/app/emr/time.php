
        
        <!DOCTYPE html>
<html>
    <head>
<head>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link type="text/css" href="<?php echo base_url();?>public/timepicker/bootstrap-timepicker.min.css" />
    </head>  
    <body>
        <div class="input-append bootstrap-timepicker">
            <input id="timepicker1" type="text" class="input-small">
            <span class="add-on"><i class="icon-time"></i></span>
        </div>
 
        <!-- TIMEPICKER -->
        <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
    	<script type="text/javascript" src="<?php echo base_url();?>public/timepicker/bootstrap-2.2.2.min.js"></script>
    	<script type="text/javascript" src="<?php echo base_url();?>public/timepicker/bootstrap-timepicker.min.js"></script>
        <script type="text/javascript" src="<?php echo base_url();?>public/timepicker/bootstrap-timepicker.js"></script>
    	<script type="text/javascript">
        	$(document).ready(function () { 
            	$('#timepicker1').timepicker();
        	});
    	</script>
        <!-- TIMEPICKER -->
    </body>
</html>