<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center — Claim Detail</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/select2.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-file-text"></i> Claim: <?php echo htmlspecialchars($claim->claim_number); ?></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/nhis_claims/claimit"><i class="fa fa-cloud-upload"></i> Claim-IT</a></li>
                    <li class="active"><?php echo htmlspecialchars($claim->claim_number); ?></li>
                </ol>
            </section>

            <section class="content">
                <?php if(isset($message) && $message){ echo $message; } ?>

                <?php
                $statusClass = [
                    'DRAFT' => 'default', 'READY' => 'info', 'SUBMITTED' => 'primary',
                    'ACCEPTED' => 'success', 'REJECTED' => 'danger', 'APPROVED' => 'success', 'PAID' => 'success'
                ];
                $cls = $statusClass[$claim->status] ?? 'default';
                $v = isset($validation) ? $validation : ['valid' => false, 'errors' => []];
                ?>

                <!-- Validation Alert -->
                <?php if(!$v['valid']): ?>
                <div class="alert alert-warning">
                    <h4><i class="fa fa-exclamation-triangle"></i> Validation Issues</h4>
                    <ul>
                        <?php foreach($v['errors'] as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Claim Info -->
                    <div class="col-md-6">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title">Claim Information</h3>
                                <span class="label label-<?php echo $cls; ?> pull-right"><?php echo $claim->status; ?></span>
                            </div>
                            <div class="box-body">
                                <table class="table table-condensed">
                                    <tr><th width="40%">Claim Number</th><td><?php echo htmlspecialchars($claim->claim_number); ?></td></tr>
                                    <tr><th>Patient No</th><td><?php echo htmlspecialchars($claim->patient_no); ?></td></tr>
                                    <tr><th>NHIS Number</th><td><?php echo htmlspecialchars($claim->nhis_number ?? '-'); ?></td></tr>
                                    <tr><th>Claim Date</th><td><?php echo date('d M Y', strtotime($claim->claim_date)); ?></td></tr>
                                    <tr><th>Total Amount</th><td><strong>GHS <?php echo number_format($claim->total_amount, 2); ?></strong></td></tr>
                                    <?php if($claim->claimit_reference): ?>
                                    <tr><th>Claim-IT Ref</th><td><code><?php echo htmlspecialchars($claim->claimit_reference); ?></code></td></tr>
                                    <?php endif; ?>
                                    <?php if($claim->submitted_at): ?>
                                    <tr><th>Submitted</th><td><?php echo date('d M Y H:i', strtotime($claim->submitted_at)); ?></td></tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="box-footer">
                                <?php if($claim->status === 'DRAFT' && $v['valid']): ?>
                                <a href="<?php echo base_url()?>app/nhis_claims/claimit_submit/<?php echo $claim->id; ?>" class="btn btn-success">
                                    <i class="fa fa-cloud-upload"></i> Mark Ready & Submit
                                </a>
                                <?php elseif($claim->status === 'READY'): ?>
                                <a href="<?php echo base_url()?>app/nhis_claims/claimit_submit/<?php echo $claim->id; ?>" class="btn btn-success">
                                    <i class="fa fa-cloud-upload"></i> Submit to Claim-IT
                                </a>
                                <?php endif; ?>
                                <a href="<?php echo base_url()?>app/nhis_claims/claimit" class="btn btn-default">
                                    <i class="fa fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Add Diagnosis -->
                    <div class="col-md-6">
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-stethoscope"></i> Add Diagnosis</h3>
                            </div>
                            <form method="post" action="<?php echo base_url()?>app/nhis_claims/add_diagnosis">
                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                <input type="hidden" name="claim_id" value="<?php echo $claim->id; ?>">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label>ICD-10 Code</label>
                                        <select name="icd10_code" id="icd10Select" class="form-control" style="width:100%;" required>
                                            <option value="">Search ICD-10...</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Type</label>
                                        <select name="diagnosis_type" class="form-control">
                                            <option value="PRIMARY">Primary</option>
                                            <option value="SECONDARY">Secondary</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="box-footer">
                                    <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Add Diagnosis</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Diagnoses -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-list"></i> Diagnoses</h3>
                            </div>
                            <div class="box-body">
                                <table class="table table-bordered">
                                    <thead><tr><th>ICD-10 Code</th><th>Description</th><th>Type</th></tr></thead>
                                    <tbody>
                                        <?php if(!empty($diagnoses)): foreach($diagnoses as $d): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($d->icd10_code); ?></code></td>
                                            <td><?php echo htmlspecialchars($d->description ?? '-'); ?></td>
                                            <td><span class="label label-<?php echo $d->diagnosis_type === 'PRIMARY' ? 'primary' : 'default'; ?>"><?php echo $d->diagnosis_type; ?></span></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="3" class="text-center text-muted">No diagnoses added</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Claim Items -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-shopping-cart"></i> Claim Items</h3>
                            </div>
                            <div class="box-body">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Service Code</th>
                                            <th>Service Name</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-right">Tariff</th>
                                            <th class="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total = 0;
                                        if(!empty($items)): foreach($items as $item): 
                                            $amt = ($item->quantity ?? 1) * ($item->tariff ?? 0);
                                            $total += $amt;
                                        ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($item->service_code); ?></code></td>
                                            <td><?php echo htmlspecialchars($item->service_name); ?></td>
                                            <td class="text-center"><?php echo (int)$item->quantity; ?></td>
                                            <td class="text-right">GHS <?php echo number_format($item->tariff, 2); ?></td>
                                            <td class="text-right">GHS <?php echo number_format($amt, 2); ?></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="5" class="text-center text-muted">No items</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="active">
                                            <th colspan="4" class="text-right">Total:</th>
                                            <th class="text-right">GHS <?php echo number_format($total, 2); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
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
    <script src="<?php echo base_url();?>public/js/select2.min.js"></script>
    <script>
    $(function(){
        $('#icd10Select').select2({
            ajax: {
                url: '<?php echo base_url()?>app/nhis_claims/search_icd10',
                dataType: 'json',
                delay: 250,
                data: function(params){ return { term: params.term }; },
                processResults: function(data){
                    return {
                        results: data.map(function(item){
                            return { id: item.code, text: item.code + ' - ' + item.description };
                        })
                    };
                }
            },
            minimumInputLength: 2,
            placeholder: 'Search ICD-10 code or description...'
        });
    });
    </script>
</body>
</html>
