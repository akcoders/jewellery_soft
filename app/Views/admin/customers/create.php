<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<style>
    .customer-create-grid { display: grid; gap: 18px; grid-template-columns: minmax(0, 1fr) 300px; }
    .customer-form-section { background: #fff; border: 1px solid #e7eaf0; border-radius: 14px; margin-bottom: 16px; padding: 20px; }
    .customer-form-section:last-child { margin-bottom: 0; }
    .customer-section-heading { align-items: center; display: flex; gap: 12px; margin-bottom: 18px; }
    .customer-section-icon { align-items: center; background: var(--erp-gold-soft); border-radius: 11px; color: var(--erp-gold-dark); display: inline-flex; flex: 0 0 42px; font-size: 17px; height: 42px; justify-content: center; }
    .customer-section-heading h6 { color: var(--erp-ink); font-size: 15px; font-weight: 760; margin: 0 0 3px; }
    .customer-section-heading p { color: var(--erp-muted); font-size: 11px; margin: 0; }
    .password-field { position: relative; }
    .password-field .form-control { padding-right: 46px; }
    .password-toggle { align-items: center; background: transparent; border: 0; color: #7b8494; display: inline-flex; height: 40px; justify-content: center; position: absolute; right: 2px; top: 1px; width: 42px; }
    .customer-access-card { background: linear-gradient(145deg, #271f28, #191f2d); border: 0; color: #fff; overflow: hidden; position: sticky; top: 82px; }
    .customer-access-card::after { background: rgba(200, 155, 30, .16); border-radius: 50%; content: ''; height: 170px; position: absolute; right: -70px; top: -80px; width: 170px; }
    .customer-access-card .card-body { position: relative; z-index: 1; }
    .customer-access-card h5 { color: #fff; font-weight: 760; }
    .customer-access-card p { color: rgba(255, 255, 255, .66); font-size: 12px; }
    .customer-access-list { display: grid; gap: 12px; margin: 22px 0 0; }
    .customer-access-item { align-items: flex-start; display: flex; gap: 10px; }
    .customer-access-item i { color: #e3c173; margin-top: 3px; }
    .customer-access-item strong { color: #fff; display: block; font-size: 12px; }
    .customer-access-item small { color: rgba(255, 255, 255, .58); display: block; font-size: 10px; margin-top: 2px; }
    @media (max-width: 991px) { .customer-create-grid { grid-template-columns: 1fr; } .customer-access-card { position: static; } }
    @media (max-width: 575px) { .customer-form-section { padding: 16px; } }
</style>

<div class="erp-page-toolbar erp-command-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Customer directory</span>
        <h4 class="mb-1">Create Customer & Portal Login</h4>
        <p class="mb-0">One save creates the customer profile and their secure order portal administrator.</p>
    </div>
    <a href="<?= site_url('admin/customers') ?>" class="btn btn-outline-primary"><i class="fe fe-arrow-left me-1"></i>Back</a>
</div>

<form action="<?= site_url('admin/customers') ?>" method="post" autocomplete="off">
    <?= csrf_field() ?>
    <div class="customer-create-grid">
        <div class="card erp-form-shell">
            <div class="card-body">
                <section class="customer-form-section">
                    <div class="customer-section-heading">
                        <span class="customer-section-icon"><i class="fe fe-user"></i></span>
                        <div><h6>Customer & Login Details</h6><p>The email and password below will be used for portal login.</p></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="customer-name">Customer Name <span class="text-danger">*</span></label>
                            <input id="customer-name" type="text" name="name" class="form-control" value="<?= esc((string) old('name')) ?>" maxlength="150" autocomplete="organization" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer-phone">Mobile Number</label>
                            <input id="customer-phone" type="tel" name="phone" class="form-control" value="<?= esc((string) old('phone')) ?>" maxlength="20" autocomplete="tel" placeholder="Customer contact number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer-email">Login Email <span class="text-danger">*</span></label>
                            <input id="customer-email" type="email" name="email" class="form-control" value="<?= esc((string) old('email')) ?>" maxlength="191" autocomplete="email" placeholder="name@company.com" required>
                            <div class="form-text">This becomes the customer administrator username.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer-gstin">GSTIN</label>
                            <input id="customer-gstin" type="text" name="gstin" class="form-control text-uppercase" value="<?= esc((string) old('gstin')) ?>" maxlength="25" placeholder="GST registration number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer-password">Portal Password <span class="text-danger">*</span></label>
                            <div class="password-field">
                                <input id="customer-password" type="password" name="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required>
                                <button type="button" class="password-toggle" data-password-target="customer-password" aria-label="Show password"><i class="fe fe-eye"></i></button>
                            </div>
                            <div class="form-text">Use at least 8 characters.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer-password-confirm">Confirm Password <span class="text-danger">*</span></label>
                            <div class="password-field">
                                <input id="customer-password-confirm" type="password" name="password_confirm" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required>
                                <button type="button" class="password-toggle" data-password-target="customer-password-confirm" aria-label="Show password"><i class="fe fe-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="customer-terms">Terms / Pricing Notes</label>
                            <textarea id="customer-terms" name="terms_text" class="form-control" rows="3" placeholder="Payment terms, making-charge arrangement or customer-specific notes"><?= esc((string) old('terms_text')) ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="customer-form-section">
                    <div class="customer-section-heading">
                        <span class="customer-section-icon"><i class="fe fe-map-pin"></i></span>
                        <div><h6>Billing Address</h6><p>Used for customer billing and account documents.</p></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Address Line 1</label><input type="text" name="billing_line1" class="form-control js-billing-field" data-copy-field="line1" value="<?= esc((string) old('billing_line1')) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Address Line 2</label><input type="text" name="billing_line2" class="form-control js-billing-field" data-copy-field="line2" value="<?= esc((string) old('billing_line2')) ?>"></div>
                        <div class="col-md-4"><label class="form-label">City</label><input type="text" name="billing_city" class="form-control js-billing-field" data-copy-field="city" value="<?= esc((string) old('billing_city')) ?>"></div>
                        <div class="col-md-4"><label class="form-label">State</label><input type="text" name="billing_state" class="form-control js-billing-field" data-copy-field="state" value="<?= esc((string) old('billing_state')) ?>"></div>
                        <div class="col-md-4"><label class="form-label">Pincode</label><input type="text" name="billing_pincode" class="form-control js-billing-field" data-copy-field="pincode" value="<?= esc((string) old('billing_pincode')) ?>"></div>
                        <div class="col-md-4"><label class="form-label">Country</label><input type="text" name="billing_country" class="form-control js-billing-field" data-copy-field="country" value="<?= esc((string) old('billing_country', 'India')) ?>"></div>
                    </div>
                </section>

                <section class="customer-form-section">
                    <div class="customer-section-heading justify-content-between flex-wrap">
                        <div class="d-flex align-items-center gap-3">
                            <span class="customer-section-icon"><i class="fe fe-truck"></i></span>
                            <div><h6>Shipping Address</h6><p>Optional delivery address for future dispatches.</p></div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="same-as-billing">
                            <label class="form-check-label" for="same-as-billing">Same as billing</label>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Address Line 1</label><input type="text" name="shipping_line1" class="form-control js-shipping-field" data-copy-field="line1" value="<?= esc((string) old('shipping_line1')) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Address Line 2</label><input type="text" name="shipping_line2" class="form-control js-shipping-field" data-copy-field="line2" value="<?= esc((string) old('shipping_line2')) ?>"></div>
                        <div class="col-md-4"><label class="form-label">City</label><input type="text" name="shipping_city" class="form-control js-shipping-field" data-copy-field="city" value="<?= esc((string) old('shipping_city')) ?>"></div>
                        <div class="col-md-4"><label class="form-label">State</label><input type="text" name="shipping_state" class="form-control js-shipping-field" data-copy-field="state" value="<?= esc((string) old('shipping_state')) ?>"></div>
                        <div class="col-md-4"><label class="form-label">Pincode</label><input type="text" name="shipping_pincode" class="form-control js-shipping-field" data-copy-field="pincode" value="<?= esc((string) old('shipping_pincode')) ?>"></div>
                        <div class="col-md-4"><label class="form-label">Country</label><input type="text" name="shipping_country" class="form-control js-shipping-field" data-copy-field="country" value="<?= esc((string) old('shipping_country', 'India')) ?>"></div>
                    </div>
                </section>

                <div class="d-flex flex-wrap justify-content-end gap-2">
                    <a href="<?= site_url('admin/customers') ?>" class="btn btn-light">Cancel</a>
                    <button class="btn btn-primary px-4" type="submit"><i class="fe fe-user-check me-1"></i>Create Customer & Login</button>
                </div>
            </div>
        </div>

        <aside>
            <div class="card customer-access-card">
                <div class="card-body p-4">
                    <span class="erp-eyebrow">Portal access</span>
                    <h5 class="mt-2">What this customer can do</h5>
                    <p>The portal intentionally keeps internal production assignments private.</p>
                    <div class="customer-access-list">
                        <div class="customer-access-item"><i class="fe fe-log-in"></i><span><strong>Secure customer login</strong><small>Email and password based access.</small></span></div>
                        <div class="customer-access-item"><i class="fe fe-plus-circle"></i><span><strong>Create fresh or repeat orders</strong><small>Repeat orders use searchable unique design codes.</small></span></div>
                        <div class="customer-access-item"><i class="fe fe-users"></i><span><strong>Add sales people</strong><small>Each salesperson receives an individual login.</small></span></div>
                        <div class="customer-access-item"><i class="fe fe-eye-off"></i><span><strong>Production privacy</strong><small>Karigar assignment and internal workflow details stay hidden.</small></span></div>
                        <div class="customer-access-item"><i class="fe fe-activity"></i><span><strong>Status tracking</strong><small>Customers see the current status of their own orders.</small></span></div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        document.querySelectorAll('[data-password-target]').forEach(function (button) {
            button.addEventListener('click', function () {
                const input = document.getElementById(button.dataset.passwordTarget || '');
                if (!input) return;
                const showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                const icon = button.querySelector('i');
                if (icon) icon.className = showing ? 'fe fe-eye' : 'fe fe-eye-off';
            });
        });

        const sameAsBilling = document.getElementById('same-as-billing');
        const copyBillingAddress = function () {
            if (!sameAsBilling || !sameAsBilling.checked) return;
            document.querySelectorAll('.js-shipping-field').forEach(function (shippingField) {
                const billingField = document.querySelector('.js-billing-field[data-copy-field="' + shippingField.dataset.copyField + '"]');
                if (billingField) shippingField.value = billingField.value;
            });
        };
        if (sameAsBilling) {
            sameAsBilling.addEventListener('change', copyBillingAddress);
            document.querySelectorAll('.js-billing-field').forEach(function (field) {
                field.addEventListener('input', copyBillingAddress);
            });
        }
    })();
</script>
<?= $this->endSection() ?>
