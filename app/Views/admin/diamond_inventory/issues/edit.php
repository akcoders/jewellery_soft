<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Edit Diamond Issue #<?= (int) $issue['id'] ?></h4>
    <a href="<?= site_url('admin/diamond-inventory/issues/view/' . $issue['id']) ?>" class="btn btn-outline-primary">Back</a>
</div>

<form method="post" action="<?= esc((string) $action) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?= $this->include('admin/diamond_inventory/issues/form') ?>
</form>
<?= $this->endSection() ?>
