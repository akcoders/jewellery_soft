<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= esc($formAction) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0">Sale Header</h5></div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Sale Date</label><input type="date" name="sale_date" class="form-control" value="<?= esc(old('sale_date', date('Y-m-d'))) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Showroom</label><select name="showroom_id" class="form-select select2" required><option value="">Select showroom</option><?php foreach (($showrooms ?? []) as $row): ?><option value="<?= (int) $row['id'] ?>" <?= old('showroom_id') == $row['id'] ? 'selected' : '' ?>><?= esc((string) $row['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Counter</label><select name="showroom_counter_id" class="form-select select2"><option value="">Any counter</option><?php foreach (($counters ?? []) as $row): ?><option value="<?= (int) $row['id'] ?>" <?= old('showroom_counter_id') == $row['id'] ? 'selected' : '' ?>><?= esc((string) ($row['showroom_name'] . ' / ' . $row['counter_name'])) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Customer</label><select name="customer_id" class="form-select select2" required><option value="">Select customer</option><?php foreach (($customers ?? []) as $row): ?><option value="<?= (int) $row['id'] ?>" <?= old('customer_id') == $row['id'] ? 'selected' : '' ?>><?= esc((string) ($row['name'] . ' / ' . ($row['phone'] ?? '-'))) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Salesperson</label><select name="salesperson_employee_id" class="form-select select2" required><option value="">Select salesperson</option><?php foreach (($salesEmployees ?? []) as $row): ?><option value="<?= (int) $row['id'] ?>" <?= old('salesperson_employee_id') == $row['id'] ? 'selected' : '' ?>><?= esc((string) ($row['full_name'] . ' / ' . ($row['designation_name'] ?? '-'))) ?></option><?php endforeach; ?></select></div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">GST %</label><input type="number" step="0.01" min="0" name="gst_percent" id="gst_percent" class="form-control" value="<?= esc(old('gst_percent', '3.00')) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Received Amount</label><input type="number" step="0.01" min="0" name="received_amount" id="received_amount" class="form-control" value="<?= esc(old('received_amount', '0.00')) ?>"></div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6"><label class="form-label">Payment Mode</label><input type="text" name="payment_mode" class="form-control" value="<?= esc(old('payment_mode', 'Cash')) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Reference No</label><input type="text" name="reference_no" class="form-control" value="<?= esc(old('reference_no')) ?>"></div>
                    </div>
                    <div class="mt-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="4"><?= esc(old('notes')) ?></textarea></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Summary</h5></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Taxable Amount</span><strong id="taxable_summary">0.00</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>GST Amount</span><strong id="gst_summary">0.00</strong></div>
                    <div class="d-flex justify-content-between mb-3"><span>Total Amount</span><strong id="total_summary">0.00</strong></div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fe fe-save"></i> Create Sale Bill</button>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Saleable FG Items</h5>
                    <input type="text" id="fgSearch" class="form-control w-auto" placeholder="Filter tag / showroom / customer">
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0" data-dt-skip="1" id="saleableFgTable">
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Tag</th>
                                    <th>Showroom</th>
                                    <th>Counter</th>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th>Reserved For</th>
                                    <th>Gross</th>
                                    <th>Net Gold</th>
                                    <th>Line Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($fgItems ?? []) as $row): ?>
                                    <tr>
                                        <td><input type="checkbox" name="fg_item_ids[]" value="<?= (int) $row['id'] ?>"></td>
                                        <td><?= esc((string) ($row['tag_no'] ?? '-')) ?></td>
                                        <td><?= esc((string) ($row['showroom_name'] ?? '-')) ?></td>
                                        <td><?= esc((string) ($row['counter_name'] ?? '-')) ?></td>
                                        <td><?= esc((string) ($row['order_no'] ?? '-')) ?></td>
                                        <td><span class="badge bg-info text-dark"><?= esc((string) ($row['showroom_stock_status'] ?? '-')) ?></span></td>
                                        <td><?= esc((string) ($row['reserved_customer_name'] ?? '-')) ?></td>
                                        <td><?= number_format((float) ($row['gross_wt'] ?? 0), 3) ?></td>
                                        <td><?= number_format((float) ($row['net_gold_wt'] ?? 0), 3) ?></td>
                                        <td><input type="number" step="0.01" min="0" name="line_rates[<?= (int) $row['id'] ?>]" class="form-control line-rate" value="<?= esc((string) old('line_rates.' . $row['id'], '0.00')) ?>"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const table = document.getElementById('saleableFgTable');
    const search = document.getElementById('fgSearch');
    const gstInput = document.getElementById('gst_percent');
    const taxableEl = document.getElementById('taxable_summary');
    const gstEl = document.getElementById('gst_summary');
    const totalEl = document.getElementById('total_summary');
    if (!table) return;

    function recalc() {
        let taxable = 0;
        table.querySelectorAll('tbody tr').forEach(function (row) {
            const checked = row.querySelector('input[type="checkbox"]');
            const rate = row.querySelector('.line-rate');
            if (checked && checked.checked && rate) {
                taxable += parseFloat(rate.value || '0') || 0;
            }
        });
        const gstPercent = parseFloat((gstInput && gstInput.value) || '0') || 0;
        const gst = taxable * gstPercent / 100;
        if (taxableEl) taxableEl.textContent = taxable.toFixed(2);
        if (gstEl) gstEl.textContent = gst.toFixed(2);
        if (totalEl) totalEl.textContent = (taxable + gst).toFixed(2);
    }

    table.addEventListener('input', recalc);
    table.addEventListener('change', recalc);
    if (gstInput) gstInput.addEventListener('input', recalc);
    recalc();

    if (search) {
        search.addEventListener('input', function () {
            const needle = search.value.toLowerCase().trim();
            table.querySelectorAll('tbody tr').forEach(function (row) {
                row.style.display = needle === '' || row.textContent.toLowerCase().indexOf(needle) !== -1 ? '' : 'none';
            });
        });
    }
})();
</script>
<?= $this->endSection() ?>
