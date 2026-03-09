<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Edit Diamond Adjustment #<?= (int) $adjustment['id'] ?></h4>
    <a href="<?= site_url('admin/diamond-inventory/adjustments/view/' . $adjustment['id']) ?>" class="btn btn-outline-primary">Back</a>
</div>

<form method="post" action="<?= esc((string) $action) ?>">
    <?= csrf_field() ?>
    <?= $this->include('admin/diamond_inventory/adjustments/form') ?>
</form>
<?= $this->endSection() ?>

