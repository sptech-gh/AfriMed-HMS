<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php require_once(APPPATH.'views/include/header.php'); ?>
<div class="wrapper row-offcanvas row-offcanvas-left">
<?php require_once(APPPATH.'views/include/sidebar.php'); ?>
<aside class="right-side">
<section class="content-header"><h1><i class="fa fa-balance-scale"></i> Walk-In Revenue Fulfillment Reconciliation</h1></section>
<section class="content">
  <?php if (!empty($message)) echo $message; ?>
  <div class="box box-default">
    <div class="box-body">
      <form method="get" class="form-inline">
        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
        <button class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
      </form>
    </div>
  </div>
  <div class="box box-primary">
    <div class="box-header with-border"><h3 class="box-title">Revenue vs Fulfillment</h3></div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover table-condensed">
        <thead><tr><th>Created</th><th>Walk-In</th><th>Client</th><th>Type</th><th class="text-right">Net</th><th class="text-right">Paid</th><th>Payment</th><th>Fulfillment</th><th>Items</th><th>Receipt</th></tr></thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="text-center text-muted">No walk-in orders for this period.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($r->created_at))); ?></td>
            <td><strong><?php echo htmlspecialchars($r->walkin_code ?: $r->walkin_order_id); ?></strong></td>
            <td><?php echo htmlspecialchars($r->customer_name ?: 'Walk-in Client'); ?><br><small><?php echo htmlspecialchars($r->phone); ?></small></td>
            <td><?php echo htmlspecialchars($r->transaction_type); ?></td>
            <td class="text-right"><?php echo number_format((float)$r->net_amount, 2); ?></td>
            <td class="text-right"><?php echo number_format((float)$r->paid_amount, 2); ?></td>
            <td><span class="label label-<?php echo $r->payment_status === 'PAID' ? 'success' : 'default'; ?>"><?php echo htmlspecialchars($r->payment_status); ?></span></td>
            <td><span class="label label-info"><?php echo htmlspecialchars($r->fulfillment_status); ?></span></td>
            <td><?php echo (int)$r->fulfilled_count; ?> / <?php echo (int)$r->item_count; ?> fulfilled<br><small><?php echo (int)$r->pending_count; ?> pending</small></td>
            <td><?php echo htmlspecialchars($r->receipt_no ?: $r->invoice_no); ?></td>
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
