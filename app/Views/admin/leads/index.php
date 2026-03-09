<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Leads</h4>
    <a href="<?= site_url('admin/leads/create') ?>" class="btn btn-primary">Add Lead</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table datatable table-hover mb-0">
                <thead>
                    <tr>
                        <th>Lead No</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Source</th>
                        <th>City</th>
                        <th>Stage</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($leads === []): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No leads found.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td><?= esc($lead['lead_no'] ?? '-') ?></td>
                            <td><?= esc($lead['name']) ?></td>
                            <td><?= esc($lead['phone']) ?></td>
                            <td><?= esc($lead['source_name'] ?? '-') ?></td>
                            <td><?= esc($lead['city'] ?? '-') ?></td>
                            <td><?= esc($lead['stage']) ?></td>
                            <td><a href="<?= site_url('admin/leads/' . $lead['id']) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>


