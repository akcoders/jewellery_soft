<?php

namespace App\Services;

use App\Models\JobCardModel;
use App\Models\OrderAttachmentModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\OrderStatusHistoryModel;
use RuntimeException;
use Throwable;

class ArattaiOrderCreationService
{
    /**
     * Copy both compulsory order images to durable approval storage before the
     * approval request is created. The returned draft can safely remain in the
     * approval payload until an admin acts on it.
     *
     * @param array<string,mixed> $draft
     * @return array{draft:array<string,mixed>,attachment_name:string,attachment_path:string}
     */
    public function prepareForApproval(array $draft): array
    {
        $reference = is_array($draft['reference_image'] ?? null) ? $draft['reference_image'] : [];
        $signed = is_array($draft['signed_order_image'] ?? null) ? $draft['signed_order_image'] : [];
        if (! $this->validDraftImage($reference) || ! $this->validDraftImage($signed)) {
            throw new RuntimeException('Reference image and signed order image are required.');
        }

        $saved = [];
        try {
            $saved['reference_image'] = $this->storeApprovalImage($reference, 'reference');
            $saved['signed_order_image'] = $this->storeApprovalImage($signed, 'signed');
        } catch (Throwable $e) {
            foreach ($saved as $image) {
                $path = (string) ($image['temp_path'] ?? '');
                if ($path !== '' && is_file($path)) {
                    @unlink($path);
                }
            }
            throw $e;
        }

        $draft['reference_image'] = $saved['reference_image'];
        $draft['signed_order_image'] = $saved['signed_order_image'];
        return [
            'draft' => $draft,
            'attachment_name' => (string) $saved['reference_image']['original_name'],
            'attachment_path' => (string) $saved['reference_image']['relative_path'],
        ];
    }

