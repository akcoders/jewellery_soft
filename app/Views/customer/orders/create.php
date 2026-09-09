<?= $this->extend('customer/layout') ?>

<?= $this->section('content') ?>
<?php
$selectedOrderType = (string) old('order_type', 'Manufacturing');
$selectedDesignType = (string) old('order_design_type', 'Fresh');
$selectedSalesPerson = (string) old('sales_person_user_id');
$selectedDesign = (string) old('design_id');
$selectedCategoryId = (string) old('order_category_id');
$selectedMaterialCategory = (string) old('material_category', 'Gold');
$selectedGoldRateStatus = (string) old('gold_rate_block_status', 'Not Fixed');
?>
<style>
    .portal-form-section { background: #fff; border: 1px solid var(--portal-border); border-radius: 14px; margin-bottom: 16px; padding: 20px; }
    .portal-section-title { align-items: center; display: flex; gap: 11px; margin-bottom: 18px; }
    .portal-section-title i { align-items: center; background: var(--portal-gold-soft); border-radius: 10px; color: var(--portal-gold); display: inline-flex; flex: 0 0 40px; height: 40px; justify-content: center; }
    .portal-section-title h5 { font-size: 15px; font-weight: 760; margin: 0 0 2px; }
    .portal-section-title p { color: var(--portal-muted); font-size: 11px; margin: 0; }
    .sales-person-summary { align-items: center; background: #f8f9fb; border: 1px solid #e5e9ef; border-radius: 11px; display: flex; gap: 11px; margin-top: 9px; min-height: 58px; padding: 10px 12px; }
    .sales-person-summary > i { align-items: center; background: #eef2f7; border-radius: 9px; color: #536176; display: inline-flex; flex: 0 0 36px; height: 36px; justify-content: center; }
    .sales-person-summary strong, .sales-person-summary small { display: block; }
    .sales-person-summary strong { font-size: 12px; }
    .sales-person-summary small { color: var(--portal-muted); font-size: 10px; margin-top: 2px; }
    .repeat-design-panel { background: linear-gradient(135deg, #fffdf8, #fff); border: 1px solid #eadfca; border-radius: 14px; padding: 16px; }
    .design-preview { align-items: center; background: #fff; border: 1px dashed #dcd4c5; border-radius: 12px; display: flex; gap: 13px; margin-top: 12px; min-height: 88px; padding: 12px; }
    .design-preview-image { align-items: center; background: #f2f3f5; border-radius: 10px; color: #9aa3b1; display: inline-flex; flex: 0 0 64px; height: 64px; justify-content: center; overflow: hidden; }
    .design-preview-image img { height: 100%; object-fit: cover; width: 100%; }
    .design-preview strong, .design-preview small { display: block; }
    .design-preview strong { font-size: 13px; }
    .design-preview small { color: var(--portal-muted); font-size: 10px; margin-top: 3px; }
    .privacy-note { align-items: flex-start; background: #eef5ff; border-radius: 12px; color: #355b8d; display: flex; font-size: 11px; gap: 10px; padding: 12px 14px; }
    .privacy-note i { margin-top: 2px; }
    .material-category-grid { display: grid; gap: 10px; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .material-category-option { position: relative; }
    .material-category-option input { opacity: 0; position: absolute; }
    .material-category-option label { align-items: center; background: #fff; border: 1px solid var(--portal-border); border-radius: 12px; cursor: pointer; display: flex; font-size: 12px; font-weight: 700; gap: 8px; justify-content: center; margin: 0; min-height: 50px; padding: 10px; transition: .18s ease; }
    .material-category-option input:checked + label { background: var(--portal-gold-soft); border-color: var(--portal-gold); box-shadow: 0 0 0 3px rgba(190, 144, 49, .1); color: #71500d; }
    .commercial-panel { background: linear-gradient(135deg, #fffaf0, #fff); border: 1px solid #eadfca; border-radius: 14px; padding: 16px; }
    .balance-preview { color: var(--portal-muted); font-size: 11px; margin-top: 7px; }
    .balance-preview strong { color: #1c2534; font-size: 13px; }
    .reference-upload { background: #fafbfc; border: 1px dashed #cbd2dc; border-radius: 14px; padding: 16px; }
    .reference-preview { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(88px, 1fr)); margin-top: 12px; }
    .reference-preview figure { background: #fff; border: 1px solid #e1e5eb; border-radius: 10px; margin: 0; overflow: hidden; }
    .reference-preview img { aspect-ratio: 1; display: block; object-fit: cover; width: 100%; }
    .reference-preview figcaption { color: var(--portal-muted); font-size: 9px; overflow: hidden; padding: 6px; text-overflow: ellipsis; white-space: nowrap; }
    @media (max-width: 575px) { .portal-form-section { padding: 16px; } .material-category-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>

<div class="portal-hero mb-4">
    <div>
        <span class="eyebrow">New request</span>
        <h2 class="mb-1">Create Order</h2>
        <p class="mb-0">Submit a fresh concept or repeat an existing design using its unique code.</p>
    </div>
    <a href="<?= site_url('customer/orders') ?>" class="btn btn-outline-dark"><i class="fe fe-arrow-left me-1"></i>Back to Orders</a>
</div>

<form method="post" action="<?= site_url('customer/orders') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="portal-card card">
        <div class="card-body p-3 p-lg-4">
            <section class="portal-form-section">
                <div class="portal-section-title">
                    <i class="fe fe-clipboard"></i>
                    <div><h5>Order &amp; Customer Details</h5><p>Customer, contact and order setup information.</p></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="order-name">Order Name <span class="text-danger">*</span></label>
                        <input type="text" name="order_name" id="order-name" class="form-control" maxlength="180" value="<?= esc((string) old('order_name')) ?>" placeholder="Example: Bridal Jhumki Set" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="order-category-select">Jewellery / Sub Category <span class="text-danger">*</span></label>
                        <select name="order_category_id" id="order-category-select" class="form-select js-searchable-select" data-placeholder="Search jewellery category" required>
                            <option value=""></option>
                            <?php foreach (($orderCategories ?? []) as $category): ?>
                                <option value="<?= (int) $category['id'] ?>" <?= $selectedCategoryId === (string) $category['id'] ? 'selected' : '' ?>><?= esc((string) $category['name']) ?> (<?= esc((string) $category['code']) ?>)</option>
                            <?php endforeach; ?>
                            <option value="0" <?= $selectedCategoryId === '0' ? 'selected' : '' ?>>+ Add New Category</option>
                        </select>
                        <input type="text" name="new_order_category" id="new-order-category" class="form-control mt-2" maxlength="100" value="<?= esc((string) old('new_order_category')) ?>" placeholder="Enter new jewellery category" style="display:none;">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Customer Name</label>
                        <input type="text" class="form-control" value="<?= esc((string) ($customer['name'] ?? '')) ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="order-received-date">Order Received Date <span class="text-danger">*</span></label>
                        <input type="date" name="order_received_date" id="order-received-date" class="form-control" value="<?= esc((string) old('order_received_date', date('Y-m-d'))) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="contact-number">Contact Number</label>
                        <input type="tel" name="contact_number" id="contact-number" class="form-control" maxlength="40" value="<?= esc((string) old('contact_number', (string) (($currentUser['mobile'] ?? '') ?: ($customer['phone'] ?? '')))) ?>" placeholder="Customer contact number">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Material Category <span class="text-danger">*</span></label>
                        <div class="material-category-grid">
                            <?php foreach (['Gold' => 'circle', 'Diamond' => 'gem', 'Jadau' => 'star', 'Silver' => 'disc'] as $material => $icon): ?>
                                <div class="material-category-option">
                                    <input type="radio" name="material_category" id="portal-material-<?= strtolower($material) ?>" value="<?= esc($material, 'attr') ?>" <?= $selectedMaterialCategory === $material ? 'checked' : '' ?> required>
                                    <label for="portal-material-<?= strtolower($material) ?>"><i class="fe fe-<?= esc($icon, 'attr') ?>"></i><?= esc($material) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="customer-order-type">Order Type <span class="text-danger">*</span></label>
                        <select name="order_type" id="customer-order-type" class="form-select js-searchable-select" required>
                            <?php foreach (['Manufacturing', 'Sales', 'Repair'] as $orderType): ?>
                                <option value="<?= esc($orderType) ?>" <?= $selectedOrderType === $orderType ? 'selected' : '' ?>><?= esc($orderType) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="design-type">Fresh or Repeat <span class="text-danger">*</span></label>
                        <select name="order_design_type" id="design-type" class="form-select js-searchable-select" required>
                            <option value="Fresh" <?= $selectedDesignType === 'Fresh' ? 'selected' : '' ?>>Fresh Order</option>
                            <option value="Repeat" <?= $selectedDesignType === 'Repeat' ? 'selected' : '' ?>>Repeat Existing Design</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <?php if (! $isSalesPerson): ?>
                            <label class="form-label" for="sales-person">Sales Person <span class="text-muted">(Optional)</span></label>
                            <select name="sales_person_user_id" id="sales-person" class="form-select js-searchable-select" data-placeholder="Search by name or mobile">
                                <option value=""></option>
                                <?php foreach (($salesPeople ?? []) as $person): ?>
                                    <option value="<?= (int) $person['id'] ?>" data-name="<?= esc((string) $person['name'], 'attr') ?>" data-mobile="<?= esc((string) ($person['mobile'] ?? ''), 'attr') ?>" <?= $selectedSalesPerson === (string) $person['id'] ? 'selected' : '' ?>>
                                        <?= esc($person['name'] . ' · ' . (($person['mobile'] ?? '') ?: 'No mobile')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="sales-person-summary d-none" id="sales-person-summary"><i class="fe fe-user-check"></i><span><strong id="sales-person-name"></strong><small id="sales-person-mobile"></small></span></div>
                        <?php else: ?>
                            <label class="form-label">Sales Person</label>
                            <div class="sales-person-summary mt-0"><i class="fe fe-user-check"></i><span><strong><?= esc((string) ($currentUser['name'] ?? session('customer_user_name'))) ?></strong><small><?= esc((string) (($currentUser['mobile'] ?? '') ?: 'Mobile not available')) ?></small></span></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12" id="repeat-design-wrap" style="<?= $selectedDesignType === 'Repeat' ? '' : 'display:none;' ?>">
                        <div class="repeat-design-panel">
                            <label class="form-label" for="design-select">Unique Design Code <span class="text-danger">*</span></label>
                            <select name="design_id" id="design-select" class="form-select js-searchable-select" data-placeholder="Search unique code, design name or category">
                                <option value=""></option>
                                <?php foreach (($designs ?? []) as $design): ?>
                                    <?php
                                    $imagePath = trim((string) ($design['image_path'] ?? ''));
                                    $imageUrl = $imagePath === '' ? '' : (preg_match('#^https?://#i', $imagePath) ? $imagePath : base_url($imagePath));
                                    $category = trim((string) (($design['subcategory'] ?? '') ?: ($design['category'] ?? '')));
                                    ?>
                                    <option
                                        value="<?= (int) $design['id'] ?>"
                                        data-code="<?= esc((string) $design['design_code'], 'attr') ?>"
                                        data-name="<?= esc((string) $design['name'], 'attr') ?>"
                                        data-category="<?= esc($category, 'attr') ?>"
                                        data-image="<?= esc($imageUrl, 'attr') ?>"
                                        data-gross="<?= esc((string) ($design['gross_weight_gm'] ?? '0'), 'attr') ?>"
                                        data-net="<?= esc((string) ($design['net_gold_weight_gm'] ?? '0'), 'attr') ?>"
                                        data-diamond="<?= esc((string) ($design['diamond_weight_cts'] ?? '0'), 'attr') ?>"
                                        <?= $selectedDesign === (string) $design['id'] ? 'selected' : '' ?>
                                    ><?= esc($design['design_code'] . ' · ' . $design['name'] . ($category !== '' ? ' · ' . $category : '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="design-preview d-none" id="design-preview">
                                <span class="design-preview-image" id="design-preview-image"><i class="fe fe-image"></i></span>
                                <span><strong id="design-preview-title"></strong><small id="design-preview-category"></small><small id="design-preview-weights"></small></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="portal-form-section">
                <div class="portal-section-title">
                    <i class="fe fe-edit-3"></i>
                    <div><h5>Jewellery Requirements</h5><p>Describe the item, number of pieces, size/length and expected weights.</p></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="item-description">Item / Design Details <span class="text-danger">*</span></label>
                        <input id="item-description" name="item_description" class="form-control" value="<?= esc((string) old('item_description')) ?>" maxlength="500" placeholder="Ring, jhumki, haaram, size and special specifications" required>
                    </div>
                    <div class="col-md-2"><label class="form-label" for="size-label">Size / Length</label><input id="size-label" name="size_label" class="form-control" value="<?= esc((string) old('size_label')) ?>" maxlength="30"></div>
                    <div class="col-md-2"><label class="form-label" for="order-qty">Qty <span class="text-danger">*</span></label><input id="order-qty" type="number" name="qty" min="1" value="<?= esc((string) old('qty', '1')) ?>" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label" for="expected-gold">Expected Gold (gm)</label><input id="expected-gold" type="number" step=".001" min="0" name="gold_required_gm" value="<?= esc((string) old('gold_required_gm', '0')) ?>" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label" for="expected-diamond">Expected Diamond (cts)</label><input id="expected-diamond" type="number" step=".001" min="0" name="diamond_required_cts" value="<?= esc((string) old('diamond_required_cts', '0')) ?>" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label" for="order-notes">Description / General Notes</label><textarea id="order-notes" name="order_notes" rows="3" class="form-control" placeholder="Describe the item and any general instruction"><?= esc((string) old('order_notes')) ?></textarea></div>
                    <div class="col-md-6"><label class="form-label" for="additional-details">Additional Details / Finish Instructions</label><textarea id="additional-details" name="additional_details" rows="3" maxlength="5000" class="form-control" placeholder="Fitting, polish, engraving or sample-piece matching"><?= esc((string) old('additional_details')) ?></textarea></div>
                </div>
            </section>

            <section class="portal-form-section">
                <div class="portal-section-title">
                    <i class="fe fe-credit-card"></i>
                    <div><h5>Certificate, Pricing &amp; Delivery</h5><p>Record certificate choice, rate status, advance and promised delivery date.</p></div>
                </div>
                <div class="commercial-panel">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="certificate-requirement">Certificate Requirement</label>
                            <select name="certificate_requirement" id="certificate-requirement" class="form-select js-searchable-select">
                                <?php foreach (['' => 'No certificate required', 'IGI' => 'IGI Certificate', 'Kalasha' => 'Kalasha Certificate', 'IGI / Kalasha' => 'IGI / Kalasha (either)', 'Other' => 'Other / Mention in details'] as $value => $label): ?>
                                    <option value="<?= esc($value, 'attr') ?>" <?= (string) old('certificate_requirement') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="gold-rate-status">Gold Rate Block</label>
                            <select name="gold_rate_block_status" id="gold-rate-status" class="form-select js-searchable-select" required>
                                <option value="Not Fixed" <?= $selectedGoldRateStatus === 'Not Fixed' ? 'selected' : '' ?>>Not Fixed</option>
                                <option value="Fixed" <?= $selectedGoldRateStatus === 'Fixed' ? 'selected' : '' ?>>Fixed</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="gold-rate-wrap" style="<?= $selectedGoldRateStatus === 'Fixed' ? '' : 'display:none;' ?>">
                            <label class="form-label" for="gold-rate-per-gm">Fixed Gold Rate / gm</label>
                            <input type="number" name="gold_rate_per_gm" id="gold-rate-per-gm" class="form-control" min="0" step="0.01" value="<?= esc((string) old('gold_rate_per_gm')) ?>" placeholder="₹ per gram">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="approximate-price">Approximate Price</label>
                            <input type="number" name="approximate_price" id="approximate-price" class="form-control" min="0" step="0.01" value="<?= esc((string) old('approximate_price')) ?>" placeholder="₹ 0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="advance-amount">Advance Amount</label>
                            <input type="number" name="advance_amount" id="advance-amount" class="form-control" min="0" step="0.01" value="<?= esc((string) old('advance_amount', '0')) ?>" placeholder="₹ 0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="required-by">Client Delivery Date</label>
                            <input id="required-by" type="date" name="due_date" value="<?= esc((string) old('due_date')) ?>" class="form-control">
                            <div class="balance-preview">Approx. balance: <strong id="approximate-balance">₹0.00</strong></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="portal-form-section">
                <div class="portal-section-title">
                    <i class="fe fe-image"></i>
                    <div><h5>Reference Images</h5><p>Add multiple clear photos of the sample, sketch or preferred finish.</p></div>
                </div>
                <div class="reference-upload">
                    <label class="form-label" for="order-images">Choose Reference Photos</label>
                    <input id="order-images" type="file" name="order_images[]" accept="image/*" multiple class="form-control">
                    <div class="form-text">Up to 10 images, 5 MB each. You can select multiple images together.</div>
                    <div id="reference-preview" class="reference-preview"></div>
                </div>
            </section>

            <div class="privacy-note mb-3"><i class="fe fe-shield"></i><span>You will only see customer-safe order information and current status. Karigar assignment and internal production details are never displayed in this portal.</span></div>
            <div class="d-flex flex-wrap justify-content-end gap-2">
                <a href="<?= site_url('customer/orders') ?>" class="btn btn-light">Cancel</a>
                <button class="btn btn-dark px-4" type="submit"><i class="fe fe-send me-1"></i>Submit Order</button>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        const type = document.getElementById('design-type');
        const wrap = document.getElementById('repeat-design-wrap');
        const design = document.getElementById('design-select');
        const designPreview = document.getElementById('design-preview');
        const sales = document.getElementById('sales-person');
        const salesSummary = document.getElementById('sales-person-summary');
        const categorySelect = document.getElementById('order-category-select');
        const newCategoryInput = document.getElementById('new-order-category');
        const goldRateStatus = document.getElementById('gold-rate-status');
        const goldRateWrap = document.getElementById('gold-rate-wrap');
        const goldRateInput = document.getElementById('gold-rate-per-gm');
        const approximatePrice = document.getElementById('approximate-price');
        const advanceAmount = document.getElementById('advance-amount');
        const approximateBalance = document.getElementById('approximate-balance');
        const orderImages = document.getElementById('order-images');
        const referencePreview = document.getElementById('reference-preview');

        function toggleNewCategory() {
            if (!categorySelect || !newCategoryInput) return;
            const adding = categorySelect.value === '0';
            newCategoryInput.style.display = adding ? '' : 'none';
            newCategoryInput.required = adding;
            if (!adding) newCategoryInput.value = '';
        }

        function updateDesignPreview() {
            if (!design || !designPreview) return;
            const option = design.options[design.selectedIndex];
            if (!option || !option.value) {
                designPreview.classList.add('d-none');
                return;
            }
            document.getElementById('design-preview-title').textContent = (option.dataset.code || '') + ' · ' + (option.dataset.name || '');
            document.getElementById('design-preview-category').textContent = option.dataset.category || 'Uncategorised design';
            document.getElementById('design-preview-weights').textContent = 'Gross ' + (option.dataset.gross || '0') + ' gm · Net gold ' + (option.dataset.net || '0') + ' gm · Diamond ' + (option.dataset.diamond || '0') + ' cts';
            const imageWrap = document.getElementById('design-preview-image');
            imageWrap.replaceChildren();
            if (option.dataset.image) {
                const image = document.createElement('img');
                image.src = option.dataset.image;
                image.alt = 'Selected design';
                imageWrap.appendChild(image);
            } else {
                const icon = document.createElement('i');
                icon.className = 'fe fe-image';
                imageWrap.appendChild(icon);
            }
            designPreview.classList.remove('d-none');
        }

        function toggleDesign() {
            const repeat = type && type.value === 'Repeat';
            if (wrap) wrap.style.display = repeat ? '' : 'none';
            if (design) {
                design.required = repeat;
                design.disabled = !repeat;
                if (!repeat) {
                    design.value = '';
                    if (window.jQuery) jQuery(design).trigger('change.select2');
                }
            }
            if (!repeat && designPreview) designPreview.classList.add('d-none');
            if (repeat) updateDesignPreview();
        }

        function updateSalesPerson() {
            if (!sales || !salesSummary) return;
            const option = sales.options[sales.selectedIndex];
            if (!option || !option.value) {
                salesSummary.classList.add('d-none');
                return;
            }
            document.getElementById('sales-person-name').textContent = option.dataset.name || option.textContent.trim();
            document.getElementById('sales-person-mobile').textContent = option.dataset.mobile || 'Mobile not available';
            salesSummary.classList.remove('d-none');
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

        function previewImages() {
            if (!orderImages || !referencePreview) return;
            referencePreview.replaceChildren();
            Array.from(orderImages.files || []).forEach(function (file) {
                const figure = document.createElement('figure');
                const image = document.createElement('img');
                const caption = document.createElement('figcaption');
                const objectUrl = URL.createObjectURL(file);
                image.src = objectUrl;
                image.alt = file.name;
                image.addEventListener('load', function () { URL.revokeObjectURL(objectUrl); }, {once: true});
                caption.textContent = file.name;
                figure.append(image, caption);
                referencePreview.appendChild(figure);
            });
        }

        if (type) window.jQuery ? jQuery(type).on('change', toggleDesign) : type.addEventListener('change', toggleDesign);
        if (design) window.jQuery ? jQuery(design).on('change', updateDesignPreview) : design.addEventListener('change', updateDesignPreview);
        if (sales) window.jQuery ? jQuery(sales).on('change', updateSalesPerson) : sales.addEventListener('change', updateSalesPerson);
        if (categorySelect) window.jQuery ? jQuery(categorySelect).on('change', toggleNewCategory) : categorySelect.addEventListener('change', toggleNewCategory);
        if (goldRateStatus) window.jQuery ? jQuery(goldRateStatus).on('change', toggleGoldRate) : goldRateStatus.addEventListener('change', toggleGoldRate);
        if (approximatePrice) approximatePrice.addEventListener('input', updateApproximateBalance);
        if (advanceAmount) advanceAmount.addEventListener('input', updateApproximateBalance);
        if (orderImages) orderImages.addEventListener('change', previewImages);
        toggleNewCategory();
        toggleDesign();
        updateSalesPerson();
        toggleGoldRate();
        updateApproximateBalance();
    })();
</script>
<?= $this->endSection() ?>
