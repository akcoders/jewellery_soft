<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Add Customer</h4>
    <a href="<?= site_url('admin/customers') ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= site_url('admin/customers') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Customer Name</label>
                    <input type="text" name="name" class="form-control" value="<?= esc(old('name')) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= esc(old('phone')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= esc(old('email')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">GSTIN</label>
                    <input type="text" name="gstin" class="form-control" value="<?= esc(old('gstin')) ?>">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Terms / Pricing Notes</label>
                    <textarea name="terms_text" class="form-control" rows="3"><?= esc(old('terms_text')) ?></textarea>
                </div>
            </div>

            <hr>
            <h6>Billing Address</h6>
            <div class="row">
                <div class="col-md-6 mb-3"><input type="text" name="billing_line1" class="form-control" placeholder="Line 1"></div>
                <div class="col-md-6 mb-3"><input type="text" name="billing_line2" class="form-control" placeholder="Line 2"></div>
                <div class="col-md-4 mb-3"><input type="text" name="billing_city" class="form-control" placeholder="City"></div>
                <div class="col-md-4 mb-3"><input type="text" name="billing_state" class="form-control" placeholder="State"></div>
                <div class="col-md-4 mb-3"><input type="text" name="billing_pincode" class="form-control" placeholder="Pincode"></div>
                <div class="col-md-4 mb-3"><input type="text" name="billing_country" class="form-control" placeholder="Country"></div>
            </div>

            <hr>
            <h6>Shipping Address</h6>
            <div class="row">
                <div class="col-md-6 mb-3"><input type="text" name="shipping_line1" class="form-control" placeholder="Line 1"></div>
                <div class="col-md-6 mb-3"><input type="text" name="shipping_line2" class="form-control" placeholder="Line 2"></div>
                <div class="col-md-4 mb-3"><input type="text" name="shipping_city" class="form-control" placeholder="City"></div>
                <div class="col-md-4 mb-3"><input type="text" name="shipping_state" class="form-control" placeholder="State"></div>
                <div class="col-md-4 mb-3"><input type="text" name="shipping_pincode" class="form-control" placeholder="Pincode"></div>
                <div class="col-md-4 mb-3"><input type="text" name="shipping_country" class="form-control" placeholder="Country"></div>
            </div>

            <button class="btn btn-primary" type="submit">Save Customer</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

