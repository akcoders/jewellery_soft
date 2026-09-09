<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$orderTypeValue = (string) old('order_type', (string) ($order['order_type'] ?? 'Sales'));
$selectedCustomerId = (string) old('customer_id', (string) ($order['customer_id'] ?? ''));
$selectedSalesPersonId = (string) old('sales_person_user_id', (string) ($order['sales_person_user_id'] ?? ''));
$selectedCategoryId = (string) old('order_category_id', (string) ($order['order_category_id'] ?? ''));
$selectedMaterialCategory = (string) old('material_category', (string) ($order['material_category'] ?? 'Gold'));
$selectedGoldRateStatus = (string) old('gold_rate_block_status', (string) ($order['gold_rate_block_status'] ?? 'Not Fixed'));
$showRepairFields = $orderTypeValue === 'Repair';
?>
<div class="erp-page-toolbar mb-3">
    <div>
        <span class="erp-eyebrow">Production workflow</span>
        <h4 class="mb-1">Edit Order: <?= esc($order['order_no']) ?></h4>
        <p class="mb-0">Update the customer, source and order requirements.</p>
    </div>
    <a href="<?= site_url((string) ($order['order_type'] ?? '') === 'Repair' ? 'admin/orders/repair' : 'admin/orders') ?>" class="btn btn-outline-primary"><i class="fe fe-arrow-left me-1"></i> Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= site_url('admin/orders/' . $order['id'] . '/update') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Order Name <span class="text-danger">*</span></label>
                    <input type="text" name="order_name" class="form-control" maxlength="180" value="<?= esc((string) old('order_name', (string) ($order['order_name'] ?? ''))) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Jewellery / Sub Category <span class="text-danger">*</span></label>
                    <select name="order_category_id" id="order-category-select" class="form-control js-searchable-select" data-placeholder="Search jewellery category" required>
                        <option value=""></option>
                        <?php foreach (($orderCategories ?? []) as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= $selectedCategoryId === (string) $category['id'] ? 'selected' : '' ?>><?= esc((string) $category['name']) ?> (<?= esc((string) $category['code']) ?>)</option>
                        <?php endforeach; ?>
                        <option value="0" <?= $selectedCategoryId === '0' ? 'selected' : '' ?>>+ Add New Category</option>
                    </select>
                    <input type="text" name="new_order_category" id="new-order-category" class="form-control mt-2" maxlength="100" value="<?= esc((string) old('new_order_category')) ?>" placeholder="Enter new jewellery category" style="display:none;">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Order Type</label>
                    <select name="order_type" id="order-type-select" class="form-control js-searchable-select" required>
                        <option value="Sales" <?= $orderTypeValue === 'Sales' ? 'selected' : '' ?>>Sales</option>
                        <option value="Manufacturing" <?= $orderTypeValue === 'Manufacturing' ? 'selected' : '' ?>>Manufacturing</option>
                        <option value="Repair" <?= $orderTypeValue === 'Repair' ? 'selected' : '' ?>>Repair</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Order From</label>
                    <input type="text" name="order_from" class="form-control" maxlength="150" value="<?= esc((string) old('order_from', (string) ($order['order_from'] ?? ''))) ?>" placeholder="Website, WhatsApp, showroom, reference">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" id="order-customer-select" class="form-control js-searchable-select" data-placeholder="Search customer">
                        <option value="">Select customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= esc((string) $customer['id']) ?>" data-phone="<?= esc((string) ($customer['phone'] ?? ''), 'attr') ?>" <?= $selectedCustomerId === (string) $customer['id'] ? 'selected' : '' ?>>
                                <?= esc($customer['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Sales Person <span class="text-muted">(Optional)</span></label>
                    <select name="sales_person_user_id" id="order-sales-person" class="form-control js-searchable-select" data-placeholder="Search salesperson">
                        <option value=""></option>
                        <?php foreach (($salesPeople ?? []) as $person): ?>
                            <option value="<?= (int) $person['id'] ?>" data-customer-id="<?= (int) $person['customer_id'] ?>" data-name="<?= esc((string) $person['name'], 'attr') ?>" data-mobile="<?= esc((string) ($person['mobile'] ?? ''), 'attr') ?>" <?= $selectedSalesPersonId === (string) $person['id'] ? 'selected' : '' ?>><?= esc($person['name'] . ' · ' . (($person['mobile'] ?? '') ?: 'No mobile')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="sales-person-detail" class="form-text"></div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-control js-searchable-select">
                        <?php foreach ($priorities as $priority): ?>
                            <option value="<?= esc($priority) ?>" <?= (string) old('priority', (string) ($order['priority'] ?? 'Medium')) === $priority ? 'selected' : '' ?>><?= esc($priority) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Order Received Date <span class="text-danger">*</span></label>
                    <input type="date" name="order_received_date" class="form-control" value="<?= esc((string) old('order_received_date', (string) (($order['order_received_date'] ?? '') ?: date('Y-m-d', strtotime((string) ($order['created_at'] ?? 'now')))))) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Contact Number</label>
                    <input type="tel" name="contact_number" id="order-contact-number" class="form-control" maxlength="40" value="<?= esc((string) old('contact_number', (string) ($order['contact_number'] ?? ''))) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Material Category <span class="text-danger">*</span></label>
                    <select name="material_category" class="form-control js-searchable-select" required>
                        <?php foreach (['Gold', 'Diamond', 'Jadau', 'Silver'] as $material): ?>
                            <option value="<?= esc($material, 'attr') ?>" <?= $selectedMaterialCategory === $material ? 'selected' : '' ?>><?= esc($material) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Client Delivery Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= esc((string) old('due_date', (string) ($order['due_date'] ?? ''))) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Certificate Requirement</label>
                    <select name="certificate_requirement" class="form-control js-searchable-select">
                        <?php foreach (['' => 'No certificate required', 'IGI' => 'IGI Certificate', 'Kalasha' => 'Kalasha Certificate', 'IGI / Kalasha' => 'IGI / Kalasha (either)', 'Other' => 'Other / Mention in details'] as $value => $label): ?>
                            <option value="<?= esc($value, 'attr') ?>" <?= (string) old('certificate_requirement', (string) ($order['certificate_requirement'] ?? '')) === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Gold Rate Block</label>
                    <select name="gold_rate_block_status" id="gold-rate-status" class="form-control js-searchable-select" required>
                        <option value="Not Fixed" <?= $selectedGoldRateStatus === 'Not Fixed' ? 'selected' : '' ?>>Not Fixed</option>
                        <option value="Fixed" <?= $selectedGoldRateStatus === 'Fixed' ? 'selected' : '' ?>>Fixed</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3" id="gold-rate-wrap" style="<?= $selectedGoldRateStatus === 'Fixed' ? '' : 'display:none;' ?>">
                    <label class="form-label">Fixed Gold Rate / gm</label>
                    <input type="number" name="gold_rate_per_gm" id="gold-rate-per-gm" class="form-control" min="0" step="0.01" value="<?= esc((string) old('gold_rate_per_gm', (string) ($order['gold_rate_per_gm'] ?? ''))) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Approximate Price</label>
                    <input type="number" name="approximate_price" class="form-control" min="0" step="0.01" value="<?= esc((string) old('approximate_price', (string) ($order['approximate_price'] ?? ''))) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Advance Amount</label>
                    <input type="number" name="advance_amount" class="form-control" min="0" step="0.01" value="<?= esc((string) old('advance_amount', (string) ($order['advance_amount'] ?? '0'))) ?>">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Order Notes</label>
                    <textarea name="order_notes" class="form-control" rows="3"><?= esc((string) old('order_notes', (string) ($order['order_notes'] ?? ''))) ?></textarea>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Additional Details / Finish Instructions</label>
                    <textarea name="additional_details" class="form-control" rows="3" maxlength="5000"><?= esc((string) old('additional_details', (string) ($order['additional_details'] ?? ''))) ?></textarea>
                </div>
            </div>

            <div id="repair-fields-wrap" class="border rounded p-3 mb-3" style="<?= $showRepairFields ? '' : 'display:none;' ?>">
                <div class="row">
                    <div class="col-12 mb-2">
                        <h6 class="mb-0">Repair Intake Details</h6>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Ornament Received Details</label>
                        <textarea name="repair_ornament_details" id="repair-ornament-details" class="form-control" rows="2"><?= esc((string) old('repair_ornament_details', (string) ($order['repair_ornament_details'] ?? ''))) ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Repair Work Details</label>
                        <textarea name="repair_work_details" id="repair-work-details" class="form-control" rows="2"><?= esc((string) old('repair_work_details', (string) ($order['repair_work_details'] ?? ''))) ?></textarea>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Receive Weight (gm)</label>
                        <input type="number" step="0.001" min="0" name="repair_receive_weight_gm" id="repair-receive-weight" class="form-control" value="<?= esc((string) old('repair_receive_weight_gm', (string) ($order['repair_receive_weight_gm'] ?? ''))) ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Received Date</label>
                        <input type="date" name="repair_received_at" id="repair-received-at" class="form-control" value="<?= esc((string) old('repair_received_at', (string) ($order['repair_received_at'] ?? ''))) ?>">
                    </div>
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Update Order</button>
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
        const customerSelect = document.getElementById('order-customer-select');
        const salesPersonSelect = document.getElementById('order-sales-person');
        const salesPersonDetail = document.getElementById('sales-person-detail');
        const categorySelect = document.getElementById('order-category-select');
        const newCategoryInput = document.getElementById('new-order-category');
        const contactNumber = document.getElementById('order-contact-number');
        const goldRateStatus = document.getElementById('gold-rate-status');
        const goldRateWrap = document.getElementById('gold-rate-wrap');
        const goldRateInput = document.getElementById('gold-rate-per-gm');
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

        function toggleNewCategory() {
            if (!categorySelect || !newCategoryInput) return;
            const adding = categorySelect.value === '0';
            newCategoryInput.style.display = adding ? '' : 'none';
            newCategoryInput.required = adding;
            if (!adding) newCategoryInput.value = '';
        }
        if (categorySelect) {
            if (window.jQuery) jQuery(categorySelect).on('change', toggleNewCategory);
            else categorySelect.addEventListener('change', toggleNewCategory);
            toggleNewCategory();
        }

        function toggleGoldRate() {
            if (!goldRateStatus || !goldRateWrap || !goldRateInput) return;
            const fixed = goldRateStatus.value === 'Fixed';
            goldRateWrap.style.display = fixed ? '' : 'none';
            goldRateInput.required = fixed;
            if (!fixed) goldRateInput.value = '';
        }

        function updateSalesPersonDetail() {
            if (!salesPersonSelect || !salesPersonDetail) return;
            const option = salesPersonSelect.options[salesPersonSelect.selectedIndex];
            salesPersonDetail.textContent = option && option.value
                ? (option.dataset.name || option.textContent.trim()) + ' · ' + (option.dataset.mobile || 'Mobile not available')
                : '';
        }

        function filterSalesPeople() {
            if (!customerSelect || !salesPersonSelect) return;
            const customerId = customerSelect.value;
            const selectedValue = salesPersonSelect.value;
            const customerOption = customerSelect.options[customerSelect.selectedIndex];
            if (contactNumber && customerOption && customerOption.value && contactNumber.value.trim() === '') {
                contactNumber.value = customerOption.dataset.phone || '';
            }
            Array.from(salesPersonSelect.options).forEach(function (option) {
                if (option.value) option.remove();
            });
            salesPersonOptions.forEach(function (option) {
                if (customerId && option.dataset.customerId === customerId) salesPersonSelect.appendChild(option.cloneNode(true));
            });
            salesPersonSelect.value = Array.from(salesPersonSelect.options).some(function (option) { return option.value === selectedValue; }) ? selectedValue : '';
            if (window.jQuery) jQuery(salesPersonSelect).trigger('change.select2');
            updateSalesPersonDetail();
        }

        if (customerSelect && window.jQuery) jQuery(customerSelect).on('change', filterSalesPeople);
        if (salesPersonSelect && window.jQuery) jQuery(salesPersonSelect).on('change', updateSalesPersonDetail);
        if (goldRateStatus) {
            if (window.jQuery) jQuery(goldRateStatus).on('change', toggleGoldRate);
            else goldRateStatus.addEventListener('change', toggleGoldRate);
        }
        filterSalesPeople();
        updateSalesPersonDetail();
        toggleGoldRate();
    })();
</script>
<?= $this->endSection() ?>
