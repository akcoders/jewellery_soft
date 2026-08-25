<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$isRepairMode = (bool) ($repairMode ?? false);
$selectedOrderType = (string) old('order_type', $isRepairMode ? 'Repair' : 'Sales');
$selectedDesignType = (string) old('order_design_type', 'Fresh');
$showRepairFields = $selectedOrderType === 'Repair';
?>
<style>
    .order-create-card { overflow: visible; }
    .order-person-card { align-items: center; background: #f8f9fb; border: 1px solid #e4e8ef; border-radius: 10px; display: flex; gap: 9px; margin-top: 8px; padding: 9px 10px; }
    .order-person-card > i { align-items: center; background: #edf1f6; border-radius: 8px; color: #536176; display: inline-flex; flex: 0 0 32px; height: 32px; justify-content: center; }
    .order-person-card strong, .order-person-card small { display: block; }
    .order-person-card strong { font-size: 11px; }
    .order-person-card small { color: var(--erp-muted); font-size: 9px; margin-top: 2px; }
</style>
<div class="erp-page-toolbar mb-3">
    <div>
        <span class="erp-eyebrow">Production workflow</span>
        <h4 class="mb-1"><?= esc($title ?? 'Create Order') ?></h4>
        <p class="mb-0">Capture customer, product and manufacturing requirements.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('order-request') ?>" target="_blank" class="btn btn-outline-success">Public Order Link</a>
        <a href="<?= site_url($isRepairMode ? 'admin/orders/repair' : 'admin/orders') ?>" class="btn btn-outline-primary">Back</a>
    </div>
</div>

<div class="card order-create-card">
    <div class="card-body">
        <form action="<?= site_url('admin/orders') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Order Type</label>
                    <select name="order_type" id="order-type-select" class="form-control js-searchable-select" required>
                        <option value="Sales" <?= $selectedOrderType === 'Sales' ? 'selected' : '' ?>>Sales</option>
                        <option value="Manufacturing" <?= $selectedOrderType === 'Manufacturing' ? 'selected' : '' ?>>Manufacturing</option>
                        <option value="Repair" <?= $selectedOrderType === 'Repair' ? 'selected' : '' ?>>Repair</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Fresh / Repeat</label>
                    <select name="order_design_type" id="order-design-type" class="form-control js-searchable-select" required>
                        <option value="Fresh" <?= $selectedDesignType === 'Fresh' ? 'selected' : '' ?>>Fresh Order</option>
                        <option value="Repeat" <?= $selectedDesignType === 'Repeat' ? 'selected' : '' ?>>Repeat Existing Design</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Order From</label>
                    <input type="text" name="order_from" class="form-control" maxlength="150" value="<?= esc((string) old('order_from')) ?>" placeholder="Website, WhatsApp, showroom, reference">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" id="order-customer-select" class="form-control js-searchable-select" data-placeholder="Search customer">
                        <option value="">Select customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= esc((string) $customer['id']) ?>" <?= (string) old('customer_id') === (string) $customer['id'] ? 'selected' : '' ?>><?= esc($customer['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Sales Person</label>
                    <select name="sales_person_user_id" id="order-sales-person" class="form-control js-searchable-select" data-placeholder="Search sales person">
                        <option value=""></option>
                        <?php foreach (($salesPeople ?? []) as $person): ?>
                            <option value="<?= (int) $person['id'] ?>" data-customer-id="<?= (int) $person['customer_id'] ?>" data-name="<?= esc((string) $person['name'], 'attr') ?>" data-mobile="<?= esc((string) ($person['mobile'] ?? ''), 'attr') ?>" <?= (string) old('sales_person_user_id') === (string) $person['id'] ? 'selected' : '' ?>><?= esc($person['name'] . ' · ' . (($person['mobile'] ?? '') ?: 'No mobile')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="sales-person-summary" class="order-person-card d-none"><i class="fe fe-user-check"></i><span><strong id="sales-person-name"></strong><small id="sales-person-mobile"></small></span></div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-control js-searchable-select">
                        <?php foreach ($priorities as $priority): ?>
                            <option value="<?= esc($priority) ?>" <?= (string) old('priority', 'Medium') === (string) $priority ? 'selected' : '' ?>><?= esc($priority) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Assign Karigar</label>
                    <select name="assigned_karigar_id" class="form-control js-searchable-select">
                        <option value="">Assign later</option>
                        <?php foreach (($karigars ?? []) as $karigar): ?>
                            <option value="<?= (int) $karigar['id'] ?>" <?= (string) old('assigned_karigar_id') === (string) $karigar['id'] ? 'selected' : '' ?>><?= esc((string) $karigar['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Current Status</label>
                    <select name="status" class="form-control js-searchable-select">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= esc($status) ?>" <?= (string) old('status', 'Confirmed') === (string) $status ? 'selected' : '' ?>><?= esc($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= esc((string) old('due_date')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Priority Level</label>
                    <input type="number" name="priority_level" min="0" max="10" class="form-control" value="<?= esc((string) old('priority_level', '0')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">WhatsApp Notification No</label>
                    <input type="tel" name="whatsapp_notification_number" class="form-control" value="<?= esc((string) old('whatsapp_notification_number')) ?>" placeholder="91XXXXXXXXXX">
                </div>
                <div class="col-md-3 mb-3 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="checkbox" name="whatsapp_notify_order_created" value="1" id="whatsapp-notify-order-created" class="form-check-input" <?= old('whatsapp_notify_order_created', '1') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="whatsapp-notify-order-created">Queue WhatsApp on save</label>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Expected Diamond Details</label>
                    <textarea name="expected_diamond_spec" class="form-control" rows="2" placeholder="Shape, color, clarity, pcs, size"><?= esc((string) old('expected_diamond_spec')) ?></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Expected Stone / Other Details</label>
                    <textarea name="expected_stone_spec" class="form-control" rows="2" placeholder="Ruby, emerald, CZ, enamel, plating"><?= esc((string) old('expected_stone_spec')) ?></textarea>
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
                <div><h6 class="mb-0">Order Items</h6><small class="text-muted">Unique design code is requested only when Repeat Order is selected.</small></div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-item-row">Add Item Row</button>
            </div>
            <div class="table-responsive mb-3">
                <table class="table table-bordered" id="items-table" data-dt-skip="true">
                    <thead>
                        <tr>
                            <th class="js-design-column">Unique Design Code</th>
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
                            <td class="js-design-column">
                                <select name="design_id[]" class="form-control js-item-searchable js-design-select">
                                    <option value="">Select design</option>
                                    <?php foreach ($designs as $design): ?>
                                        <option value="<?= esc((string) $design['id']) ?>"><?= esc($design['design_code'] . ' - ' . $design['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="gold_purity_id[]" class="form-control js-item-searchable">
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
                    <select name="file_type" class="form-control js-searchable-select">
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
        const designType = document.getElementById('order-design-type');
        const customerSelect = document.getElementById('order-customer-select');
        const salesPersonSelect = document.getElementById('order-sales-person');
        const salesSummary = document.getElementById('sales-person-summary');
        const salesName = document.getElementById('sales-person-name');
        const salesMobile = document.getElementById('sales-person-mobile');
        const salesPersonOptions = salesPersonSelect
            ? Array.from(salesPersonSelect.options).filter(function (option) { return option.value; }).map(function (option) { return option.cloneNode(true); })
            : [];

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
        const rowTemplate = tableBody && tableBody.querySelector('tr') ? tableBody.querySelector('tr').cloneNode(true) : null;
        const hasDt = typeof jQuery !== 'undefined' && typeof jQuery.fn.DataTable !== 'undefined' && jQuery.fn.DataTable.isDataTable('#items-table');
        const dt = hasDt ? jQuery('#items-table').DataTable() : null;
        if (!addBtn || !tableBody) return;

        function initItemSearch(selects) {
            if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
            jQuery(selects).each(function () {
                if (!jQuery(this).hasClass('select2-hidden-accessible')) {
                    jQuery(this).select2({width: '100%', allowClear: true, placeholder: 'Search and select'});
                }
            });
        }

        function toggleDesignSelection() {
            const repeat = designType && designType.value === 'Repeat';
            document.querySelectorAll('.js-design-column').forEach(function (column) {
                column.style.display = repeat ? '' : 'none';
            });
            document.querySelectorAll('.js-design-select').forEach(function (select) {
                select.required = repeat;
                select.disabled = !repeat;
                if (!repeat) {
                    select.value = '';
                    if (typeof jQuery !== 'undefined') jQuery(select).trigger('change');
                }
            });
        }

        function filterSalesPeople() {
            if (!salesPersonSelect || !customerSelect) return;
            const customerId = customerSelect.value;
            const selectedValue = salesPersonSelect.value;
            Array.from(salesPersonSelect.options).forEach(function (option) {
                if (option.value) option.remove();
            });
            salesPersonOptions.forEach(function (option) {
                if (customerId && option.dataset.customerId === customerId) salesPersonSelect.appendChild(option.cloneNode(true));
            });
            salesPersonSelect.value = Array.from(salesPersonSelect.options).some(function (option) { return option.value === selectedValue; }) ? selectedValue : '';
            if (typeof jQuery !== 'undefined') jQuery(salesPersonSelect).trigger('change.select2');
            updateSalesPersonSummary();
        }

        function updateSalesPersonSummary() {
            if (!salesPersonSelect || !salesSummary) return;
            const option = salesPersonSelect.options[salesPersonSelect.selectedIndex];
            if (!option || !option.value) {
                salesSummary.classList.add('d-none');
                return;
            }
            if (salesName) salesName.textContent = option.dataset.name || option.textContent.trim();
            if (salesMobile) salesMobile.textContent = option.dataset.mobile || 'Mobile not available';
            salesSummary.classList.remove('d-none');
        }

        if (designType) {
            if (typeof jQuery !== 'undefined') jQuery(designType).on('change', toggleDesignSelection);
            else designType.addEventListener('change', toggleDesignSelection);
        }
        if (customerSelect) {
            if (typeof jQuery !== 'undefined') jQuery(customerSelect).on('change', filterSalesPeople);
            else customerSelect.addEventListener('change', filterSalesPeople);
        }
        if (salesPersonSelect && typeof jQuery !== 'undefined') {
            jQuery(salesPersonSelect).on('change', updateSalesPersonSummary);
        }
        initItemSearch(document.querySelectorAll('.js-item-searchable'));
        filterSalesPeople();
        toggleDesignSelection();
        updateSalesPersonSummary();

        addBtn.addEventListener('click', function () {
            if (!rowTemplate) return;
            const clone = rowTemplate.cloneNode(true);
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
            initItemSearch(clone.querySelectorAll('.js-item-searchable'));
            toggleDesignSelection();
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
