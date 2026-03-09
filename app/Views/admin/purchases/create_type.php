<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$type = (string) $purchaseType;
$isGold = $type === 'Gold';
$isDiamond = $type === 'Diamond';
$postUrl = $isGold
    ? site_url('admin/purchases/gold')
    : ($isDiamond ? site_url('admin/purchases/diamond') : site_url('admin/purchases/stone'));
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Create <?= esc($type) ?> Purchase</h4>
    <a href="<?= site_url('admin/purchases') ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= $postUrl ?>">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Vendor</label>
                    <select name="vendor_id" class="form-control" required>
                        <option value="">Select Vendor</option>
                        <?php foreach ($vendors as $vendor): ?>
                            <option value="<?= esc((string) $vendor['id']) ?>" <?= old('vendor_id') == $vendor['id'] ? 'selected' : '' ?>><?= esc($vendor['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Purchase Date</label>
                    <input type="date" name="purchase_date" class="form-control" value="<?= esc(old('purchase_date') ?: date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Warehouse Location</label>
                    <select name="location_id" class="form-control" required>
                        <option value="">Select Location</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= esc((string) $loc['id']) ?>" <?= old('location_id') == $loc['id'] ? 'selected' : '' ?>><?= esc($loc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Invoice No</label>
                    <input type="text" name="invoice_no" class="form-control" value="<?= esc(old('invoice_no')) ?>" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Invoice Amount</label>
                    <input type="number" name="invoice_amount" class="form-control" min="0.01" step="0.01" value="<?= esc(old('invoice_amount') ?: '0.00') ?>" required>
                </div>
                <?php if ($isGold): ?>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Payment Status</label>
                        <input type="text" class="form-control" value="Paid (Auto for Gold)" readonly>
                    </div>
                <?php else: ?>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Payment Due Date</label>
                        <input type="date" name="payment_due_date" class="form-control" value="<?= esc(old('payment_due_date')) ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Payment Status</label>
                        <input type="text" class="form-control" value="Pending" readonly>
                    </div>
                <?php endif; ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= esc(old('notes')) ?>">
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="mb-0"><?= esc($type) ?> Products</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-row">Add Row</button>
            </div>

            <div class="table-responsive mb-3">
                <table class="table datatable table-bordered" id="purchase-items" data-dt-searching="false" data-dt-ordering="false" data-dt-paging="false" data-dt-info="false">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <?php if ($isGold): ?>
                                <th>Purity</th>
                                <th>Weight (gm)</th>
                            <?php elseif ($isDiamond): ?>
                                <th>Diamond Type</th>
                                <th>Size</th>
                                <th>Color</th>
                                <th>Quality</th>
                                <th>PCS</th>
                                <th>Weight (cts)</th>
                            <?php else: ?>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Color</th>
                                <th>Quality</th>
                                <th>PCS</th>
                                <th>Weight (cts)</th>
                            <?php endif; ?>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <select name="material_name[]" class="form-control">
                                    <option value="">Select Product</option>
                                    <?php foreach ($materialOptions as $opt): ?>
                                        <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <?php if ($isGold): ?>
                                <td>
                                    <select name="gold_purity_id[]" class="form-control">
                                        <option value="">Select Purity</option>
                                        <?php foreach ($goldPurities as $purity): ?>
                                            <option value="<?= esc((string) $purity['id']) ?>"><?= esc($purity['purity_code']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" name="weight_gm[]" class="form-control" min="0.001" step="0.001" value="0.001"></td>
                                <input type="hidden" name="diamond_shape[]" value="">
                                <input type="hidden" name="diamond_sieve[]" value="">
                                <input type="hidden" name="diamond_color[]" value="">
                                <input type="hidden" name="diamond_clarity[]" value="">
                                <input type="hidden" name="pcs[]" value="0">
                                <input type="hidden" name="cts[]" value="0">
                            <?php elseif ($isDiamond): ?>
                                <td>
                                    <select name="diamond_shape[]" class="form-control">
                                        <option value="">Select Type</option>
                                        <?php foreach ($shapeOptions as $opt): ?>
                                            <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="diamond_sieve[]" class="form-control">
                                        <option value="">Select Size</option>
                                        <?php foreach ($sizeOptions as $opt): ?>
                                            <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="diamond_color[]" class="form-control">
                                        <option value="">Select Color</option>
                                        <?php foreach ($colorOptions as $opt): ?>
                                            <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="diamond_clarity[]" class="form-control">
                                        <option value="">Select Quality</option>
                                        <?php foreach ($qualityOptions as $opt): ?>
                                            <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" name="pcs[]" class="form-control" min="0" step="1" value="0"></td>
                                <td><input type="number" name="cts[]" class="form-control" min="0.001" step="0.001" value="0.001"></td>
                                <input type="hidden" name="gold_purity_id[]" value="">
                                <input type="hidden" name="weight_gm[]" value="0">
                            <?php else: ?>
                                <td>
                                    <select name="diamond_shape[]" class="form-control">
                                        <option value="">Select Type</option>
                                        <?php foreach ($shapeOptions as $opt): ?>
                                            <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="diamond_sieve[]" class="form-control">
                                        <option value="">Select Size</option>
                                        <?php foreach ($sizeOptions as $opt): ?>
                                            <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="diamond_color[]" class="form-control">
                                        <option value="">Select Color</option>
                                        <?php foreach ($colorOptions as $opt): ?>
                                            <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="diamond_clarity[]" class="form-control">
                                        <option value="">Select Quality</option>
                                        <?php foreach ($qualityOptions as $opt): ?>
                                            <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" name="pcs[]" class="form-control" min="0" step="1" value="0"></td>
                                <td><input type="number" name="cts[]" class="form-control" min="0.001" step="0.001" value="0.001"></td>
                                <input type="hidden" name="gold_purity_id[]" value="">
                                <input type="hidden" name="weight_gm[]" value="0">
                            <?php endif; ?>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">X</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <button class="btn btn-primary">Save <?= esc($type) ?> Purchase</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        const tableBody = document.querySelector('#purchase-items tbody');
        const addBtn = document.getElementById('add-row');
        const hasDt = typeof jQuery !== 'undefined' && typeof jQuery.fn.DataTable !== 'undefined' && jQuery.fn.DataTable.isDataTable('#purchase-items');
        const dt = hasDt ? jQuery('#purchase-items').DataTable() : null;
        if (!tableBody || !addBtn) return;

        addBtn.addEventListener('click', function () {
            const first = tableBody.querySelector('tr');
            if (!first) return;
            const clone = first.cloneNode(true);
            clone.querySelectorAll('input').forEach(function (el) {
                if (el.type === 'hidden') return;
                if (el.name === 'weight_gm[]' || el.name === 'cts[]') el.value = '0.001';
                else el.value = '0';
            });
            clone.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
            if (dt) dt.row.add(clone).draw(false);
            else tableBody.appendChild(clone);
        });

        tableBody.addEventListener('click', function (event) {
            const t = event.target;
            if (!(t instanceof HTMLElement) || !t.classList.contains('remove-row')) return;
            const tr = t.closest('tr');
            if (!tr) return;
            const count = dt ? dt.rows().count() : tableBody.querySelectorAll('tr').length;
            if (count <= 1) return;
            if (dt) dt.row(tr).remove().draw(false);
            else tr.remove();
        });
    })();
</script>
<?= $this->endSection() ?>
