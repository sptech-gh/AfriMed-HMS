<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center — NHIS Claim-IT Dashboard</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-cloud-upload"></i> NHIS Claim-IT <small>Ghana NHIS Integration</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="<?php echo base_url()?>app/nhis_claims">NHIS Claims</a></li>
                    <li class="active">Claim-IT</li>
                </ol>
            </section>

            <section class="content">
                <?php if(isset($message) && $message){ echo $message; } ?>

                <?php
                    $s = isset($summary) ? $summary : [];
                    $mode = isset($api_mode) ? $api_mode : 'MOCK';
                ?>

                <!-- API Mode Banner -->
                <div class="callout callout-<?php echo $mode === 'LIVE' ? 'danger' : 'info'; ?>">
                    <h4><i class="fa fa-<?php echo $mode === 'LIVE' ? 'warning' : 'flask'; ?>"></i> 
                        API Mode: <?php echo $mode; ?>
                    </h4>
                    <p>
                        <?php if($mode === 'LIVE'): ?>
                            Connected to Ghana NHIS Claim-IT Live API. All submissions are real.
                        <?php else: ?>
                            Using Mock API for testing. Switch to LIVE mode for production.
                        <?php endif; ?>
                    </p>
                    <form method="post" action="<?php echo base_url()?>app/nhis_claims/toggle_api_mode" style="display:inline;">
                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                        <input type="hidden" name="mode" value="<?php echo $mode === 'LIVE' ? 'MOCK' : 'LIVE'; ?>">
                        <button type="submit" class="btn btn-sm btn-<?php echo $mode === 'LIVE' ? 'warning' : 'success'; ?>">
                            <i class="fa fa-exchange"></i> Switch to <?php echo $mode === 'LIVE' ? 'MOCK' : 'LIVE'; ?>
                        </button>
                    </form>
                </div>

                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?php echo $s['total_claims'] ?? 0; ?></h3>
                                <p>Total Claims</p>
                            </div>
                            <div class="icon"><i class="fa fa-file-text"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3><?php echo $s['draft'] ?? 0; ?></h3>
                                <p>Draft Claims</p>
                            </div>
                            <div class="icon"><i class="fa fa-pencil"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-blue">
                            <div class="inner">
                                <h3><?php echo $s['submitted'] ?? 0; ?></h3>
                                <p>Submitted</p>
                            </div>
                            <div class="icon"><i class="fa fa-cloud-upload"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3>GHS <?php echo number_format($s['total_amount'] ?? 0, 2); ?></h3>
                                <p>Total Value</p>
                            </div>
                            <div class="icon"><i class="fa fa-money"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-bolt"></i> Quick Actions</h3>
                            </div>
                            <div class="box-body">
                                <a href="<?php echo base_url()?>app/nhis_claims/submission_queue" class="btn btn-primary">
                                    <i class="fa fa-list"></i> Submission Queue
                                </a>
                                <a href="<?php echo base_url()?>app/nhis_claims/icd10_mapping" class="btn btn-info">
                                    <i class="fa fa-stethoscope"></i> ICD-10 Codes
                                </a>
                                <a href="<?php echo base_url()?>app/nhis_claims/tariff_mapping" class="btn btn-success">
                                    <i class="fa fa-tags"></i> Tariff Mapping
                                </a>
                                <a href="<?php echo base_url()?>app/nhis_claims/api_logs" class="btn btn-warning">
                                    <i class="fa fa-history"></i> API Logs
                                </a>
                                <a href="<?php echo base_url()?>app/nhis_claims" class="btn btn-default">
                                    <i class="fa fa-arrow-left"></i> Legacy Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Claims Table -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-table"></i> Recent Claims</h3>
                                <div class="box-tools">
                                    <div class="btn-group">
                                        <a href="<?php echo base_url()?>app/nhis_claims/claimit" class="btn btn-sm btn-default <?php echo !$this->input->get('status') ? 'active' : ''; ?>">All</a>
                                        <a href="<?php echo base_url()?>app/nhis_claims/claimit?status=DRAFT" class="btn btn-sm btn-default <?php echo $this->input->get('status') === 'DRAFT' ? 'active' : ''; ?>">Draft</a>
                                        <a href="<?php echo base_url()?>app/nhis_claims/claimit?status=READY" class="btn btn-sm btn-default <?php echo $this->input->get('status') === 'READY' ? 'active' : ''; ?>">Ready</a>
                                        <a href="<?php echo base_url()?>app/nhis_claims/claimit?status=SUBMITTED" class="btn btn-sm btn-default <?php echo $this->input->get('status') === 'SUBMITTED' ? 'active' : ''; ?>">Submitted</a>
                                    </div>
                                </div>
                            </div>
                            <div class="box-body">
                                <table class="table table-bordered table-striped" id="claimsTable">
                                    <thead>
                                        <tr>
                                            <th>Claim #</th>
                                            <th>Patient</th>
                                            <th>NHIS #</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($claims)): foreach($claims as $c): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars(isset($c->claim_number) ? $c->claim_number : '-'); ?></strong></td>
                                            <td><?php echo htmlspecialchars(isset($c->patient_no) ? $c->patient_no : '-'); ?></td>
                                            <td><?php echo htmlspecialchars(isset($c->nhis_number) ? $c->nhis_number : '-'); ?></td>
                                            <td><?php echo isset($c->claim_date) && $c->claim_date ? date('d M Y', strtotime($c->claim_date)) : '-'; ?></td>
                                            <td class="text-right">GHS <?php echo number_format(isset($c->total_amount) ? $c->total_amount : 0, 2); ?></td>
                                            <td>
                                                <?php
                                                $statusClass = [
                                                    'DRAFT' => 'default',
                                                    'READY' => 'info',
                                                    'SUBMITTED' => 'primary',
                                                    'ACCEPTED' => 'success',
                                                    'REJECTED' => 'danger',
                                                    'APPROVED' => 'success',
                                                    'PAID' => 'success'
                                                ];
                                                $status = isset($c->status) ? $c->status : 'DRAFT';
                                                $cls = isset($statusClass[$status]) ? $statusClass[$status] : 'default';
                                                ?>
                                                <span class="label label-<?php echo $cls; ?>"><?php echo $status; ?></span>
                                            </td>
                                            <td>
                                                <?php $claim_id = isset($c->id) ? $c->id : 0; ?>
                                                <a href="<?php echo base_url()?>app/nhis_claims/claimit_view/<?php echo $claim_id; ?>" class="btn btn-xs btn-info" title="View">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                                <?php if(isset($c->status) && $c->status === 'READY'): ?>
                                                <a href="<?php echo base_url()?>app/nhis_claims/claimit_submit/<?php echo $claim_id; ?>" class="btn btn-xs btn-success" title="Submit">
                                                    <i class="fa fa-cloud-upload"></i>
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Eligibility Check -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-check-circle"></i> Check NHIS Eligibility</h3>
                            </div>
                            <div class="box-body">
                                <div class="form-group">
                                    <label>NHIS Number</label>
                                    <input type="text" id="eligibility_nhis" class="form-control" placeholder="e.g. NHIS-001-2024">
                                </div>
                                <button type="button" id="checkEligibility" class="btn btn-success">
                                    <i class="fa fa-search"></i> Check Eligibility
                                </button>
                                <div id="eligibilityResult" class="mt-3" style="margin-top:15px;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-info-circle"></i> Readiness Checklist</h3>
                            </div>
                            <div class="box-body">
                                <ul class="list-unstyled">
                                    <li><i class="fa fa-check text-success"></i> Database schema created</li>
                                    <li><i class="fa fa-check text-success"></i> ICD-10 codes seeded</li>
                                    <li><i class="fa fa-check text-success"></i> NHIS tariffs configured</li>
                                    <li><i class="fa fa-check text-success"></i> Mock API ready</li>
                                    <li><i class="fa fa-<?php echo $mode === 'LIVE' ? 'check text-success' : 'times text-muted'; ?>"></i> Live API configured</li>
                                    <li><i class="fa fa-check text-success"></i> Claim validation engine</li>
                                    <li><i class="fa fa-check text-success"></i> Submission queue</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/jquery.dataTables.js"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/dataTables.bootstrap.js"></script>
    <script>
    $(function(){
        $('#claimsTable').DataTable({
            "order": [[3, "desc"]],
            "pageLength": 25,
            "language": {
                "emptyTable": "No claims found"
            }
        });

        $('#checkEligibility').click(function(){
            var nhis = $('#eligibility_nhis').val();
            if(!nhis) { alert('Enter NHIS number'); return; }
            
            $('#eligibilityResult').html('<i class="fa fa-spinner fa-spin"></i> Checking...');
            
            $.post('<?php echo base_url()?>app/nhis_claims/claimit_eligibility', {
                nhis_number: nhis,
                '<?php echo $this->security->get_csrf_token_name(); ?>': '<?php echo $this->security->get_csrf_hash(); ?>'
            }, function(r){
                if(r.success){
                    var d = r.data;
                    var statusCls = d.status === 'ACTIVE' ? 'success' : 'danger';
                    $('#eligibilityResult').html(
                        '<div class="alert alert-'+statusCls+'">' +
                        '<strong>' + d.member_name + '</strong><br>' +
                        'Status: <span class="label label-'+statusCls+'">' + d.status + '</span><br>' +
                        'Expiry: ' + d.expiry_date +
                        '</div>'
                    );
                } else {
                    $('#eligibilityResult').html('<div class="alert alert-danger">' + (r.error ? r.error.message : 'Error') + '</div>');
                }
            }, 'json').fail(function(){
                $('#eligibilityResult').html('<div class="alert alert-danger">Request failed</div>');
            });
        });
    });
    </script>
</body>
</html>
