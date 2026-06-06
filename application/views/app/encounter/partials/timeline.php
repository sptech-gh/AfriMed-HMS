<?php
$events = isset($encounter_timeline_events) && is_array($encounter_timeline_events) ? $encounter_timeline_events : array();
?>
<?php if (!$events || count($events) === 0) { ?>
	<div class="alert alert-info">
		No timeline events found.
	</div>
<?php } else { ?>
	<div class="table-responsive">
		<table class="table table-condensed table-bordered">
			<thead>
				<tr>
					<th style="width:160px;">Time</th>
					<th style="width:140px;">Type</th>
					<th>Summary</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($events as $e) {
					$when = isset($e->created_at) ? (string)$e->created_at : '';
					$type = isset($e->event_type) ? (string)$e->event_type : '';
					$summary = isset($e->summary) ? (string)$e->summary : '';
				?>
				<tr>
					<td><?php echo $when !== '' ? date('M d, Y H:i', strtotime($when)) : ''; ?></td>
					<td><span class="label label-default"><?php echo htmlspecialchars($type); ?></span></td>
					<td><?php echo nl2br(htmlspecialchars($summary)); ?></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
<?php } ?>
