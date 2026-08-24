<?php

namespace App\Controllers;

use App\Models\OrderAttachmentModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\OrderStatusHistoryModel;
use App\Services\OrderWhatsAppService;
use CodeIgniter\HTTP\Files\UploadedFile;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PublicOrderRequestController extends BaseController
{
    private const MAX_REFERENCE_IMAGES = 10;
    private const MAX_REFERENCE_IMAGE_BYTES = 5 * 1024 * 1024;
    private const REFERENCE_IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function create(): string
    {
        return view('public/order_request', [
            'title' => 'Create Order Request',
            'successOrderNo' => session('success_order_no'),
        ]);
    }

    public function store()
    {
        $rules = [
            'order_from' => 'required|max_length[150]',
            'phone' => 'required|max_length[20]',
            'whatsapp_notification_number' => 'permit_empty|max_length[40]',
            'order_type' => 'required|in_list[Sales,Manufacturing,Repair]',
            'product_name' => 'required|max_length[180]',
            'size_label' => 'permit_empty|max_length[30]',
            'qty' => 'required|integer|greater_than[0]',
            'gold_required_gm' => 'permit_empty|decimal',
            'diamond_required_cts' => 'permit_empty|decimal',
            'due_date' => 'permit_empty|valid_date',
            'expected_diamond_spec' => 'permit_empty',
            'expected_stone_spec' => 'permit_empty',
            'order_notes' => 'permit_empty',
            'repair_ornament_details' => 'permit_empty',
            'repair_work_details' => 'permit_empty',
            'repair_receive_weight_gm' => 'permit_empty|decimal',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        try {
            $referenceImages = $this->validatedReferenceImages();
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $attachmentModel = new OrderAttachmentModel();
        $orderModel = new OrderModel();
        $orderItemModel = new OrderItemModel();
        $historyModel = new OrderStatusHistoryModel();
        $db = db_connect();
        $movedImagePaths = [];
        $db->transStart();

        try {
            $orderFrom = trim((string) $this->request->getPost('order_from'));
            $phone = $this->normalizePhone((string) $this->request->getPost('phone'));
            if ($phone === null) {
                throw new \InvalidArgumentException('Valid phone number is required.');
            }
            $whatsappNumber = $this->normalizePhone((string) $this->request->getPost('whatsapp_notification_number'));
            if ($whatsappNumber === null) {
                $whatsappNumber = $phone;
            }

            $orderNo = 'WEB-OR' . date('ymdHis') . random_int(10, 99);
            $orderType = (string) $this->request->getPost('order_type');
            $isRepair = $orderType === 'Repair';
            $orderId = (int) $orderModel->insert([
                'order_no' => $orderNo,
                'order_type' => $orderType,
                'order_from' => $orderFrom,
                'customer_id' => null,
                'lead_id' => null,
                'status' => 'Confirmed',
                'priority' => 'Medium',
                'due_date' => $this->nullableDate((string) $this->request->getPost('due_date')),
                'order_notes' => trim((string) $this->request->getPost('order_notes')),
                'whatsapp_notification_number' => $whatsappNumber,
                'whatsapp_notify_order_created' => 1,
                'expected_diamond_spec' => trim((string) $this->request->getPost('expected_diamond_spec')) ?: null,
                'expected_stone_spec' => trim((string) $this->request->getPost('expected_stone_spec')) ?: null,
                'repair_ornament_details' => $isRepair ? trim((string) $this->request->getPost('repair_ornament_details')) : null,
                'repair_work_details' => $isRepair ? trim((string) $this->request->getPost('repair_work_details')) : null,
                'repair_receive_weight_gm' => $isRepair ? (float) $this->request->getPost('repair_receive_weight_gm') : null,
                'repair_received_at' => $isRepair ? date('Y-m-d') : null,
            ], true);

            $orderItemModel->insert([
                'order_id' => $orderId,
                'item_description' => trim((string) $this->request->getPost('product_name')),
                'size_label' => trim((string) $this->request->getPost('size_label')) ?: null,
                'qty' => max(1, (int) $this->request->getPost('qty')),
                'gold_required_gm' => (float) ($this->request->getPost('gold_required_gm') ?: 0),
                'diamond_required_cts' => (float) ($this->request->getPost('diamond_required_cts') ?: 0),
                'item_status' => 'Confirmed',
            ]);

            $historyModel->insert([
                'order_id' => $orderId,
                'from_status' => null,
                'to_status' => 'Confirmed',
                'remarks' => 'Order request submitted from public link. Order from: ' . $orderFrom,
            ]);

            $movedImagePaths = $this->storeReferenceImages($referenceImages, $orderId, $attachmentModel);
        } catch (Throwable $e) {
            $db->transRollback();
            $this->removeMovedImages($movedImagePaths);
            return redirect()->back()->withInput()->with('error', 'Could not submit order request: ' . $e->getMessage());
        }

        $db->transComplete();
        if (! $db->transStatus()) {
            $this->removeMovedImages($movedImagePaths);
            return redirect()->back()->withInput()->with('error', 'Could not submit order request. Please try again.');
        }

        try {
            (new OrderWhatsAppService())->notifyOrderCreated($orderId);
        } catch (Throwable $e) {
            log_message('error', 'Public order WhatsApp queue failed for order {id}: {message}', [
                'id' => $orderId,
                'message' => $e->getMessage(),
            ]);
        }

        return redirect()->to(site_url('order-request'))->with('success_order_no', $orderNo);
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }

    private function normalizePhone(string $phone): ?string
    {
        $normalized = preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';
        return $normalized === '' ? null : $normalized;
    }

    private function nullableDate(string $date): ?string
    {
        $date = trim($date);
        return $date === '' ? null : $date;
    }

    /**
     * @return list<UploadedFile>
     */
    private function validatedReferenceImages(): array
    {
        $files = $this->request->getFileMultiple('reference_images');
        if (! is_array($files)) {
            return [];
        }

        $images = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if (count($images) >= self::MAX_REFERENCE_IMAGES) {
                throw new InvalidArgumentException('You can attach a maximum of 10 images.');
            }

            if (! $file->isValid()) {
                throw new InvalidArgumentException('One of the selected images could not be uploaded.');
            }

            if ($file->getSize() > self::MAX_REFERENCE_IMAGE_BYTES) {
                throw new InvalidArgumentException('Each image must be 5 MB or smaller.');
            }

            $mimeType = strtolower((string) $file->getMimeType());
            if (! array_key_exists($mimeType, self::REFERENCE_IMAGE_EXTENSIONS)) {
                throw new InvalidArgumentException('Only JPG, PNG, and WebP images are allowed.');
            }

            $images[] = $file;
        }

        return $images;
    }

    /**
     * @param list<UploadedFile> $images
     * @return list<string>
     */
    private function storeReferenceImages(
        array $images,
        int $orderId,
        OrderAttachmentModel $attachmentModel
    ): array {
        if ($images === []) {
            return [];
        }

        $uploadDir = FCPATH . 'uploads/orders';
        if (! is_dir($uploadDir) && ! mkdir($uploadDir, 0775, true) && ! is_dir($uploadDir)) {
            throw new RuntimeException('Order image directory could not be created.');
        }

        $movedPaths = [];
        try {
            foreach ($images as $file) {
                $mimeType = strtolower((string) $file->getMimeType());
                $extension = self::REFERENCE_IMAGE_EXTENSIONS[$mimeType] ?? null;
                if ($extension === null) {
                    throw new RuntimeException('Unsupported order image type.');
                }

                $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
                $file->move($uploadDir, $storedName);

                $absolutePath = $uploadDir . DIRECTORY_SEPARATOR . $storedName;
                $movedPaths[] = $absolutePath;

                $inserted = $attachmentModel->insert([
                    'order_id' => $orderId,
                    'file_type' => 'reference_image',
                    'file_name' => substr($file->getClientName(), 0, 255),
                    'file_path' => 'uploads/orders/' . $storedName,
                    'uploaded_by' => null,
                ]);

                if ($inserted === false) {
                    throw new RuntimeException('Order image details could not be saved.');
                }
            }
        } catch (Throwable $e) {
            $this->removeMovedImages($movedPaths);
            throw $e;
        }

        return $movedPaths;
    }

    /**
     * @param list<string> $paths
     */
    private function removeMovedImages(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
