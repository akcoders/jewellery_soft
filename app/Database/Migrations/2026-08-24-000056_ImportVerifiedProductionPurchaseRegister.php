<?php

namespace App\Database\Migrations;

use App\Services\ProductionPurchaseWorkbookService;
use CodeIgniter\Database\Migration;
use RuntimeException;

class ImportVerifiedProductionPurchaseRegister extends Migration
{
    /** @var array<string,int> */
    private array $vendorIds = [];

    public function up()
    {
        if (! $this->db->tableExists('production_purchase_documents')) {
            return;
        }

        $this->widenReconciliationStatus();
        $this->addDocumentColumns();
        $this->importExistingDocuments();
    }

    public function down()
    {
        if (! $this->db->tableExists('production_purchase_documents')) {
            return;
        }

        if ($this->db->tableExists('account_payments')) {
            $this->db->table('account_payments')
                ->where('bill_source_type', 'production_document')
                ->delete();
        }
        if ($this->db->tableExists('vendor_payments')) {
            $this->db->table('vendor_payments')
                ->like('payment_no', 'IMP-PD-', 'after')
                ->delete();
        }

        $indexes = $this->db->getIndexData('production_purchase_documents');
        foreach (['idx_production_document_invoice', 'idx_production_document_gstin'] as $index) {
            if (isset($indexes[$index])) {
                $this->forge->dropKey('production_purchase_documents', $index);
            }
        }

        foreach (array_keys($this->documentColumns()) as $column) {
            if ($this->db->fieldExists($column, 'production_purchase_documents')) {
                $this->forge->dropColumn('production_purchase_documents', $column);
            }
        }
        if ($this->db->fieldExists('reconciliation_status', 'production_purchase_documents')) {
            $this->forge->modifyColumn('production_purchase_documents', [
                'reconciliation_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'default' => 'Source recorded'],
            ]);
        }
    }

