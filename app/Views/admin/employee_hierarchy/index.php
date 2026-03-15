<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
    $selectedEmployee = is_array($selectedEmployee ?? null) ? $selectedEmployee : null;
    $currentHierarchy = is_array($currentHierarchy ?? null) ? $currentHierarchy : null;
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-1">Employee Hierarchy Management</h4>
        <p class="text-muted mb-0">Define daily reporting, observation, review, and approval lines for each employee.</p>
    </div>
    <a href="<?= site_url('admin/employees') ?>" class="btn btn-outline-secondary">
        <i class="fe fe-users me-1"></i> Employee Master
    </a>
</div>

<div class="row">
    <div class="col-xl-4 d-flex">
        <div class="card w-100">
            <div class="card-header"><h5 class="card-title mb-0">Employees</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datatable table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Designation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($employees === []): ?>
                                <tr><td colspan="3" class="text-center text-muted">No employees found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($employees as $employee): ?>
                                <tr class="<?= (int) ($selectedId ?? 0) === (int) $employee['id'] ? 'table-primary' : '' ?>">
                                    <td>
                                        <a href="<?= site_url('admin/employee-hierarchy?employee_id=' . (int) $employee['id']) ?>" class="fw-semibold d-block">
                                            <?= esc((string) $employee['full_name']) ?>
                                        </a>
                                        <div class="small text-muted"><?= esc((string) $employee['employee_code']) ?></div>
                                    </td>
                                    <td><?= esc((string) ($employee['department_name'] ?? '-')) ?></td>
                                    <td><?= esc((string) ($employee['designation_name'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <?php if (! $selectedEmployee): ?>
            <div class="card">
                <div class="card-body text-center text-muted py-5">Select an employee to manage hierarchy.</div>
            </div>
        <?php else: ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                        <div>
                            <div class="small text-muted"><?= esc((string) ($selectedEmployee['employee_code'] ?? '-')) ?></div>
                            <h4 class="mb-1"><?= esc((string) ($selectedEmployee['full_name'] ?? '-')) ?></h4>
                            <div class="text-muted">
                                <?= esc((string) ($selectedEmployee['designation_name'] ?? '-')) ?> |
                                <?= esc((string) ($selectedEmployee['department_name'] ?? '-')) ?> |
                                <?= esc((string) ($selectedEmployee['work_location'] ?? '-')) ?>
                            </div>
                        </div>
                        <div>
                            <span class="badge <?= (int) ($selectedEmployee['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                <?= (int) ($selectedEmployee['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-7 d-flex">
                    <div class="card w-100">
                        <div class="card-header"><h5 class="card-title mb-0">Assign Hierarchy</h5></div>
                        <div class="card-body">
                            <form action="<?= esc($formAction ?? site_url('admin/employee-hierarchy')) ?>" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="employee_id" value="<?= (int) $selectedEmployee['id'] ?>">
                                <div class="mb-3">
                                    <label class="form-label">Reporting Manager</label>
                                    <select name="reporting_manager_id" class="form-select select2">
                                        <option value="">Select Reporting Manager</option>
                                        <?php foreach ($managerOptions as $manager): ?>
                                            <option value="<?= (int) $manager['id'] ?>" <?= (string) old('reporting_manager_id', (string) ($currentHierarchy['reporting_manager_id'] ?? '')) === (string) $manager['id'] ? 'selected' : '' ?>>
                                                <?= esc((string) $manager['full_name']) ?> (<?= esc((string) ($manager['designation_name'] ?? '-')) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Observing Manager</label>
                                    <select name="observing_manager_id" class="form-select select2">
                                        <option value="">Select Observing Manager</option>
                                        <?php foreach ($managerOptions as $manager): ?>
                                            <option value="<?= (int) $manager['id'] ?>" <?= (string) old('observing_manager_id', (string) ($currentHierarchy['observing_manager_id'] ?? '')) === (string) $manager['id'] ? 'selected' : '' ?>>
                                                <?= esc((string) $manager['full_name']) ?> (<?= esc((string) ($manager['designation_name'] ?? '-')) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Reviewing Manager</label>
                                    <select name="reviewing_manager_id" class="form-select select2">
                                        <option value="">Select Reviewing Manager</option>
                                        <?php foreach ($managerOptions as $manager): ?>
                                            <option value="<?= (int) $manager['id'] ?>" <?= (string) old('reviewing_manager_id', (string) ($currentHierarchy['reviewing_manager_id'] ?? '')) === (string) $manager['id'] ? 'selected' : '' ?>>
                                                <?= esc((string) $manager['full_name']) ?> (<?= esc((string) ($manager['designation_name'] ?? '-')) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Approving Manager</label>
                                    <select name="approving_manager_id" class="form-select select2">
                                        <option value="">Select Approving Manager</option>
                                        <?php foreach ($managerOptions as $manager): ?>
                                            <option value="<?= (int) $manager['id'] ?>" <?= (string) old('approving_manager_id', (string) ($currentHierarchy['approving_manager_id'] ?? '')) === (string) $manager['id'] ? 'selected' : '' ?>>
                                                <?= esc((string) $manager['full_name']) ?> (<?= esc((string) ($manager['designation_name'] ?? '-')) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Department Head</label>
                                    <select name="department_head_id" class="form-select select2">
                                        <option value="">Select Department Head</option>
                                        <?php foreach ($managerOptions as $manager): ?>
                                            <option value="<?= (int) $manager['id'] ?>" <?= (string) old('department_head_id', (string) ($currentHierarchy['department_head_id'] ?? '')) === (string) $manager['id'] ? 'selected' : '' ?>>
                                                <?= esc((string) $manager['full_name']) ?> (<?= esc((string) ($manager['designation_name'] ?? '-')) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Effective From</label>
                                        <input type="date" name="effective_from" class="form-control"
                                            value="<?= esc(old('effective_from', date('Y-m-d'))) ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Remarks</label>
                                        <input type="text" name="remarks" class="form-control"
                                            value="<?= esc(old('remarks', '')) ?>" placeholder="Optional remarks">
                                    </div>
                                </div>
                                <?php if (admin_can('organization.hierarchy.manage')): ?>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fe fe-save me-1"></i> Save Hierarchy
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 d-flex">
                    <div class="card w-100">
                        <div class="card-header"><h5 class="card-title mb-0">Current Structure</h5></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="small text-muted">Reporting Manager</div>
                                <div class="fw-semibold"><?= esc((string) ($currentHierarchy['reporting_manager_name'] ?? '-')) ?></div>
                            </div>
                            <div class="mb-3">
                                <div class="small text-muted">Observing Manager</div>
                                <div class="fw-semibold"><?= esc((string) ($currentHierarchy['observing_manager_name'] ?? '-')) ?></div>
                            </div>
                            <div class="mb-3">
                                <div class="small text-muted">Reviewing Manager</div>
                                <div class="fw-semibold"><?= esc((string) ($currentHierarchy['reviewing_manager_name'] ?? '-')) ?></div>
                            </div>
                            <div class="mb-3">
                                <div class="small text-muted">Approving Manager</div>
                                <div class="fw-semibold"><?= esc((string) ($currentHierarchy['approving_manager_name'] ?? '-')) ?></div>
                            </div>
                            <div class="mb-0">
                                <div class="small text-muted">Department Head</div>
                                <div class="fw-semibold"><?= esc((string) ($currentHierarchy['department_head_name'] ?? '-')) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-1">
                <div class="col-lg-5 d-flex">
                    <div class="card w-100">
                        <div class="card-header"><h5 class="card-title mb-0">Direct Team</h5></div>
                        <div class="card-body">
                            <?php if ($team === []): ?>
                                <div class="text-muted">No direct team members assigned yet.</div>
                            <?php else: ?>
                                <?php foreach ($team as $member): ?>
                                    <div class="border rounded p-2 mb-2">
                                        <div class="fw-semibold"><?= esc((string) $member['full_name']) ?></div>
                                        <div class="small text-muted"><?= esc((string) $member['employee_code']) ?> | <?= esc((string) ($member['designation_name'] ?? '-')) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 d-flex">
                    <div class="card w-100">
                        <div class="card-header"><h5 class="card-title mb-0">Hierarchy History</h5></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table datatable table-bordered align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Effective</th>
                                            <th>Reporting</th>
                                            <th>Observing</th>
                                            <th>Approving</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($history === []): ?>
                                            <tr><td colspan="5" class="text-center text-muted">No hierarchy history found.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($history as $entry): ?>
                                            <tr>
                                                <td>
                                                    <div><?= esc((string) ($entry['effective_from'] ?? '-')) ?></div>
                                                    <div class="small text-muted">to <?= esc((string) ($entry['effective_to'] ?? 'Current')) ?></div>
                                                </td>
                                                <td><?= esc((string) ($entry['reporting_manager_name'] ?? '-')) ?></td>
                                                <td><?= esc((string) ($entry['observing_manager_name'] ?? '-')) ?></td>
                                                <td><?= esc((string) ($entry['approving_manager_name'] ?? '-')) ?></td>
                                                <td>
                                                    <span class="badge <?= (int) ($entry['is_active'] ?? 0) === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                                        <?= (int) ($entry['is_active'] ?? 0) === 1 ? 'Current' : 'Past' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
