<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-4 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Add Vendor</h5></div>
            <div class="card-body">
                <form action="<?= site_url('admin/vendors') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">GSTIN</label>
                        <input type="text" name="gstin" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                    <button class="btn btn-primary w-100">Save Vendor</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Vendor List</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datatable table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>GSTIN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($vendors === []): ?>
                                <tr><td colspan="5" class="text-muted text-center">No vendors found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($vendors as $v): ?>
                                <tr>
                                    <td><?= esc($v['name']) ?></td>
                                    <td><?= esc($v['contact_person'] ?: '-') ?></td>
                                    <td><?= esc($v['phone'] ?: '-') ?></td>
                                    <td><?= esc($v['email'] ?: '-') ?></td>
                                    <td><?= esc($v['gstin'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

