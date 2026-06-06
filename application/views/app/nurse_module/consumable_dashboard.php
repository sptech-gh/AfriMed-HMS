<!DOCTYPE html>
<html>
<head>
<head>
    <meta charset="UTF-8">
    <title>Consumable Orders — Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
.co-wrap{max-width:1400px;margin:0 auto;padding:10px 15px}
.co-header{background:linear-gradient(135deg,#1e3a5f 0%,#2d6a9f 50%,#1e3a5f 100%);border-radius:10px;padding:18px 24px;margin-bottom:18px;color:#fff;box-shadow:0 4px 18px rgba(30,58,95,.22);position:relative;overflow:hidden}
.co-header::before{content:'';position:absolute;top:-50%;right:-10%;width:280px;height:280px;background:radial-gradient(circle,rgba(255,255,255,.07) 0%,transparent 70%);border-radius:50%}
.co-header h2{margin:0 0 2px;font-size:20px;font-weight:700;font-family:'Inter',sans-serif}
.co-header .subtitle{opacity:.8;font-size:12px;font-family:'Inter',sans-serif}
.co-pbar{display:flex;gap:18px;flex-wrap:wrap;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.15)}
.co-pbar .pi{font-size:12px;line-height:1.4;font-family:'Inter',sans-serif}
.co-pbar .pi label{font-weight:600;text-transform:uppercase;font-size:9px;letter-spacing:.5px;opacity:.65;display:block}
.co-pbar .pi span{font-size:13px;font-weight:500}
.payer-badge{display:inline-block;padding:2px 8px;border-radius:16px;font-size:10px;font-weight:600;margin-top:1px}
.payer-cash{background:rgba(46,204,113,.2);color:#2ecc71}.payer-nhis{background:rgba(52,152,219,.2);color:#5dade2}
.co-grid{display:grid;grid-template-columns:400px 1fr;gap:18px;align-items:start}
@media(max-width:992px){.co-grid{grid-template-columns:1fr}}
.co-card{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden;border:1px solid #e8ecf1;margin-bottom:16px}
.co-card-head{padding:14px 18px;border-bottom:1px solid #f0f2f5;display:flex;align-items:center;gap:8px;font-family:'Inter',sans-serif}
.co-card-head h3{margin:0;font-size:14px;font-weight:600;color:#1e3a5f}
.co-card-head .ic{width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:13px}
.co-card-body{padding:16px 18px}
.co-si{position:relative;margin-bottom:14px}
.co-si input{width:100%;padding:10px 14px 10px 38px;border:2px solid #e8ecf1;border-radius:8px;font-size:13px;transition:all .2s;background:#fafbfc;outline:none;font-family:'Inter',sans-serif}
.co-si input:focus{border-color:#2d6a9f;background:#fff;box-shadow:0 0 0 3px rgba(45,106,159,.1)}
.co-si .ico{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#a0aec0;font-size:14px}
#sR{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e0e4ea;border-top:none;border-radius:0 0 8px 8px;max-height:300px;overflow-y:auto;z-index:100;display:none;box-shadow:0 6px 20px rgba(0,0,0,.1)}
.sri{padding:9px 14px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #f5f6f8;transition:background .12s;font-family:'Inter',sans-serif}
.sri:hover{background:#f0f7ff}.sri:last-child{border-bottom:none}
.sri .sn{font-weight:500;color:#2c3e50;font-size:12px}.sri .sc{font-size:10px;color:#95a5a6;margin-top:1px}
.sri .sp{font-weight:600;color:#27ae60;font-size:12px;text-align:right}
.sri .ss{font-size:9px;padding:1px 5px;border-radius:3px;display:inline-block;margin-top:1px}
.ss-ok{background:#e8f8f0;color:#27ae60}.ss-svc{background:#eaf4fe;color:#2d6a9f}
.sre{padding:16px;text-align:center;color:#a0aec0;font-size:12px}
.ce{padding:24px 16px;text-align:center;color:#c4cdd5}.ce i{font-size:32px;margin-bottom:6px;display:block;color:#dce1e6}.ce p{margin:0;font-size:12px}
.cr{display:flex;align-items:center;gap:6px;padding:8px 0;border-bottom:1px solid #f5f6f8;font-family:'Inter',sans-serif}
.cr:last-of-type{border-bottom:none}
.cr .ci{flex:1;min-width:0}.cr .cn{font-weight:500;font-size:12px;color:#2c3e50;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cr .ct{font-size:8px;padding:1px 4px;border-radius:3px;font-weight:600;margin-left:3px;vertical-align:middle}
.tg-s{background:#eaf4fe;color:#2d6a9f}.tg-v{background:#f0e6ff;color:#8e44ad}
.cr .cq{width:55px}.cr .cq input{width:100%;padding:5px 6px;border:1px solid #e0e4ea;border-radius:5px;text-align:center;font-size:12px;font-weight:500}
.cr .cq input:focus{border-color:#2d6a9f;outline:none}
.cr .cp{width:70px;text-align:right;font-weight:600;font-size:12px;color:#2c3e50}
.cr .cv{width:75px;text-align:right;font-weight:600;font-size:12px;color:#27ae60}
.cr .cd{width:24px;text-align:center;cursor:pointer;color:#ccc;font-size:15px;transition:color .12s;padding:3px}
.cr .cd:hover{color:#e74c3c}
.cf{padding:12px 0;border-top:2px solid #f0f2f5;margin-top:6px;display:flex;justify-content:space-between;align-items:center;font-family:'Inter',sans-serif}
.cf .gl{font-size:13px;font-weight:600;color:#2c3e50}.cf .gv{font-size:18px;font-weight:700;color:#1e3a5f}
.co-sb{width:100%;padding:12px;background:linear-gradient(135deg,#1e3a5f,#2d6a9f);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;margin-top:10px;font-family:'Inter',sans-serif}
.co-sb:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 3px 12px rgba(30,58,95,.25)}
.co-sb:disabled{opacity:.4;cursor:not-allowed}
.pfi{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid #f0f2f5;border-radius:8px;margin-bottom:6px;background:#fafbfc;transition:border .2s;font-family:'Inter',sans-serif}
.pfi:hover{border-color:#2d6a9f}
.pfi-i{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.pfi-s{background:#e8f8f0;color:#27ae60}.pfi-k{background:#eaf4fe;color:#2d6a9f}
.pfi-inf{flex:1;min-width:0}.pfi-n{font-weight:500;font-size:12px;color:#2c3e50}
.pfi-m{font-size:10px;color:#95a5a6;margin-top:1px}
.pfi-p{font-weight:600;font-size:12px;color:#2c3e50;white-space:nowrap}
.fb{padding:5px 12px;background:#27ae60;color:#fff;border:none;border-radius:5px;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s;white-space:nowrap}
.fb:hover{background:#219a52}
.oht{width:100%;border-collapse:separate;border-spacing:0;font-family:'Inter',sans-serif}
.oht th{text-align:left;padding:8px 12px;font-size:10px;text-transform:uppercase;letter-spacing:.4px;color:#8898a4;font-weight:600;border-bottom:2px solid #f0f2f5;background:#fafbfc}
.oht td{padding:8px 12px;border-bottom:1px solid #f5f6f8;font-size:12px;color:#2c3e50}
.oht tr:hover td{background:#f8fafc}
.oht .on{font-weight:600;color:#2d6a9f;text-decoration:none}.oht .on:hover{text-decoration:underline}
.sb{display:inline-block;padding:2px 8px;border-radius:16px;font-size:9px;font-weight:600;letter-spacing:.2px;text-transform:uppercase}
.sb-b{background:#eaf4fe;color:#2d6a9f}.sb-f{background:#e8f8f0;color:#27ae60}.sb-c{background:#fef0e7;color:#e74c3c}
.sb-p{background:#f5edff;color:#8e44ad}.sb-w{background:#fff8e6;color:#d4a017}
.ocb{padding:3px 8px;border:1px solid #e74c3c;color:#e74c3c;background:none;border-radius:4px;font-size:10px;cursor:pointer;transition:all .12s}
.ocb:hover{background:#e74c3c;color:#fff}
.ohe{text-align:center;padding:30px 16px;color:#c4cdd5}.ohe i{font-size:36px;display:block;margin-bottom:8px;color:#dce1e6}
.co-notes textarea{width:100%;padding:8px 12px;border:1px solid #e0e4ea;border-radius:6px;font-size:12px;resize:vertical;min-height:40px;transition:border .2s;font-family:'Inter',sans-serif}
.co-notes textarea:focus{border-color:#2d6a9f;outline:none}
</style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
<?php require_once(APPPATH.'views/include/sidebar.php');?>
<aside class="right-side">
<section class="content-header">
    <h1>Consumable Orders</h1>
    <ol class="breadcrumb">
        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
        <li><a href="#">Nurse Module</a></li>
        <li class="active">Consumable Orders</li>
    </ol>
</section>
<section class="content">
<div class="co-wrap">
<?php if(isset($message) && $message != '') echo $message; ?>

<?php if(isset($patientInfo) && isset($getOPDPatient)): ?>
<div class="co-header">
    <h2><i class="fa fa-cubes"></i> Consumable Orders</h2>
    <div class="subtitle">Order supplies and services for this patient encounter</div>
    <div class="co-pbar">
        <div class="pi"><label>Patient</label><span><?php echo htmlspecialchars($patientInfo->lastname . ', ' . $patientInfo->firstname); ?></span></div>
        <div class="pi"><label>Patient ID</label><span><?php echo htmlspecialchars($patient_no); ?></span></div>
        <div class="pi"><label>Visit ID</label><span><?php echo htmlspecialchars($iop_id); ?></span></div>
        <div class="pi"><label>Type</label><span><?php echo isset($getOPDPatient->patient_type) ? htmlspecialchars($getOPDPatient->patient_type) : 'IPD'; ?></span></div>
        <div class="pi"><label>Location</label><span><?php echo isset($getOPDPatient->room_name) ? htmlspecialchars('Room ' . $getOPDPatient->room_name . ' — Bed ' . $getOPDPatient->bed_name) : '—'; ?></span></div>
        <div class="pi"><label>Payer</label>
            <?php
                $pd = isset($getOPDPatient->patient_type) ? strtoupper(trim($getOPDPatient->patient_type)) : 'CASH';
                $pc = 'payer-cash'; if(strpos($pd,'NHIS')!==false) $pc='payer-nhis';
            ?>
            <span class="payer-badge <?php echo $pc; ?>"><?php echo htmlspecialchars($pd); ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="co-grid">
<?php if(!empty($can_create)): ?>
<div>
    <div class="co-card">
        <div class="co-card-head">
            <div class="ic" style="background:#eaf4fe;color:#2d6a9f"><i class="fa fa-plus"></i></div>
            <h3>New Order</h3>
        </div>
        <div class="co-card-body">
            <div class="co-si">
                <i class="fa fa-search ico"></i>
                <input type="text" id="cS" placeholder="Search consumables, supplies, services..." autocomplete="off">
                <div id="sR"></div>
            </div>
            <form method="post" action="<?php echo base_url(); ?>app/consumable_order/create" id="oF">
                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                <input type="hidden" name="iop_id" value="<?php echo htmlspecialchars($iop_id); ?>">
                <input type="hidden" name="patient_no" value="<?php echo htmlspecialchars($patient_no); ?>">
                <input type="hidden" name="item_count" id="iC" value="0">
                <div id="cW">
                    <div id="eC" class="ce"><i class="fa fa-shopping-basket"></i><p>Search and add items above</p></div>
                    <div id="cI"></div>
                </div>
                <div class="cf" id="cF" style="display:none">
                    <span class="gl">Total</span>
                    <span class="gv" id="gT">GHS 0.00</span>
                </div>
                <div class="co-notes"><textarea name="notes" placeholder="Add notes (optional)..." rows="2"></textarea></div>
                <button type="submit" class="co-sb" id="sB" disabled><i class="fa fa-paper-plane"></i> Submit Order for Billing</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div>
<?php if(!empty($pending_items)): ?>
<div class="co-card">
    <div class="co-card-head">
        <div class="ic" style="background:#fff8e6;color:#d4a017"><i class="fa fa-clock-o"></i></div>
        <h3>Awaiting Fulfillment</h3>
        <span style="margin-left:auto;font-size:11px;padding:2px 8px;border-radius:16px;background:#fff8e6;color:#d4a017;font-weight:600"><?php echo count($pending_items); ?></span>
    </div>
    <div class="co-card-body" style="padding:10px 14px">
        <?php foreach($pending_items as $pi): ?>
        <div class="pfi">
            <div class="pfi-i <?php echo (int)$pi->is_stock_backed ? 'pfi-k' : 'pfi-s'; ?>">
                <i class="fa <?php echo (int)$pi->is_stock_backed ? 'fa-cube' : 'fa-wrench'; ?>"></i>
            </div>
            <div class="pfi-inf">
                <div class="pfi-n"><?php echo htmlspecialchars($pi->item_name); ?></div>
                <div class="pfi-m"><?php echo htmlspecialchars($pi->order_no); ?> &bull; Qty: <?php echo (float)$pi->quantity - (float)$pi->fulfilled_qty; ?></div>
            </div>
            <span class="pfi-p">GHS <?php echo number_format((float)$pi->unit_price * ((float)$pi->quantity - (float)$pi->fulfilled_qty), 2); ?></span>
            <form method="post" action="<?php echo base_url(); ?>app/consumable_order/fulfill_item" style="margin:0" onsubmit="return confirm('Confirm fulfillment?')">
                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                <input type="hidden" name="item_id" value="<?php echo (int)$pi->item_id; ?>">
                <input type="hidden" name="quantity" value="<?php echo (float)$pi->quantity - (float)$pi->fulfilled_qty; ?>">
                <input type="hidden" name="iop_id" value="<?php echo htmlspecialchars($iop_id); ?>">
                <input type="hidden" name="patient_no" value="<?php echo htmlspecialchars($patient_no); ?>">
                <button type="submit" class="fb"><i class="fa fa-check"></i> Fulfill</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="co-card">
    <div class="co-card-head">
        <div class="ic" style="background:#f0f2f5;color:#64748b"><i class="fa fa-history"></i></div>
        <h3>Order History</h3>
    </div>
    <?php if(empty($orders)): ?>
    <div class="ohe"><i class="fa fa-inbox"></i><p style="font-size:12px">No orders yet for this encounter</p></div>
    <?php else: ?>
    <div style="overflow-x:auto">
        <table class="oht">
            <thead><tr><th>Order #</th><th>Date</th><th>Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach($orders as $o):
                $st=strtoupper(trim($o->order_status));$sc='sb-b';
                if($st==='FULFILLED')$sc='sb-f';elseif($st==='CANCELLED')$sc='sb-c';
                elseif($st==='PARTIALLY_FULFILLED')$sc='sb-p';elseif($st==='PENDING_BILLING')$sc='sb-w';
            ?>
            <tr>
                <td><a class="on" href="<?php echo base_url(); ?>app/consumable_order/order_detail/<?php echo urlencode($o->order_no); ?>"><?php echo htmlspecialchars($o->order_no); ?></a></td>
                <td style="white-space:nowrap"><?php echo date('M d, H:i', strtotime($o->ordered_at)); ?></td>
                <td style="font-weight:600">GHS <?php echo number_format((float)$o->net_amount, 2); ?></td>
                <td><span class="sb <?php echo $sc; ?>"><?php echo str_replace('_',' ',$st); ?></span></td>
                <td>
                    <?php if($st!=='CANCELLED'&&$st!=='FULFILLED'&&!empty($can_create)): ?>
                    <form method="post" action="<?php echo base_url(); ?>app/consumable_order/cancel" style="margin:0" onsubmit="return confirm('Cancel this order?')">
                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                        <input type="hidden" name="order_id" value="<?php echo $o->order_id; ?>">
                        <input type="hidden" name="iop_id" value="<?php echo htmlspecialchars($iop_id); ?>">
                        <input type="hidden" name="patient_no" value="<?php echo htmlspecialchars($patient_no); ?>">
                        <input type="hidden" name="reason" value="Cancelled from dashboard">
                        <button type="submit" class="ocb"><i class="fa fa-times"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</div>
</div>
</div>
</section>
</aside>
</div>

<script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
<script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
<script>
(function(){
    var ci=0,bU='<?php echo base_url(); ?>',csrfN='<?php echo $this->security->get_csrf_token_name(); ?>',csrfV='<?php echo $this->security->get_csrf_hash(); ?>',st;
    var $s=$('#cS'),$r=$('#sR'),$cI=$('#cI'),$eC=$('#eC'),$cF=$('#cF'),$iC=$('#iC'),$gT=$('#gT'),$sB=$('#sB');

    $s.on('input',function(){
        clearTimeout(st);var t=$.trim(this.value);
        if(t.length<2){$r.hide();return;}
        st=setTimeout(function(){
            $.post(bU+'app/consumable_order/search_catalog_ajax',{term:t,[csrfN]:csrfV},function(d){
                if(!d.results||!d.results.length){$r.html('<div class="sre"><i class="fa fa-search"></i> No items found</div>').show();return;}
                var h='';
                $.each(d.results,function(i,it){
                    var sl=it.is_stock_backed?'<span class="ss ss-ok">Stock</span>':'<span class="ss ss-svc">Service</span>';
                    h+='<div class="sri" data-s="'+it.item_source+'" data-c="'+it.catalog_id+'" data-n="'+encodeURIComponent(it.item_name)+'" data-p="'+it.unit_price+'" data-k="'+(it.is_stock_backed?1:0)+'">'
                      +'<div><div class="sn">'+it.item_name+'</div><div class="sc">'+it.group_name+'</div></div>'
                      +'<div><div class="sp">GHS '+parseFloat(it.unit_price).toFixed(2)+'</div>'+sl+'</div></div>';
                });
                $r.html(h).show();
                $r.find('.sri').click(function(){
                    addI($(this).data('s'),$(this).data('c'),decodeURIComponent($(this).data('n')),parseFloat($(this).data('p')),parseInt($(this).data('k')));
                });
            },'json');
        },280);
    });

    function addI(src,cid,nm,pr,sk){
        ci++;$eC.hide();$cF.show();
        var tg=sk?'<span class="ct tg-s">STOCK</span>':'<span class="ct tg-v">SVC</span>';
        var row=$('<div class="cr" id="r_'+ci+'">'
            +'<div class="ci"><div class="cn">'+nm+tg+'</div>'
            +'<input type="hidden" name="item_source_'+ci+'" value="'+src+'">'
            +'<input type="hidden" name="catalog_id_'+ci+'" value="'+cid+'"></div>'
            +'<div class="cq"><input type="number" name="quantity_'+ci+'" value="1" min="1" data-p="'+pr+'" data-i="'+ci+'"></div>'
            +'<div class="cp">'+pr.toFixed(2)+'</div>'
            +'<div class="cv" id="v_'+ci+'">'+pr.toFixed(2)+'</div>'
            +'<div class="cd" data-i="'+ci+'">&times;</div></div>');
        $cI.append(row);$iC.val(ci);
        row.find('input[type=number]').on('input',function(){rc($(this));});
        row.find('.cd').click(function(){$('#r_'+$(this).data('i')).remove();rf();});
        rf();$r.hide();$s.val('').focus();
    }

    function rc($i){var p=parseFloat($i.data('p')),q=parseInt($i.val())||1;$('#v_'+$i.data('i')).text((p*q).toFixed(2));rf();}

    function rf(){
        var s=0;$cI.find('.cr').each(function(){var i=$(this).find('input[type=number]');if(i.length)s+=parseFloat(i.data('p'))*(parseInt(i.val())||1);});
        $gT.text('GHS '+s.toFixed(2));$sB.prop('disabled',$cI.find('.cr').length===0);
        if(!$cI.find('.cr').length){$eC.show();$cF.hide();}
    }

    $(document).click(function(e){if(!$(e.target).closest('#sR,#cS').length)$r.hide();});
})();
</script>
</body>
</html>
