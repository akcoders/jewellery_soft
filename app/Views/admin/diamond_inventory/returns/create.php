<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Create Diamond Return</h4>
    <a href="<?= site_url('admin/diamond-inventory/returns') ?>" class="btn btn-outline-primary">Back</a>
</div>

<form method="post" action="<?= esc((string) $action) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?= $this->include('admin/diamond_inventory/returns/form') ?>
</form>
<?= $this->endSection() ?>
