<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center — NHIS API Settings</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/hms-enhanced.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-cog"></i> NHIS API Settings</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="<?php echo base_url()?>app/nhis_claims">NHIS Claims</a></li>
                    <li class="active">Settings</li>
                </ol>
            </section>

            <section class="content">
                <?php if(isset($message) && $message){ echo $message; } ?>

                <?php
                    $cfg = isset($api_config) ? $api_config : array();
                    $mode = isset($cfg['api_mode']) ? $cfg['api_mode'] : 'MOCK';
                    $baseUrl = isset($cfg['api_base_url']) ? $cfg['api_base_url'] : '';
                    $apiKey = isset($cfg['api_key']) ? $cfg['api_key'] : '';
                    $approvalRate = isset($cfg['mock_approval_rate']) ? $cfg['mock_approval_rate'] : '70';
                    $underpayRate = isset($cfg['mock_underpay_rate']) ? $cfg['mock_underpay_rate'] : '15';
                    $rejectRate = isset($cfg['mock_reject_rate']) ? $cfg['mock_reject_rate'] : '15';
                    $delayMs = isset($cfg['mock_delay_ms']) ? $cfg['mock_delay_ms'] : '500';
                ?>

                <form method="post" action="<?php echo base_url(); ?>app/nhis_claims/save_settings">
                    <div class="row">
                        <!-- API Mode -->
                        <div class="col-md-6">
                            <div class="box box-primary">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-toggle-on"></i> API Mode</h3>
                                </div>
                                <div class="box-body">
                                    <div class="form-group">
                                        <label>Current Mode</label>
                                        <select name="api_mode" class="form-control" id="apiModeSelect">
                                            <option value="MOCK" <?php echo $mode==='MOCK'?'selected':''; ?>>MOCK — Simulated Responses</option>
                                            <option value="LIVE" <?php echo $mode==='LIVE'?'selected':''; ?>>LIVE — Real NHIS API</option>
                                        </select>
                                        <span class="help-block">
                                            <strong>MOCK:</strong> Uses random approval/rejection simulation for testing.<br>
                                            <strong>LIVE:</strong> Connects to real NHIS API (requires credentials).
                                        </span>
                                    </div>

                                    <div class="callout callout-warning" style="margin-bottom:0;">
                                        <i class="fa fa-info-circle"></i>
                                        Switching between MOCK and LIVE does not affect existing claims.
                                        New submissions will use the selected mode.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- LIVE API Config -->
                        <div class="col-md-6">
                            <div class="box box-success" id="liveConfigBox">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-globe"></i> Live API Configuration</h3>
                                </div>
                                <div class="box-body">
                                    <div class="form-group">
                                        <label>API Base URL</label>
                                        <input type="url" name="api_base_url" class="form-control" value="<?php echo htmlspecialchars($baseUrl); ?>" placeholder="https://api.nhis.gov.gh/v1">
                                    </div>
                                    <div class="form-group">
                                        <label>API Key</label>
                                        <div class="input-group">
                                            <input type="password" name="api_key" class="form-control" id="apiKeyInput" value="<?php echo htmlspecialchars($apiKey); ?>" placeholder="Enter API key">
                                            <span class="input-group-btn">
                                                <button type="button" class="btn btn-default" onclick="var i=document.getElementById('apiKeyInput'); i.type = i.type==='password'?'text':'password';"><i class="fa fa-eye"></i></button>
                                            </span>
                                        </div>
                                        <span class="help-block">Contact NHIS for API credentials.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mock Simulation Settings -->
                    <div class="row" id="mockConfigRow">
                        <div class="col-md-12">
                            <div class="box box-info">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-flask"></i> Mock Simulation Settings</h3>
                                </div>
                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Approval Rate (%)</label>
                                                <input type="number" name="mock_approval_rate" class="form-control" min="0" max="100" value="<?php echo htmlspecialchars($approvalRate); ?>" id="rateApproval">
                                                <span class="help-block">% of claims fully approved</span>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Underpayment Rate (%)</label>
                                                <input type="number" name="mock_underpay_rate" class="form-control" min="0" max="100" value="<?php echo htmlspecialchars($underpayRate); ?>" id="rateUnderpay">
                                                <span class="help-block">% of claims partially paid</span>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Rejection Rate (%)</label>
                                                <input type="number" name="mock_reject_rate" class="form-control" min="0" max="100" value="<?php echo htmlspecialchars($rejectRate); ?>" id="rateReject">
                                                <span class="help-block">% of claims rejected</span>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Simulated Delay (ms)</label>
                                                <input type="number" name="mock_delay_ms" class="form-control" min="0" max="5000" value="<?php echo htmlspecialchars($delayMs); ?>">
                                                <span class="help-block">Response delay simulation</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="rateWarning" class="alert alert-danger" style="display:none;">
                                        <i class="fa fa-warning"></i> Rates must sum to 100%. Currently: <span id="rateSum"></span>%
                                    </div>
                                    <div class="callout callout-info">
                                        <h4>Rate Distribution</h4>
                                        <div class="progress" style="height:24px; margin-bottom:5px;">
                                            <div class="progress-bar progress-bar-success" id="barApproval" style="width:<?php echo $approvalRate; ?>%"><?php echo $approvalRate; ?>% Approved</div>
                                            <div class="progress-bar progress-bar-warning" id="barUnderpay" style="width:<?php echo $underpayRate; ?>%"><?php echo $underpayRate; ?>% Underpaid</div>
                                            <div class="progress-bar progress-bar-danger" id="barReject" style="width:<?php echo $rejectRate; ?>%"><?php echo $rejectRate; ?>% Rejected</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fa fa-save"></i> Save Settings
                            </button>
                            <a href="<?php echo base_url(); ?>app/nhis_claims" class="btn btn-default btn-lg">
                                <i class="fa fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </form>
            </section>
        </aside>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
    <script src="<?php echo base_url()?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url()?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script>
    $(function(){
        function updateRateDisplay(){
            var a = parseInt($('#rateApproval').val()) || 0;
            var u = parseInt($('#rateUnderpay').val()) || 0;
            var r = parseInt($('#rateReject').val()) || 0;
            var sum = a + u + r;
            $('#barApproval').css('width', a+'%').text(a+'% Approved');
            $('#barUnderpay').css('width', u+'%').text(u+'% Underpaid');
            $('#barReject').css('width', r+'%').text(r+'% Rejected');
            if(sum !== 100){
                $('#rateWarning').show().find('#rateSum').text(sum);
            } else {
                $('#rateWarning').hide();
            }
        }
        $('#rateApproval, #rateUnderpay, #rateReject').on('input change', updateRateDisplay);

        function toggleMode(){
            var mode = $('#apiModeSelect').val();
            if(mode === 'MOCK'){
                $('#mockConfigRow').show();
                $('#liveConfigBox').find('input').prop('disabled', true);
            } else {
                $('#mockConfigRow').hide();
                $('#liveConfigBox').find('input').prop('disabled', false);
            }
        }
        $('#apiModeSelect').on('change', toggleMode);
        toggleMode();
    });
    </script>
</body>
</html>
