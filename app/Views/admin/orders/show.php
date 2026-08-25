<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('styles') ?>
<style>
.order-shell{max-width:1500px;margin:auto}.order-hero{background:linear-gradient(135deg,#201847,#6d49d8);color:#fff;border-radius:18px;padding:24px}.order-hero .badge{background:rgba(255,255,255,.16);color:#fff}.order-card{border:1px solid #e8e9f2;border-radius:14px;box-shadow:0 8px 28px rgba(30,24,71,.05)}.order-card .card-header{background:#fff;border-bottom:1px solid #ececf4}.order-photo{min-height:310px;background:#f7f7fb;border-radius:12px;display:flex;align-items:center;justify-content:center;overflow:hidden}.order-photo img{width:100%;max-height:480px;object-fit:contain}.detail-label{color:#7a7e96;font-size:12px;margin-bottom:3px}.detail-value{color:#252844;font-weight:600}.table thead th{background:#f8f8fc;white-space:nowrap}.ready-thumb{width:76px;height:76px;border-radius:10px;object-fit:cover;border:1px solid #e5e7eb}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$attachments = is_array($attachments ?? null) ? $attachments : [];
$readyImages = is_array($readyImages ?? null) ? $readyImages : [];
$followups = is_array($followups ?? null) ? $followups : [];
$studdedDetails = is_array($studdedDetails ?? null) ? $studdedDetails : [];
$receiveSummary = is_array($receiveSummary ?? null) ? $receiveSummary : [];
$orderPhoto = null;
foreach ($attachments as $file) {
    if (strtolower((string) ($file['file_type'] ?? '')) === 'photo') {
        $orderPhoto = base_url((string) $file['file_path']);
        break;
    }
}
if ($orderPhoto === null && $readyImages !== []) {
    $orderPhoto = site_url('admin/orders/ready-image/' . (int) $readyImages[0]['id']);
}
$status = (string) ($order['status'] ?? '');
$canReceive = ! in_array($status, ['Cancelled', 'Completed'], true) && (int) ($order['assigned_karigar_id'] ?? 0) > 0;
?>

<div class="order-shell">
    <div class="order-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="small opacity-75">Order Details</div>
                <h2 class="my-2 text-white"><?= esc((string) $order['order_no']) ?></h2>
                <div class="d-flex flex-wrap gap-2"><span class="badge rounded-pill px-3 py-2"><?= esc((string) $order['order_type']) ?></span><span class="badge rounded-pill px-3 py-2"><?= esc($status) ?></span><span class="badge rounded-pill px-3 py-2"><?= esc((string) ($order['priority'] ?? '-')) ?> priority</span></div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?php if (! in_array($status, ['Cancelled', 'Completed'], true)): ?><a href="<?= site_url('admin/orders/' . $order['id'] . '/edit') ?>" class="btn btn-light"><i class="fe fe-edit me-1"></i>Edit</a><?php endif; ?>
                <?php if ($canReceive): ?><button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#receiveModal"><i class="fe fe-check-circle me-1"></i>Receive Jewellery</button><?php endif; ?>
                <a href="<?= site_url((string) $order['order_type'] === 'Repair' ? 'admin/orders/repair' : 'admin/orders') ?>" class="btn btn-outline-light"><i class="fe fe-arrow-left me-1"></i>Back</a>
            </div>
        </div>
    </div>

    <?php if (! $canReceive && ! in_array($status, ['Cancelled', 'Completed'], true)): ?><div class="alert alert-warning">Assign a karigar before receiving finished jewellery.</div><?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card order-card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="fe fe-info me-2"></i>Order Information</h5></div>
                <div class="card-body">
                    <?php $details = ['Order From'=>($order['order_from']??'')?:'-','Customer'=>($order['customer_name']??'')?:'-','Sales Person'=>($order['sales_person_name']??'')?:'-','Sales Mobile'=>($order['sales_person_mobile']??'')?:'-','Assigned Karigar'=>($order['karigar_name']??'')?:'Not assigned','Due Date'=>($order['due_date']??'')?:'-','Type'=>($order['order_type']??'')?:'-','Design Type'=>($order['order_design_type']??'')?:'Fresh','Status'=>$status?:'-']; ?>
                    <div class="row g-4 mb-4"><?php foreach ($details as $label => $value): ?><div class="col-sm-6 col-xl-4"><div class="detail-label"><?= esc($label) ?></div><div class="detail-value"><?= esc((string) $value) ?></div></div><?php endforeach; ?></div>
                    <div class="detail-label">Notes</div><div class="detail-value mb-4"><?= nl2br(esc((string) (($order['order_notes'] ?? '') ?: '-'))) ?></div>
                    <div class="table-responsive"><table class="table table-bordered align-middle mb-0"><thead><tr><th>Design</th><th>Description</th><th>Purity</th><th>Size</th><th>Qty</th><th>Status</th></tr></thead><tbody>
                    <?php if (($items ?? []) === []): ?><tr><td colspan="6" class="text-center text-muted">No order items.</td></tr><?php endif; ?>
                    <?php foreach (($items ?? []) as $item): ?><tr><td><?= esc(trim((string)($item['design_code']??'').' '.(string)($item['design_name']??''))?:'-') ?></td><td><?= esc((string)(($item['item_description']??'')?:'-')) ?></td><td><?= esc(trim((string)($item['purity_code']??'').' '.(string)($item['color_name']??''))?:'-') ?></td><td><?= esc((string)(($item['size_label']??'')?:'-')) ?></td><td><?= esc((string)($item['qty']??0)) ?></td><td><span class="badge bg-light text-dark"><?= esc((string)($item['item_status']??'-')) ?></span></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4"><div class="card order-card h-100"><div class="card-header"><h5 class="mb-0"><i class="fe fe-image me-2"></i>Order Photo</h5></div><div class="card-body">
            <div class="order-photo"><?php if ($orderPhoto !== null): ?><a href="<?= esc($orderPhoto,'attr') ?>" target="_blank" rel="noopener"><img src="<?= esc($orderPhoto,'attr') ?>" alt="Order photo"></a><?php else: ?><div class="text-center text-muted"><i class="fe fe-image fs-1"></i><div class="mt-2">No photo uploaded</div></div><?php endif; ?></div>
            <?php if ($readyImages !== []): ?><div class="d-flex flex-wrap gap-2 mt-3"><?php foreach ($readyImages as $readyImage): ?><?php $url=site_url('admin/orders/ready-image/'.(int)$readyImage['id']); ?><a href="<?= esc($url,'attr') ?>" target="_blank"><img class="ready-thumb" src="<?= esc($url,'attr') ?>" alt="Ready jewellery"></a><?php endforeach; ?></div><?php endif; ?>
        </div></div></div>
    </div>

    <div class="card order-card mb-4"><div class="card-header"><h5 class="mb-0"><i class="fe fe-gem me-2"></i>Studded & Finished Jewellery Details</h5></div><div class="card-body">
        <?php if ($receiveSummary !== []): ?><div class="row g-3 mb-4"><div class="col-6 col-md-3"><div class="detail-label">Gross Weight</div><div class="detail-value"><?= number_format((float)($receiveSummary['gross_weight_gm']??0),3) ?> gm</div></div><div class="col-6 col-md-3"><div class="detail-label">Net Gold</div><div class="detail-value"><?= number_format((float)($receiveSummary['net_gold_weight_gm']??0),3) ?> gm</div></div><div class="col-6 col-md-3"><div class="detail-label">Pure Gold</div><div class="detail-value"><?= number_format((float)($receiveSummary['pure_gold_weight_gm']??0),3) ?> gm</div></div><div class="col-6 col-md-3"><div class="detail-label">Valuation</div><div class="detail-value">₹<?= number_format((float)($receiveSummary['total_valuation']??0),2) ?></div></div></div><?php endif; ?>
        <div class="table-responsive"><table class="table table-bordered align-middle mb-0"><thead><tr><th>Component</th><th>Description</th><th>PCS</th><th>Weight (cts)</th><th>Weight (gm)</th><th>Rate</th><th>Total</th></tr></thead><tbody>
        <?php if ($studdedDetails === []): ?><tr><td colspan="7" class="text-center text-muted">Studded details will appear after receiving.</td></tr><?php endif; ?>
        <?php foreach ($studdedDetails as $detail): ?><tr><td><span class="badge bg-light text-dark"><?= esc(ucfirst((string)($detail['component_type']??'-'))) ?></span></td><td><?= esc((string)($detail['component_name']??'-')) ?></td><td><?= number_format((float)($detail['pcs']??0),3) ?></td><td><?= number_format((float)($detail['weight_cts']??0),3) ?></td><td><?= number_format((float)($detail['weight_gm']??0),3) ?></td><td>₹<?= number_format((float)($detail['rate']??0),2) ?></td><td>₹<?= number_format((float)($detail['line_total']??0),2) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div></div>

    <div class="card order-card mb-4"><div class="card-header"><h5 class="mb-0"><i class="fe fe-message-circle me-2"></i>Followups</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-bordered align-middle mb-0"><thead><tr><th>Stage</th><th>Description</th><th>Next Followup</th><th>Taken By</th><th>Taken On</th><th>Image</th></tr></thead><tbody>
    <?php if ($followups === []): ?><tr><td colspan="6" class="text-center text-muted">No followups yet.</td></tr><?php endif; ?>
    <?php foreach ($followups as $followup): ?><tr><td><?= esc((string)(($followup['stage']??'')?:'-')) ?></td><td><?= esc((string)(($followup['description']??'')?:'-')) ?></td><td><?= esc((string)(($followup['next_followup_date']??'')?:'-')) ?></td><td><?= esc((string)(($followup['followup_taken_by_name']??'')?:'Admin')) ?></td><td><?= esc((string)(($followup['followup_taken_on']??'')?:'-')) ?></td><td><?php if (!empty($followup['image_path'])): ?><a class="btn btn-sm btn-outline-primary" href="<?= base_url((string)$followup['image_path']) ?>" target="_blank">View</a><?php else: ?>-<?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div></div>
</div>

<?php if ($canReceive): ?>
<div class="modal fade" id="receiveModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" method="post" action="<?= site_url('admin/orders/'.$order['id'].'/receive') ?>"><?= csrf_field() ?>
<div class="modal-header"><h5 class="modal-title">Manual Finished Jewellery Receiving</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body js-receive-modal"><div class="alert alert-info">Enter every value manually. Nothing is fetched from issuements.</div>
<div class="row g-3 mb-4"><div class="col-md-3"><label class="form-label">Receive Location *</label><select name="location_id" class="form-select" required><option value="">Select</option><?php foreach (($locations??[]) as $location): ?><option value="<?= (int)$location['id'] ?>"><?= esc((string)$location['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Gross Weight (gm) *</label><input type="number" step="0.001" min="0.001" name="gross_weight_gm" class="form-control js-gross-weight" required></div><div class="col-md-3"><label class="form-label">Purity % *</label><input type="number" step="0.001" min="0.001" max="100" name="purity_percent" class="form-control js-purity-percent" required></div><div class="col-md-3"><label class="form-label">Net Gold (gm)</label><input type="text" class="form-control js-net-weight" readonly></div><div class="col-md-3"><label class="form-label">Pure Gold (gm)</label><input type="text" class="form-control js-pure-weight" readonly></div><div class="col-md-3"><label class="form-label">Gold Rate / gm *</label><input type="number" step="0.01" min="0.01" name="gold_rate_per_gm" class="form-control js-gold-rate" required></div><div class="col-md-3"><label class="form-label">Gold Amount</label><input type="text" class="form-control js-gold-total" readonly></div><div class="col-md-3"><label class="form-label">Labour Rate / gm</label><input type="number" step="0.01" min="0" name="labour_rate_per_gm" class="form-control js-labour-rate"></div><div class="col-md-3"><label class="form-label">Labour Amount</label><input type="text" class="form-control js-labour-total" readonly></div><div class="col-md-9"><label class="form-label">Remarks</label><input type="text" name="notes" class="form-control"></div></div>
<?php foreach ([['dia','Studded Diamond',['studded_diamond_type','studded_diamond_pcs','studded_diamond_weight','studded_diamond_rate']],['stone','Stone',['stone_type','stone_pcs','stone_weight','stone_rate']],['other','Other Material',['other_desc','other_pcs','other_weight_line_gm','other_price']]] as $section): ?><?php [$key,$title,$names]=$section; ?><div class="d-flex justify-content-between align-items-center mt-4 mb-2"><strong><?= esc($title) ?></strong><button type="button" class="btn btn-sm btn-outline-primary js-add-row" data-kind="<?= esc($key) ?>"><i class="fe fe-plus"></i> Add More</button></div><div class="table-responsive"><table class="table table-bordered align-middle"><thead><tr><th>Description</th><th>PCS</th><th><?= $key==='other'?'Weight (gm)':'Weight (cts)' ?></th><th><?= $key==='other'?'Price':'Rate / cts' ?></th><th>Total</th><th></th></tr></thead><tbody class="js-<?= esc($key) ?>-body"><tr><td><input type="text" name="<?= esc($names[0]) ?>[]" class="form-control"></td><td><input type="number" step="0.001" min="0" name="<?= esc($names[1]) ?>[]" class="form-control"></td><td><input type="number" step="0.001" min="0" name="<?= esc($names[2]) ?>[]" class="form-control js-<?= esc($key) ?>-weight"></td><td><input type="number" step="0.01" min="0" name="<?= esc($names[3]) ?>[]" class="form-control js-<?= esc($key) ?>-rate"></td><td><input type="text" class="form-control js-<?= esc($key) ?>-total" readonly></td><td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td></tr></tbody></table></div><?php endforeach; ?>
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-success">Save & Complete Order</button></div></form></div></div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function(){const modal=document.getElementById('receiveModal');if(!modal)return;const n=v=>{const x=parseFloat(v||'0');return Number.isFinite(x)?x:0};const fields={dia:['studded_diamond_type','studded_diamond_pcs','studded_diamond_weight','studded_diamond_rate'],stone:['stone_type','stone_pcs','stone_weight','stone_rate'],other:['other_desc','other_pcs','other_weight_line_gm','other_price']};function rowHtml(k){const f=fields[k];return '<tr><td><input type="text" name="'+f[0]+'[]" class="form-control"></td><td><input type="number" step="0.001" min="0" name="'+f[1]+'[]" class="form-control"></td><td><input type="number" step="0.001" min="0" name="'+f[2]+'[]" class="form-control js-'+k+'-weight"></td><td><input type="number" step="0.01" min="0" name="'+f[3]+'[]" class="form-control js-'+k+'-rate"></td><td><input type="text" class="form-control js-'+k+'-total" readonly></td><td><button type="button" class="btn btn-sm btn-outline-danger js-remove-row"><i class="fe fe-trash"></i></button></td></tr>'}function recalc(){['dia','stone','other'].forEach(k=>modal.querySelectorAll('.js-'+k+'-body tr').forEach(row=>{const w=n((row.querySelector('.js-'+k+'-weight')||{}).value),r=n((row.querySelector('.js-'+k+'-rate')||{}).value),t=row.querySelector('.js-'+k+'-total');if(t)t.value=(k==='other'?r:w*r).toFixed(2)}));let d=0,s=0,o=0;modal.querySelectorAll('.js-dia-weight').forEach(e=>d+=n(e.value));modal.querySelectorAll('.js-stone-weight').forEach(e=>s+=n(e.value));modal.querySelectorAll('.js-other-weight').forEach(e=>o+=n(e.value));const gross=n((modal.querySelector('.js-gross-weight')||{}).value),purity=n((modal.querySelector('.js-purity-percent')||{}).value),net=gross-d*.2-s*.2-o,safe=Math.max(net,0),set=(q,v)=>{const e=modal.querySelector(q);if(e)e.value=v};set('.js-net-weight',net.toFixed(3));set('.js-pure-weight',(safe*purity/100).toFixed(3));set('.js-gold-total',(safe*n((modal.querySelector('.js-gold-rate')||{}).value)).toFixed(2));set('.js-labour-total',(safe*n((modal.querySelector('.js-labour-rate')||{}).value)).toFixed(2))}modal.addEventListener('click',e=>{const t=e.target instanceof Element?e.target:null;if(!t)return;const a=t.closest('.js-add-row');if(a){const k=a.getAttribute('data-kind'),b=modal.querySelector('.js-'+k+'-body');if(b)b.insertAdjacentHTML('beforeend',rowHtml(k))}const rm=t.closest('.js-remove-row');if(rm){const row=rm.closest('tr'),body=row?row.parentElement:null;if(body&&row&&body.children.length>1)row.remove()}recalc()});modal.addEventListener('input',recalc);recalc()})();
</script>
<?= $this->endSection() ?>
