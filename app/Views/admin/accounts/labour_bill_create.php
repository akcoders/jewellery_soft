<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
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
                    <label class="form-label">Completed Job Works <span class="text-danger">*</span></label>
                    <select name="jobworks[]" id="jobworks" class="form-select select2" multiple required>
                        <?php foreach ($jobworks as $job): ?>
                            <option value="<?= esc((string) $job['selector']) ?>"
                                data-karigar="<?= (int) $job['karigar_id'] ?>"
                                data-labour="<?= esc((string) $job['labour_amount']) ?>"
                                data-net="<?= esc((string) $job['net_weight_gm']) ?>"
                                <?= in_array((string) $job['selector'], (array) old('jobworks'), true) ? 'selected' : '' ?>>
                                <?= esc((string) $job['jobwork_date']) ?> · <?= esc((string) $job['description']) ?> · ₹<?= number_format((float) $job['labour_amount'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Only unbilled job works of the selected karigar are shown.</small>
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
    const gst = document.getElementById('gst_master_id');
    const other = document.getElementById('other_amount');
    const roundOff = document.getElementById('round_off_amount');
    if (!karigar || !jobworks || !gst) return;
    const allOptions = Array.from(jobworks.options).map(function (option) { return option.cloneNode(true); });

    function filterJobworks() {
        const selected = new Set(Array.from(jobworks.selectedOptions).map(function (option) { return option.value; }));
        const id = karigar.value;
        jobworks.innerHTML = '';
        allOptions.forEach(function (source) {
            if (id && source.dataset.karigar === id) {
                const option = source.cloneNode(true);
                option.selected = selected.has(option.value) || source.selected;
                jobworks.appendChild(option);
            }
        });
        if (window.jQuery && jQuery.fn.select2) jQuery(jobworks).trigger('change.select2');
        calculate();
    }

    function calculate() {
        const selected = Array.from(jobworks.selectedOptions);
        const labour = selected.reduce(function (sum, option) { return sum + Number(option.dataset.labour || 0); }, 0);
        const weight = selected.reduce(function (sum, option) { return sum + Number(option.dataset.net || 0); }, 0);
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
    jobworks.addEventListener('change', calculate);
    [gst, other, roundOff].forEach(function (el) { el.addEventListener('input', calculate); el.addEventListener('change', calculate); });
    filterJobworks();
})();
</script>
<?= $this->endSection() ?>
