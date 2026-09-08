<?= $this->extend('admin/layouts/main') ?>
<?= $this->section('content') ?>
<div class="erp-page-toolbar erp-command-toolbar flex-wrap mb-3">
    <div class="erp-toolbar-copy"><span class="erp-eyebrow">Diamond traceability</span><h4>Prepare Diamond Bag</h4><p>Pack calibrated diamond lots before issuing them to a karigar.</p></div>
    <a href="<?= site_url('admin/diamond-inventory/bags') ?>" class="btn btn-outline-primary"><i class="fe fe-arrow-left me-1"></i>Back</a>
</div>
<form action="<?= site_url('admin/diamond-inventory/bags') ?>" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?= $this->include('admin/diamond_bags/form') ?>
</form>
<?= $this->endSection() ?>
