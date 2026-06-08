<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo getFacilityName(); ?> | Reddy HMS</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
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
                    <h1><i class="fa fa-building-o"></i> System Branding Settings</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li class="active">Administrator</li>
                        <li class="active">Branding Settings</li>
                    </ol>
                </section>
 
                <section class="content">
 
                <?php echo isset($message) ? $message : ''; echo validation_errors(); ?>
 
                <?php
                    $facility_name = isset($facilitySettings['facility_name']) ? $facilitySettings['facility_name'] : (isset($companyInfo->company_name) ? $companyInfo->company_name : '');
                    $site_title = isset($facilitySettings['facility_short_name']) ? $facilitySettings['facility_short_name'] : (isset($companyInfo->site_title) ? $companyInfo->site_title : '');
                    $tagline = isset($facilitySettings['facility_tagline']) ? $facilitySettings['facility_tagline'] : (isset($companyInfo->hospital_tagline) ? $companyInfo->hospital_tagline : '');
                    $address = isset($facilitySettings['address']) ? $facilitySettings['address'] : (isset($companyInfo->company_address) ? $companyInfo->company_address : '');
                    $phone = isset($facilitySettings['phone']) ? $facilitySettings['phone'] : (isset($companyInfo->company_contactNo) ? $companyInfo->company_contactNo : '');
                    $email = isset($facilitySettings['email']) ? $facilitySettings['email'] : (isset($companyInfo->company_email) ? $companyInfo->company_email : '');
                    $tin = isset($facilitySettings['tin']) ? $facilitySettings['tin'] : (isset($companyInfo->TIN) ? $companyInfo->TIN : '');
                    
                    // New columns
                    $website = isset($facilitySettings['website']) ? $facilitySettings['website'] : '';
                    $registration_number = isset($facilitySettings['registration_number']) ? $facilitySettings['registration_number'] : '';
                    $footer_note = isset($facilitySettings['footer_note']) ? $facilitySettings['footer_note'] : '';
                    
                    // Logos
                    $logoFile = isset($facilitySettings['logo_path']) ? $facilitySettings['logo_path'] : (isset($companyInfo->logo) ? $companyInfo->logo : '');
                    $headerLogoFile = isset($facilitySettings['logo_light']) ? $facilitySettings['logo_light'] : (isset($companyInfo->header_logo) ? $companyInfo->header_logo : '');
                    $loginLogoFile = isset($facilitySettings['logo_dark']) ? $facilitySettings['logo_dark'] : (isset($companyInfo->login_logo) ? $companyInfo->login_logo : '');
                    $themeDefault = isset($companyInfo->theme_default) ? $companyInfo->theme_default : 'light';
                ?>
 
                <form method="post" action="<?php echo base_url()?>app/company_information/save" onSubmit="return confirm('Save branding settings?');" enctype="multipart/form-data">
                    <?php if (isset($this->security)) { echo '<input type="hidden" name="'.$this->security->get_csrf_token_name().'" value="'.$this->security->get_csrf_hash().'">'; } ?>
                    <input type="hidden" name="old_logo" value="<?php echo htmlspecialchars($logoFile); ?>">
                    <input type="hidden" name="old_header_logo" value="<?php echo htmlspecialchars($headerLogoFile); ?>">
                    <input type="hidden" name="old_login_logo" value="<?php echo htmlspecialchars($loginLogoFile); ?>">
 
                    <!-- Hospital Identity -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="box box-primary">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-hospital-o"></i> Hospital Identity</h3>
                                </div>
                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Hospital Name <span class="text-danger">*</span></label>
                                                <input class="form-control" name="company_name" id="company_name" value="<?php echo htmlspecialchars($facility_name); ?>" type="text" placeholder="Hospital Name" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Browser Tab Title</label>
                                                <input class="form-control" name="site_title" id="site_title" value="<?php echo htmlspecialchars($site_title); ?>" type="text" placeholder="Shown in browser tab">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Tagline / Motto</label>
                                        <input class="form-control" name="hospital_tagline" id="hospital_tagline" value="<?php echo htmlspecialchars($tagline); ?>" type="text" placeholder="e.g. Quality Healthcare For All">
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>Address <span class="text-danger">*</span></label>
                                                <input class="form-control" name="company_address" id="company_address" value="<?php echo htmlspecialchars($address); ?>" type="text" placeholder="Full address" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Phone <span class="text-danger">*</span></label>
                                                <input class="form-control" name="contact" id="contact" value="<?php echo htmlspecialchars($phone); ?>" type="text" placeholder="Phone" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Email</label>
                                                <input class="form-control" name="company_email" id="company_email" value="<?php echo htmlspecialchars($email); ?>" type="email" placeholder="Email">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>TIN No. <span class="text-danger">*</span></label>
                                                <input class="form-control" name="tin" id="tin" value="<?php echo htmlspecialchars($tin); ?>" type="text" placeholder="Tax ID" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Website</label>
                                                <input class="form-control" name="website" id="website" value="<?php echo htmlspecialchars($website); ?>" type="text" placeholder="e.g. www.facility.com">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Registration Number</label>
                                                <input class="form-control" name="registration_number" id="registration_number" value="<?php echo htmlspecialchars($registration_number); ?>" type="text" placeholder="e.g. MH-REG-98721">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>Footer Note / Legal Disclaimer (Printed on invoices and documents)</label>
                                                <textarea class="form-control" name="footer_note" id="footer_note" rows="3" placeholder="Enter disclaimers, refund policies, legal notices, etc."><?php echo htmlspecialchars($footer_note); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
 
                        <!-- Theme Settings & About Platform -->
                        <div class="col-md-4">
                            <div class="box box-primary">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-paint-brush"></i> Theme Settings</h3>
                                </div>
                                <div class="box-body">
                                    <div class="form-group">
                                        <label>Default Theme for New Users</label>
                                        <select name="theme_default" class="form-control">
                                            <option value="light" <?php echo $themeDefault === 'light' ? 'selected' : ''; ?>>Light Mode</option>
                                            <option value="dark" <?php echo $themeDefault === 'dark' ? 'selected' : ''; ?>>Dark Mode</option>
                                        </select>
                                        <span class="help-block">Users can still toggle their own preference.</span>
                                    </div>
                                    <?php if (isset($facilitySettings['updated_at']) && $facilitySettings['updated_at']): ?>
                                    <div class="form-group">
                                        <label>Last Updated</label>
                                        <p class="form-control-static text-muted"><?php echo date('M d, Y h:i A', strtotime($facilitySettings['updated_at'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- About Reddy HMS Platform -->
                            <div class="box box-solid box-info" style="margin-top: 20px;">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-info-circle"></i> About Reddy HMS</h3>
                                </div>
                                <div class="box-body" style="background: #fafafa; transition: background 0.3s ease;">
                                    <table class="table table-condensed table-hover" style="margin-bottom: 0;">
                                        <tbody>
                                            <tr>
                                                <td style="font-weight: 600; width: 40%;">Platform:</td>
                                                <td>Reddy HMS</td>
                                            </tr>
                                            <tr>
                                                <td style="font-weight: 600;">Tagline:</td>
                                                <td>The Healthcare OS</td>
                                            </tr>
                                            <tr>
                                                <td style="font-weight: 600;">Version:</td>
                                                <td><span class="label label-primary">1.0.0</span></td>
                                            </tr>
                                            <tr>
                                                <td style="font-weight: 600;">Build Date:</td>
                                                <td>2026-06-06</td>
                                            </tr>
                                            <tr>
                                                <td style="font-weight: 600;">License:</td>
                                                <td><span class="text-success" style="font-weight: bold;"><i class="fa fa-check-circle"></i> Active (Enterprise)</span></td>
                                            </tr>
                                            <tr>
                                                <td style="font-weight: 600;">Support:</td>
                                                <td><a href="mailto:support@reddyhms.com">support@reddyhms.com</a></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
 
                    <!-- Logo Management -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="box box-primary">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-image"></i> Main Logo</h3>
                                </div>
                                <div class="box-body text-center">
                                    <?php if ($logoFile !== '' && $logoFile !== 'sample.jpg'): ?>
                                        <img src="<?php echo base_url()?>uploads/facility_logos/default/<?php echo htmlspecialchars($logoFile); ?>" class="img-responsive" style="max-height:80px; margin:0 auto 15px;">
                                    <?php else: ?>
                                        <div style="height:80px; line-height:80px; margin-bottom:15px;" class="text-muted"><i class="fa fa-picture-o fa-3x"></i></div>
                                    <?php endif; ?>
                                    <div class="form-group text-left">
                                        <label>Upload New Logo</label>
                                        <input type="file" name="logo" accept=".jpg,.jpeg,.png,.gif,.svg">
                                        <span class="help-block">Used on reports &amp; invoices. PNG/JPG/SVG, max 2MB.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="box box-primary">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-header"></i> Header Logo</h3>
                                </div>
                                <div class="box-body text-center">
                                    <?php if ($headerLogoFile !== '' && $headerLogoFile !== 'sample.jpg'): ?>
                                        <img src="<?php echo base_url()?>uploads/facility_logos/default/<?php echo htmlspecialchars($headerLogoFile); ?>" class="img-responsive" style="max-height:80px; margin:0 auto 15px;">
                                    <?php else: ?>
                                        <div style="height:80px; line-height:80px; margin-bottom:15px;" class="text-muted"><i class="fa fa-picture-o fa-3x"></i></div>
                                    <?php endif; ?>
                                    <div class="form-group text-left">
                                        <label>Upload Header Logo</label>
                                        <input type="file" name="header_logo" accept=".jpg,.jpeg,.png,.gif,.svg">
                                        <span class="help-block">Shown in dashboard header bar. Recommended 180x60px.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="box box-primary">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-sign-in"></i> Login Logo</h3>
                                </div>
                                <div class="box-body text-center">
                                    <?php if ($loginLogoFile !== '' && $loginLogoFile !== 'sample.jpg'): ?>
                                        <img src="<?php echo base_url()?>uploads/facility_logos/default/<?php echo htmlspecialchars($loginLogoFile); ?>" class="img-responsive" style="max-height:80px; margin:0 auto 15px;">
                                    <?php else: ?>
                                        <div style="height:80px; line-height:80px; margin-bottom:15px;" class="text-muted"><i class="fa fa-picture-o fa-3x"></i></div>
                                    <?php endif; ?>
                                    <div class="form-group text-left">
                                        <label>Upload Login Page Logo</label>
                                        <input type="file" name="login_logo" accept=".jpg,.jpeg,.png,.gif,.svg">
                                        <span class="help-block">Shown on login page. Recommended 250x80px.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
 
                    <!-- Save Button -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box">
                                <div class="box-body">
                                    <button class="btn btn-primary btn-lg" name="btnSubmit" id="btnSubmit" type="submit">
                                        <i class="fa fa-save"></i> Save Branding Settings
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
 
                </form>
 
                </section><!-- /.content -->
            </aside><!-- /.right-side -->
        </div><!-- ./wrapper -->
 
        <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
        <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    </body>
</html>