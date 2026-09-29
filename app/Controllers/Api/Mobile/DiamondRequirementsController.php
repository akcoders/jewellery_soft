<?php

namespace App\Controllers\Api\Mobile;

use App\Services\DiamondRequirementService;
use App\Services\MobileNotificationEventService;
use App\Services\RbacService;
use Throwable;

class DiamondRequirementsController extends MobileBaseController
{
    private DiamondRequirementService $requirements;
    private MobileNotificationEventService $notifications;
    private RbacService $rbac;

    public function __construct()
    {
        $this->requirements = new DiamondRequirementService();
        $this->notifications = new MobileNotificationEventService();
        $this->rbac = new RbacService();
    }

    public function index()
    {
        if ($response = $this->requireMobileAuth()) {
            return $response;
        }
        $userId = (int) $this->mobileAdmin['id'];
        $items = $this->requirements->forAdmin();
        foreach ($items as &$item) {
            $item['can_prepare'] = (string) ($item['status'] ?? '') === 'assigned'
                && (int) ($item['assigned_to'] ?? 0) === $userId;
        }
        unset($item);
        return $this->ok(['items' => $items]);
    }

    public function show(int $id)
    {
        if ($response = $this->requireMobileAuth()) {
            return $response;
        }
        $row = $this->requirements->findDetailed($id);
        if (! is_array($row)) {
            return $this->fail('Diamond requirement not found.', 404);
        }
        $row['can_prepare'] = (string) ($row['status'] ?? '') === 'assigned'
            && (int) ($row['assigned_to'] ?? 0) === (int) $this->mobileAdmin['id'];
        return $this->ok([
            'requirement' => $row,
            'bag_items' => $this->requirements->bagItems($id),
            'lookups' => (string) ($row['status'] ?? '') === 'assigned'
                && (int) ($row['assigned_to'] ?? 0) === (int) $this->mobileAdmin['id']
                ? $this->requirements->preparationLookups()
                : (object) [],
        ]);
    }

    public function raise(int $orderId)
    {
        if ($response = $this->requireMobileAuth()) {
            return $response;
        }
        $payload = $this->payload();
        $userId = (int) $this->mobileAdmin['id'];
        try {
            $row = $this->requirements->raise(
                $orderId,
                $userId,
                (string) ($payload['requirement_note'] ?? ''),
                (string) ($payload['required_by'] ?? ''),
                $this->rbac->userCan($userId, 'diamond.inventory.manage')
            );
            $notification = [];
            try {
                $notification = $this->notifications->notifyDiamondRequirementRaised((int) $row['id']);
            } catch (Throwable $e) {
                log_message('error', 'Diamond requirement notification failed: {message}', ['message' => $e->getMessage()]);
            }
            return $this->ok(['requirement' => $row, 'notification' => $notification], 'Diamond requirement raised for admin approval.');
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    public function prepare(int $id)
    {
        if ($response = $this->requireMobileAuth()) {
            return $response;
        }
        $payload = $this->payload();
        $rows = $payload['items'] ?? $payload['rows'] ?? [];
        if (! is_array($rows)) {
            return $this->fail('items must be an array of diamond size rows.', 422);
        }

        $imageName = null;
        $imagePath = null;
        $imageBase64 = trim((string) ($payload['image_base64'] ?? ''));
        try {
            if ($imageBase64 !== '') {
                $image = $this->saveBase64Image($imageBase64);
                $imageName = $image['name'];
                $imagePath = $image['path'];
            }
            $row = $this->requirements->prepareBag(
                $id,
                (int) $this->mobileAdmin['id'],
                (int) ($payload['location_id'] ?? 0),
                (string) ($payload['prepared_date'] ?? date('Y-m-d')),
                array_values($rows),
                (string) ($payload['notes'] ?? ''),
                $imageName,
                $imagePath
            );
            $notification = [];
            try {
                $notification = $this->notifications->notifyDiamondBagReady($id);
            } catch (Throwable $e) {
                log_message('error', 'Diamond bag ready notification failed: {message}', ['message' => $e->getMessage()]);
            }
            return $this->ok([
                'requirement' => $row,
                'bag_items' => $this->requirements->bagItems($id),
                'notification' => $notification,
            ], 'Diamond bag prepared and marked ready.');
        } catch (Throwable $e) {
            if ($imagePath !== null && is_file(FCPATH . ltrim($imagePath, '/'))) {
                @unlink(FCPATH . ltrim($imagePath, '/'));
            }
            return $this->fail($e->getMessage(), 422);
        }
    }

    /** @return array{name:string,path:string} */
    private function saveBase64Image(string $input): array
    {
        $raw = $input;
        $extension = 'jpg';
        if (preg_match('/^data:image\/(\w+);base64,/', $input, $matches) === 1) {
            $extension = strtolower((string) ($matches[1] ?? 'jpg'));
            $raw = substr($input, strpos($input, ',') + 1);
        }
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new \RuntimeException('Bag photo must be JPG, PNG or WebP.');
        }
        $binary = base64_decode(str_replace(' ', '+', $raw), true);
        if ($binary === false || strlen($binary) > 4 * 1024 * 1024) {
            throw new \RuntimeException('Invalid bag photo or file is larger than 4 MB.');
        }
        $directory = FCPATH . 'uploads/diamond-bags';
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Could not create the bag photo directory.');
        }
        $name = 'mobile_bag_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        if (file_put_contents($directory . DIRECTORY_SEPARATOR . $name, $binary) === false) {
            throw new \RuntimeException('Could not save the bag photo.');
        }
        return ['name' => $name, 'path' => 'uploads/diamond-bags/' . $name];
    }
}
