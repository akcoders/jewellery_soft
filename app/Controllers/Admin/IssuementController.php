<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CompanySettingModel;
use App\Models\GoldInventoryIssueHeaderModel;
use App\Models\GoldInventoryIssueLineModel;
use App\Models\GoldInventoryItemModel;
use App\Models\InventoryLocationModel;
use App\Models\IssueHeaderModel;
use App\Models\IssueLineModel;
use App\Models\ItemModel;
use App\Models\KarigarModel;
use App\Models\OrderModel;
use App\Models\StoneInventoryIssueHeaderModel;
use App\Models\StoneInventoryIssueLineModel;
use App\Models\StoneInventoryItemModel;
use App\Services\DiamondInventory\StockService as DiamondStockService;
use App\Services\GoldInventory\StockService as GoldStockService;
use App\Services\StoneInventory\StockService as StoneStockService;
use Throwable;

class IssuementController extends BaseController
{
    private $orderModel;
    private $karigarModel;
    private $locationModel;
    private $companySettingModel;

    private $goldHeaderModel;
    private $goldLineModel;
    private $goldItemModel;

    private $diamondHeaderModel;
    private $diamondLineModel;
    private $diamondItemModel;

    private $stoneHeaderModel;
    private $stoneLineModel;
    private $stoneItemModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->orderModel = new OrderModel();
        $this->karigarModel = new KarigarModel();
        $this->locationModel = new InventoryLocationModel();
        $this->companySettingModel = new CompanySettingModel();

        $this->goldHeaderModel = new GoldInventoryIssueHeaderModel();
        $this->goldLineModel = new GoldInventoryIssueLineModel();
        $this->goldItemModel = new GoldInventoryItemModel();

        $this->diamondHeaderModel = new IssueHeaderModel();
        $this->diamondLineModel = new IssueLineModel();
        $this->diamondItemModel = new ItemModel();

