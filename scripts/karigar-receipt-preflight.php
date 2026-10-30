<?php

declare(strict_types=1);

/** Read-only duplicate review for a reviewed historical invoice manifest.
 * Usage: php scripts/karigar-receipt-preflight.php /path/to/manifest.json
 * Run separately against each target database. Set karigar_master_name in a
 * bill if its scanned name differs from the exact master name. Exit 2 means
 * unresolved inputs; exit 1 means an error. This script never imports data.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

try {
    if ($argc !== 2 || ! is_file($argv[1])) {
        throw new RuntimeException('Usage: php scripts/karigar-receipt-preflight.php /path/to/reviewed-manifest.json');
    }
    $manifest = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($manifest) || ! isset($manifest['bills']) || ! is_array($manifest['bills']) || $manifest['bills'] === []) {
        throw new RuntimeException('Manifest must contain a non-empty bills array.');
    }
    $root = dirname(__DIR__);
    define('APPPATH', $root . '/app/');
    require $root . '/vendor/autoload.php';
    (new CodeIgniter\Config\DotEnv($root))->load();
    require $root . '/app/Config/Database.php';
    $config = new class extends Config\Database {
        public function __construct() { $this->initEnvValue($this->default, 'default', Config\Database::class, 'database'); }
    };
    $c = $config->default;
    if (($c['DBDriver'] ?? '') !== 'MySQLi' || ! empty($c['DSN'])) {
        throw new RuntimeException('The configured default database must use MySQLi without a DSN.');
    }
    $db = new mysqli((string) $c['hostname'], (string) $c['username'], (string) $c['password'], (string) $c['database'], (int) $c['port']);
    $db->set_charset('utf8mb4');
    $db->query('SET TRANSACTION READ ONLY');
    $db->begin_transaction();
    $findKarigar = $db->prepare('SELECT id, name, is_active FROM karigars WHERE name = ?');
    $findBills = $db->prepare('SELECT id, bill_no, bill_date, order_id, receive_movement_id FROM labour_bills WHERE karigar_id = ? ORDER BY bill_date, id');
    // Search all dates: invoice and receiving dates can differ. Either exact gross or
    // net is enough to be a review candidate; an unknown purity is not a mismatch.
    $findReceipts = $db->prepare("SELECT m.id movement_id, m.order_id, o.order_no, o.order_name, o.customer_id, o.deleted_at, m.created_at movement_created_at, r.created_at summary_created_at, COALESCE(r.gross_weight_gm,m.gross_weight_gm) gross_g, COALESCE(r.net_gold_weight_gm,m.net_gold_weight_gm,m.gold_gm) net_g, COALESCE(r.pure_gold_weight_gm,m.pure_gold_weight_gm) pure_g, r.id summary_id, gp.purity_code, gp.purity_percent, p.purity_label, p.source_sheet, p.source_row FROM order_material_movements m LEFT JOIN orders o ON o.id=m.order_id LEFT JOIN order_receive_summaries r ON r.movement_id=m.id LEFT JOIN gold_purities gp ON gp.id=COALESCE(r.gold_purity_id,m.gold_purity_id) LEFT JOIN production_ready_items p ON p.order_id=m.order_id WHERE m.movement_type='receive' AND COALESCE(m.karigar_id,o.assigned_karigar_id)=? AND (ABS(COALESCE(r.gross_weight_gm,m.gross_weight_gm)-?) < 0.0005 OR ABS(COALESCE(r.net_gold_weight_gm,m.net_gold_weight_gm,m.gold_gm)-?) < 0.0005) ORDER BY m.created_at,m.id");
    $findOrphanSummaries = $db->prepare('SELECT r.movement_id, r.order_id, o.order_no, o.order_name, o.customer_id, o.deleted_at, NULL movement_created_at, r.created_at summary_created_at, r.gross_weight_gm gross_g, r.net_gold_weight_gm net_g, r.pure_gold_weight_gm pure_g, r.id summary_id, gp.purity_code, gp.purity_percent, p.purity_label, p.source_sheet, p.source_row FROM order_receive_summaries r LEFT JOIN order_material_movements m ON m.id=r.movement_id JOIN orders o ON o.id=r.order_id LEFT JOIN gold_purities gp ON gp.id=r.gold_purity_id LEFT JOIN production_ready_items p ON p.order_id=r.order_id WHERE m.id IS NULL AND o.assigned_karigar_id=? AND (ABS(r.gross_weight_gm-?) < 0.0005 OR ABS(r.net_gold_weight_gm-?) < 0.0005)');
    $report = ['target' => ['host' => $c['hostname'], 'database' => $c['database']], 'manifest' => realpath($argv[1]), 'manifest_sha256' => hash_file('sha256', $argv[1]), 'warning' => 'Review candidates individually. No match does not establish uniqueness; invoice dates need not equal receiving dates. No database writes performed.', 'bills' => []];
    $issues = [];
    foreach ($manifest['bills'] as $index => $bill) {
        if (! is_array($bill)) { throw new RuntimeException('Bill ' . ($index + 1) . ' is not an object.'); }
        $date = (string) ($bill['invoice_date'] ?? '');
        $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $dateObject || $dateObject->format('Y-m-d') !== $date || $date < '2026-08-01' || $date >= '2026-10-01') {
            throw new RuntimeException('Bill ' . ($index + 1) . ' has an invalid or out-of-scope invoice date; only August and September 2026 are allowed.');
        }
        $name = trim((string) ($bill['karigar_master_name'] ?? $bill['karigar'] ?? ''));
        $invoice = trim((string) ($bill['invoice_no'] ?? ''));
        if ($name === '' || $invoice === '' || ! isset($bill['items']) || ! is_array($bill['items']) || $bill['items'] === []) {
            throw new RuntimeException('Each bill requires a karigar master name, invoice number, and item list.');
        }
        $findKarigar->bind_param('s', $name); $findKarigar->execute();
        $masters = $findKarigar->get_result()->fetch_all(MYSQLI_ASSOC);
        $out = ['invoice_no' => $invoice, 'invoice_date' => $date, 'karigar_master_name' => $name, 'master_matches' => $masters, 'existing_labour_bills' => [], 'items' => []];
        if (count($masters) !== 1) {
            $issues[] = "Bill $invoice: master name '$name' matched " . count($masters) . ' records; provide the exact karigar_master_name.';
            $report['bills'][] = $out;
            continue;
        }
        $karigarId = (int) $masters[0]['id'];
        $findBills->bind_param('i', $karigarId); $findBills->execute();
        foreach ($findBills->get_result()->fetch_all(MYSQLI_ASSOC) as $existingBill) {
            // Include same-date bills as well as exact invoice numbers: invoice numbering
            // may differ between scanned and existing formatted references.
            if (strcasecmp(trim((string) $existingBill['bill_no']), $invoice) === 0 || $existingBill['bill_date'] === $date) {
                $out['existing_labour_bills'][] = $existingBill;
            }
        }
        foreach ($bill['items'] as $lineIndex => $item) {
            if (! is_array($item)) { throw new RuntimeException("Bill $invoice item is not an object."); }
            $grossValue = $item['gross_weight_g'] ?? $item['gross_g'] ?? null;
            $netValue = $item['net_weight_g'] ?? $item['net_g'] ?? null;
            if (! is_numeric($grossValue) || ! is_numeric($netValue) || (float) $grossValue <= 0 || (float) $netValue <= 0) {
                $issues[] = "Bill $invoice item " . ($lineIndex + 1) . ': gross and net weights must both be known and positive.';
                $out['items'][] = ['line' => $item['line_no'] ?? ($lineIndex + 1), 'error' => 'Missing valid gross or net weight; no search performed.'];
                continue;
            }
            $gross = (float) $grossValue; $net = (float) $netValue;
            $karat = isset($item['karat']) && is_numeric($item['karat']) ? (int) $item['karat'] : null;
            $pure = $item['pure_weight_g'] ?? $item['pure_g_as_printed'] ?? null;
            $findReceipts->bind_param('idd', $karigarId, $gross, $net); $findReceipts->execute();
            $candidates = $findReceipts->get_result()->fetch_all(MYSQLI_ASSOC);
            $findOrphanSummaries->bind_param('idd', $karigarId, $gross, $net); $findOrphanSummaries->execute();
            $orphans = $findOrphanSummaries->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($orphans as &$orphan) { $orphan['orphan_summary'] = true; }
            unset($orphan);
            $candidates = array_merge($candidates, $orphans);
            foreach ($candidates as &$candidate) {
                $candidate['gross_matches'] = $candidate['gross_g'] !== null && abs((float) $candidate['gross_g'] - $gross) < 0.0005;
                $candidate['net_matches'] = $candidate['net_g'] !== null && abs((float) $candidate['net_g'] - $net) < 0.0005;
                $candidate['pure_matches'] = is_numeric($pure) && $candidate['pure_g'] !== null ? abs((float) $candidate['pure_g'] - (float) $pure) < 0.0005 : null;
                $label = (string) (($candidate['purity_label'] ?? '') ?: ($candidate['purity_code'] ?? ''));
                $candidate['purity_matches'] = $karat !== null && preg_match('/\b(14|18|22|24)\s*K(?:T)?\b/i', $label, $m) ? (int) $m[1] === $karat : null;
                $candidateDate = substr((string) ($candidate['summary_created_at'] ?: $candidate['movement_created_at']), 0, 10);
                $candidate['days_from_invoice'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidateDate) ? (int) $dateObject->diff(new DateTimeImmutable($candidateDate))->format('%r%a') : null;
            }
            unset($candidate);
            $out['items'][] = ['line' => $item['line_no'] ?? ($lineIndex + 1), 'description' => $item['description'] ?? null, 'gross_g' => $gross, 'net_g' => $net, 'karat' => $karat, 'pure_g' => $pure, 'candidates' => $candidates];
        }
        $report['bills'][] = $out;
    }
    $db->rollback();
    $report['issues'] = $issues;
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
    exit($issues === [] ? 0 : 2);
} catch (Throwable $e) {
    fwrite(STDERR, 'Preflight failed: ' . $e->getMessage() . "\n");
    exit(1);
}
