<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= esc($formAction) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Transfer Setup</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Showroom</label>
                        <select name="showroom_id" class="form-select select2" required>
                            <option value="">Select showroom</option>
                            <?php foreach (($showrooms ?? []) as $showroom): ?>
                                <option value="<?= (int) $showroom['id'] ?>"><?= esc((string) $showroom['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="4" placeholder="Reason / transfer note"></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fe fe-save"></i> Transfer</button>
                        <a href="<?= site_url('admin/showroom-stock') ?>" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">FG Items In Store</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table datatable table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Tag</th>
                                    <th>Order</th>
                                    <th>Gross</th>
                                    <th>Net Gold</th>
                                    <th>Diamond</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($fgItems ?? []) as $row): ?>
                                    <tr>
                                        <td><input type="checkbox" name="fg_item_ids[]" value="<?= (int) $row['id'] ?>"></td>
                                        <td><?= esc((string) ($row['tag_no'] ?? '-')) ?></td>
                                        <td><?= esc((string) ($row['order_no'] ?? '-')) ?></td>
                                        <td><?= number_format((float) ($row['gross_wt'] ?? 0), 3) ?></td>
                                        <td><?= number_format((float) ($row['net_gold_wt'] ?? 0), 3) ?></td>
                                        <td><?= number_format((float) ($row['diamond_cts'] ?? 0), 3) ?></td>
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
