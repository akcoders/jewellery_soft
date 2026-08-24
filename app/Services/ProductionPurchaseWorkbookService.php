<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class ProductionPurchaseWorkbookService
{
    private const WORKBOOK = 'Database/Data/production_purchase_register.xlsx';

    /** @var array{purchases:list<array<string,mixed>>,vendors:array<string,array<string,mixed>>,lines:array<string,list<array<string,mixed>>>}|null */
    private ?array $data = null;

    public function workbookPath(): string
    {
        return APPPATH . self::WORKBOOK;
    }

    /** @return list<array<string,mixed>> */
    public function purchases(): array
    {
        return $this->load()['purchases'];
    }

    /** @return array<string,array<string,mixed>> */
    public function vendors(): array
    {
        return $this->load()['vendors'];
    }

    /** @return array<string,array<string,mixed>> */
    public function purchaseMap(): array
    {
        $map = [];
        foreach ($this->purchases() as $purchase) {
            $map[$this->normalizePath((string) $purchase['source_path'])] = $purchase;
        }
        return $map;
    }

    /**
     * Merge the verified Excel register into the PDF file records. PDF contents are never parsed.
     *
     * @param list<array<string,mixed>> $documents
     * @return list<array<string,mixed>>
     */
    public function enrichDocuments(array $documents): array
    {
        $map = $this->purchaseMap();
        $result = [];
        foreach ($documents as $document) {
            $key = $this->normalizePath((string) ($document['source_path'] ?? ''));
            $purchase = $map[$key] ?? null;
            if (! $purchase) {
                throw new RuntimeException('The verified purchase workbook has no row for PDF: ' . $key);
            }
            $metadata = json_decode((string) ($document['metadata_json'] ?? ''), true);
            if (! is_array($metadata)) {
                $metadata = [];
            }
            $metadata['purchase_workbook'] = basename($this->workbookPath());
            $metadata['purchase_workbook_row'] = (int) $purchase['workbook_row'];
            $metadata['verification_status'] = 'Verified from source PDF into Excel';

            $result[] = array_merge($document, [
                'category' => strtolower((string) $purchase['category_label']),
                'vendor_key' => $purchase['vendor_key'],
                'vendor_name' => $purchase['vendor_name'],
                'vendor_address' => $purchase['vendor_address'],
                'vendor_gstin' => $purchase['vendor_gstin'],
                'vendor_phone' => $purchase['vendor_phone'],
                'vendor_email' => $purchase['vendor_email'],
                'document_date' => $purchase['invoice_date'],
                'invoice_no' => $purchase['invoice_no'],
                'invoice_amount' => $purchase['invoice_total'],
                'payment_status' => $purchase['payment_status'],
                'paid_amount' => $purchase['paid_amount'],
                'payment_date' => $purchase['payment_date'],
                'reconciliation_status' => $purchase['payment_status'] === 'Paid'
                    ? 'Filename marked PAID; full verified invoice total posted as paid'
                    : 'Verified invoice imported from Excel; payment pending',
                'payment_terms' => $purchase['payment_terms'],
                'due_date' => $purchase['due_date'],
                'place_of_supply' => $purchase['place_of_supply'],
                'purchase_description' => $purchase['description'],
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
        }

        if (count($result) !== count($map)) {
            throw new RuntimeException(sprintf('The archive contains %d purchase PDFs but the verified workbook contains %d invoices.', count($result), count($map)));
        }

        return $result;
    }

    /** @return array{purchases:list<array<string,mixed>>,vendors:array<string,array<string,mixed>>,lines:array<string,list<array<string,mixed>>>} */
    private function load(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }
        $path = $this->workbookPath();
        if (! is_file($path)) {
            throw new RuntimeException('Verified production purchase workbook is missing: ' . $path);
        }
        $book = IOFactory::load($path);
        $vendorRows = $this->sheetRows($book->getSheetByName('Vendors'), 'Vendors');
        $vendors = [];
        foreach ($vendorRows as $row) {
            $key = trim((string) ($row['Vendor Key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $vendors[$key] = [
                'vendor_key' => $key,
                'name' => trim((string) ($row['Vendor Name'] ?? '')),
                'address' => trim((string) ($row['Address'] ?? '')),
                'gstin' => strtoupper(trim((string) ($row['GSTIN'] ?? ''))),
                'phone' => trim((string) ($row['Phone'] ?? '')),
                'email' => trim((string) ($row['Email'] ?? '')),
            ];
        }

        $lineRows = $this->sheetRows($book->getSheetByName('Line Items'), 'Line Items');
        $lines = [];
        foreach ($lineRows as $row) {
            $sourcePath = $this->normalizePath((string) ($row['Source PDF'] ?? ''));
            if ($sourcePath === '') {
                continue;
            }
            $lines[$sourcePath][] = [
                'line_no' => (int) ($row['Line No'] ?? 0),
                'description' => trim((string) ($row['Description'] ?? '')),
                'hsn_sac' => trim((string) ($row['HSN/SAC'] ?? '')),
                'quantity' => $this->decimal($row['Quantity'] ?? 0),
                'unit' => trim((string) ($row['Unit'] ?? '')),
                'rate' => $this->decimal($row['Rate'] ?? 0),
                'amount' => $this->decimal($row['Line Amount'] ?? 0),
            ];
        }

        $purchaseRows = $this->sheetRows($book->getSheetByName('Purchases'), 'Purchases');
        $purchases = [];
        foreach ($purchaseRows as $index => $row) {
            $sourcePath = $this->normalizePath((string) ($row['Source PDF'] ?? ''));
            if ($sourcePath === '') {
                continue;
            }
            $vendorKey = trim((string) ($row['Vendor Key'] ?? ''));
            if (! isset($vendors[$vendorKey])) {
                throw new RuntimeException('Unknown vendor key in purchase workbook: ' . $vendorKey);
            }
            $paymentStatus = trim((string) ($row['Payment Status'] ?? 'Pending'));
            $filenamePaid = str_contains(strtoupper(basename($sourcePath)), 'PAID');
            if (($paymentStatus === 'Paid') !== $filenamePaid) {
                throw new RuntimeException('Payment status must follow the PAID filename marker: ' . $sourcePath);
            }
            $invoiceTotal = $this->decimal($row['Invoice Total'] ?? 0);
            $paidAmount = $this->decimal($row['Paid Amount'] ?? 0);
            if ($filenamePaid && abs($paidAmount - $invoiceTotal) > 0.01) {
                throw new RuntimeException('Paid invoice must carry the full invoice amount: ' . $sourcePath);
            }
            $taxable = $this->decimal($row['Taxable Amount'] ?? 0);
            $cgst = $this->decimal($row['CGST Amount'] ?? 0);
            $sgst = $this->decimal($row['SGST Amount'] ?? 0);
            $igst = $this->decimal($row['IGST Amount'] ?? 0);
            $roundOff = $this->decimal($row['Round Off'] ?? 0);
            if (abs(round($taxable + $cgst + $sgst + $igst + $roundOff, 2) - $invoiceTotal) > 0.01) {
                throw new RuntimeException('Tax arithmetic does not reconcile in workbook row ' . ($index + 2));
            }

            $purchases[] = [
                'source_path' => $sourcePath,
                'category_label' => trim((string) ($row['Category'] ?? '')),
                'vendor_key' => $vendorKey,
                'vendor_name' => trim((string) ($row['Vendor Name'] ?? $vendors[$vendorKey]['name'])),
                'vendor_address' => trim((string) ($row['Vendor Address'] ?? $vendors[$vendorKey]['address'])),
                'vendor_gstin' => strtoupper(trim((string) ($row['Vendor GSTIN'] ?? $vendors[$vendorKey]['gstin']))),
                'vendor_phone' => trim((string) ($row['Vendor Phone'] ?? $vendors[$vendorKey]['phone'])),
                'vendor_email' => trim((string) ($row['Vendor Email'] ?? $vendors[$vendorKey]['email'])),
                'invoice_no' => trim((string) ($row['Invoice No'] ?? '')),
                'invoice_date' => trim((string) ($row['Invoice Date'] ?? '')),
                'payment_terms' => trim((string) ($row['Payment Terms'] ?? '')),
                'due_date' => $this->nullableString($row['Due Date'] ?? null),
                'place_of_supply' => trim((string) ($row['Place of Supply'] ?? '')),
                'description' => trim((string) ($row['Description'] ?? '')),
                'taxable_amount' => $taxable,
                'cgst_rate' => $this->nullableDecimal($row['CGST Rate %'] ?? null),
                'cgst_amount' => $cgst,
                'sgst_rate' => $this->nullableDecimal($row['SGST Rate %'] ?? null),
                'sgst_amount' => $sgst,
                'igst_rate' => $this->nullableDecimal($row['IGST Rate %'] ?? null),
                'igst_amount' => $igst,
                'gst_amount' => round($cgst + $sgst + $igst, 2),
                'round_off_amount' => $roundOff,
                'invoice_total' => $invoiceTotal,
                'payment_status' => $filenamePaid ? 'Paid' : 'Pending',
                'paid_amount' => $filenamePaid ? $invoiceTotal : 0.0,
                'payment_date' => $this->nullableString($row['Payment Date'] ?? null),
                'verification_status' => trim((string) ($row['Verification'] ?? 'Verified from source PDF')),
                'notes' => trim((string) ($row['Notes'] ?? '')),
                'line_items' => $lines[$sourcePath] ?? [],
                'workbook_row' => $index + 2,
            ];
        }
        $book->disconnectWorksheets();

        if (count($purchases) !== 35 || array_sum(array_map('count', $lines)) !== 39) {
            throw new RuntimeException('Verified purchase workbook must contain 35 invoices and 39 line items.');
        }

        return $this->data = ['purchases' => $purchases, 'vendors' => $vendors, 'lines' => $lines];
    }

    /** @return list<array<string,mixed>> */
    private function sheetRows($sheet, string $name): array
    {
        if ($sheet === null) {
            throw new RuntimeException('Missing worksheet: ' . $name);
        }
        $values = $sheet->toArray(null, true, true, false);
        $headers = array_map(static fn($value): string => trim((string) $value), array_shift($values) ?? []);
        $rows = [];
        foreach ($values as $valuesRow) {
            $row = [];
            foreach ($headers as $column => $header) {
                if ($header !== '') {
                    $row[$header] = $valuesRow[$column] ?? null;
                }
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private function normalizePath(string $path): string
    {
        return trim(preg_replace('#/+#', '/', str_replace('\\', '/', $path)) ?? '', '/');
    }

    private function decimal($value): float
    {
        return round((float) str_replace([',', '₹', 'Rs'], '', (string) $value), 3);
    }

    private function nullableDecimal($value): ?float
    {
        return $value === null || trim((string) $value) === '' ? null : $this->decimal($value);
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
