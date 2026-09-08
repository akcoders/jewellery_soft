<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="erp-page-toolbar flex-wrap mb-3">
    <div>
        <span class="erp-eyebrow">Reusable production library</span>
        <h4 class="mb-1">Design Master</h4>
        <p class="mb-0">Completed fresh designs become reusable here with their karigar and material specifications.</p>
    </div>
    <?php if (admin_can('masters.designs.manage')): ?>
        <a href="<?= site_url('admin/designs/create') ?>" class="btn btn-primary">Add Design</a>
    <?php endif; ?>
</div>

<div class="card erp-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0 erp-responsive-wide">
                <thead>
                    <tr>
                        <th>Preview</th>
                        <th>Design Code</th>
                        <th>Name</th>
                        <th>Classification</th>
                        <th>Karigar</th>
                        <th>Gold Weights</th>
                        <th>Studded</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($designs === []): ?>
                        <tr><td colspan="8" class="text-center text-muted py-5">No designs found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($designs as $design): ?>
                        <tr>
                            <td>
                                <?php if (! empty($design['image_path'])): ?>
                                    <a href="<?= base_url($design['image_path']) ?>" target="_blank" class="erp-design-thumb">
                                        <img src="<?= base_url($design['image_path']) ?>" alt="<?= esc($design['design_code'], 'attr') ?>">
                                    </a>
                                <?php else: ?>
                                    <span class="erp-design-thumb erp-design-thumb-empty"><i class="fe fe-image"></i></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-primary"><?= esc($design['design_code']) ?></span></td>
                            <td><div class="fw-semibold"><?= esc($design['name']) ?></div><small class="text-muted"><?= esc((string) ($design['purity_label'] ?: '-')) ?></small></td>
                            <td><div><?= esc($design['category'] ?: '-') ?></div><small class="text-muted"><?= esc((string) ($design['subcategory'] ?: '-')) ?></small></td>
                            <td><?= esc((string) ($design['source_karigar_name'] ?: '-')) ?></td>
                            <td>
                                <div>Gross: <strong><?= number_format((float) ($design['gross_weight_gm'] ?? 0), 3) ?> gm</strong></div>
                                <small class="text-muted">Net <?= number_format((float) ($design['net_gold_weight_gm'] ?? 0), 3) ?> · Pure <?= number_format((float) ($design['pure_gold_weight_gm'] ?? 0), 3) ?></small>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary mb-1 js-design-diamonds"
                                    data-url="<?= esc(site_url('admin/designs/' . (int) $design['id'] . '/diamonds'), 'attr') ?>"
                                    data-bs-toggle="modal" data-bs-target="#designDiamondModal"
                                    aria-label="<?= esc('View diamonds used in ' . $design['design_code'], 'attr') ?>">
                                    <i class="fe fe-eye me-1" aria-hidden="true"></i>Diamond · <?= number_format((float) ($design['diamond_weight_cts'] ?? 0), 3) ?> cts
                                </button>
                                <div class="small text-muted">Click Diamond to view sizes</div>
                                <small class="text-muted">Stone <?= number_format((float) ($design['stone_weight_cts'] ?? 0), 3) ?> cts</small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= esc((string) ($design['source_type'] ?: 'Manual')) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal fade" id="designDiamondModal" tabindex="-1" aria-labelledby="designDiamondModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="designDiamondModalLabel">Diamonds used in this design</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="design-diamond-content" aria-live="polite"></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<style>
    #designDiamondModal table { min-width: 620px; }
    #designDiamondModal th, #designDiamondModal td { padding: .8rem; vertical-align: middle; }
    #designDiamondModal thead, #designDiamondModal tfoot { background: #f7f8fa; }
    #designDiamondModal .modal-body { padding: 1.25rem; }
    #designDiamondModal [hidden] { display: none !important; }
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const modal = document.getElementById('designDiamondModal');
    const content = document.getElementById('design-diamond-content');
    let request;

    modal.addEventListener('show.bs.modal', async function (event) {
        if (request) request.abort();
        request = new AbortController();
        const current = request;
        const trigger = event.relatedTarget;
        content.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading diamond details…</div>';
        try {
            const response = await fetch(trigger.dataset.url, {
                credentials: 'same-origin', cache: 'no-store', signal: current.signal,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            if (!response.ok || response.redirected) throw new Error('request_failed');
            const html = await response.text();
            if (current !== request) return;
            content.innerHTML = html;
        } catch (error) {
            if (error.name === 'AbortError' || current !== request) return;
            content.innerHTML = '<div class="alert alert-warning mb-0">Diamond details could not be loaded. Please close this window and try again. If your session expired, sign in again.</div>';
        }
    });
    modal.addEventListener('hidden.bs.modal', function () {
        if (request) request.abort();
        request = null;
        content.replaceChildren();
    });
    content.addEventListener('change', function (event) {
        if (!event.target.matches('[data-design-diamond-order]')) return;
        content.querySelectorAll('[data-design-diamond-panel]').forEach(function (panel) {
            panel.hidden = panel.dataset.designDiamondPanel !== event.target.value;
        });
    });
})();
</script>
<?= $this->endSection() ?>
