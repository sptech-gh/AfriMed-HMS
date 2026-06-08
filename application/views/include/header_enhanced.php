<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <?php
        $enhCompanyName = getFacilityName();
        $enhSiteTitle = getFacilityName() . ' | ' . getPlatformName();
    ?>
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?><?php echo htmlspecialchars(getFacilityName()); ?> | <?php echo getPlatformName(); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BrandingService::platformLogo(); ?>">
    
    <!-- Core CSS -->
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    
    <!-- Enhanced UI/UX CSS -->
    <link href="<?php echo base_url();?>public/css/hms-enhanced.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>assets/css/hms-responsive.css?v=<?php echo time(); ?>" rel="stylesheet" type="text/css" />
    
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
        <a href="<?php echo base_url()?>app/dashboard" class="logo" style="display: flex; align-items: center; gap: 8px; justify-content: center; text-decoration: none;">
            <img src="<?php echo getFacilityLogo(); ?>" alt="Logo" style="max-height: 32px; width: auto; border-radius: 4px;">
            <span style="font-weight: bold; font-size: 15px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #fff;"><?php echo getFacilityName(); ?></span>
        </a>
        
        <nav class="navbar navbar-static-top" role="navigation">
            <!-- Sidebar toggle button -->
            <a href="#" class="navbar-btn sidebar-toggle" data-toggle="offcanvas" role="button" aria-label="Toggle navigation">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </a>
            
            <div class="logo2" style="display: flex; align-items: center; gap: 10px; height: 50px; float: left; padding: 0 15px; line-height: 50px; color: #fff; font-size: 20px; font-weight: 500;">
                <span class="facility-header-name" style="font-weight: 700; font-size: 18px; color: #ffffff;"><?php echo getFacilityName(); ?></span>
                <span class="platform-power-badge" style="font-size: 10px; background: rgba(255,255,255,0.15); padding: 2px 8px; border-radius: 20px; line-height: 1.4; color: rgba(255,255,255,0.85); font-weight: 600; display: inline-flex; align-items: center; gap: 4px; vertical-align: middle;">
                    Powered by Reddy HMS
                </span>
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
