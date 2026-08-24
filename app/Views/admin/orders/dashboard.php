<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$summary = is_array($summary ?? null) ? $summary : [];
$statusCounts = is_array($statusCounts ?? null) ? $statusCounts : [];
$selectedStatus = (string) ($selectedStatus ?? '');
$selectedView = (string) ($selectedView ?? '');
$publicOrderRequestUrl = (string) ($publicOrderRequestUrl ?? site_url('order-request'));
$statusClass = static fn(string $status): string => match ($status) {
    'Completed', 'Dispatched', 'Ready' => 'success',
    'Cancelled' => 'danger',
    'QC', 'Packed' => 'info',
    'In Production' => 'warning',
    default => 'secondary',
};
$formatDate = static function (?string $date, string $fallback = '-'): string {
    $value = trim((string) $date);
    if ($value === '') {
        return $fallback;
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? $fallback : date('d M Y', $timestamp);
};
?>

<style>
    .order-dashboard-summary {
        display: grid;
        gap: 14px;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .order-summary-card {
        align-items: center;
        background: #fff;
        border: 1px solid var(--erp-border);
        border-radius: 14px;
        box-shadow: var(--erp-shadow);
        color: var(--erp-ink) !important;
        display: flex;
        gap: 14px;
        min-height: 112px;
        padding: 18px;
        text-decoration: none;
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .order-summary-card:hover,
    .order-summary-card.active {
        box-shadow: 0 14px 34px rgba(31, 41, 55, .11);
        color: var(--erp-ink) !important;
        transform: translateY(-2px);
    }
    .order-summary-icon {
        align-items: center;
        background: #f4f6f8;
        border-radius: 12px;
        color: #475467;
        display: inline-flex;
        flex: 0 0 48px;
        font-size: 20px;
        height: 48px;
        justify-content: center;
    }
    .order-summary-card.delay .order-summary-icon { background: #feecee; color: #bd1727; }
    .order-summary-card.repeat .order-summary-icon { background: #fff4d5; color: #9b7010; }
    .order-summary-card.design .order-summary-icon { background: #eeeaff; color: #6851c8; }
    .order-summary-card small,
    .order-summary-card strong,
    .order-summary-card span { display: block; }
    .order-summary-card small { color: #667085; font-size: 11px; font-weight: 700; }
    .order-summary-card strong { color: #18202f; font-size: 27px; line-height: 1.1; margin: 4px 0; }
    .order-summary-card span { color: #98a2b3; font-size: 10px; }
    .order-status-strip {
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    }
    .order-status-card {
        background: #fff;
        border: 1px solid var(--erp-border);
        border-radius: 12px;
        color: #475467 !important;
        padding: 13px 14px;
        text-decoration: none;
    }
    .order-status-card:hover,
    .order-status-card.active {
        border-color: rgba(179, 18, 31, .42);
        box-shadow: 0 8px 22px rgba(31, 41, 55, .07);
        color: var(--erp-red) !important;
    }
    .order-status-card strong { color: #18202f; display: block; font-size: 21px; }
    .order-status-card span { font-size: 11px; font-weight: 700; }
    .repeat-design-stack { display: flex; flex-direction: column; gap: 5px; }
    .repeat-design-badge {
        background: #fff3cd;
        border: 1px solid #f0d68a;
        border-radius: 999px;
        color: #7a5709;
        display: inline-flex;
        font-size: 10px;
        font-weight: 750;
        gap: 5px;
        max-width: 240px;
        padding: 5px 8px;
        width: fit-content;
    }
    .repeat-design-badge span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .delay-box { max-width: 280px; }
    .delay-reason {
        color: #667085;
        display: -webkit-box;
        font-size: 11px;
        line-height: 1.35;
        margin-top: 5px;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }
    .timeline-shell { position: relative; }
    .timeline-entry {
        border-left: 2px solid #ead9dc;
        margin-left: 10px;
        padding: 0 0 22px 24px;
        position: relative;
    }
    .timeline-entry:last-child { padding-bottom: 0; }
    .timeline-entry::before {
        background: #fff;
        border: 3px solid var(--erp-red);
        border-radius: 50%;
        content: '';
        height: 14px;
        left: -8px;
        position: absolute;
        top: 3px;
        width: 14px;
    }
    .timeline-entry-card {
        background: #fafbfc;
        border: 1px solid #edf0f4;
        border-radius: 12px;
        padding: 14px;
    }
    .timeline-image {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        height: 112px;
        object-fit: cover;
        width: 150px;
    }
    .order-image-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
    }
    .order-image-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 11px;
        color: #344054 !important;
        overflow: hidden;
        text-decoration: none;
    }
    .order-image-card img { height: 125px; object-fit: cover; width: 100%; }
    .order-image-card span { display: block; font-size: 10px; overflow: hidden; padding: 8px; text-overflow: ellipsis; white-space: nowrap; }
    @media (max-width: 991px) { .order-dashboard-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 575px) {
        .order-dashboard-summary { grid-template-columns: 1fr; }
        .timeline-entry { padding-left: 17px; }
        .timeline-image { height: 100px; width: 100%; }
    }
</style>

<div class="erp-page-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Order intelligence</span>
        <h4 class="mb-1">Order Dashboard</h4>
        <p class="mb-0">Monitor status, delayed work, repeat designs and the complete follow-up timeline.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (admin_can('orders.create')): ?>
            <button type="button" id="copy-dashboard-order-link" class="btn btn-outline-secondary" data-copy-url="<?= esc($publicOrderRequestUrl, 'attr') ?>">
                <i class="fe fe-copy me-1"></i> <span>Copy External Order Link</span>
            </button>
            <a href="<?= site_url('admin/orders/create') ?>" class="btn btn-primary"><i class="fe fe-plus me-1"></i> Create Order</a>
        <?php endif; ?>
        <a href="<?= site_url('admin/orders') ?>" class="btn btn-outline-primary"><i class="fe fe-list me-1"></i> Order List</a>
    </div>
</div>

<div class="order-dashboard-summary mb-3">
    <a href="<?= site_url('admin/orders/dashboard') ?>" class="order-summary-card <?= $selectedStatus === '' && $selectedView === '' ? 'active' : '' ?>">
        <span class="order-summary-icon"><i class="fe fe-shopping-bag"></i></span>
        <span><small>All Orders</small><strong><?= esc((string) ($summary['total_orders'] ?? 0)) ?></strong><span>Complete order register</span></span>
    </a>
    <a href="<?= site_url('admin/orders/dashboard?view=delayed') ?>" class="order-summary-card delay <?= $selectedView === 'delayed' ? 'active' : '' ?>">
        <span class="order-summary-icon"><i class="fe fe-alert-triangle"></i></span>
        <span><small>Delayed Orders</small><strong><?= esc((string) ($summary['delayed_orders'] ?? 0)) ?></strong><span>Past due and still open</span></span>
    </a>
    <a href="<?= site_url('admin/orders/dashboard?view=repeat') ?>" class="order-summary-card repeat <?= $selectedView === 'repeat' ? 'active' : '' ?>">
        <span class="order-summary-icon"><i class="fe fe-repeat"></i></span>
        <span><small>Orders With Repeat Design</small><strong><?= esc((string) ($summary['repeat_orders'] ?? 0)) ?></strong><span>Orders containing reused designs</span></span>
    </a>
    <a href="<?= site_url('admin/orders/dashboard?view=repeat') ?>" class="order-summary-card design <?= $selectedView === 'repeat' ? 'active' : '' ?>">
        <span class="order-summary-icon"><i class="fe fe-image"></i></span>
        <span><small>Unique Repeat Designs</small><strong><?= esc((string) ($summary['repeat_designs'] ?? 0)) ?></strong><span>Designs used in multiple orders</span></span>
    </a>
</div>

<div class="order-status-strip mb-3">
    <?php foreach ($statusCounts as $status => $count): ?>
        <a href="<?= site_url('admin/orders/dashboard?status=' . rawurlencode((string) $status)) ?>" class="order-status-card <?= $selectedStatus === (string) $status ? 'active' : '' ?>">
            <strong><?= esc((string) $count) ?></strong>
            <span><?= esc((string) $status) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="card overflow-hidden">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h5 class="card-title mb-1">Order Status Register</h5>
            <div class="small text-muted">
                <?= esc((string) count($orders)) ?> order<?= count($orders) === 1 ? '' : 's' ?> shown
                <?php if ($selectedStatus !== ''): ?> · Status: <?= esc($selectedStatus) ?><?php endif; ?>
                <?php if ($selectedView === 'delayed'): ?> · Delayed only<?php endif; ?>
                <?php if ($selectedView === 'repeat'): ?> · Repeat designs only<?php endif; ?>
            </div>
        </div>
        <?php if ($selectedStatus !== '' || $selectedView !== ''): ?>
            <a href="<?= site_url('admin/orders/dashboard') ?>" class="btn btn-sm btn-outline-secondary"><i class="fe fe-x me-1"></i> Clear Filter</a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-hover align-middle mb-0" data-dt-page-length="25">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer / Source</th>
                        <th>Repeat Design</th>
                        <th>Status</th>
                        <th>Due / Delay</th>
                        <th>Karigar</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders === []): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">No orders match this dashboard filter.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $order): ?>
                        <?php $source = trim((string) (($order['customer_name'] ?? '') ?: ($order['order_from'] ?? '') ?: '-')); ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('admin/orders/' . (int) $order['id']) ?>" class="erp-data-link"><?= esc((string) $order['order_no']) ?></a>
                                <div class="small text-muted mt-1"><?= esc((string) ($order['order_type'] ?? '-')) ?> · <?= esc($formatDate((string) ($order['created_at'] ?? ''))) ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= esc($source) ?></div>
                                <?php if (! empty($order['order_from']) && ! empty($order['customer_name'])): ?>
                                    <div class="small text-muted">From: <?= esc((string) $order['order_from']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (! empty($order['repeat_designs'])): ?>
                                    <div class="repeat-design-stack">
                                        <?php foreach ($order['repeat_designs'] as $design): ?>
                                            <span class="repeat-design-badge" title="This design appears in <?= esc((string) $design['count']) ?> orders">
                                                <i class="fe fe-repeat"></i>
                                                <span><?= esc((string) $design['name']) ?></span>
                                                <strong>×<?= esc((string) $design['count']) ?></strong>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">First-time design</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-<?= esc($statusClass((string) ($order['status'] ?? ''))) ?>"><?= esc((string) ($order['status'] ?? '-')) ?></span></td>
                            <td>
                                <div class="delay-box">
                                    <div class="fw-semibold"><?= esc($formatDate((string) ($order['due_date'] ?? ''), 'Not set')) ?></div>
                                    <?php if (! empty($order['is_delayed'])): ?>
                                        <span class="badge bg-danger mt-1"><i class="fe fe-clock me-1"></i>Delay · <?= esc((string) $order['delay_days']) ?> day<?= (int) $order['delay_days'] === 1 ? '' : 's' ?></span>
                                        <div class="delay-reason" title="<?= esc((string) $order['delay_reason'], 'attr') ?>">
                                            <strong>Why:</strong> <?= esc((string) $order['delay_reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= esc((string) (($order['karigar_name'] ?? '') ?: 'Not assigned')) ?></td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary js-order-timeline"
                                    data-order-no="<?= esc((string) $order['order_no'], 'attr') ?>"
                                    data-timeline-url="<?= esc(site_url('admin/orders/' . (int) $order['id'] . '/timeline'), 'attr') ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#orderTimelineModal">
                                    <i class="fe fe-clock me-1"></i> Timeline
                                </button>
                                <a href="<?= site_url('admin/orders/' . (int) $order['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Open order"><i class="fe fe-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="orderTimelineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="erp-eyebrow">Order activity</span>
                    <h5 class="modal-title">Timeline · <span id="timeline-order-no">Order</span></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="timeline-modal-body">
                <div class="text-center text-muted py-5">
                    <span class="spinner-border text-primary" aria-hidden="true"></span>
                    <div class="mt-2">Loading order timeline...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        const timelineModal = document.getElementById('orderTimelineModal');
        const timelineBody = document.getElementById('timeline-modal-body');
        const timelineOrderNo = document.getElementById('timeline-order-no');
        let requestSequence = 0;

        function esc(value) {
            const node = document.createElement('div');
            node.textContent = String(value == null ? '' : value);
            return node.innerHTML;
        }

        function safeUrl(value) {
            try {
                const url = new URL(String(value || ''), window.location.origin);
                return ['http:', 'https:'].includes(url.protocol) ? esc(url.href) : '';
            } catch (error) {
                return '';
            }
        }

        function formatDateTime(value) {
            if (!value) return '-';
            const parsed = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(parsed.getTime())) return esc(value);
            return esc(parsed.toLocaleString('en-IN', {
                day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
            }));
        }

        function loadingHtml() {
            return '<div class="text-center text-muted py-5">'
                + '<span class="spinner-border text-primary" aria-hidden="true"></span>'
                + '<div class="mt-2">Loading order timeline...</div></div>';
        }

        function renderTimeline(data) {
            const order = data.order || {};
            const followups = Array.isArray(data.followups) ? data.followups : [];
            const history = Array.isArray(data.status_history) ? data.status_history : [];
            const images = Array.isArray(data.images) ? data.images : [];
            const source = order.customer_name || order.order_from || '-';

            const followupHtml = followups.length === 0
                ? '<div class="alert alert-light border text-muted mb-0">No follow-ups have been recorded for this order.</div>'
                : '<div class="timeline-shell">' + followups.map(function (row) {
                    const imageUrl = safeUrl(row.image_url);
                    const image = imageUrl
                        ? '<a href="' + imageUrl + '" target="_blank" rel="noopener" class="d-inline-block mt-3">'
                            + '<img src="' + imageUrl + '" alt="Follow-up image" class="timeline-image"></a>'
                        : '';
                    const nextDate = row.next_followup_date
                        ? '<span class="badge bg-light text-dark border">Next: ' + formatDateTime(row.next_followup_date) + '</span>'
                        : '';
                    return '<div class="timeline-entry"><div class="timeline-entry-card">'
                        + '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">'
                        + '<span class="badge bg-primary">' + esc(row.stage || '-') + '</span>'
                        + '<span class="small text-muted">' + formatDateTime(row.taken_on) + '</span></div>'
                        + '<div class="text-dark">' + esc(row.description || 'No description') + '</div>'
                        + '<div class="d-flex flex-wrap align-items-center gap-2 small text-muted mt-2">'
                        + '<span><i class="fe fe-user me-1"></i>' + esc(row.taken_by || 'Admin') + '</span>' + nextDate + '</div>'
                        + image + '</div></div>';
                }).join('') + '</div>';

            const imagesHtml = images.length === 0
                ? '<div class="text-muted small">No order images attached.</div>'
                : '<div class="order-image-grid">' + images.map(function (image) {
                    const url = safeUrl(image.url);
                    if (!url) return '';
                    return '<a href="' + url + '" target="_blank" rel="noopener" class="order-image-card">'
                        + '<img src="' + url + '" alt="' + esc(image.name || 'Order image') + '">'
                        + '<span>' + esc(image.name || image.type || 'Order image') + '</span></a>';
                }).join('') + '</div>';

            const historyHtml = history.length === 0
                ? '<div class="text-muted small">No status changes recorded.</div>'
                : '<div class="list-group list-group-flush">' + history.map(function (row) {
                    return '<div class="list-group-item px-0">'
                        + '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2">'
                        + '<div><span class="badge bg-light text-dark border">' + esc(row.from_status) + '</span>'
                        + '<i class="fe fe-arrow-right mx-2 text-muted"></i><span class="badge bg-secondary">' + esc(row.to_status) + '</span></div>'
                        + '<span class="small text-muted">' + formatDateTime(row.created_at) + '</span></div>'
                        + (row.remarks ? '<div class="small text-muted mt-2">' + esc(row.remarks) + '</div>' : '')
                        + '<div class="small text-muted mt-1">By ' + esc(row.changed_by || 'System') + '</div></div>';
                }).join('') + '</div>';

            timelineBody.innerHTML = '<div class="rounded-3 border bg-light p-3 mb-4">'
                + '<div class="row g-3"><div class="col-md-3"><small class="text-muted d-block">Order</small><strong>' + esc(order.order_no || '-') + '</strong></div>'
                + '<div class="col-md-3"><small class="text-muted d-block">Customer / Source</small><strong>' + esc(source) + '</strong></div>'
                + '<div class="col-md-2"><small class="text-muted d-block">Status</small><strong>' + esc(order.status || '-') + '</strong></div>'
                + '<div class="col-md-2"><small class="text-muted d-block">Karigar</small><strong>' + esc(order.karigar_name || 'Not assigned') + '</strong></div>'
                + '<div class="col-md-2"><small class="text-muted d-block">Due</small><strong>' + esc(order.due_date || 'Not set') + '</strong></div></div></div>'
                + '<div class="row g-4"><div class="col-lg-7"><h6 class="mb-3">All Follow-ups (' + followups.length + ')</h6>' + followupHtml + '</div>'
                + '<div class="col-lg-5"><h6 class="mb-3">Order Images (' + images.length + ')</h6>' + imagesHtml
                + '<hr class="my-4"><h6 class="mb-3">Status History</h6>' + historyHtml + '</div></div>';
        }

        if (timelineModal) {
            timelineModal.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;
                if (!(trigger instanceof Element)) return;
                const url = trigger.getAttribute('data-timeline-url') || '';
                const orderNo = trigger.getAttribute('data-order-no') || 'Order';
                requestSequence += 1;
                const currentRequest = requestSequence;
                if (timelineOrderNo) timelineOrderNo.textContent = orderNo;
                if (timelineBody) timelineBody.innerHTML = loadingHtml();

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Timeline request failed.');
                        return response.json();
                    })
                    .then(function (payload) {
                        if (currentRequest !== requestSequence || !timelineBody) return;
                        if (!payload || payload.status !== 'ok') throw new Error(payload && payload.message ? payload.message : 'Timeline unavailable.');
                        renderTimeline(payload.data || {});
                    })
                    .catch(function (error) {
                        if (currentRequest !== requestSequence || !timelineBody) return;
                        timelineBody.innerHTML = '<div class="alert alert-danger mb-0">' + esc(error.message || 'Could not load timeline.') + '</div>';
                    });
            });
        }

        const copyButton = document.getElementById('copy-dashboard-order-link');
        if (copyButton) {
            copyButton.addEventListener('click', async function () {
                const url = copyButton.getAttribute('data-copy-url') || '';
                try {
                    await navigator.clipboard.writeText(url);
                    const label = copyButton.querySelector('span');
                    if (label) label.textContent = 'Link Copied';
                    copyButton.classList.add('btn-success');
                    window.setTimeout(function () {
                        if (label) label.textContent = 'Copy External Order Link';
                        copyButton.classList.remove('btn-success');
                    }, 2200);
                } catch (error) {
                    window.prompt('Copy this external order link:', url);
                }
            });
        }
    })();
</script>
<?= $this->endSection() ?>
