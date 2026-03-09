<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <form method="post" action="<?= esc((string) $action) ?>">
            <?= csrf_field() ?>
            <?= $this->include('admin/gold_inventory/products/form') ?>
            <div class="mt-2">
                <button type="submit" class="btn btn-primary"><i class="fe fe-save"></i> Update Product</button>
                <a href="<?= site_url('admin/gold-inventory/products') ?>" class="btn btn-light">Back</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

