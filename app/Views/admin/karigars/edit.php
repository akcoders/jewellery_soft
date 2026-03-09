<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Edit Karigar</h4>
    <div class="d-flex gap-2">
        <a href="<?= site_url('admin/karigars/' . $karigar['id']) ?>" class="btn btn-outline-primary">View</a>
        <a href="<?= site_url('admin/karigars') ?>" class="btn btn-outline-secondary">Back</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= site_url('admin/karigars/' . $karigar['id'] . '/update') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required value="<?= esc(old('name', $karigar['name'])) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= esc(old('phone', $karigar['phone'])) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= esc(old('email', $karigar['email'])) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Department</label>
                    <input type="text" name="department" class="form-control" value="<?= esc(old('department', $karigar['department'])) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Rate Per Gram</label>
                    <input type="number" step="0.01" min="0" name="rate_per_gm" class="form-control" required value="<?= esc(old('rate_per_gm', (string) $karigar['rate_per_gm'])) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Allowed Wastage (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="wastage_percentage" class="form-control" required value="<?= esc(old('wastage_percentage', (string) ($karigar['wastage_percentage'] ?? 0))) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Joining Date</label>
                    <input type="date" name="joining_date" class="form-control" value="<?= esc(old('joining_date', $karigar['joining_date'])) ?>">
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= esc(old('address', $karigar['address'])) ?></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= esc(old('city', $karigar['city'])) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">State</label>
                    <input type="text" name="state" class="form-control" value="<?= esc(old('state', $karigar['state'])) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Pincode</label>
                    <input type="text" name="pincode" class="form-control" value="<?= esc(old('pincode', $karigar['pincode'])) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Aadhaar No</label>
                    <input type="text" name="aadhaar_no" class="form-control" value="<?= esc(old('aadhaar_no', $karigar['aadhaar_no'])) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">PAN No</label>
                    <input type="text" name="pan_no" class="form-control" value="<?= esc(old('pan_no', $karigar['pan_no'])) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Skills</label>
                    <input type="text" name="skills_text" class="form-control" value="<?= esc(old('skills_text', $karigar['skills_text'])) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Bank Name</label>
                    <input type="text" name="bank_name" class="form-control" value="<?= esc(old('bank_name', $karigar['bank_name'])) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Bank Account No</label>
                    <input type="text" name="bank_account_no" class="form-control" value="<?= esc(old('bank_account_no', $karigar['bank_account_no'])) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">IFSC Code</label>
                    <input type="text" name="ifsc_code" class="form-control" value="<?= esc(old('ifsc_code', $karigar['ifsc_code'])) ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= esc(old('notes', $karigar['notes'])) ?></textarea>
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="mb-0">Add New Documents</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-doc-row">Add Document</button>
            </div>
            <div class="table-responsive mb-3">
                <table class="table datatable table-bordered" id="doc-table" data-dt-searching="false" data-dt-ordering="false" data-dt-paging="false" data-dt-info="false">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>File</th>
                            <th>Remarks</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <select name="doc_types[]" class="form-control">
                                    <?php foreach ($docTypes as $type): ?>
                                        <option value="<?= esc($type) ?>"><?= esc($type) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="file" name="doc_files[]" class="form-control"></td>
                            <td><input type="text" name="doc_remarks[]" class="form-control"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-doc">X</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="table-responsive mb-3">
                <table class="table datatable table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Existing Type</th>
                            <th>File</th>
                            <th>Remarks</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($documents === []): ?>
                            <tr><td colspan="4" class="text-center text-muted">No documents uploaded.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($documents as $d): ?>
                            <tr>
                                <td><?= esc($d['document_type']) ?></td>
                                <td><a href="<?= base_url($d['file_path']) ?>" target="_blank"><?= esc($d['file_name']) ?></a></td>
                                <td><?= esc($d['remarks'] ?: '-') ?></td>
                                <td><?= esc((string) $d['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-control">
                        <option value="1" <?= (int) old('is_active', (string) $karigar['is_active']) === 1 ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= (int) old('is_active', (string) $karigar['is_active']) === 0 ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <button class="btn btn-primary mt-3">Update Karigar</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        const tableBody = document.querySelector('#doc-table tbody');
        const addBtn = document.getElementById('add-doc-row');
        const hasDt = typeof jQuery !== 'undefined' && typeof jQuery.fn.DataTable !== 'undefined' && jQuery.fn.DataTable.isDataTable('#doc-table');
        const dt = hasDt ? jQuery('#doc-table').DataTable() : null;
        if (!tableBody || !addBtn) return;

        addBtn.addEventListener('click', function () {
            const first = tableBody.querySelector('tr');
            if (!first) return;
            const clone = first.cloneNode(true);
            clone.querySelectorAll('input').forEach(function (el) { el.value = ''; });
            clone.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
            if (dt) dt.row.add(clone).draw(false);
            else tableBody.appendChild(clone);
        });

        tableBody.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLElement) || !target.classList.contains('remove-doc')) return;
            const row = target.closest('tr');
            if (!row) return;
            const count = dt ? dt.rows().count() : tableBody.querySelectorAll('tr').length;
            if (count <= 1) return;
            if (dt) dt.row(row).remove().draw(false);
            else row.remove();
        });
    })();
</script>
<?= $this->endSection() ?>
