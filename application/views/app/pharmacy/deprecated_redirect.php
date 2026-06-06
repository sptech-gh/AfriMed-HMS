<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Redirecting... - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .deprecation-notice {
            max-width: 600px;
            margin: 100px auto;
            padding: 40px;
            text-align: center;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .deprecation-icon {
            font-size: 64px;
            color: #f39c12;
            margin-bottom: 20px;
        }
        .deprecation-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 15px;
        }
        .deprecation-message {
            font-size: 16px;
            color: #666;
            margin-bottom: 25px;
        }
        .redirect-countdown {
            font-size: 14px;
            color: #999;
        }
        .redirect-countdown span {
            font-weight: bold;
            color: #3c8dbc;
        }
        .btn-redirect {
            margin-top: 15px;
        }
    </style>
</head>
<body class="skin-blue" style="background: #ecf0f5;">
    <div class="deprecation-notice">
        <div class="deprecation-icon">
            <i class="fa fa-exclamation-triangle"></i>
        </div>
        <div class="deprecation-title">
            Deprecated View
        </div>
        <div class="deprecation-message">
            The view <strong><?php echo htmlspecialchars($deprecated_view); ?></strong> has been deprecated.<br>
            You are being redirected to the new <strong>Pharmacy Dashboard</strong>.
        </div>
        <div class="redirect-countdown">
            Redirecting in <span id="countdown">3</span> seconds...
        </div>
        <a href="<?php echo $redirect_url; ?>" class="btn btn-primary btn-redirect">
            <i class="fa fa-arrow-right"></i> Go to Pharmacy Dashboard Now
        </a>
    </div>

    <script>
        var seconds = 3;
        var countdownEl = document.getElementById('countdown');
        var redirectUrl = '<?php echo $redirect_url; ?>';

        var timer = setInterval(function() {
            seconds--;
            countdownEl.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = redirectUrl;
            }
        }, 1000);
    </script>
</body>
</html>
