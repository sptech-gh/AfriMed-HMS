<!DOCTYPE html>
<html lang="en">
  
<head>
<head>

    <meta charset="utf-8">
    <title><?php echo $companyInfo->company_name;?> | Log in</title>

	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes"> 
    
<link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <!-- <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" /> -->
        <!-- <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" /> -->
        <!-- <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" /> -->

</head>
<body>

    
<style type="text/css">
	.header-icon {
		font-family: "trebuchet";
		font-size: 14px;
		color: #E9893D;
		font-weight: bold;
		margin-top: 2px;
	}

	.content-icon {
		font-family: "trebuchet";
		font-weight: bold;
		color: #0171D5;
	}

	.powered{
		margin-top: 50px;
		font-family: "trebuchet";
		font-weight: bold;
		color: #0171D5;
		font-size: 10px;
	}

	.login-icon{
		font-size: 12px;
		font-family: "trebuchet";
	}
</style>




<div class="row">

<div class="col-md-12">
	<div style="background: transparent url('<?php echo base_url()?>public/img/login_details/vmh-bg.png') center; height:600px;">

<br><br><br><br><br>
<div class="row">
	<div class="col-md-12">
		<center><img src="<?php echo base_url()?>public/img/login_details/hms-title.png" class="img-responsive"></center>
	</div>
</div>
<br><br>
<div class="row">

<div class="col-md-12">
	<div class="col-md-2"></div>
	<div class="col-md-2" style="background: transparent url('<?php echo base_url()?>public/img/login_details/admin-icon.png') no-repeat center; height:225px; 	">
		<br><br><br><br><Br><br><br>
		<CENTER>
			<div class="header-icon">ADMINISTRATOR LOGIN</div>
			<div class="content-icon">
			Username: demo-hmsh<br>
			Password: hospital
			</div>
			<div class="login-icon"><a href="<?php echo base_url()?>login/loginNow/demo-hmsh/hospital" target="_blank">LOGIN NOW</a></div>
		</CENTER>
	</div>

	<div class="col-md-2" style="background: transparent url('<?php echo base_url()?>public/img/login_details/doctor-icon.png') no-repeat center; height:230px; 	">
		<br><br><br><br><Br><br><br>
		<CENTER>
			<div class="header-icon">DOCTOR LOGIN</div>
			<div class="content-icon">
			Username: doctor<br>
			Password: demo-doctor
			</div>
			<div class="login-icon"><a href="<?php echo base_url()?>login/loginNow/doctor/demo-doctor" target="_blank">LOGIN NOW</a></div>
		</CENTER>
	</div>

	<div class="col-md-2" style="background: transparent url('<?php echo base_url()?>public/img/login_details/nurse-icon.png') no-repeat center; height:230px; 	">
		<br><br><br><br><Br><br><br>
		<CENTER>
			<div class="header-icon">NURSE LOGIN</div>
			<div class="content-icon">
			Username: nurse<br>
			Password: demo-nurse
			</div>
			<div class="login-icon"><a href="<?php echo base_url()?>login/loginNow/nurse/demo-nurse" target="_blank">LOGIN NOW</a></div>
		</CENTER>
	</div>

	<div class="col-md-2" style="background: transparent url('<?php echo base_url()?>public/img/login_details/receptionist-icon.png') no-repeat center; height:230px; 	">
		<br><br><br><br><Br><br><br>
		<CENTER>
			<div class="header-icon">RECEPTIONIST LOGIN</div>
			<div class="content-icon">
			Username: receptionist<br>
			Password: demo-receptionist
			</div>
			<div class="login-icon"><a href="<?php echo base_url()?>login/loginNow/receptionist/demo-receptionist" target="_blank">LOGIN NOW</a></div>
		</CENTER>
	</div>

	<div class="col-md-2"></div>
</div>

</div>


</div>

</div>

</div>
	



<script src="<?php echo base_url()?>public/login/js/bootstrap.js"></script>

<script src="<?php echo base_url()?>public/login/js/signin.js"></script>

</body>

</html>
