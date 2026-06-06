<?php require_once(APPPATH.'views/include/header.php'); ?>
<?php require_once(APPPATH.'views/include/sidebar.php'); ?>

<aside class="right-side">
    <section class="content-header">
        <h1><i class="fa fa-link"></i> URL Migration Tool</h1>
        <ol class="breadcrumb">
            <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
            <li><a href="#">Administrator</a></li>
            <li class="active">URL Migration</li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> 
                    <strong>URL Migration Tool</strong> - This tool identifies and fixes IDs containing spaces that cause URL encoding issues (Forbidden errors).
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-search"></i> Analysis Results</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Table</th>
                                    <th>Total Records</th>
                                    <th>With Spaces</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analysis as $table => $data): ?>
                                <tr>
                                    <td><code><?php echo $table; ?></code></td>
                                    <td><?php echo isset($data['total']) ? number_format($data['total']) : 'N/A'; ?></td>
                                    <td>
                                        <?php if (isset($data['with_spaces']) && $data['with_spaces'] > 0): ?>
                                            <span class="label label-warning"><?php echo number_format($data['with_spaces']); ?></span>
                                        <?php else: ?>
                                            <span class="label label-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!isset($data['exists']) || !$data['exists']): ?>
                                            <span class="label label-default">Not Found</span>
                                        <?php elseif (isset($data['with_spaces']) && $data['with_spaces'] > 0): ?>
                                            <span class="label label-warning">Needs Fix</span>
                                        <?php else: ?>
                                            <span class="label label-success">OK</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-eye"></i> Sample IDs with Spaces</h3>
                    </div>
                    <div class="box-body">
                        <?php 
                        $hasSamples = false;
                        foreach ($analysis as $table => $data): 
                            if (isset($data['samples']) && !empty($data['samples'])):
                                $hasSamples = true;
                        ?>
                        <p><strong><?php echo $table; ?>:</strong></p>
                        <ul>
                            <?php foreach ($data['samples'] as $sample): ?>
                            <li><code><?php echo htmlspecialchars($sample); ?></code> → <code><?php echo htmlspecialchars(str_replace(' ', '', $sample)); ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php 
                            endif;
                        endforeach; 
                        if (!$hasSamples):
                        ?>
                        <p class="text-success"><i class="fa fa-check"></i> No IDs with spaces found. System is clean!</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-wrench"></i> Execute Migration</h3>
                    </div>
                    <div class="box-body">
                        <p><strong>Warning:</strong> This will update all IDs in the database to remove spaces.</p>
                        <p>Example: <code>OP 000001</code> → <code>OP000001</code></p>
                        
                        <div class="form-group">
                            <button type="button" class="btn btn-warning btn-block" id="btn-dry-run">
                                <i class="fa fa-eye"></i> Dry Run (Preview Changes)
                            </button>
                        </div>
                        <div class="form-group">
                            <button type="button" class="btn btn-danger btn-block" id="btn-execute" disabled>
                                <i class="fa fa-bolt"></i> Execute Migration
                            </button>
                        </div>
                        
                        <div id="migration-results" style="display:none;">
                            <hr>
                            <h4>Migration Results:</h4>
                            <pre id="results-output"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</aside>

<script>
$(document).ready(function() {
    $('#btn-dry-run').click(function() {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Running...');
        
        $.ajax({
            url: '<?php echo base_url(); ?>app/url_migration/execute',
            type: 'POST',
            data: { 
                dry_run: '1',
                <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
            },
            dataType: 'json',
            success: function(response) {
                $('#migration-results').show();
                $('#results-output').text(JSON.stringify(response, null, 2));
                btn.prop('disabled', false).html('<i class="fa fa-eye"></i> Dry Run (Preview Changes)');
                $('#btn-execute').prop('disabled', false);
            },
            error: function(xhr) {
                alert('Error: ' + xhr.responseText);
                btn.prop('disabled', false).html('<i class="fa fa-eye"></i> Dry Run (Preview Changes)');
            }
        });
    });

    $('#btn-execute').click(function() {
        if (!confirm('Are you sure you want to execute the migration? This will modify database records.')) {
            return;
        }
        
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Executing...');
        
        $.ajax({
            url: '<?php echo base_url(); ?>app/url_migration/execute',
            type: 'POST',
            data: { 
                dry_run: '0',
                <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
            },
            dataType: 'json',
            success: function(response) {
                $('#migration-results').show();
                $('#results-output').text(JSON.stringify(response, null, 2));
                btn.html('<i class="fa fa-check"></i> Migration Complete');
                if (response.success) {
                    alert('Migration completed successfully! Refresh the page to see updated analysis.');
                }
            },
            error: function(xhr) {
                alert('Error: ' + xhr.responseText);
                btn.prop('disabled', false).html('<i class="fa fa-bolt"></i> Execute Migration');
            }
        });
    });
});
</script>

<?php require_once(APPPATH.'views/include/footer.php'); ?>
