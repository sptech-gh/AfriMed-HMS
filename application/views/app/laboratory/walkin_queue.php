<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php require_once(APPPATH.'views/include/header.php'); ?>
<div class="wrapper row-offcanvas row-offcanvas-left">
<?php require_once(APPPATH.'views/include/sidebar.php'); ?>
<aside class="right-side">
<section class="content-header">
  <h1><i class="fa fa-flask"></i> Walk-In Lab Fulfillment</h1>
</section>
<section class="content">
  <?php if (!empty($message)) echo $message; ?>
  <div class="box box-primary">
    <div class="box-header with-border"><h3 class="box-title">Paid Investigations</h3></div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover table-condensed">
        <thead><tr><th>Receipt</th><th>Client</th><th>Investigation</th><th>Status</th><th style="width:360px;">Action</th></tr></thead>
        <tbody>
        <?php if (empty($pending)): ?>
          <tr><td colspan="5" class="text-center text-muted">No paid walk-in lab investigations awaiting action.</td></tr>
        <?php else: foreach ($pending as $r): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($r->receipt_no ?: $r->invoice_no); ?></strong><br><small><?php echo htmlspecialchars($r->walkin_code); ?></small></td>
            <td><?php echo htmlspecialchars($r->customer_name ?: 'Walk-in Client'); ?><br><small><?php echo htmlspecialchars($r->phone); ?></small></td>
            <td><?php echo htmlspecialchars($r->item_name); ?></td>
            <td><span class="label label-info"><?php echo htmlspecialchars($r->department_status); ?></span></td>
            <td>
              <form method="post" action="<?php echo base_url('app/laboratory/walkin_update_status'); ?>" class="form-inline">
                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                <input type="hidden" name="item_id" value="<?php echo (int)$r->internal_id; ?>">
                <select name="status" class="form-control input-sm">
                  <option value="SAMPLE_COLLECTED">Sample Collected</option>
                  <option value="IN_PROGRESS">In Progress</option>
                  <option value="COMPLETED">Completed</option>
                  <option value="CANCELLED">Cancelled</option>
                </select>
                <input type="text" name="notes" class="form-control input-sm" placeholder="Notes">
                <button class="btn btn-success btn-sm"><i class="fa fa-check"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
</aside>
</div>
<script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
