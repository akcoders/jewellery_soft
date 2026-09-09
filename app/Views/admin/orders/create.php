<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$isRepairMode = (bool) ($repairMode ?? false);
$selectedOrderType = (string) old('order_type', $isRepairMode ? 'Repair' : 'Sales');
$selectedDesignType = (string) old('order_design_type', 'Fresh');
$selectedCategoryId = (string) old('order_category_id');
$selectedMaterialCategory = (string) old('material_category', 'Gold');
$selectedGoldRateStatus = (string) old('gold_rate_block_status', 'Not Fixed');
$showRepairFields = $selectedOrderType === 'Repair';
?>
<style>
    .order-create-card { border: 0; box-shadow: 0 12px 35px rgba(31, 40, 58, .07); overflow: visible; }
    .order-form-section { border-bottom: 1px solid #e9edf3; margin-bottom: 22px; padding-bottom: 8px; }
    .order-form-section:last-of-type { border-bottom: 0; }
    .order-form-heading { align-items: center; display: flex; gap: 11px; margin-bottom: 18px; }
    .order-form-heading > i { align-items: center; background: #fff4df; border-radius: 11px; color: #a87308; display: inline-flex; flex: 0 0 42px; height: 42px; justify-content: center; }
    .order-form-heading h5 { font-size: 15px; font-weight: 750; margin: 0 0 2px; }
    .order-form-heading p { color: var(--erp-muted); font-size: 11px; margin: 0; }
    .material-category-grid { display: grid; gap: 10px; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .material-category-option { position: relative; }
    .material-category-option input { opacity: 0; position: absolute; }
    .material-category-option label { align-items: center; background: #fff; border: 1px solid #dfe4eb; border-radius: 12px; cursor: pointer; display: flex; font-size: 12px; font-weight: 700; gap: 8px; justify-content: center; margin: 0; min-height: 48px; padding: 10px; transition: .18s ease; }
    .material-category-option input:checked + label { background: #fff6e9; border-color: #c58a1a; box-shadow: 0 0 0 3px rgba(197, 138, 26, .1); color: #7f5400; }
    .order-commercial-summary { background: linear-gradient(135deg, #fffaf0, #fff); border: 1px solid #eadfc9; border-radius: 14px; padding: 16px; }
    .order-balance-preview { color: #7b8492; font-size: 11px; margin-top: 7px; }
    .order-balance-preview strong { color: #1c2534; font-size: 13px; }
    .order-upload-zone { background: #fafbfc; border: 1px dashed #cbd2dc; border-radius: 14px; padding: 16px; }
    .order-image-preview { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(92px, 1fr)); margin-top: 12px; }
    .order-image-preview figure { background: #fff; border: 1px solid #e1e5eb; border-radius: 10px; margin: 0; overflow: hidden; }
    .order-image-preview img { aspect-ratio: 1; display: block; object-fit: cover; width: 100%; }
    .order-image-preview figcaption { color: #687386; font-size: 9px; overflow: hidden; padding: 6px 7px; text-overflow: ellipsis; white-space: nowrap; }
    .order-person-card { align-items: center; background: #f8f9fb; border: 1px solid #e4e8ef; border-radius: 10px; display: flex; gap: 9px; margin-top: 8px; padding: 9px 10px; }
    .order-person-card > i { align-items: center; background: #edf1f6; border-radius: 8px; color: #536176; display: inline-flex; flex: 0 0 32px; height: 32px; justify-content: center; }
    .order-person-card strong, .order-person-card small { display: block; }
    .order-person-card strong { font-size: 11px; }
    .order-person-card small { color: var(--erp-muted); font-size: 9px; margin-top: 2px; }
    @media (max-width: 767px) { .material-category-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
<div class="erp-page-toolbar mb-3">
    <div>
        <span class="erp-eyebrow">Production workflow</span>
        <h4 class="mb-1"><?= esc($title ?? 'Create Order') ?></h4>
        <p class="mb-0">Capture customer, product and manufacturing requirements.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url($isRepairMode ? 'admin/orders/repair' : 'admin/orders') ?>" class="btn btn-outline-primary">Back</a>
    </div>
</div>

<div class="card order-create-card">
    <div class="card-body">
        <form action="<?= site_url('admin/orders') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="order-form-section">
            <div class="order-form-heading"><i class="fe fe-file-text"></i><div><h5>Order &amp; Customer Details</h5><p>Customer, contact and order source information.</p></div></div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Order Name <span class="text-danger">*</span></label>
                    <input type="text" name="order_name" class="form-control" maxlength="180" value="<?= esc((string) old('order_name')) ?>" placeholder="Example: Bridal Jhumki Set" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Order Received Date <span class="text-danger">*</span></label>
                    <input type="date" name="order_received_date" class="form-control" value="<?= esc((string) old('order_received_date', date('Y-m-d'))) ?>" required>
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
                            <option value="<?= esc((string) $customer['id']) ?>" data-phone="<?= esc((string) ($customer['phone'] ?? ''), 'attr') ?>" <?= (string) old('customer_id') === (string) $customer['id'] ? 'selected' : '' ?>><?= esc($customer['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Contact Number</label>
                    <input type="tel" name="contact_number" id="order-contact-number" class="form-control" maxlength="40" value="<?= esc((string) old('contact_number')) ?>" placeholder="Customer contact number">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Material Category <span class="text-danger">*</span></label>
                    <div class="material-category-grid">
                        <?php foreach (['Gold' => 'circle', 'Diamond' => 'gem', 'Jadau' => 'star', 'Silver' => 'disc'] as $material => $icon): ?>
                            <div class="material-category-option">
                                <input type="radio" name="material_category" id="material-<?= strtolower($material) ?>" value="<?= esc($material, 'attr') ?>" <?= $selectedMaterialCategory === $material ? 'checked' : '' ?> required>
                                <label for="material-<?= strtolower($material) ?>"><i class="fe fe-<?= esc($icon, 'attr') ?>"></i><?= esc($material) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Sales Person <span class="text-muted">(Optional)</span></label>
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
                    <label class="form-label">Current Status</label>
                    <select name="status" class="form-control js-searchable-select">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= esc($status) ?>" <?= (string) old('status', 'Confirmed') === (string) $status ? 'selected' : '' ?>><?= esc($status) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                    <label class="form-label">General Order Notes</label>
                    <textarea name="order_notes" class="form-control" rows="2"><?= esc((string) old('order_notes')) ?></textarea>
                </div>
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

            <div class="order-form-section">
            <div class="order-form-heading"><i class="fe fe-edit-3"></i><div><h5>Jewellery Specifications</h5><p>Add description, pieces, size/length and expected gold/diamond weight.</p></div></div>
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
                            <th>Size / Length</th>
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
            </div>

            <div class="order-form-section">
                <div class="order-form-heading"><i class="fe fe-credit-card"></i><div><h5>Certificate, Pricing &amp; Delivery</h5><p>Capture the commercial details shown on the physical order form.</p></div></div>
                <div class="order-commercial-summary">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Certificate Requirement</label>
                            <select name="certificate_requirement" class="form-control js-searchable-select">
                                <?php foreach (['' => 'No certificate required', 'IGI' => 'IGI Certificate', 'Kalasha' => 'Kalasha Certificate', 'IGI / Kalasha' => 'IGI / Kalasha (either)', 'Other' => 'Other / Mention in details'] as $value => $label): ?>
                                    <option value="<?= esc($value, 'attr') ?>" <?= (string) old('certificate_requirement') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
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
                            <input type="number" name="gold_rate_per_gm" id="gold-rate-per-gm" class="form-control" min="0" step="0.01" value="<?= esc((string) old('gold_rate_per_gm')) ?>" placeholder="₹ per gram">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Approximate Price</label>
                            <input type="number" name="approximate_price" id="approximate-price" class="form-control" min="0" step="0.01" value="<?= esc((string) old('approximate_price')) ?>" placeholder="₹ 0.00">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Advance Amount</label>
                            <input type="number" name="advance_amount" id="advance-amount" class="form-control" min="0" step="0.01" value="<?= esc((string) old('advance_amount', '0')) ?>" placeholder="₹ 0.00">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Client Delivery Date</label>
                            <input type="date" name="due_date" class="form-control" value="<?= esc((string) old('due_date')) ?>">
                            <div class="order-balance-preview">Approx. balance: <strong id="approximate-balance">₹0.00</strong></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Additional Details / Finish Instructions</label>
                            <textarea name="additional_details" class="form-control" rows="3" maxlength="5000" placeholder="Fitting, polish, engraving, sample-piece matching or any other instruction"><?= esc((string) old('additional_details')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="order-form-section">
            <div class="order-form-heading"><i class="fe fe-image"></i><div><h5>Reference Images &amp; Files</h5><p>Select multiple photos together; previews appear before saving.</p></div></div>
            <div class="row">
                <div class="col-md-8 mb-3">
                    <div class="order-upload-zone">
                        <label class="form-label" for="order-reference-files">Reference Images / CAD / Approval</label>
                        <input type="file" name="order_files[]" id="order-reference-files" class="form-control" accept="image/*,.pdf,.dwg,.dxf" multiple>
                        <div class="form-text">Use Add files again to select more. Multiple reference images are supported.</div>
                        <div id="order-image-preview" class="order-image-preview"></div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Attachment Type</label>
                    <select name="file_type" class="form-control js-searchable-select">
                        <option value="reference" <?= old('file_type') === 'reference' ? 'selected' : '' ?>>Reference</option>
                        <option value="cad" <?= old('file_type') === 'cad' ? 'selected' : '' ?>>CAD</option>
                        <option value="photo" <?= old('file_type') === 'photo' ? 'selected' : '' ?>>Photo</option>
                        <option value="approval" <?= old('file_type') === 'approval' ? 'selected' : '' ?>>Approval</option>
                    </select>
                </div>
            </div>
            </div>

            <div class="d-flex justify-content-end"><button class="btn btn-primary px-4" type="submit"><i class="fe fe-check-circle me-1"></i>Save Order</button></div>
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
        const categorySelect = document.getElementById('order-category-select');
        const newCategoryInput = document.getElementById('new-order-category');
        const contactNumber = document.getElementById('order-contact-number');
        const goldRateStatus = document.getElementById('gold-rate-status');
        const goldRateWrap = document.getElementById('gold-rate-wrap');
        const goldRateInput = document.getElementById('gold-rate-per-gm');
        const approximatePrice = document.getElementById('approximate-price');
        const advanceAmount = document.getElementById('advance-amount');
        const approximateBalance = document.getElementById('approximate-balance');
        const referenceFiles = document.getElementById('order-reference-files');
        const imagePreview = document.getElementById('order-image-preview');
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
            if (typeof jQuery !== 'undefined') jQuery(categorySelect).on('change', toggleNewCategory);
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

        function updateApproximateBalance() {
            if (!approximateBalance) return;
            const price = Number(approximatePrice ? approximatePrice.value : 0) || 0;
            const advance = Number(advanceAmount ? advanceAmount.value : 0) || 0;
            approximateBalance.textContent = new Intl.NumberFormat('en-IN', {
                style: 'currency', currency: 'INR', maximumFractionDigits: 2
            }).format(Math.max(0, price - advance));
        }

        function previewReferenceImages() {
            if (!referenceFiles || !imagePreview) return;
            imagePreview.replaceChildren();
            Array.from(referenceFiles.files || []).forEach(function (file) {
                if (!file.type.startsWith('image/')) return;
                const figure = document.createElement('figure');
                const image = document.createElement('img');
                const caption = document.createElement('figcaption');
                const objectUrl = URL.createObjectURL(file);
                image.src = objectUrl;
                image.alt = file.name;
                image.addEventListener('load', function () { URL.revokeObjectURL(objectUrl); }, {once: true});
                caption.textContent = file.name;
                figure.append(image, caption);
                imagePreview.appendChild(figure);
            });
        }

        if (goldRateStatus) {
            if (typeof jQuery !== 'undefined') jQuery(goldRateStatus).on('change', toggleGoldRate);
            else goldRateStatus.addEventListener('change', toggleGoldRate);
        }
        if (approximatePrice) approximatePrice.addEventListener('input', updateApproximateBalance);
        if (advanceAmount) advanceAmount.addEventListener('input', updateApproximateBalance);
        if (referenceFiles) referenceFiles.addEventListener('change', previewReferenceImages);
        toggleGoldRate();
        updateApproximateBalance();

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
