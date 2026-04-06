<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= esc($formAction) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Return Counter Stock</h5></div>
                <div class="card-body">
                    <div class="alert alert-light border mb-3">
                        Move selected FG tags back from counter to showroom floor inventory.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="4" placeholder="Counter return note"></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fe fe-corner-up-left"></i> Return To Showroom</button>
                        <a href="<?= site_url('admin/showroom-stock') ?>" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Counter Allocated Items</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table datatable table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Tag</th>
                                    <th>Showroom</th>
                                    <th>Counter</th>
                                    <th>Status</th>
                                    <th>Gross</th>
                                    <th>Net Gold</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($fgItems ?? []) as $row): ?>
                                    <tr>
                                        <td><input type="checkbox" name="fg_item_ids[]" value="<?= (int) $row['id'] ?>" <?= (int) ($prefillFgItemId ?? 0) === (int) $row['id'] ? 'checked' : '' ?>></td>
                                        <td><?= esc((string) ($row['tag_no'] ?? '-')) ?></td>
                                        <td><?= esc((string) ($row['showroom_name'] ?? '-')) ?></td>
                                        <td><?= esc((string) ($row['counter_name'] ?? '-')) ?></td>
                                        <td><?= esc((string) ($row['showroom_stock_status'] ?? '-')) ?></td>
                                        <td><?= number_format((float) ($row['gross_wt'] ?? 0), 3) ?></td>
                                        <td><?= number_format((float) ($row['net_gold_wt'] ?? 0), 3) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
