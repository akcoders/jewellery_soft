<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Lead: <?= esc($lead['name']) ?> (<?= esc($lead['lead_no'] ?? '-') ?>)</h4>
    <a href="<?= site_url('admin/leads') ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="row">
    <div class="col-lg-4 d-flex">
        <div class="card w-100">
            <div class="card-body">
                <p class="mb-1"><strong>Phone:</strong> <?= esc($lead['phone']) ?></p>
                <p class="mb-1"><strong>Email:</strong> <?= esc($lead['email'] ?: '-') ?></p>
                <p class="mb-1"><strong>Source:</strong> <?= esc($lead['source_name'] ?: '-') ?></p>
                <p class="mb-1"><strong>City:</strong> <?= esc($lead['city'] ?: '-') ?></p>
                <p class="mb-2"><strong>Requirement:</strong> <?= esc($lead['requirement_text'] ?: '-') ?></p>

                <form action="<?= site_url('admin/leads/' . $lead['id'] . '/stage') ?>" method="post" class="mt-3">
                    <?= csrf_field() ?>
                    <label class="form-label">Lead Stage</label>
                    <div class="d-flex gap-2">
                        <select name="stage" class="form-control">
                            <?php foreach ($leadStages as $stage): ?>
                                <option value="<?= esc($stage) ?>" <?= $lead['stage'] === $stage ? 'selected' : '' ?>><?= esc($stage) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Images</h5></div>
            <div class="card-body">
                <form action="<?= site_url('admin/leads/' . $lead['id'] . '/images') ?>" method="post" enctype="multipart/form-data" class="mb-3">
                    <?= csrf_field() ?>
                    <div class="d-flex gap-2">
                        <input type="file" name="lead_images[]" class="form-control" multiple>
                        <button class="btn btn-primary" type="submit">Upload</button>
                    </div>
                </form>
                <div class="row">
                    <?php if ($images === []): ?>
                        <p class="text-muted mb-0">No images uploaded.</p>
                    <?php endif; ?>
                    <?php foreach ($images as $img): ?>
                        <div class="col-md-3 mb-3">
                            <a href="<?= base_url($img['file_path']) ?>" target="_blank">
                                <img src="<?= base_url($img['file_path']) ?>" class="img-fluid rounded border" alt="<?= esc($img['file_name']) ?>">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Follow-up Reminders</h5></div>
            <div class="card-body">
                <form action="<?= site_url('admin/leads/' . $lead['id'] . '/followups') ?>" method="post" class="mb-3">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Follow-up At</label>
                            <input type="datetime-local" name="followup_at" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Reminder At</label>
                            <input type="datetime-local" name="reminder_at" class="form-control">
                        </div>
                        <div class="col-12 mb-2">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm">Add Follow-up</button>
                </form>

                <div class="table-responsive">
                    <table class="table datatable table-hover mb-0">
                        <thead><tr><th>Date</th><th>Status</th><th>Notes</th></tr></thead>
                        <tbody>
                            <?php if ($followups === []): ?>
                                <tr><td colspan="3" class="text-muted text-center">No follow-ups yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($followups as $f): ?>
                                <tr>
                                    <td><?= esc((string) $f['followup_at']) ?></td>
                                    <td><?= esc($f['status']) ?></td>
                                    <td><?= esc($f['notes'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Notes History</h5></div>
            <div class="card-body">
                <form action="<?= site_url('admin/leads/' . $lead['id'] . '/notes') ?>" method="post" class="mb-3">
                    <?= csrf_field() ?>
                    <div class="d-flex gap-2">
                        <textarea name="note" class="form-control" rows="2" placeholder="Add note..." required></textarea>
                        <button class="btn btn-primary btn-sm" type="submit">Save</button>
                    </div>
                </form>

                <ul class="list-group">
                    <?php if ($notes === []): ?>
                        <li class="list-group-item text-muted">No notes added.</li>
                    <?php endif; ?>
                    <?php foreach ($notes as $n): ?>
                        <li class="list-group-item">
                            <div><?= esc($n['note']) ?></div>
                            <small class="text-muted"><?= esc((string) $n['created_at']) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>


