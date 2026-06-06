<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php require_once(APPPATH.'views/include/header.php'); ?>
<div class="wrapper row-offcanvas row-offcanvas-left">
<?php require_once(APPPATH.'views/include/sidebar.php'); ?>
<aside class="right-side">
<section class="content-header">
  <h1><i class="fa fa-shopping-bag"></i> Walk-In Pharmacy Fulfillment</h1>
</section>
<section class="content">
  <?php if (!empty($message)) echo $message; ?>
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">Paid Items Awaiting Dispense</h3>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover table-condensed">
        <thead>
          <tr>
            <th>Receipt</th><th>Client</th><th>Item</th><th class="text-right">Paid Qty</th><th class="text-right">Remaining</th><th>Status</th><th style="width:260px;">Dispense</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($pending)): ?>
          <tr><td colspan="7" class="text-center text-muted">No paid walk-in pharmacy items awaiting dispense.</td></tr>
        <?php else: foreach ($pending as $r):
          $remaining = isset($r->remaining_qty) ? (float)$r->remaining_qty : max(0, (float)$r->quantity - (float)$r->fulfilled_qty);
        ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($r->receipt_no ?: $r->invoice_no); ?></strong><br><small><?php echo htmlspecialchars($r->walkin_code); ?></small></td>
            <td><?php echo htmlspecialchars($r->customer_name ?: 'Walk-in Client'); ?><br><small><?php echo htmlspecialchars($r->phone); ?></small></td>
            <td><?php echo htmlspecialchars($r->item_name); ?></td>
            <td class="text-right"><?php echo number_format((float)$r->quantity, 2); ?></td>
            <td class="text-right"><?php echo number_format($remaining, 2); ?></td>
            <td><span class="label label-info"><?php echo htmlspecialchars($r->department_status); ?></span></td>
            <td>
              <form method="post" action="<?php echo base_url('app/pharmacy/walkin_fulfill_item'); ?>" class="form-inline walkin-dispense-form">
                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                <input type="hidden" name="item_ref" value="<?php echo htmlspecialchars($r->item_ref); ?>">
                <input type="hidden" name="idempotency_key" value="<?php echo htmlspecialchars('disp-'.$r->internal_id.'-'.time()); ?>">
                <input type="number" name="qty" min="0.01" step="0.01" max="<?php echo htmlspecialchars(number_format($remaining, 2, '.', '')); ?>" value="<?php echo htmlspecialchars(number_format($remaining, 2, '.', '')); ?>" class="form-control input-sm" style="width:85px;">
                <input type="text" name="notes" class="form-control input-sm" placeholder="Notes" style="width:90px;">
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
<script>
$(document).on('submit', '.walkin-dispense-form', function(e) {
  e.preventDefault();
  var $f = $(this);
  $.post($f.attr('action'), $f.serialize(), function(res) {
    if (res && res.ok) { location.reload(); return; }
    alert((res && res.error) || (res && res.result && res.result.error) || 'Dispense failed');
  }, 'json').fail(function(){ alert('Dispense failed'); });
});
</script>
