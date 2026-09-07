<?php
$alerts = $adminAlertCenter ?? [];
$payments = $alerts['payments'] ?? ['count' => 0, 'amount' => 0, 'items' => []];
$orders = $alerts['orders'] ?? ['count' => 0, 'overdue_count' => 0, 'items' => []];
$followups = $alerts['followups'] ?? ['count' => 0, 'overdue_count' => 0, 'items' => []];
$total = (int) ($alerts['total_count'] ?? 0);
$formatDate = static function (?string $date, bool $withTime = false): string {
    $value = trim((string) $date);
    if ($value === '') {
        return 'Not set';
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date($withTime ? 'd M, h:i A' : 'd M Y', $timestamp);
};
?>
<div class="modal fade admin-alert-modal" id="adminAlertCenterModal" tabindex="-1" aria-labelledby="adminAlertCenterTitle" aria-hidden="true" data-auto-open="<?= ! empty($showAdminAlertCenter) ? '1' : '0' ?>">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header admin-alert-modal__header">
                <div>
                    <span class="admin-alert-modal__eyebrow">ACTION CENTRE</span>
                    <h4 class="modal-title" id="adminAlertCenterTitle">Pending attention</h4>
                    <p class="mb-0">Payments, open orders and follow-ups that need action.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="admin-alert-summary">
                    <?php if ($canAccounts): ?>
                    <button type="button" class="admin-alert-stat admin-alert-stat--payment" data-alert-target="adminAlertPayments">
                        <span class="admin-alert-stat__icon"><i class="fe fe-credit-card"></i></span>
                        <span><small>Pending payments</small><strong><?= number_format((int) ($payments['count'] ?? 0)) ?></strong><em>₹<?= number_format((float) ($payments['amount'] ?? 0), 2) ?></em></span>
                    </button>
                    <?php endif; ?>
                    <?php if ($canOrders): ?>
                    <button type="button" class="admin-alert-stat admin-alert-stat--order" data-alert-target="adminAlertOrders">
                        <span class="admin-alert-stat__icon"><i class="fe fe-package"></i></span>
                        <span><small>Pending orders</small><strong><?= number_format((int) ($orders['count'] ?? 0)) ?></strong><em><?= number_format((int) ($orders['overdue_count'] ?? 0)) ?> delayed</em></span>
                    </button>
                    <button type="button" class="admin-alert-stat admin-alert-stat--followup" data-alert-target="adminAlertFollowups">
                        <span class="admin-alert-stat__icon"><i class="fe fe-clock"></i></span>
                        <span><small>Follow-ups due</small><strong><?= number_format((int) ($followups['count'] ?? 0)) ?></strong><em><?= number_format((int) ($followups['overdue_count'] ?? 0)) ?> overdue</em></span>
                    </button>
                    <?php endif; ?>
                </div>

                <?php if ($total === 0): ?>
                    <div class="admin-alert-empty">
                        <span><i class="fe fe-check-circle"></i></span>
                        <h5>Everything is up to date</h5>
                        <p>No pending alerts are available for your access.</p>
                    </div>
                <?php else: ?>
                    <?php if ($canAccounts): ?>
                    <section class="admin-alert-section" id="adminAlertPayments">
                        <div class="admin-alert-section__head">
                            <div><span class="admin-alert-section__icon payment"><i class="fe fe-credit-card"></i></span><div><h5>Pending payments</h5><p>Purchase and labour bills with an unpaid balance</p></div></div>
                            <a href="<?= site_url('admin/accounts/payments') ?>">View payments <i class="fe fe-arrow-up-right"></i></a>
                        </div>
                        <?php if (empty($payments['items'])): ?>
                            <p class="admin-alert-section__empty">No pending payment.</p>
                        <?php else: ?>
                            <div class="admin-alert-list">
                                <?php foreach ($payments['items'] as $item): ?>
                                    <a class="admin-alert-row" href="<?= esc((string) ($item['url'] ?? site_url('admin/accounts/payments'))) ?>">
                                        <span class="admin-alert-row__marker payment"><i class="fe fe-file-text"></i></span>
                                        <span class="admin-alert-row__main"><strong><?= esc((string) ($item['party'] ?? 'Party')) ?></strong><small><span class="admin-alert-type-badge"><?= esc((string) ($item['type'] ?? 'Payment')) ?></span> <?= esc((string) ($item['reference'] ?? '')) ?></small></span>
                                        <span class="admin-alert-row__meta"><strong>₹<?= number_format((float) ($item['pending_amount'] ?? 0), 2) ?></strong><small class="<?= ! empty($item['is_overdue']) ? 'text-danger' : '' ?>"><?= ! empty($item['is_overdue']) ? 'Overdue · ' : 'Due · ' ?><?= esc($formatDate((string) ($item['due_date'] ?? ''))) ?></small></span>
                                        <i class="fe fe-chevron-right admin-alert-row__arrow"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>

                    <?php if ($canOrders): ?>
                    <section class="admin-alert-section" id="adminAlertOrders">
                        <div class="admin-alert-section__head">
                            <div><span class="admin-alert-section__icon order"><i class="fe fe-package"></i></span><div><h5>Pending orders</h5><p>Active orders that have not reached a final status</p></div></div>
                            <a href="<?= site_url('admin/orders') ?>">View orders <i class="fe fe-arrow-up-right"></i></a>
                        </div>
                        <?php if (empty($orders['items'])): ?>
                            <p class="admin-alert-section__empty">No pending order.</p>
                        <?php else: ?>
                            <div class="admin-alert-list">
                                <?php foreach ($orders['items'] as $item): ?>
                                    <a class="admin-alert-row" href="<?= esc((string) ($item['url'] ?? site_url('admin/orders'))) ?>">
                                        <?php if (! empty($item['thumbnail_url'])): ?>
                                            <img class="admin-alert-row__thumb" src="<?= esc((string) $item['thumbnail_url']) ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <span class="admin-alert-row__marker order"><i class="fe fe-image"></i></span>
                                        <?php endif; ?>
                                        <span class="admin-alert-row__main"><strong><?= esc((string) (($item['order_name'] ?? '') ?: ($item['order_no'] ?? '') ?: 'Order')) ?></strong><small><?= esc((string) ($item['order_no'] ?? '')) ?> <span class="admin-alert-status"><?= esc((string) ($item['status'] ?? 'Pending')) ?></span></small></span>
                                        <span class="admin-alert-row__meta"><strong class="<?= ! empty($item['is_overdue']) ? 'text-danger' : '' ?>"><?= ! empty($item['is_overdue']) ? 'Delayed' : 'Open' ?></strong><small>Due · <?= esc($formatDate((string) ($item['due_date'] ?? ''))) ?></small></span>
                                        <i class="fe fe-chevron-right admin-alert-row__arrow"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="admin-alert-section" id="adminAlertFollowups">
                        <div class="admin-alert-section__head">
                            <div><span class="admin-alert-section__icon followup"><i class="fe fe-clock"></i></span><div><h5>Follow-ups due</h5><p>Follow-ups due today or already overdue</p></div></div>
                            <a href="<?= site_url('admin/orders/followups') ?>">View follow-ups <i class="fe fe-arrow-up-right"></i></a>
                        </div>
                        <?php if (empty($followups['items'])): ?>
                            <p class="admin-alert-section__empty">No follow-up is due.</p>
                        <?php else: ?>
                            <div class="admin-alert-list">
                                <?php foreach ($followups['items'] as $item): ?>
                                    <a class="admin-alert-row" href="<?= esc((string) ($item['url'] ?? site_url('admin/orders/followups'))) ?>">
                                        <?php if (! empty($item['thumbnail_url'])): ?>
                                            <img class="admin-alert-row__thumb" src="<?= esc((string) $item['thumbnail_url']) ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <span class="admin-alert-row__marker followup"><i class="fe fe-phone-call"></i></span>
                                        <?php endif; ?>
                                        <span class="admin-alert-row__main"><strong><?= esc((string) (($item['order_name'] ?? '') ?: ($item['order_no'] ?? '') ?: 'Order')) ?></strong><small><?= esc((string) ($item['order_no'] ?? '')) ?><?= ! empty($item['stage']) ? ' · ' . esc((string) $item['stage']) : '' ?></small></span>
                                        <span class="admin-alert-row__meta"><strong class="<?= ! empty($item['is_overdue']) ? 'text-danger' : 'text-warning' ?>"><?= ! empty($item['is_overdue']) ? 'Overdue' : 'Due today' ?></strong><small><?= esc($formatDate((string) ($item['due_at'] ?? ''), true)) ?></small></span>
                                        <i class="fe fe-chevron-right admin-alert-row__arrow"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="modal-footer admin-alert-modal__footer">
                <small>Updated <?= esc((string) ($alerts['generated_at'] ?? 'just now')) ?></small>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
