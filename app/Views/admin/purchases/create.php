<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Create Purchase</h4>
    <a href="<?= site_url('admin/purchases') ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= site_url('admin/purchases') ?>">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Vendor</label>
                    <select name="vendor_id" class="form-control" required>
                        <option value="">Select Vendor</option>
                        <?php foreach ($vendors as $vendor): ?>
                            <option value="<?= esc((string) $vendor['id']) ?>"><?= esc($vendor['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Purchase Date</label>
                    <input type="date" name="purchase_date" class="form-control" value="<?= esc(date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Location</label>
                    <select name="location_id" class="form-control" required>
                        <option value="">Select Location</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= esc((string) $loc['id']) ?>"><?= esc($loc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control">
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="mb-0">Purchase Items</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-row">Add Row</button>
            </div>

            <div class="table-responsive mb-3">
                <table class="table datatable table-bordered" id="purchase-items" data-dt-searching="false" data-dt-ordering="false" data-dt-paging="false" data-dt-info="false">
                    <thead>
                        <tr>
                            <th>Item Type</th>
                            <th>Material</th>
                            <th>Gold Purity</th>
                            <th>Shape/Type</th>
                            <th>Size</th>
                            <th>Color</th>
                            <th>Quality</th>
                            <th>PCS</th>
                            <th>Weight (gm)</th>
                            <th>CTS</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <select name="item_type[]" class="form-control">
                                    <option value="Gold">Gold</option>
                                    <option value="Diamond">Diamond</option>
                                    <option value="Stone">Stone</option>
                                </select>
                            </td>
                            <td>
                                <select name="material_name[]" class="form-control">
                                    <option value="">Select Material</option>
                                    <?php foreach ($materialOptions as $opt): ?>
                                        <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="gold_purity_id[]" class="form-control">
                                    <option value="">-</option>
                                    <?php foreach ($goldPurities as $purity): ?>
                                        <option value="<?= esc((string) $purity['id']) ?>"><?= esc($purity['purity_code']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="diamond_shape[]" class="form-control">
                                    <option value="">-</option>
                                    <?php foreach ($shapeOptions as $opt): ?>
                                        <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="diamond_sieve[]" class="form-control">
                                    <option value="">-</option>
                                    <?php foreach ($sizeOptions as $opt): ?>
                                        <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="diamond_color[]" class="form-control">
                                    <option value="">-</option>
                                    <?php foreach ($colorOptions as $opt): ?>
                                        <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="diamond_clarity[]" class="form-control">
                                    <option value="">-</option>
                                    <?php foreach ($qualityOptions as $opt): ?>
                                        <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="pcs[]" class="form-control" min="0" value="0"></td>
                            <td><input type="number" name="weight_gm[]" class="form-control" min="0" step="0.001" value="0"></td>
                            <td><input type="number" name="cts[]" class="form-control" min="0" step="0.001" value="0"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">X</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <button class="btn btn-primary">Save Purchase</button>
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
            clone.querySelectorAll('input').forEach(function (el) { el.value = '0'; });
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
