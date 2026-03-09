<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Create Stone Purchase</h4>
    <a href="<?= site_url('admin/stone-inventory/purchases') ?>" class="btn btn-outline-primary">Back</a>
</div>

<form method="post" action="<?= esc((string) $action) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?= $this->include('admin/stone_inventory/purchases/form') ?>
</form>
<?= $this->endSection() ?>
