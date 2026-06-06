<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <?php
        $enhCompanyName = (isset($companyInfo) && isset($companyInfo->company_name)) ? trim((string)$companyInfo->company_name) : 'Hebrew Medical Center';
        $enhSiteTitle = (isset($companyInfo) && isset($companyInfo->site_title) && trim((string)$companyInfo->site_title) !== '') ? trim((string)$companyInfo->site_title) : $enhCompanyName;
    ?>
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?><?php echo htmlspecialchars($enhSiteTitle); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo base_url()?>public/company_logo/<?php echo $companyInfo->logo?>">
    
    <!-- Core CSS -->
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    
    <!-- Enhanced UI/UX CSS -->
    <link href="<?php echo base_url();?>public/css/hms-enhanced.css" rel="stylesheet" type="text/css" />
    
    <!-- Select2 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
    
    <!-- Datepicker -->
    <link rel="stylesheet" href="<?php echo base_url(); ?>public/datepicker/css/datepicker.css">
    
    <!-- HTML5 Shim and Respond.js IE8 support -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
    <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
    <![endif]-->
    
    <!-- Header styling handled by hms-enhanced.css design tokens -->
</head>
<body class="skin-blue">
    <!-- Skip to main content for accessibility -->
    <a href="#main-content" class="skip-to-main">Skip to main content</a>
    
    <!-- Alert auto-fade script -->
    <script>
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.3s';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        }, 100);
    </script>
    
    <!-- jQuery (load early for compatibility) -->
    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    
    <!-- Header -->
    <header class="header">
        <a href="<?php echo base_url()?>app/dashboard" class="logo">
            <div class="logo-pms">
                <img src="<?php echo base_url()?>public/company_logo/<?php echo $companyInfo->logo?>" 
                     alt="<?php echo $companyInfo->company_name?>" 
                     height="45">
            </div>
        </a>
        
        <nav class="navbar navbar-static-top" role="navigation">
            <!-- Sidebar toggle button -->
            <a href="#" class="navbar-btn sidebar-toggle" data-toggle="offcanvas" role="button" aria-label="Toggle navigation">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </a>
            
            <div class="logo2"> 
                <?php echo $companyInfo->company_name?>
            </div>
            
            <div class="navbar-right">
                <ul class="nav navbar-nav">
                    <!-- User Account Menu -->
                    <li class="dropdown user user-menu">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="glyphicon glyphicon-user"></i>
                            <span><?php echo $userInfo->firstname." ".$userInfo->lastname;?> <i class="caret"></i></span>
                        </a>
                        <ul class="dropdown-menu">
                            <!-- User image -->
                            <li class="user-header bg-light-blue">
                                <?php if($userInfo->picture == ""){?>
                                    <img src="<?php echo base_url()?>public/user_picture/no_avatar.gif" 
                                         class="img-circle" 
                                         alt="User Image" />
                                <?php }else{?>
                                    <img src="<?php echo base_url()?>public/user_picture/<?php echo $userInfo->picture;?>" 
                                         class="img-circle" 
                                         alt="User Image" />
                                <?php }?>
                                <p>
                                    <?php echo $userInfo->firstname." ".$userInfo->lastname;?><br>
                                    <small><?php echo $userInfo->designation;?></small>
                                </p>
                            </li>
                            <!-- Menu Footer -->
                            <li class="user-footer">
                                <div class="pull-left">
                                    <a href="<?php echo base_url()?>myprofile" class="btn btn-default btn-flat">
                                        <i class="fa fa-user"></i> Profile
                                    </a>
                                </div>
                                <div class="pull-right">
                                    <a href="<?php echo base_url()?>login/logout" class="btn btn-default btn-flat">
                                        <i class="fa fa-sign-out"></i> Sign out
                                    </a>
                                </div>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
    </header>