    private function widenReconciliationStatus(): void
    {
        if ($this->db->fieldExists('reconciliation_status', 'production_purchase_documents')) {
            $this->forge->modifyColumn('production_purchase_documents', [
                'reconciliation_status' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true, 'default' => 'Source recorded'],
            ]);
        }
    }

    private function addDocumentColumns(): void
    {
        foreach ($this->documentColumns() as $name => $definition) {
            if (! $this->db->fieldExists($name, 'production_purchase_documents')) {
                $this->forge->addColumn('production_purchase_documents', [$name => $definition]);
            }
        }

        $indexes = $this->db->getIndexData('production_purchase_documents');
        if (! isset($indexes['idx_production_document_invoice'])) {
            $this->db->query('ALTER TABLE `production_purchase_documents` ADD INDEX `idx_production_document_invoice` (`invoice_no`)');
        }
        if (! isset($indexes['idx_production_document_gstin'])) {
            $this->db->query('ALTER TABLE `production_purchase_documents` ADD INDEX `idx_production_document_gstin` (`vendor_gstin`)');
        }
    }

    /** @return array<string,array<string,mixed>> */
    private function documentColumns(): array
    {
        return [
            'vendor_address' => ['type' => 'TEXT', 'null' => true, 'after' => 'vendor_name'],
            'vendor_gstin' => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true, 'after' => 'vendor_address'],
            'vendor_phone' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'vendor_gstin'],
            'vendor_email' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'vendor_phone'],
            'payment_terms' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'reconciliation_status'],
            'due_date' => ['type' => 'DATE', 'null' => true, 'after' => 'payment_terms'],
            'place_of_supply' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'due_date'],
            'purchase_description' => ['type' => 'TEXT', 'null' => true, 'after' => 'place_of_supply'],
            'taxable_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true, 'after' => 'purchase_description'],
            'cgst_rate' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'null' => true, 'after' => 'taxable_amount'],
            'cgst_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'cgst_rate'],
            'sgst_rate' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'null' => true, 'after' => 'cgst_amount'],
            'sgst_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'sgst_rate'],
            'igst_rate' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'null' => true, 'after' => 'sgst_amount'],
            'igst_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'igst_rate'],
            'gst_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'igst_amount'],
            'round_off_amount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'gst_amount'],
            'line_items_json' => ['type' => 'LONGTEXT', 'null' => true, 'after' => 'round_off_amount'],
            'workbook_row' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'line_items_json'],
            'verification_status' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'workbook_row'],
        ];
    }

    private function importExistingDocuments(): void
    {
        $documents = $this->db->table('production_purchase_documents')->get()->getResultArray();
        if ($documents === []) {
            return;
        }

        $documentMap = [];
        foreach ($documents as $document) {
            $documentMap[$this->normalizePath((string) $document['source_path'])] = $document;
        }

        $service = new ProductionPurchaseWorkbookService();
        $purchases = $service->purchases();
        $matched = 0;
        foreach ($purchases as $purchase) {
            $sourcePath = $this->normalizePath((string) $purchase['source_path']);
            $document = $documentMap[$sourcePath] ?? $this->findSuffixMatch($documentMap, $sourcePath);
            if (! $document) {
                throw new RuntimeException('Imported purchase document not found for verified workbook row: ' . $sourcePath);
            }

            $vendorId = $this->ensureVendor($purchase, isset($document['vendor_id']) ? (int) $document['vendor_id'] : null);
            $metadata = json_decode((string) ($document['metadata_json'] ?? ''), true);
            if (! is_array($metadata)) {
                $metadata = [];
            }
            $metadata['purchase_workbook'] = basename($service->workbookPath());
            $metadata['purchase_workbook_row'] = (int) $purchase['workbook_row'];
            $metadata['verification_status'] = (string) $purchase['verification_status'];

            $this->db->table('production_purchase_documents')->where('id', (int) $document['id'])->update([
                'category' => strtolower((string) $purchase['category_label']),
                'vendor_id' => $vendorId,
                'vendor_name' => $purchase['vendor_name'],
                'vendor_address' => $purchase['vendor_address'] ?: null,
                'vendor_gstin' => $purchase['vendor_gstin'] ?: null,
                'vendor_phone' => $purchase['vendor_phone'] ?: null,
                'vendor_email' => $purchase['vendor_email'] ?: null,
                'document_date' => $purchase['invoice_date'],
                'invoice_no' => $purchase['invoice_no'],
                'invoice_amount' => $purchase['invoice_total'],
                'payment_status' => $purchase['payment_status'],
                'paid_amount' => $purchase['paid_amount'],
                'payment_date' => $purchase['payment_date'],
                'reconciliation_status' => $purchase['payment_status'] === 'Paid'
                    ? 'Verified PAID filename; full invoice paid'
                    : 'Verified Excel import; payment pending',
                'payment_terms' => $purchase['payment_terms'] ?: null,
                'due_date' => $purchase['due_date'],
                'place_of_supply' => $purchase['place_of_supply'] ?: null,
                'purchase_description' => $purchase['description'] ?: null,
                'taxable_amount' => $purchase['taxable_amount'],
                'cgst_rate' => $purchase['cgst_rate'],
                'cgst_amount' => $purchase['cgst_amount'],
                'sgst_rate' => $purchase['sgst_rate'],
                'sgst_amount' => $purchase['sgst_amount'],
                'igst_rate' => $purchase['igst_rate'],
                'igst_amount' => $purchase['igst_amount'],
                'gst_amount' => $purchase['gst_amount'],
                'round_off_amount' => $purchase['round_off_amount'],
                'line_items_json' => json_encode($purchase['line_items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'workbook_row' => $purchase['workbook_row'],
                'verification_status' => $purchase['verification_status'],
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            if ($purchase['payment_status'] === 'Paid') {
                $this->ensurePayment((int) $document['id'], $vendorId, $purchase);
            }
            $matched++;
        }

        if ($matched !== 35) {
            throw new RuntimeException('Expected to reconcile all 35 production purchase documents.');
        }
    }

    /** @param array<string,array<string,mixed>> $documents */
    private function findSuffixMatch(array $documents, string $sourcePath): ?array
    {
        foreach ($documents as $path => $document) {
            if (str_ends_with($path, $sourcePath) || str_ends_with($sourcePath, $path)) {
                return $document;
            }
        }
        return null;
    }

    /** @param array<string,mixed> $purchase */
    private function ensureVendor(array $purchase, ?int $preferredId): int
    {
        $key = (string) $purchase['vendor_key'];
        if (isset($this->vendorIds[$key])) {
            return $this->vendorIds[$key];
        }

        $gstin = strtoupper(trim((string) $purchase['vendor_gstin']));
        $name = trim((string) $purchase['vendor_name']);
        $vendor = $gstin !== '' ? $this->db->table('vendors')->where('gstin', $gstin)->get()->getRowArray() : null;
        if (! $vendor) {
            $vendor = $this->db->table('vendors')->where('name', $name)->get()->getRowArray();
        }
        if (! $vendor && $preferredId && ! in_array($preferredId, $this->vendorIds, true)) {
            $vendor = $this->db->table('vendors')->where('id', $preferredId)->get()->getRowArray();
        }

        $values = [
            'name' => $name,
            'address' => trim((string) $purchase['vendor_address']) ?: null,
            'gstin' => $gstin ?: null,
            'phone' => trim((string) $purchase['vendor_phone']) ?: null,
            'email' => trim((string) $purchase['vendor_email']) ?: null,
            'is_active' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($vendor) {
            $vendorId = (int) $vendor['id'];
            $this->db->table('vendors')->where('id', $vendorId)->update($values);
        } else {
            $this->db->table('vendors')->insert($values + ['created_at' => date('Y-m-d H:i:s')]);
            $vendorId = (int) $this->db->insertID();
        }

        return $this->vendorIds[$key] = $vendorId;
    }

    /** @param array<string,mixed> $purchase */
    private function ensurePayment(int $documentId, int $vendorId, array $purchase): void
    {
        if (! $this->db->tableExists('account_payments') || ! $this->db->tableExists('vendor_payments')) {
            return;
        }

        $paymentNo = 'IMP-PD-' . str_pad((string) $documentId, 8, '0', STR_PAD_LEFT);
        $date = (string) (($purchase['payment_date'] ?? '') ?: $purchase['invoice_date']);
        $amount = round((float) $purchase['paid_amount'], 2);
        $notes = 'Full payment imported because the verified source PDF filename is marked PAID.';
        $accountPayment = $this->db->table('account_payments')
            ->where('bill_source_type', 'production_document')
            ->where('bill_source_id', $documentId)
            ->get()->getRowArray();
        if (! $accountPayment) {
            $this->db->table('account_payments')->insert([
                'payment_no' => $paymentNo,
                'payment_date' => $date,
                'party_type' => 'vendor',
                'vendor_id' => $vendorId,
                'amount' => $amount,
                'payment_mode' => 'Source Record',
                'reference_no' => substr((string) $purchase['invoice_no'], 0, 80),
                'bill_type' => 'purchase',
                'bill_source_type' => 'production_document',
                'bill_source_id' => $documentId,
                'notes' => $notes,
                'created_at' => $date . ' 18:10:00',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $accountPaymentId = (int) $this->db->insertID();
        } else {
            $accountPaymentId = (int) $accountPayment['id'];
        }

        $vendorPayment = $this->db->table('vendor_payments')->where('payment_no', $paymentNo)->get()->getRowArray();
        if (! $vendorPayment) {
            $this->db->table('vendor_payments')->insert([
                'payment_no' => $paymentNo,
                'payment_date' => $date,
                'vendor_id' => $vendorId,
                'purchase_invoice_id' => null,
                'amount' => $amount,
                'payment_mode' => 'Source Record',
                'reference_no' => substr((string) $purchase['invoice_no'], 0, 80),
                'notes' => $notes,
                'created_at' => $date . ' 18:10:00',
            ]);
        }

        $this->db->table('production_purchase_documents')->where('id', $documentId)->update([
            'account_payment_id' => $accountPaymentId,
        ]);
    }

    private function normalizePath(string $path): string
    {
        return trim(preg_replace('#/+#', '/', str_replace('\\', '/', $path)) ?? '', '/');
    }
}