    /** @param array<string,mixed> $draft */
    public function discardApprovalDraft(array $draft): void
    {
        $root = FCPATH . 'uploads/order-approvals' . DIRECTORY_SEPARATOR;
        foreach (['reference_image', 'signed_order_image'] as $key) {
            $image = is_array($draft[$key] ?? null) ? $draft[$key] : [];
            $path = (string) ($image['temp_path'] ?? '');
            if ($path !== '' && str_starts_with($path, $root) && is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @param array<string,mixed> $draft
     * @return array{order_id:int,order_no:string}
     */
    public function create(array $draft, int $createdBy): array
    {
        $customerId = (int) ($draft['customer_id'] ?? 0);
        $categoryId = (int) ($draft['order_category_id'] ?? 0);
        $endCustomerName = trim((string) ($draft['end_customer_name'] ?? ''));
        $contactNumber = preg_replace('/\D+/', '', (string) ($draft['contact_number'] ?? '')) ?? '';
        $salesExecutive = trim((string) ($draft['sales_executive_name'] ?? ''));
        $receivedDate = trim((string) ($draft['order_received_date'] ?? ''));
        $materialCategory = trim((string) ($draft['material_category'] ?? ''));
        $dueDate = trim((string) ($draft['due_date'] ?? ''));
        $referenceImage = is_array($draft['reference_image'] ?? null) ? $draft['reference_image'] : [];
        $signedImage = is_array($draft['signed_order_image'] ?? null) ? $draft['signed_order_image'] : [];

        if ($customerId <= 0 || $categoryId <= 0 || $endCustomerName === '' || $salesExecutive === '') {
            throw new RuntimeException('Order From, customer name, sales executive and sub category are required.');
        }
        if (strlen($contactNumber) < 10 || strlen($contactNumber) > 15) {
            throw new RuntimeException('Enter a valid customer mobile number.');
        }
        if ($receivedDate === '' || strtotime($receivedDate) === false
            || ($dueDate !== '' && strtotime($dueDate) === false)) {
            throw new RuntimeException('Enter a valid order date and delivery date.');
        }
        if (! in_array($materialCategory, ['Gold', 'Diamond', 'Jadau', 'Silver'], true)) {
            throw new RuntimeException('Select a valid material category.');
        }
        if (! $this->validDraftImage($referenceImage) || ! $this->validDraftImage($signedImage)) {
            throw new RuntimeException('Reference image and signed order image are required.');
        }

        $db = db_connect();
        $customer = $db->table('customers')->select('id, name')->where('id', $customerId)
            ->where('is_active', 1)->get()->getRowArray();
        if (! is_array($customer)) {
            throw new RuntimeException('Selected Order From customer is no longer active.');
        }
        $category = (new OrderCategoryService($db))->resolve($categoryId);
        $quantity = max(1, (int) ($draft['qty'] ?? 1));
        $itemDescription = trim((string) ($draft['item_description'] ?? '')) ?: (string) $category['name'];
        $approximatePrice = $this->nullableMoney($draft['approximate_price'] ?? null);
        $advanceAmount = $this->nullableMoney($draft['advance_amount'] ?? null) ?? 0.0;
        if ($approximatePrice !== null && $advanceAmount > $approximatePrice) {
            throw new RuntimeException('Advance amount cannot exceed the approximate price.');
        }

        $savedFiles = [];
        $orderId = 0;
        $orderNo = '';
        try {
            $db->transException(true)->transStart();
            $orderNo = (new OrderNumberService($db))->generate(
                $customerId,
                (string) $category['code'],
                0,
                (string) ($customer['name'] ?? ''),
                $salesExecutive
            );
            $orderName = mb_substr($endCustomerName . ' - ' . (string) $category['name'], 0, 180);
            $orderId = (int) (new OrderModel())->insert([
                'order_no' => $orderNo,
                'order_name' => $orderName,
                'order_category_id' => (int) $category['id'],
                'order_type' => 'Sales',
                'order_design_type' => 'Fresh',
                'order_from' => trim((string) ($customer['name'] ?? '')) ?: null,
                'order_received_date' => date('Y-m-d', strtotime($receivedDate)),
                'contact_number' => $contactNumber,
                'material_category' => $materialCategory,
                'certificate_requirement' => trim((string) ($draft['certificate_requirement'] ?? '')) ?: 'Not Required',
                'additional_details' => trim((string) ($draft['additional_details'] ?? '')) ?: null,
                'gold_rate_block_status' => (string) ($draft['gold_rate_block_status'] ?? 'Not Fixed'),
                'gold_rate_per_gm' => $this->nullableMoney($draft['gold_rate_per_gm'] ?? null),
                'approximate_price' => $approximatePrice,
                'advance_amount' => $advanceAmount,
                'customer_id' => $customerId,
                'end_customer_name' => $endCustomerName,
                'sales_person_user_id' => null,
                'sales_executive_name' => $salesExecutive,
                'status' => 'Confirmed',
                'priority' => 'Medium',
                'due_date' => $dueDate !== '' ? date('Y-m-d', strtotime($dueDate)) : null,
                'order_notes' => trim((string) ($draft['additional_details'] ?? '')),
                'created_by' => $createdBy > 0 ? $createdBy : null,
            ], true);
            if ($orderId <= 0) {
                throw new RuntimeException('The order header could not be saved.');
            }

            $itemId = (int) (new OrderItemModel())->insert([
                'order_id' => $orderId,
                'design_id' => null,
                'gold_purity_id' => null,
                'item_description' => $itemDescription,
                'size_label' => trim((string) ($draft['size_label'] ?? '')) ?: null,
                'qty' => $quantity,
                'gold_required_gm' => max(0, (float) ($draft['gold_required_gm'] ?? 0)),
                'diamond_required_cts' => max(0, (float) ($draft['diamond_required_cts'] ?? 0)),
                'item_status' => 'Confirmed',
            ], true);
            if ($itemId <= 0) {
                throw new RuntimeException('The order item could not be saved.');
            }

            $jobCardId = (int) (new JobCardModel())->insert([
                'job_card_no' => 'JC' . date('ymdHis') . random_int(100, 999),
                'order_id' => $orderId,
                'order_item_id' => $itemId,
                'status' => 'Pending',
                'priority' => 'Medium',
                'due_date' => $dueDate !== '' ? date('Y-m-d', strtotime($dueDate)) : null,
                'qc_status' => 'Pending',
                'created_by' => $createdBy > 0 ? $createdBy : null,
            ], true);
            if ($jobCardId <= 0) {
                throw new RuntimeException('The order job card could not be saved.');
            }

            foreach ([
                ['file_type' => 'reference', 'image' => $referenceImage],
                ['file_type' => 'signed_order', 'image' => $signedImage],
            ] as $attachment) {
                $stored = $this->storeDraftImage((array) $attachment['image']);
                $savedFiles[] = $stored['absolute'];
                $attachmentId = (int) (new OrderAttachmentModel())->insert([
                    'order_id' => $orderId,
                    'order_item_id' => $itemId,
                    'file_type' => (string) $attachment['file_type'],
                    'file_name' => (string) $stored['original_name'],
                    'file_path' => (string) $stored['relative'],
                    'uploaded_by' => $createdBy > 0 ? $createdBy : null,
                ], true);
                if ($attachmentId <= 0) {
                    throw new RuntimeException('An order image could not be saved.');
                }
            }

            $historyId = (int) (new OrderStatusHistoryModel())->insert([
                'order_id' => $orderId,
                'from_status' => null,
                'to_status' => 'Confirmed',
                'remarks' => 'Order created from Arattai.',
                'changed_by' => $createdBy > 0 ? $createdBy : null,
            ], true);
            if ($historyId <= 0) {
                throw new RuntimeException('The order status history could not be saved.');
            }
            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('The order transaction was rolled back.');
            }
        } catch (Throwable $e) {
            $db->transRollback();
            foreach ($savedFiles as $savedFile) {
                if (is_file($savedFile)) {
                    @unlink($savedFile);
                }
            }
            throw $e;
        }

        foreach ([$referenceImage, $signedImage] as $draftImage) {
            $path = (string) ($draftImage['temp_path'] ?? '');
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
        try {
            (new MobileNotificationEventService())->notifyOrderCreated($orderId, 'arattai');
        } catch (Throwable $e) {
            log_message('error', 'Arattai-created order notification failed: {message}', ['message' => $e->getMessage()]);
        }

        return ['order_id' => $orderId, 'order_no' => $orderNo];
    }

    /** @param array<string,mixed> $image */
    private function validDraftImage(array $image): bool
    {
        $path = (string) ($image['temp_path'] ?? '');
        return $path !== '' && is_file($path) && filesize($path) > 0;
    }

    /** @param array<string,mixed> $image @return array{absolute:string,relative:string,original_name:string} */
    private function storeDraftImage(array $image): array
    {
        $source = (string) ($image['temp_path'] ?? '');
        if (! $this->validDraftImage($image)) {
            throw new RuntimeException('A required order image is missing.');
        }
        $extension = strtolower((string) ($image['extension'] ?? pathinfo($source, PATHINFO_EXTENSION)));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('Only JPG, PNG and WEBP order images are allowed.');
        }
        $directory = FCPATH . 'uploads/orders';
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the order upload directory.');
        }
        $name = 'arattai_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
        $absolute = $directory . DIRECTORY_SEPARATOR . $name;
        if (! copy($source, $absolute)) {
            throw new RuntimeException('Could not store an Arattai order image.');
        }
        return [
            'absolute' => $absolute,
            'relative' => 'uploads/orders/' . $name,
            'original_name' => trim((string) ($image['original_name'] ?? '')) ?: $name,
        ];
    }

    /** @param array<string,mixed> $image @return array<string,string> */
    private function storeApprovalImage(array $image, string $label): array
    {
        $source = (string) ($image['temp_path'] ?? '');
        if (! $this->validDraftImage($image)) {
            throw new RuntimeException('A required order image is missing.');
        }
        $extension = strtolower((string) ($image['extension'] ?? pathinfo($source, PATHINFO_EXTENSION)));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('Only JPG, PNG and WEBP order images are allowed.');
        }
        $directory = FCPATH . 'uploads/order-approvals';
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the order approval upload directory.');
        }
        $name = 'arattai_' . $label . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
        $absolute = $directory . DIRECTORY_SEPARATOR . $name;
        if (! copy($source, $absolute)) {
            throw new RuntimeException('Could not store an Arattai order approval image.');
        }
        return [
            'temp_path' => $absolute,
            'relative_path' => 'uploads/order-approvals/' . $name,
            'extension' => $extension,
            'original_name' => trim((string) ($image['original_name'] ?? '')) ?: $name,
        ];
    }

    private function nullableMoney(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        return round((float) $value, 2);
    }
}
