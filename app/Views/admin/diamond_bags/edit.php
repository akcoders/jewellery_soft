<?= $this->extend('admin/layouts/main') ?>
<?= $this->section('content') ?>
<div class="erp-page-toolbar erp-command-toolbar flex-wrap mb-3">
    <div class="erp-toolbar-copy"><span class="erp-eyebrow">Diamond traceability</span><h4>Edit <?= esc((string) $bag['bag_no']) ?></h4><p>Only an unissued physical bag can be changed.</p></div>
    <a href="<?= site_url('admin/diamond-inventory/bags/' . (int) $bag['id']) ?>" class="btn btn-outline-primary"><i class="fe fe-arrow-left me-1"></i>Back</a>
</div>
<form action="<?= site_url('admin/diamond-inventory/bags/' . (int) $bag['id'] . '/update') ?>" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?= $this->include('admin/diamond_bags/form') ?>
</form>
<?= $this->endSection() ?>
