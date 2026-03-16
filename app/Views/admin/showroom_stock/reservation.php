<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= esc($formAction) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Reservation Details</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">FG Item</label>
                        <select name="fg_item_id" class="form-select select2" required>
                            <option value="">Select tag</option>
                            <?php foreach (($fgItems ?? []) as $item): ?>
                                <option value="<?= (int) $item['id'] ?>">
                                    <?= esc((string) $item['tag_no']) ?> / <?= esc((string) ($item['showroom_name'] ?? '-')) ?><?= ! empty($item['counter_name']) ? ' / ' . esc((string) $item['counter_name']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <select name="customer_id" class="form-select select2">
                            <option value="">Select customer</option>
                            <?php foreach (($customers ?? []) as $customer): ?>
                                <option value="<?= (int) $customer['id'] ?>"><?= esc((string) $customer['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Order Reference</label>
                        <select name="order_id" class="form-select select2">
                            <option value="">Select order</option>
                            <?php foreach (($orders ?? []) as $order): ?>
                                <option value="<?= (int) $order['id'] ?>"><?= esc((string) ($order['order_no'] ?? '-')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Reserved For Name</label>
                            <input type="text" name="reserved_for_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reserved For Phone</label>
                            <input type="text" name="reserved_for_phone" class="form-control">
                        </div>
                    </div>
                    <div class="mt-3 mb-3">
                        <label class="form-label">Expiry</label>
                        <input type="datetime-local" name="expires_on" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fe fe-save"></i> Reserve</button>
                        <a href="<?= site_url('admin/showroom-stock') ?>" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Reservable Items</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table datatable table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Tag</th>
                                    <th>Showroom</th>
                                    <th>Counter</th>
                                    <th>Gross</th>
                                    <th>Net Gold</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($fgItems ?? []) as $row): ?>
                                    <tr>
                                        <td><?= esc((string) ($row['tag_no'] ?? '-')) ?></td>
                                        <td><?= esc((string) ($row['showroom_name'] ?? '-')) ?></td>
                                        <td><?= esc((string) ($row['counter_name'] ?? '-')) ?></td>
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
