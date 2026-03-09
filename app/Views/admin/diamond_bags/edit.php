<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Edit Diamond Bag: <?= esc($bag['bag_no']) ?></h4>
    <a href="<?= site_url('admin/diamond-bags/' . $bag['id']) ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= site_url('admin/diamond-bags/' . $bag['id'] . '/update') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Order Reference <span class="text-danger">*</span></label>
                    <select name="order_id" class="form-control" required>
                        <option value="">Select order</option>
                        <?php $selectedOrderId = old('order_id') ?: $bag['order_id']; ?>
                        <?php foreach ($orders as $order): ?>
                            <option value="<?= esc((string) $order['id']) ?>" <?= (string) $selectedOrderId === (string) $order['id'] ? 'selected' : '' ?>>
                                <?= esc($order['order_no']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Inventory Location <span class="text-danger">*</span></label>
                    <select name="location_id" class="form-control" required>
                        <option value="">Select location</option>
                        <?php $selectedLocationId = old('location_id') ?: ($selectedLocationId ?? ''); ?>
                        <?php foreach (($locations ?? []) as $location): ?>
                            <option value="<?= esc((string) $location['id']) ?>" <?= (string) $selectedLocationId === (string) $location['id'] ? 'selected' : '' ?>>
                                <?= esc((string) $location['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="<?= esc(old('notes') ?: ($bag['notes'] ?? '')) ?>">
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="mb-0">Bag Rows</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-bag-row">Add Row</button>
            </div>

            <div class="table-responsive mb-3">
                <table class="table datatable table-bordered" id="bag-items-table" data-dt-searching="false" data-dt-ordering="false" data-dt-paging="false" data-dt-info="false">
                    <thead>
                        <tr>
                            <th>Diamond Type</th>
                            <th>Size</th>
                            <th>Color</th>
                            <th>Quality</th>
                            <th>PCS</th>
                            <th>Weight (cts)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rows = $items === [] ? [[
                            'diamond_type' => '',
                            'size' => '',
                            'color' => '',
                            'quality' => '',
                            'pcs_total' => 1,
                            'weight_cts_total' => 0.001,
                        ]] : $items; ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <select name="diamond_type[]" class="form-control">
                                        <option value="">Select Diamond Type</option>
                                        <?php foreach ($diamondTypeOptions as $opt): ?>
                                            <option value="<?= esc($opt) ?>" <?= (string) ($row['diamond_type'] ?? '') === (string) $opt ? 'selected' : '' ?>><?= esc($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="size[]" class="form-control">
                                        <option value="">Select Size</option>
                                        <?php foreach ($sizeOptions as $opt): ?>
                                            <option value="<?= esc($opt) ?>" <?= (string) ($row['size'] ?? '') === (string) $opt ? 'selected' : '' ?>><?= esc($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="color[]" class="form-control">
                                        <option value="">Select Color</option>
                                        <?php foreach ($colorOptions as $opt): ?>
                                            <option value="<?= esc($opt) ?>" <?= (string) ($row['color'] ?? '') === (string) $opt ? 'selected' : '' ?>><?= esc($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="quality[]" class="form-control">
                                        <option value="">Select Quality</option>
                                        <?php foreach ($qualityOptions as $opt): ?>
                                            <option value="<?= esc($opt) ?>" <?= (string) ($row['quality'] ?? '') === (string) $opt ? 'selected' : '' ?>><?= esc($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" name="pcs[]" class="form-control" min="1" value="<?= esc((string) ($row['pcs_total'] ?? 1)) ?>"></td>
                                <td><input type="number" name="weight_cts[]" class="form-control" step="0.001" min="0.001" value="<?= esc((string) ($row['weight_cts_total'] ?? 0.001)) ?>"></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">X</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button class="btn btn-primary" type="submit">Update Bag</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        const addBtn = document.getElementById('add-bag-row');
        const tableBody = document.querySelector('#bag-items-table tbody');
        const hasDt = typeof jQuery !== 'undefined' && typeof jQuery.fn.DataTable !== 'undefined' && jQuery.fn.DataTable.isDataTable('#bag-items-table');
        const dt = hasDt ? jQuery('#bag-items-table').DataTable() : null;
        if (!addBtn || !tableBody) return;

        addBtn.addEventListener('click', function () {
            const firstRow = tableBody.querySelector('tr');
            if (!firstRow) return;
            const clone = firstRow.cloneNode(true);
            clone.querySelectorAll('input').forEach(function (input) {
                if (input.name === 'pcs[]') input.value = '1';
                else if (input.name === 'weight_cts[]') input.value = '0.001';
                else input.value = '';
            });
            clone.querySelectorAll('select').forEach(function (select) {
                select.value = '';
            });

            if (dt) dt.row.add(clone).draw(false);
            else tableBody.appendChild(clone);
        });

        tableBody.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLElement) || !target.classList.contains('remove-row')) return;
            const row = target.closest('tr');
            if (!row) return;
            const rowCount = dt ? dt.rows().count() : tableBody.querySelectorAll('tr').length;
            if (rowCount <= 1) return;

            if (dt) dt.row(row).remove().draw(false);
            else row.remove();
        });
    })();
</script>
<?= $this->endSection() ?>
