<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $this->load->view("template/header"); ?>

<div class="content-wrapper">
  <section class="content-header">
    <h1><i class="fa fa-history"></i> Smart Billing History</h1>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
      <li><a href="<?php echo base_url(); ?>app/billing/smart_billing">Smart Billing</a></li>
      <li class="active">Patient History</li>
    </ol>
  </section>

  <section class="content">
    <?php if (!empty($message)) echo $message; ?>

    <?php if ($patient): ?>
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title">
          <i class="fa fa-user"></i>
          <?php echo htmlspecialchars($patient->patient_name); ?>
          <small>&nbsp;<?php echo htmlspecialchars($patient->patient_no); ?></small>
        </h3>
        <div class="box-tools pull-right">
          <a href="<?php echo base_url(); ?>app/billing/smart_billing" class="btn btn-default btn-sm">
            <i class="fa fa-arrow-left"></i> Back to Queue
          </a>
        </div>
      </div>
      <div class="box-body">
        <div class="row">
          <div class="col-md-4">
            <p><strong>Patient No:</strong> <?php echo htmlspecialchars($patient->patient_no); ?></p>
          </div>
          <div class="col-md-4">
            <p><strong>DOB:</strong> <?php echo htmlspecialchars($patient->birthday); ?></p>
          </div>
          <div class="col-md-4">
            <p><strong>Insurance:</strong> <?php echo htmlspecialchars($patient->Insurance_comp ?: 'Cash Patient'); ?></p>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="box box-default">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-table"></i> Billing History</h3>
      </div>
      <div class="box-body no-padding">
        <?php if (empty($history)): ?>
          <div class="text-center" style="padding:30px;color:#aaa;">
            <i class="fa fa-info-circle fa-2x"></i><br>
            No smart billing records found for this patient.
          </div>
        <?php else: ?>
        <?php
        $visitBadge = array(
            'FIRST_VISIT'        => 'label-primary',
            'REVIEW'             => 'label-success',
            'FOLLOW_UP'          => 'label-info',
            'WALK_IN'            => 'label-default',
            'MISSED_APPOINTMENT' => 'label-warning',
            'EMERGENCY'          => 'label-danger',
        );
        $statusBadge = array(
            'PENDING'   => 'label-warning',
            'BILLED'    => 'label-success',
            'CANCELLED' => 'label-danger',
        );
        ?>
        <table class="table table-bordered table-striped table-hover">
          <thead>
            <tr>
              <th>Date</th>
              <th>Visit Type</th>
              <th>OPD No</th>
              <th style="text-align:right;">Reg Fee</th>
              <th style="text-align:right;">Consult Fee</th>
              <th style="text-align:right;">Total</th>
              <th>Waiver</th>
              <th>Status</th>
              <th>Billed At</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $totalReg = 0; $totalCon = 0; $waiversCount = 0;
            foreach ($history as $h):
              $vClass  = isset($visitBadge[$h->visit_type])  ? $visitBadge[$h->visit_type]  : 'label-default';
              $sClass  = isset($statusBadge[$h->status])     ? $statusBadge[$h->status]     : 'label-default';
              $vLabel  = str_replace('_', ' ', $h->visit_type);
              $reg     = (float)$h->registration_fee;
              $con     = (float)$h->consultation_fee;
              $total   = $reg + $con;
              $totalReg += $reg; $totalCon += $con;
              if ($h->consultation_waived) $waiversCount++;
            ?>
            <tr>
              <td><?php echo $h->date_visit ? date('d M Y', strtotime($h->date_visit)) : date('d M Y', strtotime($h->created_at)); ?></td>
              <td><span class="label <?php echo $vClass; ?>"><?php echo htmlspecialchars($vLabel); ?></span></td>
              <td><code><?php echo htmlspecialchars($h->iop_id); ?></code></td>
              <td style="text-align:right;">
                <?php if ($reg > 0): ?>
                  <strong>GHS <?php echo number_format($reg, 2); ?></strong>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;">
                <?php if ($h->consultation_waived): ?>
                  <span class="text-muted" style="text-decoration:line-through;">Waived</span>
                <?php elseif ($con > 0): ?>
                  GHS <?php echo number_format($con, 2); ?>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;"><strong>GHS <?php echo number_format($total, 2); ?></strong></td>
              <td>
                <?php if ($h->consultation_waived): ?>
                  <span class="label label-success"><i class="fa fa-check"></i> Waived</span><br>
                  <?php if ($h->waiver_reason): ?>
                    <small class="text-muted"><?php echo htmlspecialchars($h->waiver_reason); ?></small>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><span class="label <?php echo $sClass; ?>"><?php echo htmlspecialchars($h->status); ?></span></td>
              <td>
                <small><?php echo $h->billed_at ? date('d M Y H:i', strtotime($h->billed_at)) : '<span class="text-muted">Pending</span>'; ?></small>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="active">
              <th colspan="3">Totals (<?php echo count($history); ?> records)</th>
              <th style="text-align:right;">GHS <?php echo number_format($totalReg, 2); ?></th>
              <th style="text-align:right;">GHS <?php echo number_format($totalCon, 2); ?></th>
              <th style="text-align:right;"><strong>GHS <?php echo number_format($totalReg + $totalCon, 2); ?></strong></th>
              <th><?php echo $waiversCount; ?> waiver(s)</th>
              <th colspan="2"></th>
            </tr>
          </tfoot>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>

<?php $this->load->view("template/footer"); ?>
