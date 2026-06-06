<section class="content-header">
	<h1><i class="fa fa-file-text-o"></i> Order Detail
		<small><?php echo isset($order->order_no) ? htmlspecialchars($order->order_no) : ''; ?></small>
	</h1>
	<ol class="breadcrumb">
		<li><a href="<?php echo base_url(); ?>app/consumable_order/index/<?php echo urlencode($order->iop_id); ?>/<?php echo urlencode($order->patient_no); ?>"><i class="fa fa-arrow-left"></i> Back to Orders</a></li>
	</ol>
</section>

<section class="content">
	<?php if(isset($message) && $message != '') echo $message; ?>

	<!-- Order Header -->
	<div class="box box-primary">
		<div class="box-header with-border"><h3 class="box-title"><i class="fa fa-info-circle"></i> Order Information</h3></div>
		<div class="box-body">
			<div class="row">
				<div class="col-sm-3"><strong>Order #:</strong><br><?php echo htmlspecialchars($order->order_no); ?></div>
				<div class="col-sm-3"><strong>Patient:</strong><br><?php echo isset($patientInfo) ? htmlspecialchars($patientInfo->lastname . ', ' . $patientInfo->firstname) : htmlspecialchars($order->patient_no); ?></div>
				<div class="col-sm-2"><strong>Visit:</strong><br><?php echo htmlspecialchars($order->iop_id); ?></div>
				<div class="col-sm-2"><strong>Ordered:</strong><br><?php echo date('M d, Y H:i', strtotime($order->ordered_at)); ?></div>
				<div class="col-sm-2"><strong>Status:</strong><br>
					<?php
						$st = strtoupper(trim($order->order_status));
						$badge = 'label-info';
						if($st==='FULFILLED') $badge='label-success';
						elseif($st==='CANCELLED') $badge='label-danger';
						elseif($st==='PARTIALLY_FULFILLED') $badge='label-warning';
						elseif($st==='PENDING_BILLING') $badge='label-default';
					?>
					<span class="label <?php echo $badge; ?>"><?php echo str_replace('_',' ',$st); ?></span>
				</div>
			</div>
			<?php if(!empty($order->notes)): ?>
			<div class="row" style="margin-top:10px">
				<div class="col-sm-12"><strong>Notes:</strong> <?php echo htmlspecialchars($order->notes); ?></div>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Order Items -->
	<div class="box box-info">
		<div class="box-header with-border"><h3 class="box-title"><i class="fa fa-list"></i> Line Items</h3></div>
		<div class="box-body no-padding">
			<table class="table table-striped">
				<thead>
					<tr>
						<th>#</th><th>Item</th><th>Source</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Fulfilled</th><th>Status</th>
						<?php if(!empty($can_create) && $st !== 'CANCELLED'): ?><th>Action</th><?php endif; ?>
					</tr>
				</thead>
				<tbody>
				<?php $n=0; foreach($items as $it): $n++; ?>
					<tr>
						<td><?php echo $n; ?></td>
						<td>
							<?php echo htmlspecialchars($it->item_name); ?>
							<?php if((int)$it->is_stock_backed): ?><span class="label label-info" style="font-size:9px">STOCK</span><?php endif; ?>
						</td>
						<td><small><?php echo htmlspecialchars($it->item_source); ?></small></td>
						<td><?php echo (float)$it->quantity; ?></td>
						<td>GHS <?php echo number_format((float)$it->unit_price, 2); ?></td>
						<td><strong>GHS <?php echo number_format((float)$it->net_amount, 2); ?></strong></td>
						<td><?php echo (float)$it->fulfilled_qty; ?> / <?php echo (float)$it->quantity; ?></td>
						<td>
							<?php
								$ist = strtoupper(trim($it->fulfillment_status));
								$ibadge = 'label-default';
								if($ist==='FULFILLED') $ibadge='label-success';
								elseif($ist==='CANCELLED') $ibadge='label-danger';
							?>
							<span class="label <?php echo $ibadge; ?>"><?php echo $ist; ?></span>
						</td>
						<?php if(!empty($can_create) && $st !== 'CANCELLED'): ?>
						<td>
							<?php if($ist === 'PENDING'): ?>
							<form method="post" action="<?php echo base_url(); ?>app/consumable_order/fulfill_item" style="display:inline" onsubmit="return confirm('Fulfill this item?')">
								<input type="hidden" name="item_id" value="<?php echo (int)$it->item_id; ?>">
								<input type="hidden" name="quantity" value="<?php echo (float)$it->quantity - (float)$it->fulfilled_qty; ?>">
								<input type="hidden" name="iop_id" value="<?php echo htmlspecialchars($order->iop_id); ?>">
								<input type="hidden" name="patient_no" value="<?php echo htmlspecialchars($order->patient_no); ?>">
								<button type="submit" class="btn btn-success btn-xs"><i class="fa fa-check"></i> Fulfill</button>
							</form>
							<?php endif; ?>
						</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="5" class="text-right"><strong>Grand Total:</strong></td>
						<td><strong style="color:#27ae60">GHS <?php echo number_format((float)$order->net_amount, 2); ?></strong></td>
						<td colspan="<?php echo (!empty($can_create) && $st !== 'CANCELLED') ? 3 : 2; ?>"></td>
					</tr>
				</tfoot>
			</table>
		</div>
	</div>

	<!-- Cancel Button -->
	<?php if(!empty($can_create) && $st !== 'CANCELLED' && $st !== 'FULFILLED'): ?>
	<div style="margin-top:10px">
		<form method="post" action="<?php echo base_url(); ?>app/consumable_order/cancel" onsubmit="return confirm('Cancel this entire order?')">
			<input type="hidden" name="order_id" value="<?php echo $order->order_id; ?>">
			<input type="hidden" name="iop_id" value="<?php echo htmlspecialchars($order->iop_id); ?>">
			<input type="hidden" name="patient_no" value="<?php echo htmlspecialchars($order->patient_no); ?>">
			<input type="hidden" name="reason" value="Cancelled from detail page">
			<button type="submit" class="btn btn-danger"><i class="fa fa-times"></i> Cancel Order</button>
		</form>
	</div>
	<?php endif; ?>
</section>
