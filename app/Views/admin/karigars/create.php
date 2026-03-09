<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Add Karigar</h4>
    <a href="<?= site_url('admin/karigars') ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= site_url('admin/karigars') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required value="<?= esc(old('name')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= esc(old('phone')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= esc(old('email')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Department</label>
                    <input type="text" name="department" class="form-control" value="<?= esc(old('department')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Rate Per Gram</label>
                    <input type="number" step="0.01" min="0" name="rate_per_gm" class="form-control" required value="<?= esc(old('rate_per_gm') ?: '0') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Allowed Wastage (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="wastage_percentage" class="form-control" required value="<?= esc(old('wastage_percentage') ?: '0') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Joining Date</label>
                    <input type="date" name="joining_date" class="form-control" value="<?= esc(old('joining_date')) ?>">
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= esc(old('address')) ?></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= esc(old('city')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">State</label>
                    <input type="text" name="state" class="form-control" value="<?= esc(old('state')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Pincode</label>
                    <input type="text" name="pincode" class="form-control" value="<?= esc(old('pincode')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Aadhaar No</label>
                    <input type="text" name="aadhaar_no" class="form-control" value="<?= esc(old('aadhaar_no')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">PAN No</label>
                    <input type="text" name="pan_no" class="form-control" value="<?= esc(old('pan_no')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Skills</label>
                    <input type="text" name="skills_text" class="form-control" value="<?= esc(old('skills_text')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Bank Name</label>
                    <input type="text" name="bank_name" class="form-control" value="<?= esc(old('bank_name')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Bank Account No</label>
                    <input type="text" name="bank_account_no" class="form-control" value="<?= esc(old('bank_account_no')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">IFSC Code</label>
                    <input type="text" name="ifsc_code" class="form-control" value="<?= esc(old('ifsc_code')) ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= esc(old('notes')) ?></textarea>
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="mb-0">Documents</h6>
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

            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-control">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>

            <button class="btn btn-primary mt-3">Save Karigar</button>
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
