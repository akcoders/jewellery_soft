<?= $this->extend('customer/layout') ?>

<?= $this->section('content') ?>
<?php
$selectedOrderType = (string) old('order_type', 'Manufacturing');
$selectedDesignType = (string) old('order_design_type', 'Fresh');
$selectedSalesPerson = (string) old('sales_person_user_id');
$selectedDesign = (string) old('design_id');
$selectedCategoryId = (string) old('order_category_id');
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
    @media (max-width: 575px) { .portal-form-section { padding: 16px; } }
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
                    <div><h5>Order Setup</h5><p>Select order type, fresh/repeat design and salesperson.</p></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="order-name">Order Name <span class="text-danger">*</span></label>
                        <input type="text" name="order_name" id="order-name" class="form-control" maxlength="180" value="<?= esc((string) old('order_name')) ?>" placeholder="Example: Bridal Jhumki Set" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="order-category-select">Jewellery Category <span class="text-danger">*</span></label>
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
                    <div><h5>Jewellery Requirements</h5><p>Describe the item, expected weights and required delivery date.</p></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="item-description">Item / Design Details <span class="text-danger">*</span></label>
                        <input id="item-description" name="item_description" class="form-control" value="<?= esc((string) old('item_description')) ?>" maxlength="500" placeholder="Ring, jhumki, haaram, size and special specifications" required>
                    </div>
                    <div class="col-md-2"><label class="form-label" for="size-label">Size</label><input id="size-label" name="size_label" class="form-control" value="<?= esc((string) old('size_label')) ?>" maxlength="30"></div>
                    <div class="col-md-2"><label class="form-label" for="order-qty">Qty <span class="text-danger">*</span></label><input id="order-qty" type="number" name="qty" min="1" value="<?= esc((string) old('qty', '1')) ?>" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label" for="expected-gold">Expected Gold (gm)</label><input id="expected-gold" type="number" step=".001" min="0" name="gold_required_gm" value="<?= esc((string) old('gold_required_gm', '0')) ?>" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label" for="expected-diamond">Expected Diamond (cts)</label><input id="expected-diamond" type="number" step=".001" min="0" name="diamond_required_cts" value="<?= esc((string) old('diamond_required_cts', '0')) ?>" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label" for="required-by">Required By</label><input id="required-by" type="date" name="due_date" value="<?= esc((string) old('due_date')) ?>" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label" for="order-images">Reference Photos</label><input id="order-images" type="file" name="order_images[]" accept="image/*" multiple class="form-control"><div class="form-text">Up to 10 images, 5 MB each.</div></div>
                    <div class="col-12"><label class="form-label" for="order-notes">Notes</label><textarea id="order-notes" name="order_notes" rows="3" class="form-control" placeholder="Any delivery, finish, engraving or approval instructions"><?= esc((string) old('order_notes')) ?></textarea></div>
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

        if (type) jQuery(type).on('change', toggleDesign);
        if (design) jQuery(design).on('change', updateDesignPreview);
        if (sales) jQuery(sales).on('change', updateSalesPerson);
        if (categorySelect) jQuery(categorySelect).on('change', toggleNewCategory);
        toggleNewCategory();
        toggleDesign();
        updateSalesPerson();
    })();
</script>
<?= $this->endSection() ?>