        $this->stoneHeaderModel = new StoneInventoryIssueHeaderModel();
        $this->stoneLineModel = new StoneInventoryIssueLineModel();
        $this->stoneItemModel = new StoneInventoryItemModel();
    }

    public function index(): string
    {
        $db = db_connect();

        $goldList = [];
        $goldRows = $db->table('gold_inventory_issue_headers ih')
            ->select("'Gold' as material_type, ih.id, ih.voucher_no, ih.issue_date, ih.attachment_path, o.order_no, k.name as karigar_name, iloc.name as warehouse_name, ih.purpose, COALESCE(SUM(il.weight_gm),0) as total_qty, 0 as total_pcs, COALESCE(SUM(il.line_value),0) as total_value", false)
            ->join('gold_inventory_issue_lines il', 'il.issue_id = ih.id', 'left')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->groupBy('ih.id')
            ->orderBy('ih.issue_date', 'DESC')
            ->orderBy('ih.id', 'DESC')
            ->get()
            ->getResultArray();
        $rows = [];
        foreach ($goldRows as $row) {
            $row['voucher_url'] = site_url('admin/issuements/voucher/' . rawurlencode((string) ($row['voucher_no'] ?? '')));
            $row['view_url'] = site_url('admin/gold-inventory/issues/view/' . (int) ($row['id'] ?? 0));
            $row['attachment_url'] = ((string) ($row['attachment_path'] ?? '') !== '') ? base_url((string) $row['attachment_path']) : '';
            $goldList[] = $row;
            $rows[] = $row;
        }
        $goldRows = $goldList ?? [];

        $diamondList = [];
        $diamondRows = $db->table('issue_headers ih')
            ->select("'Diamond' as material_type, ih.id, ih.voucher_no, ih.issue_date, ih.attachment_path, o.order_no, k.name as karigar_name, iloc.name as warehouse_name, ih.purpose, COALESCE(SUM(il.carat),0) as total_qty, COALESCE(SUM(il.pcs),0) as total_pcs, COALESCE(SUM(il.line_value),0) as total_value", false)
            ->join('issue_lines il', 'il.issue_id = ih.id', 'left')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->groupBy('ih.id')
            ->orderBy('ih.issue_date', 'DESC')
            ->orderBy('ih.id', 'DESC')
            ->get()
            ->getResultArray();
        foreach ($diamondRows as $row) {
            $row['voucher_url'] = site_url('admin/issuements/voucher/' . rawurlencode((string) ($row['voucher_no'] ?? '')));
            $row['view_url'] = site_url('admin/diamond-inventory/issues/view/' . (int) ($row['id'] ?? 0));
            $row['attachment_url'] = ((string) ($row['attachment_path'] ?? '') !== '') ? base_url((string) $row['attachment_path']) : '';
            $diamondList[] = $row;
            $rows[] = $row;
        }
        $diamondRows = $diamondList ?? [];

        $stoneList = [];
        $stoneRows = $db->table('stone_inventory_issue_headers ih')
            ->select("'Stone' as material_type, ih.id, ih.voucher_no, ih.issue_date, ih.attachment_path, o.order_no, k.name as karigar_name, iloc.name as warehouse_name, ih.purpose, COALESCE(SUM(il.qty),0) as total_qty, COALESCE(SUM(il.pcs),0) as total_pcs, COALESCE(SUM(il.line_value),0) as total_value", false)
            ->join('stone_inventory_issue_lines il', 'il.issue_id = ih.id', 'left')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->groupBy('ih.id')
            ->orderBy('ih.issue_date', 'DESC')
            ->orderBy('ih.id', 'DESC')
            ->get()
            ->getResultArray();
        foreach ($stoneRows as $row) {
            $row['voucher_url'] = site_url('admin/issuements/voucher/' . rawurlencode((string) ($row['voucher_no'] ?? '')));
            $row['view_url'] = site_url('admin/stone-inventory/issues/view/' . (int) ($row['id'] ?? 0));
            $row['attachment_url'] = ((string) ($row['attachment_path'] ?? '') !== '') ? base_url((string) $row['attachment_path']) : '';
            $stoneList[] = $row;
            $rows[] = $row;
        }
        $stoneRows = $stoneList ?? [];

        $groupedRows = [];
        foreach ($rows as $row) {
            $voucherNo = trim((string) ($row['voucher_no'] ?? ''));
            $groupKey = $voucherNo !== ''
                ? ('vch:' . $voucherNo)
                : ('single:' . (string) ($row['material_type'] ?? '') . ':' . (string) ($row['id'] ?? '0'));

            if (! isset($groupedRows[$groupKey])) {
                $groupedRows[$groupKey] = $row;
                $groupedRows[$groupKey]['material_types'] = [(string) ($row['material_type'] ?? '')];
                $groupedRows[$groupKey]['voucher_list'] = [(string) ($row['voucher_no'] ?? '')];
                continue;
            }

            $groupedRows[$groupKey]['total_qty'] = (float) ($groupedRows[$groupKey]['total_qty'] ?? 0) + (float) ($row['total_qty'] ?? 0);
            $groupedRows[$groupKey]['total_pcs'] = (float) ($groupedRows[$groupKey]['total_pcs'] ?? 0) + (float) ($row['total_pcs'] ?? 0);
            $groupedRows[$groupKey]['total_value'] = (float) ($groupedRows[$groupKey]['total_value'] ?? 0) + (float) ($row['total_value'] ?? 0);
            $groupedRows[$groupKey]['material_types'][] = (string) ($row['material_type'] ?? '');
            $groupedRows[$groupKey]['voucher_list'][] = (string) ($row['voucher_no'] ?? '');

            if ((int) ($row['id'] ?? 0) > (int) ($groupedRows[$groupKey]['id'] ?? 0)) {
                $groupedRows[$groupKey]['id'] = (int) $row['id'];
                $groupedRows[$groupKey]['issue_date'] = (string) ($row['issue_date'] ?? '');
            }
        }

        $rows = [];
        foreach ($groupedRows as $row) {
            $types = array_values(array_unique(array_filter((array) ($row['material_types'] ?? []))));
            if (count($types) > 1) {
                $row['material_type'] = 'Mixed';
            }
            $voucherList = array_values(array_unique(array_filter((array) ($row['voucher_list'] ?? []))));
            $primaryVoucherNo = $voucherList !== [] ? (string) $voucherList[0] : (string) ($row['voucher_no'] ?? '');
            $row['voucher_no'] = $primaryVoucherNo;
            if ((string) ($row['material_type'] ?? '') === 'Mixed') {
                $row['view_url'] = site_url('admin/issuements/view/' . rawurlencode($primaryVoucherNo));
            }
            $row['voucher_url'] = site_url('admin/issuements/voucher/' . rawurlencode($primaryVoucherNo));
            $rows[] = $row;
        }

        usort($rows, static function (array $a, array $b): int {
            $dateCompare = strcmp((string) ($b['issue_date'] ?? ''), (string) ($a['issue_date'] ?? ''));
            if ($dateCompare !== 0) {
                return $dateCompare;
            }
            return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
        });

        return view('admin/issuements/index', [
            'title' => 'Issuements',
            'rows' => $rows,
            'goldRows' => $goldRows,
            'diamondRows' => $diamondRows,
            'stoneRows' => $stoneRows,
        ]);
    }

    public function create(): string
    {
        return view('admin/issuements/create', [
            'title' => 'Create Issuement',
            'orders' => $this->orderOptions(),
            'karigars' => $this->karigarOptions(),
            'locations' => $this->locationOptions(),
            'goldItems' => $this->goldItemOptions(),
            'diamondItems' => $this->diamondItemOptions(),
            'stoneItems' => $this->stoneItemOptions(),
        ]);
    }

    public function show(string $voucherNoParam): string
    {
        $voucherNo = trim(urldecode($voucherNoParam));
        $bundle = $this->loadIssueBundle($voucherNo);

        return view('admin/issuements/show', [
            'title' => 'Issuement Details',
            'company' => $this->companySetting(),
            'voucherNo' => $bundle['voucherNo'],
            'issue' => $bundle['issue'],
            'materialType' => $bundle['materialType'],
            'supplierName' => $bundle['supplierName'],
            'goldLines' => $bundle['goldLines'],
            'diamondLines' => $bundle['diamondLines'],
            'stoneLines' => $bundle['stoneLines'],
            'totalValue' => $bundle['totalValue'],
        ]);
    }

    public function voucher(string $voucherNoParam): string
    {
        $voucherNo = trim(urldecode($voucherNoParam));
        $bundle = $this->loadIssueBundle($voucherNo);

        return view('admin/vouchers/issuement_combined', [
            'title' => 'Issuement Voucher',
            'company' => $this->companySetting(),
            'voucherNo' => $bundle['voucherNo'],
            'issue' => $bundle['issue'],
            'materialType' => $bundle['materialType'],
            'supplierName' => $bundle['supplierName'],
            'goldLines' => $bundle['goldLines'],
            'diamondLines' => $bundle['diamondLines'],
            'stoneLines' => $bundle['stoneLines'],
            'totalValue' => $bundle['totalValue'],
        ]);
    }

    public function store()
    {
        $headerError = $this->validateHeader();
        if ($headerError !== null) {
            return redirect()->back()->withInput()->with('error', $headerError);
        }

        $goldLines = $this->collectGoldLines();
        if ($goldLines['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $goldLines['error']);
        }
        $diamondLines = $this->collectDiamondLines();
        if ($diamondLines['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $diamondLines['error']);
        }
        $stoneLines = $this->collectStoneLines();
        if ($stoneLines['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $stoneLines['error']);
        }

        if ($goldLines['lines'] === [] && $diamondLines['lines'] === [] && $stoneLines['lines'] === []) {
            return redirect()->back()->withInput()->with('error', 'Add at least one line in Gold, Diamond, or Stone section.');
        }

        $attachment = $this->processAttachment();
        if ($attachment['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $attachment['error']);
        }

        $db = db_connect();
        $goldService = new GoldStockService($db);
        $diamondService = new DiamondStockService($db);
        $stoneService = new StoneStockService($db);

        $issueDate = (string) $this->request->getPost('issue_date');
        $orderId = (int) $this->request->getPost('order_id');
        $karigarId = (int) $this->request->getPost('karigar_id');
        $locationId = (int) $this->request->getPost('location_id');
        $purpose = trim((string) $this->request->getPost('purpose'));
        $notes = trim((string) $this->request->getPost('notes')) ?: null;
        $karigar = $this->karigarModel->find($karigarId);
        $issueTo = (string) ($karigar['name'] ?? '');
        $adminId = (int) session('admin_id');
        $commonVoucherNo = $this->generateGlobalIssueVoucherNo();

        $createdMaterials = [];

        try {
            $db->transException(true)->transStart();

            if ($goldLines['lines'] !== []) {
                $goldIssueId = (int) $this->goldHeaderModel->insert([
                    'voucher_no' => $commonVoucherNo,
                    'issue_date' => $issueDate,
                    'order_id' => $orderId,
                    'karigar_id' => $karigarId,
                    'location_id' => $locationId,
                    'issue_to' => $issueTo,
                    'purpose' => $purpose,
                    'notes' => $notes,
                    'attachment_name' => $attachment['name'],
                    'attachment_path' => $attachment['path'],
                    'created_by' => $adminId,
                ], true);

                foreach ($goldLines['lines'] as $line) {
                    $itemId = (int) $line['item_id'];
                    $this->goldLineModel->insert([
                        'issue_id' => $goldIssueId,
                        'item_id' => $itemId,
                        'weight_gm' => $line['weight_gm'],
                        'fine_weight_gm' => $goldService->calculateFineWeightForItem($itemId, (float) $line['weight_gm']),
                        'rate_per_gm' => $line['rate_per_gm'],
                        'line_value' => $line['line_value'],
                    ]);
                }

                $goldService->applyIssue($goldIssueId, [
                    'txn_date' => $issueDate,
                    'order_id' => $orderId,
                    'karigar_id' => $karigarId,
                    'location_id' => $locationId,
                    'created_by' => $adminId,
                    'notes' => 'Common issuement - Gold',
                ]);

                $createdMaterials[] = 'Gold';
            }

            if ($diamondLines['lines'] !== []) {
                $diamondIssueId = (int) $this->diamondHeaderModel->insert([
                    'voucher_no' => $commonVoucherNo,
                    'issue_date' => $issueDate,
                    'order_id' => $orderId,
                    'karigar_id' => $karigarId,
                    'location_id' => $locationId,
                    'issue_to' => $issueTo,
                    'purpose' => $purpose,
                    'notes' => $notes,
                    'attachment_name' => $attachment['name'],
                    'attachment_path' => $attachment['path'],
                    'created_by' => $adminId,
                ], true);

                foreach ($diamondLines['lines'] as $line) {
                    $this->diamondLineModel->insert([
                        'issue_id' => $diamondIssueId,
                        'item_id' => $line['item_id'],
                        'pcs' => $line['pcs'],
                        'carat' => $line['carat'],
                        'rate_per_carat' => $line['rate_per_carat'],
                        'line_value' => $line['line_value'],
                    ]);
                }

                $diamondService->applyIssue($diamondIssueId);
                $createdMaterials[] = 'Diamond';
            }

            if ($stoneLines['lines'] !== []) {
                $stoneIssueId = (int) $this->stoneHeaderModel->insert([
                    'voucher_no' => $commonVoucherNo,
                    'issue_date' => $issueDate,
                    'order_id' => $orderId,
                    'karigar_id' => $karigarId,
                    'location_id' => $locationId,
                    'issue_to' => $issueTo,
                    'purpose' => $purpose,
                    'notes' => $notes,
                    'attachment_name' => $attachment['name'],
                    'attachment_path' => $attachment['path'],
                    'created_by' => $adminId,
                ], true);

                foreach ($stoneLines['lines'] as $line) {
                    $this->stoneLineModel->insert([
                        'issue_id' => $stoneIssueId,
                        'item_id' => $line['item_id'],
                        'pcs' => $line['pcs'],
                        'qty' => $line['qty'],
                        'rate' => $line['rate'],
                        'line_value' => $line['line_value'],
                    ]);
                }

                $stoneService->applyIssue($stoneIssueId);
                $createdMaterials[] = 'Stone';
            }

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/issuements'))
            ->with('success', 'Issuement saved. Voucher: ' . $commonVoucherNo . ' | Materials: ' . implode(', ', $createdMaterials));
    }

    /**
     * @return array{
     *   voucherNo:string,
     *   issue:array<string,mixed>,
     *   materialType:string,
     *   supplierName:string,
     *   goldLines:list<array<string,mixed>>,
     *   diamondLines:list<array<string,mixed>>,
     *   stoneLines:list<array<string,mixed>>,
     *   totalValue:float
     * }
     */
    private function loadIssueBundle(string $voucherNo): array
    {
        $voucherNo = trim($voucherNo);
        if ($voucherNo === '') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Voucher not found.');
        }

        $db = db_connect();

        $goldHeader = $db->table('gold_inventory_issue_headers ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name, k.name as labour_name, k.phone as labour_phone, k.email as labour_email, k.address as labour_address, k.city as labour_city, k.state as labour_state, k.pincode as labour_pincode, k.department as labour_department, k.skills_text as labour_skills, k.rate_per_gm as labour_rate_per_gm, k.wastage_percentage as labour_wastage_percentage, k.aadhaar_no as labour_aadhaar_no, k.pan_no as labour_pan_no, k.joining_date as labour_joining_date, k.bank_name as labour_bank_name, k.bank_account_no as labour_bank_account_no, k.ifsc_code as labour_ifsc_code')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->where('ih.voucher_no', $voucherNo)
            ->orderBy('ih.id', 'DESC')
            ->get()
            ->getRowArray();

        $diamondHeader = $db->table('issue_headers ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name, k.name as labour_name, k.phone as labour_phone, k.email as labour_email, k.address as labour_address, k.city as labour_city, k.state as labour_state, k.pincode as labour_pincode, k.department as labour_department, k.skills_text as labour_skills, k.rate_per_gm as labour_rate_per_gm, k.wastage_percentage as labour_wastage_percentage, k.aadhaar_no as labour_aadhaar_no, k.pan_no as labour_pan_no, k.joining_date as labour_joining_date, k.bank_name as labour_bank_name, k.bank_account_no as labour_bank_account_no, k.ifsc_code as labour_ifsc_code')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->where('ih.voucher_no', $voucherNo)
            ->orderBy('ih.id', 'DESC')
            ->get()
            ->getRowArray();

        $stoneHeader = $db->table('stone_inventory_issue_headers ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name, k.name as labour_name, k.phone as labour_phone, k.email as labour_email, k.address as labour_address, k.city as labour_city, k.state as labour_state, k.pincode as labour_pincode, k.department as labour_department, k.skills_text as labour_skills, k.rate_per_gm as labour_rate_per_gm, k.wastage_percentage as labour_wastage_percentage, k.aadhaar_no as labour_aadhaar_no, k.pan_no as labour_pan_no, k.joining_date as labour_joining_date, k.bank_name as labour_bank_name, k.bank_account_no as labour_bank_account_no, k.ifsc_code as labour_ifsc_code')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->where('ih.voucher_no', $voucherNo)
            ->orderBy('ih.id', 'DESC')
            ->get()
            ->getRowArray();

        if (! $goldHeader && ! $diamondHeader && ! $stoneHeader) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Voucher not found.');
        }

        $goldLines = [];
        if ($goldHeader) {
            $goldLines = $db->table('gold_inventory_issue_lines il')
                ->select('il.*, gi.form_type, gi.color_name, gi.purity_code, gp.purity_code as master_purity_code')
                ->join('gold_inventory_items gi', 'gi.id = il.item_id', 'left')
                ->join('gold_purities gp', 'gp.id = gi.gold_purity_id', 'left')
                ->where('il.issue_id', (int) $goldHeader['id'])
                ->orderBy('il.id', 'ASC')
                ->get()
                ->getResultArray();
        }

        $diamondLines = [];
        if ($diamondHeader) {
            $diamondLines = $db->table('issue_lines il')
                ->select('il.*, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, i.cut')
                ->join('items i', 'i.id = il.item_id', 'left')
                ->where('il.issue_id', (int) $diamondHeader['id'])
                ->orderBy('il.id', 'ASC')
                ->get()
                ->getResultArray();
        }

        $stoneLines = [];
        if ($stoneHeader) {
            $stoneLines = $db->table('stone_inventory_issue_lines il')
                ->select('il.*, si.product_name, si.stone_type')
                ->join('stone_inventory_items si', 'si.id = il.item_id', 'left')
                ->where('il.issue_id', (int) $stoneHeader['id'])
                ->orderBy('il.id', 'ASC')
                ->get()
                ->getResultArray();
        }

        $issue = $goldHeader ?: ($diamondHeader ?: $stoneHeader);
        $supplierName = trim((string) ($issue['issue_to'] ?? ''));
        if ($supplierName === '') {
            $supplierName = (string) ($issue['karigar_name'] ?? '-');
        }

        $goldTotalValue = 0.0;
        foreach ($goldLines as $line) {
            $goldTotalValue += (float) ($line['line_value'] ?? 0);
        }
        $diamondTotalValue = 0.0;
        foreach ($diamondLines as $line) {
            $diamondTotalValue += (float) ($line['line_value'] ?? 0);
        }
        $stoneTotalValue = 0.0;
        foreach ($stoneLines as $line) {
            $stoneTotalValue += (float) ($line['line_value'] ?? 0);
        }

        $materialCount = 0;
        if ($goldLines !== []) {
            $materialCount++;
        }
        if ($diamondLines !== []) {
            $materialCount++;
        }
        if ($stoneLines !== []) {
            $materialCount++;
        }
        $materialType = $materialCount > 1 ? 'Mixed' : ($goldLines !== [] ? 'Gold' : ($diamondLines !== [] ? 'Diamond' : 'Stone'));

        return [
            'voucherNo' => $voucherNo,
            'issue' => is_array($issue) ? $issue : [],
            'materialType' => $materialType,
            'supplierName' => $supplierName,
            'goldLines' => $goldLines,
            'diamondLines' => $diamondLines,
            'stoneLines' => $stoneLines,
            'totalValue' => $goldTotalValue + $diamondTotalValue + $stoneTotalValue,
        ];
    }

    private function validateHeader(): ?string
    {
        if (! $this->validate([
            'issue_date' => 'required|valid_date',
            'order_id' => 'required|integer|greater_than[0]',
            'karigar_id' => 'required|integer|greater_than[0]',
            'location_id' => 'required|integer|greater_than[0]',
            'purpose' => 'required|max_length[50]',
            'notes' => 'permit_empty',
        ])) {
            $errors = $this->validator ? $this->validator->getErrors() : [];
            return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
        }

        $orderId = (int) $this->request->getPost('order_id');
        $karigarId = (int) $this->request->getPost('karigar_id');
        $locationId = (int) $this->request->getPost('location_id');

        $order = db_connect()->table('orders')
            ->select('id, assigned_karigar_id, status')
            ->where('id', $orderId)
            ->whereNotIn('status', ['Cancelled', 'Completed'])
            ->get()
            ->getRowArray();
        if (! $order || (int) ($order['assigned_karigar_id'] ?? 0) <= 0) {
            return 'Only karigar-assigned active orders are allowed for issuance.';
        }
        if ((int) ($order['assigned_karigar_id'] ?? 0) !== $karigarId) {
            return 'Selected karigar does not match order assignment.';
        }

        if ($this->karigarModel->where('id', $karigarId)->where('is_active', 1)->countAllResults() === 0) {
            return 'Selected karigar was not found or inactive.';
        }
        if ($this->locationModel->where('id', $locationId)->where('is_active', 1)->countAllResults() === 0) {
            return 'Selected warehouse was not found.';
        }

        return null;
    }

    /** @return array{lines:list<array<string,mixed>>,error:?string} */
    private function collectGoldLines(): array
    {
        $itemIds = (array) $this->request->getPost('gold_item_id');
        $weights = (array) $this->request->getPost('gold_weight_gm');
        $rates = (array) $this->request->getPost('gold_rate_per_gm');

        $max = max(count($itemIds), count($weights), count($rates));
        $lines = [];
        for ($i = 0; $i < $max; $i++) {
            $itemId = (int) ($itemIds[$i] ?? 0);
            $weight = (float) ($weights[$i] ?? 0);
            $rateRaw = trim((string) ($rates[$i] ?? ''));

            $isBlank = $itemId <= 0 && $weight <= 0 && $rateRaw === '';
            if ($isBlank) {
                continue;
            }
            if ($itemId <= 0) {
                return ['lines' => [], 'error' => 'Select gold item for each gold line.'];
            }
            if (! $this->goldItemModel->find($itemId)) {
                return ['lines' => [], 'error' => 'Selected gold item does not exist.'];
            }
            if ($weight <= 0) {
                return ['lines' => [], 'error' => 'Gold weight must be greater than zero.'];
            }

            $rate = $rateRaw === '' ? null : (float) $rateRaw;
            if ($rate !== null && $rate < 0) {
                return ['lines' => [], 'error' => 'Gold rate cannot be negative.'];
            }

            $lines[] = [
                'item_id' => $itemId,
                'weight_gm' => round($weight, 3),
                'rate_per_gm' => $rate === null ? null : round($rate, 2),
                'line_value' => $rate === null ? null : round($weight * $rate, 2),
            ];
        }

        return ['lines' => $lines, 'error' => null];
    }

    /** @return array{lines:list<array<string,mixed>>,error:?string} */
    private function collectDiamondLines(): array
    {
        $itemIds = (array) $this->request->getPost('diamond_item_id');
        $pcsList = (array) $this->request->getPost('diamond_pcs');
        $carats = (array) $this->request->getPost('diamond_carat');
        $rates = (array) $this->request->getPost('diamond_rate_per_carat');

        $max = max(count($itemIds), count($pcsList), count($carats), count($rates));
        $lines = [];
        for ($i = 0; $i < $max; $i++) {
            $itemId = (int) ($itemIds[$i] ?? 0);
            $pcs = (float) ($pcsList[$i] ?? 0);
            $carat = (float) ($carats[$i] ?? 0);
            $rateRaw = trim((string) ($rates[$i] ?? ''));

            $isBlank = $itemId <= 0 && $pcs <= 0 && $carat <= 0 && $rateRaw === '';
            if ($isBlank) {
                continue;
            }
            if ($itemId <= 0) {
                return ['lines' => [], 'error' => 'Select diamond item for each diamond line.'];
            }
            if (! $this->diamondItemModel->find($itemId)) {
                return ['lines' => [], 'error' => 'Selected diamond item does not exist.'];
            }
            if ($pcs < 0) {
                return ['lines' => [], 'error' => 'Diamond PCS cannot be negative.'];
            }
            if ($carat <= 0) {
                return ['lines' => [], 'error' => 'Diamond cts must be greater than zero.'];
            }

            $rate = $rateRaw === '' ? null : (float) $rateRaw;
            if ($rate !== null && $rate < 0) {
                return ['lines' => [], 'error' => 'Diamond rate cannot be negative.'];
            }

            $lines[] = [
                'item_id' => $itemId,
                'pcs' => round($pcs, 3),
                'carat' => round($carat, 3),
                'rate_per_carat' => $rate === null ? null : round($rate, 2),
                'line_value' => $rate === null ? null : round($carat * $rate, 2),
            ];
        }

        return ['lines' => $lines, 'error' => null];
    }

    /** @return array{lines:list<array<string,mixed>>,error:?string} */
    private function collectStoneLines(): array
    {
        $itemIds = (array) $this->request->getPost('stone_item_id');
        $pcsList = (array) $this->request->getPost('stone_pcs');
        $qtys = (array) $this->request->getPost('stone_qty');
        $rates = (array) $this->request->getPost('stone_rate');

        $max = max(count($itemIds), count($pcsList), count($qtys), count($rates));
        $lines = [];
        for ($i = 0; $i < $max; $i++) {
            $itemId = (int) ($itemIds[$i] ?? 0);
            $pcs = (float) ($pcsList[$i] ?? 0);
            $qty = (float) ($qtys[$i] ?? 0);
            $rateRaw = trim((string) ($rates[$i] ?? ''));

            $isBlank = $itemId <= 0 && $pcs <= 0 && $qty <= 0 && $rateRaw === '';
            if ($isBlank) {
                continue;
            }
            if ($itemId <= 0) {
                return ['lines' => [], 'error' => 'Select stone item for each stone line.'];
            }
            if (! $this->stoneItemModel->find($itemId)) {
                return ['lines' => [], 'error' => 'Selected stone item does not exist.'];
            }
            if ($pcs < 0) {
                return ['lines' => [], 'error' => 'Stone PCS cannot be negative.'];
            }
            if ($qty <= 0) {
                return ['lines' => [], 'error' => 'Stone qty must be greater than zero.'];
            }

            $rate = $rateRaw === '' ? null : (float) $rateRaw;
            if ($rate !== null && $rate < 0) {
                return ['lines' => [], 'error' => 'Stone rate cannot be negative.'];
            }

            $lines[] = [
                'item_id' => $itemId,
                'pcs' => round($pcs, 3),
                'qty' => round($qty, 3),
                'rate' => $rate === null ? null : round($rate, 2),
                'line_value' => $rate === null ? null : round($qty * $rate, 2),
            ];
        }

        return ['lines' => $lines, 'error' => null];
    }

    /** @return array{name:?string,path:?string,error:?string} */
    private function processAttachment(): array
    {
        $file = $this->request->getFile('attachment');
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return ['name' => null, 'path' => null, 'error' => 'Attachment is required for issuement.'];
        }
        if (! $file->isValid()) {
            return ['name' => null, 'path' => null, 'error' => 'Invalid attachment upload.'];
        }
        if ($file->getSizeByUnit('kb') > 10240) {
            return ['name' => null, 'path' => null, 'error' => 'Attachment size must be 10MB or less.'];
        }

        $ext = strtolower((string) $file->getExtension());
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
            return ['name' => null, 'path' => null, 'error' => 'Attachment must be jpg, png, webp, or pdf.'];
        }

        $uploadDir = FCPATH . 'uploads/issuements/common';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $newName = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        $file->move($uploadDir, $newName);

        return [
            'name' => (string) $file->getClientName(),
            'path' => 'uploads/issuements/common/' . $newName,
            'error' => null,
        ];
    }

    private function generateGlobalIssueVoucherNo(): string
    {
        $db = db_connect();
        $prefix = strtoupper(trim((string) ($this->companySetting()['issuement_suffix'] ?? 'ISS')));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?? 'ISS';
        if ($prefix === '') {
            $prefix = 'ISS';
        }

        $tables = ['gold_inventory_issue_headers', 'issue_headers', 'stone_inventory_issue_headers'];
        $maxSerial = 0;
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';

        foreach ($tables as $table) {
            if (! $db->tableExists($table)) {
                continue;
            }
            $rows = $db->table($table)->select('voucher_no')->like('voucher_no', $prefix, 'after')->get()->getResultArray();
            foreach ($rows as $row) {
                $voucherNo = (string) ($row['voucher_no'] ?? '');
                if (preg_match($pattern, $voucherNo, $m) === 1) {
                    $n = (int) $m[1];
                    if ($n > $maxSerial) {
                        $maxSerial = $n;
                    }
                }
            }
        }

        do {
            $maxSerial++;
            $voucher = $prefix . str_pad((string) $maxSerial, 3, '0', STR_PAD_LEFT);
            $exists = false;
            foreach ($tables as $table) {
                if (! $db->tableExists($table)) {
                    continue;
                }
                if ($db->table($table)->where('voucher_no', $voucher)->countAllResults() > 0) {
                    $exists = true;
                    break;
                }
            }
        } while ($exists);

        return $voucher;
    }

    /** @return list<array<string,mixed>> */
    private function orderOptions(): array
    {
        return db_connect()->table('orders o')
            ->select('o.id, o.order_no, o.order_type, o.assigned_karigar_id, k.name as karigar_name, COALESCE(SUM(oi.gold_required_gm),0) as gold_budget_gm, COALESCE(SUM(oi.diamond_required_cts),0) as diamond_budget_cts', false)
            ->join('order_items oi', 'oi.order_id = o.id', 'left')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'left')
            ->whereNotIn('o.status', ['Cancelled', 'Completed'])
            ->where('o.assigned_karigar_id IS NOT NULL', null, false)
            ->where('o.assigned_karigar_id >', 0)
            ->groupBy('o.id')
            ->orderBy('o.id', 'DESC')
            ->limit(500)
            ->get()
            ->getResultArray();
    }

    /** @return list<array<string,mixed>> */
    private function karigarOptions(): array
    {
        return $this->karigarModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    /** @return list<array<string,mixed>> */
    private function locationOptions(): array
    {
        return $this->locationModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    /** @return list<array<string,mixed>> */
    private function goldItemOptions(): array
    {
        return db_connect()->table('gold_inventory_items gi')
            ->select('gi.id, gi.form_type, gi.color_name, gi.purity_code, gp.purity_code as master_purity_code, gp.color_name as master_color_name, COALESCE(gs.avg_cost_per_gm, 0) as avg_cost_per_gm', false)
            ->join('gold_purities gp', 'gp.id = gi.gold_purity_id', 'left')
            ->join('gold_inventory_stock gs', 'gs.item_id = gi.id', 'left')
            ->orderBy('gi.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /** @return list<array<string,mixed>> */
    private function diamondItemOptions(): array
    {
        return db_connect()->table('items i')
            ->select('i.*, COALESCE(s.avg_cost_per_carat, 0) as avg_cost_per_carat', false)
            ->join('stock s', 's.item_id = i.id', 'left')
            ->orderBy('i.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /** @return list<array<string,mixed>> */
    private function stoneItemOptions(): array
    {
        return $this->stoneItemModel->orderBy('id', 'DESC')->findAll();
    }

    /** @return array<string,mixed> */
    private function companySetting(): array
    {
        $row = $this->companySettingModel->orderBy('id', 'ASC')->first();
        return is_array($row) ? $row : [];
    }
}
