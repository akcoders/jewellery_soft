<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$isRepairMode = (bool) ($repairMode ?? false);
$selectedOrderType = (string) old('order_type', $isRepairMode ? 'Repair' : 'Sales');
$showRepairFields = $selectedOrderType === 'Repair';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0"><?= esc($title ?? 'Create Order') ?></h4>
    <a href="<?= site_url($isRepairMode ? 'admin/orders/repair' : 'admin/orders') ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= site_url('admin/orders') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Order Type</label>
                    <select name="order_type" id="order-type-select" class="form-control" required>
                        <option value="Sales" <?= $selectedOrderType === 'Sales' ? 'selected' : '' ?>>Sales</option>
                        <option value="Manufacturing" <?= $selectedOrderType === 'Manufacturing' ? 'selected' : '' ?>>Manufacturing</option>
                        <option value="Repair" <?= $selectedOrderType === 'Repair' ? 'selected' : '' ?>>Repair</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-control">
                        <option value="">Select customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= esc((string) $customer['id']) ?>" <?= (string) old('customer_id') === (string) $customer['id'] ? 'selected' : '' ?>><?= esc($customer['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Lead</label>
                    <select name="lead_id" class="form-control">
                        <option value="">Select lead</option>
                        <?php foreach ($leads as $lead): ?>
                            <option value="<?= esc((string) $lead['id']) ?>" <?= (string) old('lead_id') === (string) $lead['id'] ? 'selected' : '' ?>><?= esc($lead['lead_no'] . ' - ' . $lead['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-control">
                        <?php foreach ($priorities as $priority): ?>
                            <option value="<?= esc($priority) ?>" <?= (string) old('priority', 'Medium') === (string) $priority ? 'selected' : '' ?>><?= esc($priority) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Current Status</label>
                    <select name="status" class="form-control">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= esc($status) ?>" <?= (string) old('status', 'Confirmed') === (string) $status ? 'selected' : '' ?>><?= esc($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= esc((string) old('due_date')) ?>">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Order Notes</label>
                    <textarea name="order_notes" class="form-control" rows="2"><?= esc((string) old('order_notes')) ?></textarea>
                </div>
            </div>

            <div id="repair-fields-wrap" class="border rounded p-3 mb-3" style="<?= $showRepairFields ? '' : 'display:none;' ?>">
                <div class="row">
                    <div class="col-12 mb-2">
                        <h6 class="mb-0">Repair Intake Details</h6>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Ornament Received Details</label>
                        <textarea name="repair_ornament_details" id="repair-ornament-details" class="form-control" rows="2" placeholder="Ex: Old ring, 22K, loose stone"><?= esc((string) old('repair_ornament_details')) ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Repair Work Details</label>
                        <textarea name="repair_work_details" id="repair-work-details" class="form-control" rows="2" placeholder="Ex: Resizing + setting tighten + polish"><?= esc((string) old('repair_work_details')) ?></textarea>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Receive Weight (gm)</label>
                        <input type="number" step="0.001" min="0" name="repair_receive_weight_gm" id="repair-receive-weight" class="form-control" value="<?= esc((string) old('repair_receive_weight_gm')) ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Received Date</label>
                        <input type="date" name="repair_received_at" id="repair-received-at" class="form-control" value="<?= esc((string) old('repair_received_at', date('Y-m-d'))) ?>">
                    </div>
                </div>
            </div>

            <hr>
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="mb-0">Order Items</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-item-row">Add Item Row</button>
            </div>
            <div class="table-responsive mb-3">
                <table class="table datatable table-bordered" id="items-table" data-dt-searching="false" data-dt-ordering="false" data-dt-paging="false" data-dt-info="false">
                    <thead>
                        <tr>
                            <th>Design</th>
                            <th>Gold Purity</th>
                            <th>Description</th>
                            <th>Size</th>
                            <th>Qty</th>
                            <th>Gold Req (gm)</th>
                            <th>Diamond Req (cts)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <select name="design_id[]" class="form-control">
                                    <option value="">Select design</option>
                                    <?php foreach ($designs as $design): ?>
                                        <option value="<?= esc((string) $design['id']) ?>"><?= esc($design['design_code'] . ' - ' . $design['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="gold_purity_id[]" class="form-control">
                                    <option value="">Select purity</option>
                                    <?php foreach ($goldPurities as $purity): ?>
                                        <option value="<?= esc((string) $purity['id']) ?>">
                                            <?= esc($purity['purity_code'] . ' (' . $purity['purity_percent'] . '%) ' . ($purity['color_name'] ? '- ' . $purity['color_name'] : '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="item_description[]" class="form-control"></td>
                            <td><input type="text" name="size_label[]" class="form-control"></td>
                            <td><input type="number" name="qty[]" class="form-control" min="1" value="1"></td>
                            <td><input type="number" name="gold_required_gm[]" class="form-control" step="0.001" min="0" value="0"></td>
                            <td><input type="number" name="diamond_required_cts[]" class="form-control" step="0.001" min="0" value="0"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">X</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <hr>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Attachments (CAD/photo/approval)</label>
                    <input type="file" name="order_files[]" class="form-control" multiple>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Attachment Type</label>
                    <select name="file_type" class="form-control">
                        <option value="reference" <?= old('file_type') === 'reference' ? 'selected' : '' ?>>Reference</option>
                        <option value="cad" <?= old('file_type') === 'cad' ? 'selected' : '' ?>>CAD</option>
                        <option value="photo" <?= old('file_type') === 'photo' ? 'selected' : '' ?>>Photo</option>
                        <option value="approval" <?= old('file_type') === 'approval' ? 'selected' : '' ?>>Approval</option>
                    </select>
                </div>
            </div>

            <button class="btn btn-primary" type="submit">Save Order</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        const orderTypeSelect = document.getElementById('order-type-select');
        const repairWrap = document.getElementById('repair-fields-wrap');
        const repairOrnament = document.getElementById('repair-ornament-details');
        const repairWork = document.getElementById('repair-work-details');
        const repairWeight = document.getElementById('repair-receive-weight');
        const repairDate = document.getElementById('repair-received-at');

        function toggleRepairFields() {
            if (!orderTypeSelect || !repairWrap) return;
            const isRepair = orderTypeSelect.value === 'Repair';
            repairWrap.style.display = isRepair ? '' : 'none';

            if (repairOrnament) repairOrnament.required = isRepair;
            if (repairWork) repairWork.required = isRepair;
            if (repairWeight) repairWeight.required = isRepair;
            if (repairDate) repairDate.required = isRepair;
        }

        if (orderTypeSelect) {
            orderTypeSelect.addEventListener('change', toggleRepairFields);
            toggleRepairFields();
        }

        const addBtn = document.getElementById('add-item-row');
        const tableBody = document.querySelector('#items-table tbody');
        const hasDt = typeof jQuery !== 'undefined' && typeof jQuery.fn.DataTable !== 'undefined' && jQuery.fn.DataTable.isDataTable('#items-table');
        const dt = hasDt ? jQuery('#items-table').DataTable() : null;
        if (!addBtn || !tableBody) return;

        addBtn.addEventListener('click', function () {
            const firstRow = tableBody.querySelector('tr');
            if (!firstRow) return;
            const clone = firstRow.cloneNode(true);
            clone.querySelectorAll('input').forEach(function (input) {
                if (input.name === 'qty[]') input.value = '1';
                else if (input.name === 'gold_required_gm[]' || input.name === 'diamond_required_cts[]') input.value = '0';
                else input.value = '';
            });
            clone.querySelectorAll('select').forEach(function (select) {
                select.selectedIndex = 0;
            });

            if (dt) {
                dt.row.add(clone).draw(false);
            } else {
                tableBody.appendChild(clone);
            }
        });

        tableBody.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLElement) || !target.classList.contains('remove-row')) return;
            const row = target.closest('tr');
            if (!row) return;

            const rowCount = dt ? dt.rows().count() : tableBody.querySelectorAll('tr').length;
            if (rowCount <= 1) return;

            if (dt) {
                dt.row(row).remove().draw(false);
            } else {
                row.remove();
            }
        });
    })();
</script>
<?= $this->endSection() ?>

