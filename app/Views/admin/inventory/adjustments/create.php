<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Create Inventory Adjustment</h4>
    <a href="<?= site_url('admin/inventory/adjustments') ?>" class="btn btn-outline-primary">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= site_url('admin/inventory/adjust') ?>" class="row g-2" id="adjustmentForm">
            <?= csrf_field() ?>
            <div class="col-md-3 mb-2">
                <label class="form-label">Date</label>
                <input type="date" name="txn_date" class="form-control" value="<?= esc((string) old('txn_date', date('Y-m-d'))) ?>" required>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Adjustment Type</label>
                <select name="adjust_mode" class="form-control" required>
                    <option value="plus" <?= old('adjust_mode', 'plus') === 'plus' ? 'selected' : '' ?>>Plus</option>
                    <option value="minus" <?= old('adjust_mode') === 'minus' ? 'selected' : '' ?>>Minus</option>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Warehouse</label>
                <select name="location_id" id="locationSelect" class="form-control" required>
                    <option value="">Select Warehouse</option>
                    <?php foreach (($locations ?? []) as $loc): ?>
                        <option value="<?= esc((string) $loc['id']) ?>" <?= (string) old('location_id') === (string) $loc['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $loc['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Bin</label>
                <select name="bin_id" id="binSelect" class="form-control">
                    <option value="">Auto Main Bin</option>
                    <?php foreach (($bins ?? []) as $bin): ?>
                        <option value="<?= esc((string) $bin['id']) ?>" data-location="<?= esc((string) $bin['location_id']) ?>" <?= (string) old('bin_id') === (string) $bin['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $bin['bin_code']) ?> - <?= esc((string) $bin['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 mb-2">
                <label class="form-label">Item Type</label>
                <select name="item_type" class="form-control" required>
                    <option value="Gold" <?= old('item_type', 'Gold') === 'Gold' ? 'selected' : '' ?>>Gold</option>
                    <option value="Diamond" <?= old('item_type') === 'Diamond' ? 'selected' : '' ?>>Diamond</option>
                    <option value="Stone" <?= old('item_type') === 'Stone' ? 'selected' : '' ?>>Stone</option>
                    <option value="Finished Goods" <?= old('item_type') === 'Finished Goods' ? 'selected' : '' ?>>Finished Goods</option>
                </select>
            </div>
            <div class="col-md-5 mb-2">
                <label class="form-label">Material</label>
                <select name="material_name" class="form-control" required>
                    <option value="">Select Material</option>
                    <?php foreach (($materialOptions ?? []) as $opt): ?>
                        <option value="<?= esc($opt) ?>" <?= old('material_name') === $opt ? 'selected' : '' ?>><?= esc($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label">Gold Purity</label>
                <select name="gold_purity_id" class="form-control">
                    <option value="">Select Purity</option>
                    <?php foreach (($goldPurities ?? []) as $p): ?>
                        <option value="<?= esc((string) $p['id']) ?>" <?= (string) old('gold_purity_id') === (string) $p['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $p['purity_code']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 mb-2">
                <label class="form-label">PCS</label>
                <input type="number" name="pcs" class="form-control" min="0" step="1" value="<?= esc((string) old('pcs', '0')) ?>">
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Weight (gm)</label>
                <input type="number" name="weight_gm" class="form-control" min="0" step="0.001" value="<?= esc((string) old('weight_gm', '0')) ?>">
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">CTS</label>
                <input type="number" name="cts" class="form-control" min="0" step="0.001" value="<?= esc((string) old('cts', '0')) ?>">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Document No</label>
                <input type="text" name="document_no" class="form-control" value="<?= esc((string) old('document_no')) ?>" placeholder="ADJ-0001">
            </div>

            <div class="col-md-3 mb-2">
                <label class="form-label">Diamond Shape</label>
                <input type="text" name="diamond_shape" class="form-control" value="<?= esc((string) old('diamond_shape')) ?>" placeholder="Round/Pear">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Diamond Sieve</label>
                <input type="text" name="diamond_sieve" class="form-control" value="<?= esc((string) old('diamond_sieve')) ?>" placeholder="0.18-0.20">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Sieve Min</label>
                <input type="number" name="diamond_sieve_min" class="form-control" min="0" step="0.001" value="<?= esc((string) old('diamond_sieve_min')) ?>">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Sieve Max</label>
                <input type="number" name="diamond_sieve_max" class="form-control" min="0" step="0.001" value="<?= esc((string) old('diamond_sieve_max')) ?>">
            </div>

            <div class="col-md-3 mb-2">
                <label class="form-label">Diamond Color</label>
                <input type="text" name="diamond_color" class="form-control" value="<?= esc((string) old('diamond_color')) ?>" placeholder="EF/GH">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Diamond Clarity</label>
                <input type="text" name="diamond_clarity" class="form-control" value="<?= esc((string) old('diamond_clarity')) ?>" placeholder="VS/SI">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Cut</label>
                <input type="text" name="diamond_cut" class="form-control" value="<?= esc((string) old('diamond_cut')) ?>" placeholder="EX/VG">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Quality</label>
                <input type="text" name="diamond_quality" class="form-control" value="<?= esc((string) old('diamond_quality')) ?>" placeholder="A/AA">
            </div>

            <div class="col-md-3 mb-2">
                <label class="form-label">Fluorescence</label>
                <input type="text" name="diamond_fluorescence" class="form-control" value="<?= esc((string) old('diamond_fluorescence')) ?>" placeholder="None/Faint">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Lab</label>
                <input type="text" name="diamond_lab" class="form-control" value="<?= esc((string) old('diamond_lab')) ?>" placeholder="GIA/IGI">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Certificate No</label>
                <input type="text" name="certificate_no" class="form-control" value="<?= esc((string) old('certificate_no')) ?>" placeholder="Optional">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Packet No</label>
                <input type="text" name="packet_no" class="form-control" value="<?= esc((string) old('packet_no')) ?>" placeholder="PKT-001">
            </div>

            <div class="col-md-3 mb-2">
                <label class="form-label">Lot No</label>
                <input type="text" name="lot_no" class="form-control" value="<?= esc((string) old('lot_no')) ?>" placeholder="LOT-001">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Stone Type</label>
                <input type="text" name="stone_type" class="form-control" value="<?= esc((string) old('stone_type')) ?>" placeholder="Ruby/Emerald">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Stone Size</label>
                <input type="text" name="stone_size" class="form-control" value="<?= esc((string) old('stone_size')) ?>" placeholder="2x3">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Stone Color Shade</label>
                <input type="text" name="stone_color_shade" class="form-control" value="<?= esc((string) old('stone_color_shade')) ?>" placeholder="Deep Red">
            </div>

            <div class="col-md-3 mb-2">
                <label class="form-label">Stone Quality Grade</label>
                <input type="text" name="stone_quality_grade" class="form-control" value="<?= esc((string) old('stone_quality_grade')) ?>" placeholder="Premium">
            </div>
            <div class="col-md-9 mb-2">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control" value="<?= esc((string) old('notes')) ?>" placeholder="Reason / remarks">
            </div>

            <div class="col-12 mt-2">
                <button type="submit" class="btn btn-primary">Save Adjustment</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const warehouse = document.getElementById('locationSelect');
    const bin = document.getElementById('binSelect');
    if (!warehouse || !bin) {
        return;
    }

    function refreshBins() {
        const selectedWarehouse = warehouse.value;
        const options = Array.from(bin.options);
        options.forEach((option, index) => {
            if (index === 0) {
                option.hidden = false;
                return;
            }
            const owner = option.getAttribute('data-location');
            option.hidden = selectedWarehouse !== '' && owner !== selectedWarehouse;
        });

        const selectedOption = bin.options[bin.selectedIndex];
        if (selectedOption && selectedOption.hidden) {
            bin.value = '';
        }
    }

    warehouse.addEventListener('change', refreshBins);
    refreshBins();
})();
</script>
<?= $this->endSection() ?>
