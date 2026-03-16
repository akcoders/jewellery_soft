<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= esc($formAction) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Counter Allocation</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Counter</label>
                        <select name="showroom_counter_id" class="form-select select2" required>
                            <option value="">Select counter</option>
                            <?php foreach (($counters ?? []) as $counter): ?>
                                <option value="<?= (int) $counter['id'] ?>"><?= esc((string) $counter['showroom_name']) ?> / <?= esc((string) $counter['counter_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fe fe-save"></i> Allocate</button>
                        <a href="<?= site_url('admin/showroom-stock') ?>" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Showroom Available FG</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table datatable table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Tag</th>
                                    <th>Showroom</th>
                                    <th>Status</th>
                                    <th>Gross</th>
                                    <th>Net Gold</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($fgItems ?? []) as $row): ?>
                                    <tr>
                                        <td><input type="checkbox" name="fg_item_ids[]" value="<?= (int) $row['id'] ?>"></td>
                                        <td><?= esc((string) ($row['tag_no'] ?? '-')) ?></td>
                                        <td><?= esc((string) ($row['showroom_name'] ?? '-')) ?></td>
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
