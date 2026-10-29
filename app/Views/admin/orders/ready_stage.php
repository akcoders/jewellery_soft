<?= $this->extend('admin/layouts/main') ?>
<?= $this->section('content') ?>
<div class="erp-page-toolbar mb-4">
    <div>
        <h4 class="mb-1">Move Order to Ready</h4>
        <p class="text-muted mb-0">Select an unreceived order and correct its production stage.</p>
    </div>
    <a class="btn btn-light" href="<?= site_url('admin/orders') ?>">View orders</a>
</div>

<?php foreach (['success' => 'success', 'error' => 'danger'] as $key => $tone): ?>
    <?php if ($message = session()->getFlashdata($key)): ?>
        <div class="alert alert-<?= $tone ?>" role="alert"><?= esc((string) $message) ?></div>
    <?php endif; ?>
<?php endforeach; ?>

<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="alert alert-info">
            <strong>Ready → Receiving → Completed</strong>
            <div class="mt-1">Completed is recorded after finished-jewellery receiving. This page can also correct older Completed orders whose receiving is still pending.</div>
        </div>
        <div class="card">
            <div class="card-body p-4">
                <form method="get" action="<?= site_url('admin/orders/ready-stage') ?>" class="mb-4">
                    <label for="ready-search" class="form-label">Find an order</label>
                    <div class="d-flex flex-wrap gap-2">
                        <input id="ready-search" class="form-control flex-grow-1" style="flex-basis:220px;width:auto" type="search" name="q" value="<?= esc($query, 'attr') ?>" placeholder="Order number, order name or customer">
                        <button type="submit" class="btn btn-outline-primary">Search</button>
                        <?php if ($query !== ''): ?><a class="btn btn-light" href="<?= site_url('admin/orders/ready-stage') ?>">Clear</a><?php endif; ?>
                    </div>
                </form>
                <form method="post" action="<?= site_url('admin/orders/ready-stage') ?>" id="ready-stage-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="q" value="<?= esc($query, 'attr') ?>">
                    <label for="ready-order" class="form-label">Order to move to Ready</label>
                    <select class="form-select js-select2" id="ready-order" name="order_id" required aria-describedby="ready-help">
                        <option value="">Select an order</option>
                        <?php foreach ($orders as $order): ?>
                            <option value="<?= (int) $order['id'] ?>" data-status="<?= esc((string) $order['status'], 'attr') ?>" <?= (int) $order['id'] === $selectedOrderId ? 'selected' : '' ?>><?= esc(implode(' · ', array_filter([(string) $order['order_no'], (string) ($order['order_name'] ?? ''), (string) ($order['customer_name'] ?? ''), (string) $order['status']], static fn(string $part): bool => $part !== ''))) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="ready-help" class="form-text mt-2">Only unreceived orders are shown. Search above if you cannot find an older order.</div>
                    <?php if ($orders === []): ?>
                        <div class="alert alert-light border mt-3 mb-0">No eligible orders found<?= $query !== '' ? ' for this search' : '' ?>.</div>
                    <?php endif; ?>
                    <div class="rounded border bg-light p-3 mt-3" id="ready-selection" aria-live="polite">Select an order to see its current stage.</div>
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button class="btn btn-primary" type="submit" id="ready-submit" <?= $orders === [] ? 'disabled' : '' ?>>Mark selected order Ready</button>
                        <a class="btn btn-outline-secondary <?= $selectedOrderId > 0 ? '' : 'd-none' ?>" id="ready-details" href="<?= $selectedOrderId > 0 ? site_url('admin/orders/' . $selectedOrderId) : '#' ?>">Open order / receiving</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('ready-stage-form');
    const select = document.getElementById('ready-order');
    const summary = document.getElementById('ready-selection');
    const button = document.getElementById('ready-submit');
    const details = document.getElementById('ready-details');
    const base = <?= json_encode(rtrim(site_url('admin/orders'), '/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const update = function () {
        const option = select.selectedOptions[0];
        const selected = select.value !== '';
        summary.textContent = selected ? 'Current stage: ' + option.dataset.status + ' → Ready. Receiving remains pending.' : 'Select an order to see its current stage.';
        button.disabled = !selected;
        details.classList.toggle('d-none', !selected);
        if (selected) details.href = base + '/' + encodeURIComponent(select.value);
    };
    select.addEventListener('change', update);
    if (window.jQuery) window.jQuery(select).on('change', update);
    form.addEventListener('submit', function () {
        if (form.checkValidity()) {
            button.disabled = true;
            button.textContent = 'Saving Ready stage…';
        }
    });
    update();
});
</script>
<?= $this->endSection() ?>
