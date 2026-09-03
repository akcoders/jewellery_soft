<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<style>
    .jobwork-toolbar { align-items: center; display: flex; gap: 12px; justify-content: space-between; margin-bottom: 12px; }
    .jobwork-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(310px, 1fr)); }
    .jobwork-card { background: #fff; border: 1px solid #dfe4eb; border-radius: 13px; cursor: pointer; display: flex; gap: 12px; min-height: 112px; padding: 12px; position: relative; transition: border-color .18s, box-shadow .18s, transform .18s; }
    .jobwork-card:hover { border-color: #c4ccd8; transform: translateY(-1px); }
    .jobwork-card.is-selected { border-color: var(--erp-red, #bd1422); box-shadow: 0 0 0 2px rgba(189,20,34,.1); }
    .jobwork-check { height: 18px; margin: 0; position: absolute; right: 12px; top: 12px; width: 18px; }
    .jobwork-photo { align-items: center; background: #f3f4f6; border-radius: 10px; color: #9ba4b1; display: inline-flex; flex: 0 0 88px; height: 88px; justify-content: center; overflow: hidden; }
    .jobwork-photo img { height: 100%; object-fit: cover; width: 100%; }
    .jobwork-copy { min-width: 0; padding-right: 20px; }
    .jobwork-copy strong, .jobwork-copy small { display: block; }
    .jobwork-order-name { color: #222b3a; font-size: 13px; font-weight: 780; line-height: 1.3; margin: 4px 0; }
    .jobwork-code { color: var(--erp-red, #bd1422); font-size: 10px; font-weight: 750; overflow-wrap: anywhere; }
    .jobwork-meta { color: #707b8c; font-size: 10px; line-height: 1.55; }
    .jobwork-empty { background: #fafbfc; border: 1px dashed #d9dee7; border-radius: 12px; color: #7a8493; padding: 30px; text-align: center; }
    @media (max-width: 575px) { .jobwork-grid { grid-template-columns: 1fr; } .jobwork-toolbar { align-items: stretch; flex-direction: column; } }
</style>
<div class="erp-page-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Karigar accounts</span>
        <h4 class="mb-1">Add Labour Bill</h4>
        <p class="mb-0">Combine one or more completed job works in the supplier's actual invoice.</p>
    </div>
    <a href="<?= site_url('admin/accounts/labour-bills') ?>" class="btn btn-outline-primary"><i class="fe fe-arrow-left me-1"></i>Labour Bills</a>
</div>

<form method="post" action="<?= site_url('admin/accounts/labour-bills') ?>" enctype="multipart/form-data" id="labour-bill-form">
    <?= csrf_field() ?>
    <div class="card mb-3">
        <div class="card-header"><h5 class="card-title mb-0">Bill Information</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-4 mb-3">
                    <label class="form-label">Karigar <span class="text-danger">*</span></label>
                    <select name="karigar_id" id="karigar_id" class="form-select select2" required>
                        <option value="">Select karigar</option>
                        <?php foreach ($karigars as $karigar): ?>
                            <option value="<?= (int) $karigar['id'] ?>" <?= (int) (old('karigar_id') ?: $selectedKarigarId) === (int) $karigar['id'] ? 'selected' : '' ?>><?= esc((string) $karigar['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-4 mb-3"><label class="form-label">Supplier Bill No <span class="text-danger">*</span></label><input type="text" name="bill_no" class="form-control" maxlength="40" value="<?= esc(old('bill_no')) ?>" required></div>
                <div class="col-lg-2 col-md-6 mb-3"><label class="form-label">Bill Date <span class="text-danger">*</span></label><input type="date" name="bill_date" class="form-control" value="<?= esc(old('bill_date') ?: date('Y-m-d')) ?>" required></div>
                <div class="col-lg-2 col-md-6 mb-3"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control" value="<?= esc(old('due_date')) ?>"></div>
                <div class="col-12 mb-3">
                    <div class="jobwork-toolbar">
                        <div><label class="form-label mb-0">Completed Job Works <span class="text-danger">*</span></label><small class="text-muted d-block">Select one or multiple unbilled job works from the chosen karigar.</small></div>
                        <input type="search" id="jobwork-search" class="form-control" style="max-width:280px" placeholder="Search order ID or name">
                    </div>
                    <?php $oldJobworks = array_map('strval', (array) old('jobworks')); ?>
                    <div id="jobworks" class="jobwork-grid">
                        <?php foreach ($jobworks as $job): ?>
                            <?php $searchText = strtolower(trim((string) (($job['order_id'] ?? '') . ' ' . ($job['order_no'] ?? '') . ' ' . ($job['order_name'] ?? '') . ' ' . ($job['description'] ?? '')))); ?>
                            <label class="jobwork-card<?= in_array((string) $job['selector'], $oldJobworks, true) ? ' is-selected' : '' ?>" data-karigar="<?= (int) $job['karigar_id'] ?>" data-search="<?= esc($searchText, 'attr') ?>">
                                <input type="checkbox" class="form-check-input jobwork-check" name="jobworks[]" value="<?= esc((string) $job['selector'], 'attr') ?>" data-labour="<?= esc((string) $job['labour_amount'], 'attr') ?>" data-net="<?= esc((string) $job['net_weight_gm'], 'attr') ?>" <?= in_array((string) $job['selector'], $oldJobworks, true) ? 'checked' : '' ?>>
                                <span class="jobwork-photo"><?php if (! empty($job['image_url'])): ?><img src="<?= esc((string) $job['image_url'], 'attr') ?>" alt="" loading="lazy" onerror="this.style.display='none'"><?php else: ?><i class="fe fe-image"></i><?php endif; ?></span>
                                <span class="jobwork-copy">
                                    <small class="jobwork-code">Order DB #<?= (int) ($job['order_id'] ?? 0) ?> · <?= esc((string) (($job['order_no'] ?? '') ?: 'No order number')) ?></small>
                                    <span class="jobwork-order-name"><?= esc((string) (($job['order_name'] ?? '') ?: ($job['description'] ?? 'Completed job work'))) ?></span>
                                    <small class="jobwork-meta"><?= esc((string) ($job['jobwork_date'] ?? '-')) ?> · Gross <?= number_format((float) ($job['gross_weight_gm'] ?? 0), 3) ?> gm · Net <?= number_format((float) ($job['net_weight_gm'] ?? 0), 3) ?> gm</small>
                                    <small class="jobwork-meta">Labour ₹<?= number_format((float) ($job['labour_amount'] ?? 0), 2) ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div id="jobwork-empty" class="jobwork-empty">Select a karigar to view completed job works.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Tax & Attachment</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">GST Master <span class="text-danger">*</span></label>
                            <select name="gst_master_id" id="gst_master_id" class="form-select" required>
                                <option value="">Select tax structure</option>
                                <?php foreach ($gstMasters as $master): ?>
                                    <option value="<?= (int) $master['id'] ?>" data-components='<?= esc(json_encode($master['components']), 'attr') ?>' <?= (string) old('gst_master_id') === (string) $master['id'] ? 'selected' : '' ?>><?= esc((string) $master['name']) ?> (<?= number_format((float) $master['total_percentage'], 3) ?>%)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3"><label class="form-label">Other Charge / Adjustment</label><input type="number" name="other_amount" id="other_amount" class="form-control" step="0.01" value="<?= esc(old('other_amount') ?: '0.00') ?>"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Round Off</label><input type="number" name="round_off_amount" id="round_off_amount" class="form-control" step="0.01" value="<?= esc(old('round_off_amount') ?: '0.00') ?>"></div>
                        <div class="col-md-7 mb-3"><label class="form-label">Bill Attachment</label><input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp"><small class="text-muted">PDF/JPG/PNG/WebP, maximum 10 MB.</small></div>
                        <div class="col-md-5 mb-3"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control" value="<?= esc(old('notes')) ?>" placeholder="Optional remarks"></div>
                    </div>
                    <div id="tax-breakup" class="small text-muted">Select a GST master to see the tax breakup.</div>
                </div>
            </div>
        </div>
        <div class="col-xl-5 mt-3 mt-xl-0">
            <div class="card h-100 border-primary">
                <div class="card-header"><h5 class="card-title mb-0">Bill Summary</h5></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom"><span>Selected job works</span><strong id="summary-count">0</strong></div>
                    <div class="d-flex justify-content-between py-2 border-bottom"><span>Net gold weight</span><strong id="summary-weight">0.000 gm</strong></div>
                    <div class="d-flex justify-content-between py-2 border-bottom"><span>Job-work labour</span><strong id="summary-labour">₹0.00</strong></div>
                    <div class="d-flex justify-content-between py-2 border-bottom"><span>Taxable amount</span><strong id="summary-taxable">₹0.00</strong></div>
                    <div class="d-flex justify-content-between py-2 border-bottom"><span>GST</span><strong id="summary-gst">₹0.00</strong></div>
                    <div class="d-flex justify-content-between pt-3 fs-5"><span>Invoice total</span><strong class="text-primary" id="summary-total">₹0.00</strong></div>
                    <button class="btn btn-primary w-100 mt-4"><i class="fe fe-save me-1"></i>Save Labour Bill</button>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const karigar = document.getElementById('karigar_id');
    const jobworks = document.getElementById('jobworks');
    const search = document.getElementById('jobwork-search');
    const empty = document.getElementById('jobwork-empty');
    const gst = document.getElementById('gst_master_id');
    const other = document.getElementById('other_amount');
    const roundOff = document.getElementById('round_off_amount');
    if (!karigar || !jobworks || !gst) return;
    const cards = Array.from(jobworks.querySelectorAll('.jobwork-card'));

    function filterJobworks() {
        const id = karigar.value;
        const query = String(search ? search.value : '').trim().toLowerCase();
        let visible = 0;
        cards.forEach(function (card) {
            const correctKarigar = !!id && card.dataset.karigar === id;
            const matchesSearch = !query || String(card.dataset.search || '').includes(query);
            const show = correctKarigar && matchesSearch;
            card.style.display = show ? '' : 'none';
            if (show) visible += 1;
            if (!correctKarigar) {
                const checkbox = card.querySelector('.jobwork-check');
                if (checkbox) checkbox.checked = false;
                card.classList.remove('is-selected');
            }
        });
        if (empty) {
            empty.style.display = visible === 0 ? '' : 'none';
            empty.textContent = id ? (query ? 'No job work matches this search.' : 'No unbilled job work is available for this karigar.') : 'Select a karigar to view completed job works.';
        }
        calculate();
    }

    function calculate() {
        const selected = Array.from(jobworks.querySelectorAll('.jobwork-check:checked'));
        const labour = selected.reduce(function (sum, checkbox) { return sum + Number(checkbox.dataset.labour || 0); }, 0);
        const weight = selected.reduce(function (sum, checkbox) { return sum + Number(checkbox.dataset.net || 0); }, 0);
        const taxable = Math.max(0, labour + Number(other.value || 0));
        const option = gst.options[gst.selectedIndex];
        let components = [];
        try { components = option && option.dataset.components ? JSON.parse(option.dataset.components) : []; } catch (e) { components = []; }
        let gstAmount = 0;
        const labels = components.map(function (part) {
            const amount = taxable * Number(part.percentage || 0) / 100;
            gstAmount += amount;
            return part.name + ' ' + Number(part.percentage || 0).toFixed(3) + '% = ₹' + amount.toFixed(2);
        });
        const total = taxable + gstAmount + Number(roundOff.value || 0);
        document.getElementById('summary-count').textContent = String(selected.length);
        document.getElementById('summary-weight').textContent = weight.toFixed(3) + ' gm';
        document.getElementById('summary-labour').textContent = '₹' + labour.toFixed(2);
        document.getElementById('summary-taxable').textContent = '₹' + taxable.toFixed(2);
        document.getElementById('summary-gst').textContent = '₹' + gstAmount.toFixed(2);
        document.getElementById('summary-total').textContent = '₹' + total.toFixed(2);
        document.getElementById('tax-breakup').textContent = labels.length ? labels.join(' · ') : 'No GST component in this master.';
    }
    karigar.addEventListener('change', filterJobworks);
    if (search) search.addEventListener('input', filterJobworks);
    jobworks.addEventListener('change', function (event) {
        const checkbox = event.target instanceof Element ? event.target.closest('.jobwork-check') : null;
        if (checkbox) checkbox.closest('.jobwork-card')?.classList.toggle('is-selected', checkbox.checked);
        calculate();
    });
    [gst, other, roundOff].forEach(function (el) { el.addEventListener('input', calculate); el.addEventListener('change', calculate); });
    filterJobworks();
})();
</script>
<?= $this->endSection() ?>
