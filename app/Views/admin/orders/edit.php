<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$orderTypeValue = (string) old('order_type', (string) ($order['order_type'] ?? 'Sales'));
$showRepairFields = $orderTypeValue === 'Repair';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Edit Order: <?= esc($order['order_no']) ?></h4>
    <a href="<?= site_url((string) ($order['order_type'] ?? '') === 'Repair' ? 'admin/orders/repair' : 'admin/orders') ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= site_url('admin/orders/' . $order['id'] . '/update') ?>" method="post">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Order Type</label>
                    <select name="order_type" id="order-type-select" class="form-control" required>
                        <option value="Sales" <?= $orderTypeValue === 'Sales' ? 'selected' : '' ?>>Sales</option>
                        <option value="Manufacturing" <?= $orderTypeValue === 'Manufacturing' ? 'selected' : '' ?>>Manufacturing</option>
                        <option value="Repair" <?= $orderTypeValue === 'Repair' ? 'selected' : '' ?>>Repair</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-control">
                        <option value="">Select customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= esc((string) $customer['id']) ?>" <?= (string) old('customer_id', (string) ($order['customer_id'] ?? '')) === (string) $customer['id'] ? 'selected' : '' ?>>
                                <?= esc($customer['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Lead</label>
                    <select name="lead_id" class="form-control">
                        <option value="">Select lead</option>
                        <?php foreach ($leads as $lead): ?>
                            <option value="<?= esc((string) $lead['id']) ?>" <?= (string) old('lead_id', (string) ($order['lead_id'] ?? '')) === (string) $lead['id'] ? 'selected' : '' ?>>
                                <?= esc(($lead['lead_no'] ?? '') . ' - ' . $lead['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-control">
                        <?php foreach ($priorities as $priority): ?>
                            <option value="<?= esc($priority) ?>" <?= (string) old('priority', (string) ($order['priority'] ?? 'Medium')) === $priority ? 'selected' : '' ?>><?= esc($priority) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= esc((string) old('due_date', (string) ($order['due_date'] ?? ''))) ?>">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Order Notes</label>
                    <textarea name="order_notes" class="form-control" rows="3"><?= esc((string) old('order_notes', (string) ($order['order_notes'] ?? ''))) ?></textarea>
                </div>
            </div>

            <div id="repair-fields-wrap" class="border rounded p-3 mb-3" style="<?= $showRepairFields ? '' : 'display:none;' ?>">
                <div class="row">
                    <div class="col-12 mb-2">
                        <h6 class="mb-0">Repair Intake Details</h6>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Ornament Received Details</label>
                        <textarea name="repair_ornament_details" id="repair-ornament-details" class="form-control" rows="2"><?= esc((string) old('repair_ornament_details', (string) ($order['repair_ornament_details'] ?? ''))) ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Repair Work Details</label>
                        <textarea name="repair_work_details" id="repair-work-details" class="form-control" rows="2"><?= esc((string) old('repair_work_details', (string) ($order['repair_work_details'] ?? ''))) ?></textarea>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Receive Weight (gm)</label>
                        <input type="number" step="0.001" min="0" name="repair_receive_weight_gm" id="repair-receive-weight" class="form-control" value="<?= esc((string) old('repair_receive_weight_gm', (string) ($order['repair_receive_weight_gm'] ?? ''))) ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Received Date</label>
                        <input type="date" name="repair_received_at" id="repair-received-at" class="form-control" value="<?= esc((string) old('repair_received_at', (string) ($order['repair_received_at'] ?? ''))) ?>">
                    </div>
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Update Order</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        const orderTypeSelect = document.getElementById('order-type-select');
        const repairWrap = document.getElementById('repair-fields-wrap');
        const repairOrnament = document.getElementById('repair-ornament-details');
        const repairWork = document.getElementById('repair-work-details');
        const repairWeight = document.getElementById('repair-receive-weight');
        const repairDate = document.getElementById('repair-received-at');

        function toggleRepairFields() {
            if (!orderTypeSelect || !repairWrap) return;
            const isRepair = orderTypeSelect.value === 'Repair';
            repairWrap.style.display = isRepair ? '' : 'none';
            if (repairOrnament) repairOrnament.required = isRepair;
            if (repairWork) repairWork.required = isRepair;
            if (repairWeight) repairWeight.required = isRepair;
            if (repairDate) repairDate.required = isRepair;
        }

        if (orderTypeSelect) {
            orderTypeSelect.addEventListener('change', toggleRepairFields);
            toggleRepairFields();
        }
    })();
</script>
<?= $this->endSection() ?>
