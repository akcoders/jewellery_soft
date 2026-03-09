<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Main Company Details</h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?= site_url('admin/company-settings') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Company Name</label>
                    <input type="text" name="company_name" class="form-control" value="<?= esc((string) old('company_name', (string) ($setting['company_name'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= esc((string) old('phone', (string) ($setting['phone'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="text" name="email" class="form-control" value="<?= esc((string) old('email', (string) ($setting['email'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">GSTIN</label>
                    <input type="text" name="gstin" class="form-control" value="<?= esc((string) old('gstin', (string) ($setting['gstin'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= esc((string) old('city', (string) ($setting['city'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">State</label>
                    <input type="text" name="state" class="form-control" value="<?= esc((string) old('state', (string) ($setting['state'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Pincode</label>
                    <input type="text" name="pincode" class="form-control" value="<?= esc((string) old('pincode', (string) ($setting['pincode'] ?? ''))) ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Address</label>
                    <input type="text" name="address_line" class="form-control" value="<?= esc((string) old('address_line', (string) ($setting['address_line'] ?? ''))) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Issuement Prefix</label>
                    <input type="text" name="issuement_suffix" class="form-control" value="<?= esc((string) old('issuement_suffix', (string) ($setting['issuement_suffix'] ?? 'ISS'))) ?>">
                    <small class="text-muted">Format: PREFIX + 3 digit serial (example: ISS001)</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Delivery Challan Prefix</label>
                    <input type="text" name="delivery_challan_suffix" class="form-control" value="<?= esc((string) old('delivery_challan_suffix', (string) ($setting['delivery_challan_suffix'] ?? 'DC'))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sale Bill Prefix</label>
                    <input type="text" name="sale_bill_suffix" class="form-control" value="<?= esc((string) old('sale_bill_suffix', (string) ($setting['sale_bill_suffix'] ?? 'SB'))) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Company Logo</label>
                    <input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <?php if (! empty($setting['logo_path'])): ?>
                        <div class="border rounded p-2">
                            <img src="<?= base_url((string) $setting['logo_path']) ?>" alt="Company Logo" style="max-height:60px;">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
