<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Create Stone Item</h4>
    <a href="<?= site_url('admin/stone-inventory/items') ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= site_url('admin/stone-inventory/items') ?>">
            <?= csrf_field() ?>
            <?= $this->include('admin/stone_inventory/items/form') ?>
            <button type="submit" class="btn btn-primary">Save Item</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
