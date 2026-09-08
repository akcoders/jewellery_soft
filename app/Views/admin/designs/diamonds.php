<div class="mb-3">
    <div class="fw-semibold text-dark"><?= esc((string) $design['name']) ?></div>
    <span class="small text-muted"><?= esc((string) $design['design_code']) ?></span>
</div>
<?php if ($orders === []): ?>
    <div class="alert alert-light border mb-0">No production order is linked to this design yet. Diamond sizes will appear here when a linked order has size records.</div>
<?php else: ?>
    <?php if (count($orders) > 1): ?>
        <label for="design-diamond-order" class="form-label">Production order</label>
        <select id="design-diamond-order" class="form-select mb-3" data-design-diamond-order>
            <?php foreach ($orders as $order): ?>
                <option value="<?= (int) $order['id'] ?>"><?= esc((string) $order['order_no']) ?><?= $order['is_source'] ? ' · Original design' : '' ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <?php foreach ($orders as $index => $order): ?>
        <section data-design-diamond-panel="<?= (int) $order['id'] ?>" <?= $index > 0 ? 'hidden' : '' ?>>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <span class="badge bg-light text-dark border"><?= esc((string) $order['order_no']) ?></span>
                <span class="badge <?= $order['historical'] ? 'bg-warning text-dark' : 'bg-success' ?>"><?= $order['historical'] ? 'Historical issue / return reference' : 'Receiving record' ?></span>
            </div>
            <?php if ($order['historical']): ?>
                <p class="small text-muted">These sizes come from the matched historical issue sheet. Negative rows are returns or adjustments; their sizes are shown only when recorded. This is the net issue reference, not a size-by-size confirmation of studding.</p>
                <?php if ($order['has_receiving']): ?>
                    <div class="alert alert-light border py-3 small">
                        <div class="fw-semibold text-dark mb-1">Actual received: <?= number_format((float) $order['received_total_pcs'], 0) ?> PCS · <?= number_format((float) $order['received_total_cts'], 3) ?> CTS</div>
                        <?php if ($order['reference_exceeds_receiving']): ?>
                            <div class="text-warning">Historical reference totals (<?= number_format((float) $order['total_pcs'], 0) ?> PCS · <?= number_format((float) $order['total_cts'], 3) ?> CTS) exceed the receiving record. These quantities need reconciliation before using them as a studded size breakdown.</div>
                        <?php else: ?>
                            <div>Size reference covers <?= number_format((float) $order['total_pcs'], 0) ?> of <?= number_format((float) $order['received_total_pcs'], 0) ?> received PCS and <?= number_format((float) $order['total_cts'], 3) ?> of <?= number_format((float) $order['received_total_cts'], 3) ?> received CTS.</div>
                            <?php if ($order['unmatched_received_pcs'] > 0.0005 || $order['unmatched_received_cts'] > 0.0005): ?>
                                <div class="mt-1">Remaining diamonds (<?= number_format((float) $order['unmatched_received_pcs'], 0) ?> PCS · <?= number_format((float) $order['unmatched_received_cts'], 3) ?> CTS) have no matched size record.</div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($order['multiple_items']): ?>
                <div class="alert alert-light border py-2 small">This order contains multiple items. These diamond records are recorded for the whole order, not allocated to an individual design item.</div>
            <?php endif; ?>
            <?php if ($order['rows'] === []): ?>
                <div class="alert alert-light border mb-0">No diamond size records are available for this order yet.</div>
            <?php else: ?>
                <div class="table-responsive border rounded">
                    <table class="table table-sm table-hover mb-0" data-dt-skip="true">
                        <thead><tr><th>Diamond</th><th>Shape</th><th>Size / Chalni</th><th class="text-end">PCS</th><th class="text-end">CTS</th><th>Reference</th></tr></thead>
                        <tbody>
                            <?php foreach ($order['rows'] as $row): ?>
                                <?php
                                $size = trim((string) (($row['size_label'] ?? '') ?: ($row['size_code'] ?? '')));
                                if (strtoupper($size) === 'RETURN') {
                                    $size = '';
                                }
                                $chalni = trim((string) ($row['chalni_label'] ?? ''));
                                $adjustment = (float) $row['pcs'] < 0 || (float) $row['weight_cts'] < 0;
                                ?>
                                <tr>
                                    <td><span class="fw-semibold"><?= esc((string) (($row['quality'] ?? '') ?: 'Diamond')) ?></span>
                                        <?php if (! empty($row['shade'])): ?><div class="small text-muted"><?= esc((string) $row['shade']) ?></div><?php endif; ?>
                                        <?php if ($adjustment): ?><div><span class="badge bg-light text-danger border">Return / adjustment</span></div><?php endif; ?>
                                    </td>
                                    <td><?= esc((string) (($row['shape_name'] ?? '') ?: 'Not recorded')) ?></td>
                                    <td><?= esc($size ?: 'Not recorded') ?><?php if ($chalni !== '' && $chalni !== $size): ?><div class="small text-muted">Chalni: <?= esc($chalni) ?></div><?php endif; ?></td>
                                    <td class="text-end text-nowrap <?= $adjustment ? 'text-danger' : '' ?>"><?= rtrim(rtrim(number_format((float) $row['pcs'], 3, '.', ','), '0'), '.') ?></td>
                                    <td class="text-end text-nowrap <?= $adjustment ? 'text-danger' : '' ?>"><?= number_format((float) $row['weight_cts'], 3) ?></td>
                                    <td class="small">
                                        <?php if ($order['historical']): ?>
                                            <div><?= esc((string) $row['source_sheet']) ?> · Row <?= (int) $row['source_row'] ?></div>
                                            <div class="text-muted"><?= esc((string) $row['source_file']) ?></div>
                                            <?php if (! empty($row['notes'])): ?><div class="text-muted mt-1" style="max-width: 24rem; white-space: normal; overflow-wrap: anywhere"><?= esc((string) $row['notes']) ?></div><?php endif; ?>
                                        <?php else: ?>
                                            <?= ! empty($row['bag_no']) ? 'Bag ' . esc((string) $row['bag_no']) : 'Received without bag size' ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr><th colspan="3"><?= $order['historical'] ? 'Net issue reference' : 'Received total' ?></th><th class="text-end text-nowrap"><?= rtrim(rtrim(number_format((float) $order['total_pcs'], 3, '.', ','), '0'), '.') ?></th><th class="text-end text-nowrap"><?= number_format((float) $order['total_cts'], 3) ?></th><th></th></tr></tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
