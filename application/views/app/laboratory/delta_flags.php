<?php $this->load->view('includes/header'); ?>

<div class="content-wrapper">
    <section class="content-header">
        <h1>
            <i class="fa fa-exchange text-warning"></i> Delta Check Flags
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?php echo base_url(); ?>app/laboratory"><i class="fa fa-flask"></i> Laboratory</a></li>
            <li class="active">Delta Flags</li>
        </ol>
    </section>

    <section class="content">
        <?php if (isset($message) && $message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-flag"></i> Pending Delta Flags for Review</h3>
                <div class="box-tools pull-right">
                    <a href="<?php echo base_url(); ?>app/laboratory/safety_dashboard" class="btn btn-sm btn-default">
                        <i class="fa fa-dashboard"></i> Safety Dashboard
                    </a>
                </div>
            </div>
            <div class="box-body">
                <?php if (empty($flags)): ?>
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i> No pending delta flags. All flags have been reviewed.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> Delta checks flag results that differ significantly (>50%) from the patient's previous result for the same test. Please review and confirm or reject.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="deltaTable">
                            <thead>
                                <tr class="bg-warning">
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Test</th>
                                    <th>Previous Value</th>
                                    <th>Current Value</th>
                                    <th>Delta %</th>
                                    <th>Previous Date</th>
                                    <th>Flag Reason</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($flags as $flag): ?>
                                    <?php
                                    $patient_name = trim(($flag->firstname ?? '') . ' ' . ($flag->lastname ?? ''));
                                    if (!$patient_name) $patient_name = $flag->patient_no;
                                    
                                    $delta_class = 'warning';
                                    if ($flag->delta_percent >= 100) $delta_class = 'danger';
                                    ?>
                                    <tr>
                                        <td><?php echo $flag->delta_id; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($patient_name); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($flag->patient_no); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($flag->test_name ?? ''); ?></td>
                                        <td>
                                            <span class="text-muted"><?php echo htmlspecialchars($flag->previous_value ?? ''); ?></span>
                                            <?php if ($flag->previous_date): ?>
                                                <br><small class="text-muted"><?php echo date('d-M-Y', strtotime($flag->previous_date)); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong class="text-<?php echo $delta_class; ?>"><?php echo htmlspecialchars($flag->current_value); ?></strong>
                                        </td>
                                        <td>
                                            <span class="label label-<?php echo $delta_class; ?>">
                                                <?php echo round($flag->delta_percent, 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $flag->previous_date ? date('d-M-Y H:i', strtotime($flag->previous_date)) : 'N/A'; ?>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($flag->flag_reason ?? ''); ?></small></td>
                                        <td><?php echo date('d-M-Y H:i', strtotime($flag->created_at)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Delta Check Info -->
        <div class="row">
            <div class="col-md-6">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">What is a Delta Check?</h3>
                    </div>
                    <div class="box-body">
                        <p>A <strong>delta check</strong> compares a patient's current test result with their previous result for the same test. If the difference exceeds a threshold (typically 50%), the result is flagged for review.</p>
                        <p>This helps identify:</p>
                        <ul>
                            <li>Potential specimen mix-ups</li>
                            <li>Pre-analytical errors</li>
                            <li>Significant clinical changes requiring attention</li>
                            <li>Possible transcription errors</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Review Actions</h3>
                    </div>
                    <div class="box-body">
                        <p><strong>When reviewing a delta flag:</strong></p>
                        <ul>
                            <li><span class="label label-success">ACCEPT</span> - Result is correct, clinical change is real</li>
                            <li><span class="label label-danger">REJECT</span> - Result appears erroneous, investigate further</li>
                            <li><span class="label label-warning">REPEAT</span> - Order repeat testing to confirm</li>
                        </ul>
                        <p class="text-muted">All review actions are logged in the audit trail.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
$(document).ready(function() {
    $('#deltaTable').DataTable({
        "order": [[5, "desc"]],
        "pageLength": 25
    });
});
</script>

<?php $this->load->view('includes/footer'); ?>
