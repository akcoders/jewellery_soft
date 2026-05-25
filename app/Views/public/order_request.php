<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Order Request') ?></title>
    <link rel="shortcut icon" href="<?= base_url('template/assets/img/favicon.png') ?>">
    <link rel="stylesheet" href="<?= base_url('template/assets/css/bootstrap.min.css') ?>">
    <style>
        body { background: #f6f7fb; color: #1f2937; }
        .page-shell { max-width: 980px; margin: 0 auto; padding: 28px 14px; }
        .brand-logo { max-height: 64px; width: auto; }
        .form-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }
        .section-title { font-size: 15px; font-weight: 700; color: #111827; margin-bottom: 14px; }
        .form-label { font-weight: 600; font-size: 13px; }
    </style>
</head>
<body>
    <main class="page-shell">
        <div class="text-center mb-4">
            <img src="<?= base_url('template/assets/img/logo.png') ?>" alt="Logo" class="brand-logo mb-3">
            <h3 class="mb-1">Create Order Request</h3>
            <p class="text-muted mb-0">Submit jewellery order details and WhatsApp notification number.</p>
        </div>

        <?php if (! empty($successOrderNo)): ?>
            <div class="alert alert-success">
                Order request submitted successfully. Reference: <strong><?= esc((string) $successOrderNo) ?></strong>
            </div>
        <?php endif; ?>
        <?php if (session('error')): ?>
            <div class="alert alert-danger"><?= esc((string) session('error')) ?></div>
        <?php endif; ?>

        <div class="form-card">
            <div class="p-4">
                <form method="post" action="<?= site_url('order-request') ?>">
                    <?= csrf_field() ?>

                    <div class="section-title">Customer Details</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Customer Name</label>
                            <input type="text" name="customer_name" class="form-control" value="<?= esc((string) old('customer_name')) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" value="<?= esc((string) old('phone')) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">WhatsApp Notification No</label>
                            <input type="tel" name="whatsapp_notification_number" class="form-control" value="<?= esc((string) old('whatsapp_notification_number')) ?>" placeholder="Leave blank to use phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= esc((string) old('email')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="<?= esc((string) old('city')) ?>">
                        </div>
                    </div>

                    <div class="section-title">Order Details</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Order Type</label>
                            <select name="order_type" id="public-order-type" class="form-select" required>
                                <?php foreach (['Sales', 'Manufacturing', 'Repair'] as $type): ?>
                                    <option value="<?= esc($type) ?>" <?= (string) old('order_type', 'Sales') === $type ? 'selected' : '' ?>><?= esc($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Product / Ornament</label>
                            <input type="text" name="product_name" class="form-control" value="<?= esc((string) old('product_name')) ?>" placeholder="Ring, bangle, necklace, repair work" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Size</label>
                            <input type="text" name="size_label" class="form-control" value="<?= esc((string) old('size_label')) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Qty</label>
                            <input type="number" name="qty" min="1" class="form-control" value="<?= esc((string) old('qty', '1')) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Gold Required (gm)</label>
                            <input type="number" step="0.001" min="0" name="gold_required_gm" class="form-control" value="<?= esc((string) old('gold_required_gm', '0')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Diamond Required (cts)</label>
                            <input type="number" step="0.001" min="0" name="diamond_required_cts" class="form-control" value="<?= esc((string) old('diamond_required_cts', '0')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Expected Date</label>
                            <input type="date" name="due_date" class="form-control" value="<?= esc((string) old('due_date')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Diamond Details</label>
                            <textarea name="expected_diamond_spec" class="form-control" rows="3" placeholder="Shape, quality, color, size, pcs"><?= esc((string) old('expected_diamond_spec')) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Stone / Other Details</label>
                            <textarea name="expected_stone_spec" class="form-control" rows="3" placeholder="Ruby, emerald, CZ, enamel, plating, etc."><?= esc((string) old('expected_stone_spec')) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Order Notes</label>
                            <textarea name="order_notes" class="form-control" rows="4" placeholder="Design idea, budget, occasion, delivery instructions"><?= esc((string) old('order_notes')) ?></textarea>
                        </div>
                    </div>

                    <div id="public-repair-fields" class="border rounded p-3 mb-4" style="display:none;">
                        <div class="section-title">Repair Details</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Ornament Received Details</label>
                                <textarea name="repair_ornament_details" class="form-control" rows="2"><?= esc((string) old('repair_ornament_details')) ?></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Repair Work Details</label>
                                <textarea name="repair_work_details" class="form-control" rows="2"><?= esc((string) old('repair_work_details')) ?></textarea>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Weight (gm)</label>
                                <input type="number" step="0.001" min="0" name="repair_receive_weight_gm" class="form-control" value="<?= esc((string) old('repair_receive_weight_gm')) ?>">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Submit Order Request</button>
                </form>
            </div>
        </div>
    </main>

    <script>
        (function () {
            const type = document.getElementById('public-order-type');
            const repair = document.getElementById('public-repair-fields');
            function toggleRepair() {
                if (!type || !repair) return;
                repair.style.display = type.value === 'Repair' ? '' : 'none';
            }
            if (type) type.addEventListener('change', toggleRepair);
            toggleRepair();
        })();
    </script>
</body>
</html>
