<?php

namespace App\Services;

use App\Models\ArattaiOrderConversationModel;
use App\Models\ReturnHeaderModel;
use App\Models\ReturnLineModel;
use Config\Jewellery;
use RuntimeException;
use Throwable;

/**
 * Conversational Arattai entry points for real ERP transactions.
 *
 * The service deliberately delegates final posting to the same domain services
 * used by the admin/PWA screens. Conversation state contains only a draft and
 * is claimed before the final write so repeated provider webhooks stay safe.
 */
class ArattaiTransactionConversationService
{
    private const PREFIX = 'erp_';
    private const MENU_COMMANDS = [
        'follow-up', 'followup', 'issuement', 'return', 'bag creation',
        'purchase', 'receiving', 'packing list', 'delivery challan',
    ];

    public function __construct(private ?ArattaiOrderConversationModel $model = null)
    {
        $this->model ??= new ArattaiOrderConversationModel();
    }

    /**
     * @param array<string,mixed> $payload
     * @param callable(string,string):void $sendText
     * @param callable(string,string,list<string>):void $sendOptions
     * @param callable(string,string,string):void $sendDocument event, caption, public URL
     * @return array{handled:bool,status:string,message:string}
     */
    public function handle(
        array $payload,
        string $userId,
        string $text,
        int $actorId,
        callable $sendText,
        callable $sendOptions,
        callable $sendDocument
    ): array {
        if (! $this->ready()) {
            return ['handled' => false, 'status' => 'unavailable', 'message' => 'Arattai conversation migration is pending.'];
        }

        $command = mb_strtolower(trim($text));
        $session = $this->session($userId);
        $isTransactionSession = is_array($session)
            && str_starts_with((string) ($session['current_step'] ?? ''), self::PREFIX);
        $activeTransaction = $isTransactionSession
            && (string) ($session['status'] ?? '') === 'active';

        if ($isTransactionSession && (string) ($session['status'] ?? '') === 'processing') {
            $sendText('arattai_erp_processing', 'This action is already being processed. Please wait for confirmation.');
            return ['handled' => true, 'status' => 'processing', 'message' => 'Transaction already processing.'];
        }

        if ($isTransactionSession
            && (string) ($session['status'] ?? '') === 'completed'
            && str_starts_with($command, 'confirm ')) {
            $sendText('arattai_erp_completed', 'This action was already completed successfully. Send Hi to start another transaction.');
            return ['handled' => true, 'status' => 'duplicate', 'message' => 'Transaction already completed.'];
        }

        if (in_array($command, ['cancel', 'cancel current process'], true) && $activeTransaction) {
            $this->cleanupImages($this->data($session));
            $this->finish($session, 'cancelled');
            $sendText('arattai_erp_cancelled', 'Current ERP process cancelled. Send Hi to open the main menu.');
            return ['handled' => true, 'status' => 'cancelled', 'message' => 'Arattai ERP transaction cancelled.'];
        }

        if (preg_match('/^take[\s-]*follow[\s-]*up\s+(\d+)$/i', trim($text), $takeMatch) === 1) {
            $order = db_connect()->table('orders')
                ->select('id, order_no, order_name, followup_assigned_to, status')
                ->where('id', (int) $takeMatch[1])->where('deleted_at', null)->get()->getRowArray();
            if (! is_array($order)
                || (int) ($order['followup_assigned_to'] ?? 0) !== $actorId
                || $this->terminalStage((string) ($order['status'] ?? ''))) {
                $sendText('arattai_followup_unavailable', 'This order is no longer available for your follow-up.');
                return ['handled' => true, 'status' => 'unavailable', 'message' => 'Follow-up order unavailable.'];
            }
            if ($activeTransaction) {
                $this->cleanupImages($this->data($session));
                $this->finish($session, 'cancelled');
            }
            $session = $this->start($userId, $actorId, 'erp_followup_stage', [
                'module' => 'follow-up',
                'order_id' => (int) $order['id'],
                'order_no' => (string) ($order['order_no'] ?? ''),
                'order_name' => (string) ($order['order_name'] ?? ''),
            ]);
            $this->prompt($session, $sendText, $sendOptions);
            return ['handled' => true, 'status' => 'started', 'message' => 'Follow-up started for selected order.'];
        }

        if (in_array($command, self::MENU_COMMANDS, true)) {
            if ($activeTransaction) {
                $this->cleanupImages($this->data($session));
                $this->finish($session, 'cancelled');
            }
            $normalized = match ($command) {
                'followup' => 'follow-up',
                default => $command,
            };
            $step = match ($normalized) {
                'follow-up' => 'erp_followup_order',
                'issuement' => 'erp_issue_karigar',
                'return' => 'erp_return_bag',
                'bag creation' => 'erp_bag_order',
                'purchase' => 'erp_purchase_type',
                'receiving' => 'erp_receive_order',
                'packing list' => 'erp_packing_order',
                'delivery challan' => 'erp_challan_mode',
                default => '',
            };
            $session = $this->start($userId, $actorId, $step, ['module' => $normalized]);
            $this->prompt($session, $sendText, $sendOptions);
            return ['handled' => true, 'status' => 'started', 'message' => ucfirst($normalized) . ' conversation started.'];
        }

        if (! $activeTransaction) {
            return ['handled' => false, 'status' => 'not_transaction', 'message' => 'No active Arattai ERP transaction.'];
        }

        try {
            return $this->accept($payload, $session, trim($text), $actorId, $sendText, $sendOptions, $sendDocument);
        } catch (Throwable $e) {
            $sendText('arattai_erp_validation', $e->getMessage());
            $fresh = $this->session($userId) ?? $session;
            if ((string) ($fresh['status'] ?? '') === 'active') {
                $this->prompt($fresh, $sendText, $sendOptions);
            }
            return ['handled' => true, 'status' => 'validation_failed', 'message' => $e->getMessage()];
        }
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $session */
    private function accept(
        array $payload,
        array $session,
        string $text,
        int $actorId,
        callable $sendText,
        callable $sendOptions,
        callable $sendDocument
    ): array {
        $step = (string) ($session['current_step'] ?? '');
        $data = $this->data($session);
        $normalized = mb_strtolower($text);

        if ($normalized === 'back') {
            $previous = $this->previous($step, $data);
            $this->save($session, $previous, $data, $actorId);
            $session['current_step'] = $previous;
            $this->prompt($session, $sendText, $sendOptions);
            return ['handled' => true, 'status' => 'back', 'message' => 'Moved to previous step.'];
        }

        if (str_starts_with($step, 'erp_followup_')) {
            return $this->acceptFollowup($payload, $session, $step, $data, $text, $actorId, $sendText, $sendOptions);
        }
        if (str_starts_with($step, 'erp_issue_')) {
            return $this->acceptIssue($payload, $session, $step, $data, $text, $actorId, $sendText, $sendOptions);
        }
        if (str_starts_with($step, 'erp_return_')) {
            return $this->acceptReturn($session, $step, $data, $text, $actorId, $sendText, $sendOptions, $sendDocument);
        }
        if (str_starts_with($step, 'erp_bag_')) {
            return $this->acceptBag($session, $step, $data, $text, $actorId, $sendText, $sendOptions);
        }
        if (str_starts_with($step, 'erp_receive_')) {
            return $this->acceptReceiving($session, $step, $data, $text, $actorId, $sendText, $sendOptions, $sendDocument);
        }
        if (str_starts_with($step, 'erp_purchase_')) {
            return $this->acceptPurchase($payload, $session, $step, $data, $text, $actorId, $sendText, $sendOptions);
        }
        if (str_starts_with($step, 'erp_custom_challan_') || $step === 'erp_challan_mode') {
            return $this->acceptCustomChallan($session, $step, $data, $text, $actorId, $sendText, $sendOptions, $sendDocument);
        }
        if (in_array($step, ['erp_packing_order', 'erp_challan_order'], true)) {
            return $this->acceptDocument($session, $step, $data, $text, $actorId, $sendText, $sendDocument);
        }

        return ['handled' => false, 'status' => 'unknown_step', 'message' => 'Unknown Arattai transaction step.'];
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $session @param array<string,mixed> $data */
    private function acceptFollowup(
        array $payload,
        array $session,
        string $step,
        array $data,
        string $text,
        int $actorId,
        callable $sendText,
        callable $sendOptions
    ): array {
        switch ($step) {
            case 'erp_followup_order':
                $row = $this->selected($text, $data, 'orders', 'id', ['order_no', 'order_name']);
                if (! is_array($row)) throw new RuntimeException('Select an order shown in the list.');
                $data['order_id'] = (int) $row['id'];
                $data['order_no'] = (string) ($row['order_no'] ?? '');
                return $this->advance($session, 'erp_followup_stage', $data, $actorId, $sendText, $sendOptions);

            case 'erp_followup_stage':
                $statuses = array_values(array_map('strval', config(Jewellery::class)->orderStatuses));
                $stage = $this->matchLabel($text, $statuses);
                if ($stage === '') throw new RuntimeException('Select a valid order stage.');
                $data['stage'] = $stage;
                return $this->advance($session, 'erp_followup_description', $data, $actorId, $sendText, $sendOptions);

            case 'erp_followup_description':
                if ($text === '' || mb_strlen($text) > 2000) throw new RuntimeException('Enter a follow-up note up to 2000 characters.');
                $data['description'] = $text;
                return $this->advance($session, 'erp_followup_date', $data, $actorId, $sendText, $sendOptions);

            case 'erp_followup_date':
                if (mb_strtolower($text) === 'skip') {
                    if (! $this->terminalStage((string) ($data['stage'] ?? ''))) {
                        throw new RuntimeException('Next follow-up date is required while the order remains open.');
                    }
                    $data['next_followup_date'] = '';
                } else {
                    $date = $this->dateTime($text);
                    if ($date === null) throw new RuntimeException('Enter date/time like 25/10/2026 11:00 AM.');
                    $data['next_followup_date'] = $date;
                }
                return $this->advance($session, 'erp_followup_image', $data, $actorId, $sendText, $sendOptions);

            case 'erp_followup_image':
                if (in_array(mb_strtolower($text), ['skip', 'skip image'], true)) {
                    $data['image'] = null;
                } else {
                    $data['image'] = (new ArattaiOrderConversationService())->storeIncomingImage($payload, 'followup');
                }
                return $this->advance($session, 'erp_followup_confirm', $data, $actorId, $sendText, $sendOptions);

            case 'erp_followup_confirm':
                if (mb_strtolower($text) !== 'confirm follow-up') throw new RuntimeException('Tap Confirm Follow-up to submit it.');
                if (! $this->claim($session)) {
                    $sendText('arattai_followup_duplicate', 'Action already taken or currently processing.');
                    return ['handled' => true, 'status' => 'duplicate', 'message' => 'Follow-up already processed.'];
                }
                $publishedPath = null;
                try {
                    [$imageName, $imagePath] = $this->publishImage($data['image'] ?? null, 'followups');
                    $publishedPath = $imagePath;
                    $result = (new OrderFollowupService())->createPendingApproval(
                        (int) $data['order_id'],
                        [
                            'stage' => (string) $data['stage'],
                            'description' => (string) $data['description'],
                            'next_followup_date' => (string) ($data['next_followup_date'] ?? ''),
                            'followup_taken_on' => date('Y-m-d H:i:s'),
                            'followup_scope' => 'order',
                        ],
                        $actorId,
                        'Order ' . (string) $data['order_no'] . ' follow-up: ' . (string) $data['stage'],
                        $imageName,
                        $imagePath
                    );
                    $data['approval_request_id'] = (int) ($result['approval_request']['id'] ?? 0);
                    $this->cleanupImages($data);
                    $this->complete($session, $data, (int) $data['order_id']);
                    $sendText('arattai_followup_submitted', 'Follow-up sent for approval successfully. Request #' . $data['approval_request_id'] . '.');
                    return ['handled' => true, 'status' => 'submitted', 'message' => 'Follow-up submitted for approval.'];
                } catch (Throwable $e) {
                    $this->removePublishedImage($publishedPath);
                    $this->release($session, $data);
                    throw $e;
                }
        }
        throw new RuntimeException('Invalid follow-up step.');
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $session @param array<string,mixed> $data */
    private function acceptIssue(
        array $payload,
        array $session,
        string $step,
        array $data,
        string $text,
        int $actorId,
        callable $sendText,
        callable $sendOptions
    ): array {
        $db = db_connect();
        switch ($step) {
            case 'erp_issue_karigar':
                $row = $this->selected($text, $data, 'karigars', 'id', ['name']);
                if (! is_array($row)) throw new RuntimeException('Select an active karigar.');
                $data['karigar_id'] = (int) $row['id'];
                $data['karigar_name'] = (string) $row['name'];
                return $this->advance($session, 'erp_issue_location', $data, $actorId, $sendText, $sendOptions);

            case 'erp_issue_location':
                $row = $this->selected($text, $data, 'inventory_locations', 'id', ['name', 'code']);
                if (! is_array($row)) throw new RuntimeException('Select a warehouse/location.');
                $data['location_id'] = (int) $row['id'];
                $data['location_name'] = (string) $row['name'];
                $data['issue_date'] = date('Y-m-d');
                $data['purpose'] = 'Production';
                return $this->advance($session, 'erp_issue_material', $data, $actorId, $sendText, $sendOptions);

            case 'erp_issue_material':
            case 'erp_issue_more':
                $choice = mb_strtolower($text);
                if ($choice === 'confirm issuement') {
                    if (($data['gold_lines'] ?? []) === [] && ($data['diamond_lines'] ?? []) === [] && ($data['stone_lines'] ?? []) === []) {
                        throw new RuntimeException('Add at least one Gold, Diamond or Stone material.');
                    }
                    return $this->advance($session, 'erp_issue_image', $data, $actorId, $sendText, $sendOptions);
                }
                $next = match ($choice) {
                    'add gold', 'gold' => 'erp_issue_gold_item',
                    'add diamond', 'diamond' => 'erp_issue_diamond_bag',
                    'add stone', 'stone' => 'erp_issue_stone_order',
                    default => '',
                };
                if ($next === '') throw new RuntimeException('Select Add Gold, Add Diamond, Add Stone or Confirm Issuement.');
                return $this->advance($session, $next, $data, $actorId, $sendText, $sendOptions);

            case 'erp_issue_gold_item':
                $row = $this->selected($text, $data, 'gold_inventory_items', 'id', ['purity_code', 'color_name', 'form_type']);
                if (! is_array($row)) throw new RuntimeException('Select an available gold inventory item.');
                $data['pending_gold_item'] = $row;
                return $this->advance($session, 'erp_issue_gold_weight', $data, $actorId, $sendText, $sendOptions);

            case 'erp_issue_gold_weight':
                $weight = $this->positive($text);
                if ($weight === null) throw new RuntimeException('Enter a positive gold weight in grams.');
                $item = is_array($data['pending_gold_item'] ?? null) ? $data['pending_gold_item'] : [];
                if ($weight > (float) ($item['balance'] ?? 0) + 0.0005) {
                    throw new RuntimeException('Gold weight exceeds available stock of ' . number_format((float) ($item['balance'] ?? 0), 3) . ' gm.');
                }
                $purity = (float) ($item['purity_percent'] ?? 0);
                $data['gold_lines'][] = [
                    'item_id' => (int) ($item['id'] ?? 0),
                    'display_name' => $this->goldLabel($item),
                    'weight_gm' => $weight,
                    'fine_weight_gm' => round($weight * $purity / 100, 3),
                    'rate_per_gm' => null,
                    'line_value' => null,
                ];
                unset($data['pending_gold_item']);
                return $this->advance($session, 'erp_issue_more', $data, $actorId, $sendText, $sendOptions);

            case 'erp_issue_diamond_bag':
                $bag = $this->selected($text, $data, 'diamond_bags', 'id', ['bag_no', 'order_no']);
                if (! is_array($bag)) throw new RuntimeException('Select an available diamond bag.');
                $context = (new DiamondBagService($db))->issuementContext((int) $bag['id']);
                if ((int) $context['karigar_id'] !== (int) $data['karigar_id']) {
                    throw new RuntimeException('This bag belongs to a different order karigar. Select the matching karigar or bag.');
                }
                if ((int) $context['location_id'] !== (int) $data['location_id']) {
                    throw new RuntimeException('This bag belongs to a different inventory location.');
                }
                foreach ((array) $context['diamond_lines'] as $line) {
                    $line['display_name'] = (string) $bag['bag_no'] . ' · Order ' . (string) $context['order_no'];
                    $data['diamond_lines'][] = $line;
                }
                $data['diamond_bag_ids'][] = (int) $bag['id'];
                return $this->advance($session, 'erp_issue_more', $data, $actorId, $sendText, $sendOptions);

            case 'erp_issue_stone_order':
                $order = $this->selected($text, $data, 'orders', 'id', ['order_no', 'order_name']);
                if (! is_array($order)) throw new RuntimeException('Select an order for stone issuement.');
                $data['stone_order_id'] = (int) $order['id'];
                $data['stone_order_no'] = (string) $order['order_no'];
                return $this->advance($session, 'erp_issue_stone_item', $data, $actorId, $sendText, $sendOptions);

            case 'erp_issue_stone_item':
                $row = $this->selected($text, $data, 'stone_inventory_items', 'id', ['product_name', 'stone_type']);
                if (! is_array($row)) throw new RuntimeException('Select an available stone item.');
                $data['pending_stone_item'] = $row;
                return $this->advance($session, 'erp_issue_stone_pcs', $data, $actorId, $sendText, $sendOptions);

            case 'erp_issue_stone_pcs':
                $pcs = $this->nonNegative($text);
                if ($pcs === null) throw new RuntimeException('Enter stone PCS as zero or a positive number.');
                $data['pending_stone_pcs'] = $pcs;
                return $this->advance($session, 'erp_issue_stone_weight', $data, $actorId, $sendText, $sendOptions);

            case 'erp_issue_stone_weight':
                $qty = $this->positive($text);
                if ($qty === null) throw new RuntimeException('Enter a positive stone weight/quantity.');
                $item = is_array($data['pending_stone_item'] ?? null) ? $data['pending_stone_item'] : [];
                if ($qty > (float) ($item['balance'] ?? 0) + 0.0005) {
                    throw new RuntimeException('Stone quantity exceeds available stock of ' . number_format((float) ($item['balance'] ?? 0), 3) . '.');
                }
                $data['stone_lines'][] = [
                    'item_id' => (int) ($item['id'] ?? 0),
                    'display_name' => trim((string) (($item['product_name'] ?? '') . ' · ' . ($item['stone_type'] ?? '')), ' ·'),
                    'pcs' => (float) ($data['pending_stone_pcs'] ?? 0),
                    'qty' => $qty,
                    'rate' => null,
                    'line_value' => null,
                ];
                unset($data['pending_stone_item'], $data['pending_stone_pcs']);
                return $this->advance($session, 'erp_issue_more', $data, $actorId, $sendText, $sendOptions);

            case 'erp_issue_image':
                $data['image'] = (new ArattaiOrderConversationService())->storeIncomingImage($payload, 'issuement');
                return $this->advance($session, 'erp_issue_confirm', $data, $actorId, $sendText, $sendOptions);

            case 'erp_issue_confirm':
                if (mb_strtolower($text) !== 'confirm issuement') throw new RuntimeException('Tap Confirm Issuement to submit for approval.');
                if (! $this->claim($session)) {
                    $sendText('arattai_issue_duplicate', 'Action already taken or currently processing.');
                    return ['handled' => true, 'status' => 'duplicate', 'message' => 'Issuement already processed.'];
                }
                $publishedPath = null;
                try {
                    [$imageName, $imagePath] = $this->publishImage($data['image'] ?? null, 'issuements/common');
                    $publishedPath = $imagePath;
                    if ($imagePath === null) throw new RuntimeException('Issuement image is compulsory. Please upload it again.');
                    $materials = [];
                    if (($data['gold_lines'] ?? []) !== []) $materials[] = 'Gold';
                    if (($data['diamond_lines'] ?? []) !== []) $materials[] = 'Diamond';
                    if (($data['stone_lines'] ?? []) !== []) $materials[] = 'Stone';
                    $requestPayload = [
                        'issue_date' => (string) $data['issue_date'],
                        'karigar_id' => (int) $data['karigar_id'],
                        'karigar_name' => (string) $data['karigar_name'],
                        'location_id' => (int) $data['location_id'],
                        'location_name' => (string) $data['location_name'],
                        'purpose' => (string) $data['purpose'],
                        'notes' => 'Created from Arattai conversation',
                        'voucher_no' => '',
                        'stone_order_id' => (int) ($data['stone_order_id'] ?? 0),
                        'stone_order_no' => (string) ($data['stone_order_no'] ?? ''),
                        'gold_lines' => array_values((array) ($data['gold_lines'] ?? [])),
                        'diamond_lines' => array_values((array) ($data['diamond_lines'] ?? [])),
                        'stone_lines' => array_values((array) ($data['stone_lines'] ?? [])),
                    ];
                    $subjectBagId = count((array) ($data['diamond_bag_ids'] ?? [])) === 1
                        ? (int) $data['diamond_bag_ids'][0] : null;
                    $approval = (new MobileApprovalService($db))->submit(
                        'issuement',
                        $requestPayload,
                        $actorId,
                        implode(' + ', $materials) . ' issuement to ' . (string) $data['karigar_name'],
                        $imageName,
                        $imagePath,
                        $subjectBagId ? 'diamond_bags' : null,
                        $subjectBagId
                    );
                    $data['approval_request_id'] = (int) ($approval['id'] ?? 0);
                    $this->cleanupImages($data);
                    $this->complete($session, $data, (int) ($data['stone_order_id'] ?? 0) ?: null);
                    $sendText('arattai_issue_submitted', 'Issuement sent for approval successfully. Request #' . $data['approval_request_id'] . '. PDF will arrive after approval.');
                    return ['handled' => true, 'status' => 'submitted', 'message' => 'Issuement submitted for approval.'];
                } catch (Throwable $e) {
                    $this->removePublishedImage($publishedPath);
                    $this->release($session, $data);
                    throw $e;
                }
        }
        throw new RuntimeException('Invalid issuement step.');
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $data */
    private function acceptReturn(
        array $session,
        string $step,
        array $data,
        string $text,
        int $actorId,
        callable $sendText,
        callable $sendOptions,
        callable $sendDocument
    ): array {
        switch ($step) {
            case 'erp_return_bag':
                $bag = $this->selected($text, $data, 'return_bags', 'bag_id', ['bag_no', 'voucher_no']);
                if (! is_array($bag)) throw new RuntimeException('Select an issued diamond bag.');
                $data['bag_id'] = (int) $bag['bag_id'];
                $data['bag_no'] = (string) $bag['bag_no'];
                $data['issue_id'] = (int) $bag['issue_id'];
                $data['karigar_id'] = (int) $bag['karigar_id'];
                return $this->advance($session, 'erp_return_item', $data, $actorId, $sendText, $sendOptions);

            case 'erp_return_item':
                $line = $this->selected($text, $data, 'return_lines', 'issue_line_id', ['label']);
                if (! is_array($line)) throw new RuntimeException('Select an issued bag item.');
                foreach ((array) ($data['return_lines'] ?? []) as $selectedLine) {
                    if ((int) ($selectedLine['issue_line_id'] ?? 0) === (int) $line['issue_line_id']) {
                        throw new RuntimeException('This bag item is already included in the return draft.');
                    }
                }
                $data['pending_return_line'] = $line;
                return $this->advance($session, 'erp_return_pcs', $data, $actorId, $sendText, $sendOptions);

            case 'erp_return_pcs':
                $pcs = $this->nonNegative($text);
                if ($pcs === null || floor($pcs) !== $pcs) throw new RuntimeException('Enter whole-number returned PCS.');
                $max = (float) ($data['pending_return_line']['available_pcs'] ?? 0);
                if ($pcs > $max) throw new RuntimeException('Returned PCS cannot exceed available PCS ' . $max . '.');
                $data['pending_return_pcs'] = (int) $pcs;
                return $this->advance($session, 'erp_return_weight', $data, $actorId, $sendText, $sendOptions);

            case 'erp_return_weight':
                $cts = $this->positive($text);
                if ($cts === null) throw new RuntimeException('Enter positive returned weight in CTS.');
                $line = is_array($data['pending_return_line'] ?? null) ? $data['pending_return_line'] : [];
                if ($cts > (float) ($line['available_cts'] ?? 0) + 0.0005) {
                    throw new RuntimeException('Returned weight exceeds available ' . number_format((float) $line['available_cts'], 3) . ' CTS.');
                }
                $data['return_lines'][] = [
                    'issue_line_id' => (int) $line['issue_line_id'],
                    'item_id' => (int) $line['item_id'],
                    'bag_id' => (int) ($line['bag_id'] ?? 0),
                    'bag_item_id' => (int) ($line['bag_item_id'] ?? 0),
                    'allocation_order_id' => (int) ($line['allocation_order_id'] ?? 0),
                    'pcs' => (int) ($data['pending_return_pcs'] ?? 0),
                    'carat' => $cts,
                    'rate_per_carat' => $line['rate_per_carat'] ?? null,
                    'line_value' => ($line['rate_per_carat'] ?? null) === null ? null : round($cts * (float) $line['rate_per_carat'], 2),
                    'label' => (string) ($line['label'] ?? ''),
                ];
                unset($data['pending_return_line'], $data['pending_return_pcs']);
                return $this->advance($session, 'erp_return_more', $data, $actorId, $sendText, $sendOptions);

            case 'erp_return_more':
                if (mb_strtolower($text) === 'add another item') {
                    return $this->advance($session, 'erp_return_item', $data, $actorId, $sendText, $sendOptions);
                }
                if (mb_strtolower($text) !== 'confirm return') throw new RuntimeException('Select Add Another Item or Confirm Return.');
                return $this->advance($session, 'erp_return_confirm', $data, $actorId, $sendText, $sendOptions);

            case 'erp_return_confirm':
                if (mb_strtolower($text) !== 'confirm return') throw new RuntimeException('Tap Confirm Return to save it.');
                if (! $this->claim($session)) {
                    $sendText('arattai_return_duplicate', 'Action already taken or currently processing.');
                    return ['handled' => true, 'status' => 'duplicate', 'message' => 'Return already processed.'];
                }
                try {
                    $result = $this->createDiamondReturn($data, $actorId);
                    $data['return_id'] = (int) $result['id'];
                    $this->complete($session, $data, (int) ($result['order_id'] ?? 0) ?: null);
                    $sendText('arattai_return_created', 'Diamond return saved successfully as ' . (string) $result['voucher_no'] . '.');
                    $sendDocument('arattai_return_pdf', 'Diamond Return ' . (string) $result['voucher_no'], base_url('api/documents/mobile/diamond/returns/' . (int) $result['id']));
                    return ['handled' => true, 'status' => 'created', 'message' => 'Diamond return created.'];
                } catch (Throwable $e) {
                    $this->release($session, $data);
                    throw $e;
                }
        }
        throw new RuntimeException('Invalid return step.');
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $data */
    private function acceptBag(
        array $session,
        string $step,
        array $data,
        string $text,
        int $actorId,
        callable $sendText,
        callable $sendOptions
    ): array {
        switch ($step) {
            case 'erp_bag_order':
                $order = $this->selected($text, $data, 'orders', 'id', ['order_no', 'order_name']);
                if (! is_array($order)) throw new RuntimeException('Select an eligible Diamond/Jadau order.');
                $data['order_id'] = (int) $order['id'];
                $data['order_no'] = (string) $order['order_no'];
                $location = $this->defaultInventoryLocation();
                $data['location_id'] = (int) $location['id'];
                $data['location_name'] = (string) $location['name'];
                return $this->advance($session, 'erp_bag_group', $data, $actorId, $sendText, $sendOptions);

            case 'erp_bag_group':
                $group = $this->selected($text, $data, 'diamond_chalni_groups', 'id', ['name', 'range_label']);
                if (! is_array($group)) throw new RuntimeException('Select a Diamond Chalni Group.');
                $data['pending_group'] = $group;
                return $this->advance($session, 'erp_bag_chalni', $data, $actorId, $sendText, $sendOptions);

            case 'erp_bag_chalni':
                $size = $this->selected($text, $data, 'diamond_sizes', 'id', ['size_label', 'size_code']);
                if (! is_array($size)) throw new RuntimeException('Select a Chalni/size from this group.');
                $data['pending_size'] = $size;
                return $this->advance($session, 'erp_bag_pcs', $data, $actorId, $sendText, $sendOptions);

            case 'erp_bag_pcs':
                $pcs = $this->positive($text);
                if ($pcs === null || floor($pcs) !== $pcs) throw new RuntimeException('Enter positive whole-number PCS.');
                $data['pending_bag_pcs'] = (int) $pcs;
                return $this->advance($session, 'erp_bag_weight', $data, $actorId, $sendText, $sendOptions);

            case 'erp_bag_weight':
                $cts = $this->positive($text);
                if ($cts === null) throw new RuntimeException('Enter positive diamond weight in CTS.');
                $row = $this->resolveBagRow(
                    (array) ($data['pending_group'] ?? []),
                    (array) ($data['pending_size'] ?? []),
                    (int) ($data['pending_bag_pcs'] ?? 0),
                    $cts
                );
                $data['bag_rows'][] = $row;
                unset($data['pending_group'], $data['pending_size'], $data['pending_bag_pcs']);
                return $this->advance($session, 'erp_bag_more', $data, $actorId, $sendText, $sendOptions);

            case 'erp_bag_more':
                if (mb_strtolower($text) === 'add another chalni') {
                    return $this->advance($session, 'erp_bag_group', $data, $actorId, $sendText, $sendOptions);
                }
                if (mb_strtolower($text) !== 'confirm bag') throw new RuntimeException('Select Add Another Chalni or Confirm Bag.');
                return $this->advance($session, 'erp_bag_confirm', $data, $actorId, $sendText, $sendOptions);

            case 'erp_bag_confirm':
                if (mb_strtolower($text) !== 'confirm bag') throw new RuntimeException('Tap Confirm Bag to create it.');
                if (! $this->claim($session)) {
                    $sendText('arattai_bag_duplicate', 'Action already taken or currently processing.');
                    return ['handled' => true, 'status' => 'duplicate', 'message' => 'Bag already processed.'];
                }
                try {
                    $bag = (new DiamondBagService())->create(
                        (int) $data['order_id'],
                        $actorId,
                        (int) $data['location_id'],
                        date('Y-m-d'),
                        array_values((array) ($data['bag_rows'] ?? [])),
                        'Created from Arattai conversation'
                    );
                    $data['bag_id'] = (int) ($bag['id'] ?? 0);
                    $data['bag_no'] = (string) ($bag['bag_no'] ?? '');
                    $this->complete($session, $data, (int) $data['order_id']);
                    $sendText('arattai_bag_created', 'Diamond bag ' . $data['bag_no'] . ' created successfully for order ' . $data['order_no'] . '.');
                    return ['handled' => true, 'status' => 'created', 'message' => 'Diamond bag created.'];
                } catch (Throwable $e) {
                    $this->release($session, $data);
                    throw $e;
                }
        }
        throw new RuntimeException('Invalid bag creation step.');
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $data */
    private function acceptReceiving(
        array $session,
        string $step,
        array $data,
        string $text,
        int $actorId,
        callable $sendText,
        callable $sendOptions,
        callable $sendDocument
    ): array {
        switch ($step) {
            case 'erp_receive_order':
                $order = $this->selected($text, $data, 'orders', 'id', ['order_no', 'order_name']);
                if (! is_array($order)) throw new RuntimeException('Select a Ready order.');
                $data['order_id'] = (int) $order['id'];
                $data['order_no'] = (string) $order['order_no'];
                $data['karigar_id'] = (int) ($order['assigned_karigar_id'] ?? 0);
                $form = (new OrderReceivingService())->formData((int) $order['id']);
                $data['diamond_issue_line_ids'] = array_values(array_map(
                    static fn(array $row): int => (int) ($row['issue_line_id'] ?? 0),
                    (array) ($form['diamond_bag_lines'] ?? [])
                ));
                $data['stone_rows'] = $this->stoneRowsForReceiving((int) $order['id'], (int) $data['karigar_id']);
                $warnings = [];
                if ($data['diamond_issue_line_ids'] === []) $warnings[] = 'Warning: No issued diamond bag line was found for this order.';
                if ($data['stone_rows'] === []) $warnings[] = 'Warning: No issued stone line was found for this order.';
                $data['warnings'] = $warnings;
                if ($warnings !== []) $sendText('arattai_receive_warning', implode("\n", $warnings));
                $location = $this->receivingLocation((int) $order['id'], (int) $data['karigar_id']);
                $data['location_id'] = (int) $location['id'];
                $data['location_name'] = (string) $location['name'];
                return $this->advance($session, 'erp_receive_gross', $data, $actorId, $sendText, $sendOptions);

            case 'erp_receive_gross':
                $value = $this->positive($text);
                if ($value === null) throw new RuntimeException('Enter positive gross weight in grams.');
                $data['gross_weight_gm'] = $value;
                return $this->advance($session, 'erp_receive_purity', $data, $actorId, $sendText, $sendOptions);

            case 'erp_receive_purity':
                $row = $this->selected($text, $data, 'gold_purities', 'id', ['purity_code', 'purity_percent']);
                if (! is_array($row)) throw new RuntimeException('Select ornament purity.');
                $data['gold_purity_id'] = (int) $row['id'];
                $data['purity_label'] = (string) $row['purity_code'];
                return $this->advance($session, 'erp_receive_gold_rate', $data, $actorId, $sendText, $sendOptions);

            case 'erp_receive_gold_rate':
            case 'erp_receive_labour_rate':
            case 'erp_receive_wastage':
                $allowZero = $step !== 'erp_receive_gold_rate';
                $value = $allowZero ? $this->nonNegative($text) : $this->positive($text);
                if ($value === null) throw new RuntimeException('Enter a valid non-negative number.');
                $key = match ($step) {
                    'erp_receive_gold_rate' => 'gold_rate_per_gm',
                    'erp_receive_labour_rate' => 'labour_rate_per_gm',
                    default => 'wastage_percent',
                };
                $data[$key] = $value;
                $next = match ($step) {
                    'erp_receive_gold_rate' => 'erp_receive_labour_rate',
                    'erp_receive_labour_rate' => 'erp_receive_wastage_type',
                    default => 'erp_receive_other_choice',
                };
                return $this->advance($session, $next, $data, $actorId, $sendText, $sendOptions);

            case 'erp_receive_wastage_type':
                $choice = mb_strtolower($text);
                if (! in_array($choice, ['normal', 'direct'], true)) throw new RuntimeException('Select Normal or Direct wastage.');
                $data['wastage_type'] = $choice;
                return $this->advance($session, 'erp_receive_wastage', $data, $actorId, $sendText, $sendOptions);

            case 'erp_receive_other_choice':
                if (mb_strtolower($text) === 'no other weight') {
                    $data['others'] = [];
                    return $this->advance($session, 'erp_receive_confirm', $data, $actorId, $sendText, $sendOptions);
                }
                if (mb_strtolower($text) !== 'add other weight') throw new RuntimeException('Select Add Other Weight or No Other Weight.');
                return $this->advance($session, 'erp_receive_other_name', $data, $actorId, $sendText, $sendOptions);

            case 'erp_receive_other_name':
                if ($text === '' || mb_strlen($text) > 120) throw new RuntimeException('Enter other component name.');
                $data['pending_other_name'] = $text;
                return $this->advance($session, 'erp_receive_other_pcs', $data, $actorId, $sendText, $sendOptions);

            case 'erp_receive_other_pcs':
                $value = $this->nonNegative($text);
                if ($value === null) throw new RuntimeException('Enter other component PCS or 0.');
                $data['pending_other_pcs'] = $value;
                return $this->advance($session, 'erp_receive_other_weight', $data, $actorId, $sendText, $sendOptions);

            case 'erp_receive_other_weight':
                $value = $this->positive($text);
                if ($value === null) throw new RuntimeException('Enter positive other weight in grams.');
                $data['pending_other_weight'] = $value;
                return $this->advance($session, 'erp_receive_other_rate', $data, $actorId, $sendText, $sendOptions);

            case 'erp_receive_other_rate':
                $rate = $this->nonNegative($text);
                if ($rate === null) throw new RuntimeException('Enter other component rate or 0.');
                $weight = (float) ($data['pending_other_weight'] ?? 0);
                $data['others'][] = [
                    'name' => (string) $data['pending_other_name'],
                    'pcs' => (float) ($data['pending_other_pcs'] ?? 0),
                    'weight_gm' => $weight,
                    'weight_cts' => 0,
                    'rate' => $rate,
                    'line_total' => round($weight * $rate, 2),
                ];
                unset($data['pending_other_name'], $data['pending_other_pcs'], $data['pending_other_weight']);
                return $this->advance($session, 'erp_receive_confirm', $data, $actorId, $sendText, $sendOptions);

            case 'erp_receive_confirm':
                if (mb_strtolower($text) !== 'confirm receiving') throw new RuntimeException('Tap Confirm Receiving to save it.');
                if (! $this->claim($session)) {
                    $sendText('arattai_receive_duplicate', 'Action already taken or currently processing.');
                    return ['handled' => true, 'status' => 'duplicate', 'message' => 'Receiving already processed.'];
                }
                try {
                    $diamondIds = array_values(array_filter(array_map('intval', (array) ($data['diamond_issue_line_ids'] ?? []))));
                    $diamonds = $diamondIds === [] ? [] : [['issue_line_ids' => $diamondIds, 'name' => 'Issued diamonds']];
                    $result = (new OrderReceivingService())->receive((int) $data['order_id'], [
                        'location_id' => (int) $data['location_id'],
                        'gold_purity_id' => (int) $data['gold_purity_id'],
                        'gross_weight_gm' => (float) $data['gross_weight_gm'],
                        'gold_rate_per_gm' => (float) $data['gold_rate_per_gm'],
                        'labour_rate_per_gm' => (float) $data['labour_rate_per_gm'],
                        'wastage_percent' => (float) $data['wastage_percent'],
                        'wastage_type' => (string) $data['wastage_type'],
                        'diamonds' => $diamonds,
                        'stones' => array_values((array) ($data['stone_rows'] ?? [])),
                        'others' => array_values((array) ($data['others'] ?? [])),
                        'notes' => 'Received from Arattai conversation',
                    ], $actorId);
                    $data['receipt'] = $result['receipt'] ?? [];
                    $this->complete($session, $data, (int) $data['order_id']);
                    $sendText('arattai_receive_created', 'Order ' . $data['order_no'] . ' received successfully. Packing list is ready.');
                    $sendDocument('arattai_packing_pdf', 'Packing List · ' . $data['order_no'], base_url('api/documents/orders/' . (int) $data['order_id'] . '/packing-list?download=1'));
                    $sendOptions('arattai_post_receive', 'What would you like next?', ['Delivery Challan', 'Packing List', 'Menu']);
                    return ['handled' => true, 'status' => 'received', 'message' => 'Order receiving completed.'];
                } catch (Throwable $e) {
                    $this->release($session, $data);
                    throw $e;
                }
        }
        throw new RuntimeException('Invalid receiving step.');
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $data */
    private function acceptDocument(
        array $session,
        string $step,
        array $data,
        string $text,
        int $actorId,
        callable $sendText,
        callable $sendDocument
    ): array {
        $order = $this->selected($text, $data, 'orders', 'id', ['order_no', 'order_name']);
        if (! is_array($order)) throw new RuntimeException('Select a completed and received order.');
        $orderId = (int) $order['id'];
        $orderNo = (string) $order['order_no'];
        $isPacking = $step === 'erp_packing_order';
        $url = $isPacking
            ? base_url('api/documents/orders/' . $orderId . '/packing-list?download=1')
            : base_url('api/documents/orders/' . $orderId . '/delivery-challan?download=1');
        $caption = ($isPacking ? 'Packing List · ' : 'Delivery Challan · ') . $orderNo;
        $data['order_id'] = $orderId;
        $data['order_no'] = $orderNo;
        $this->complete($session, $data, $orderId);
        $sendText('arattai_document_ready', $caption . ' is ready.');
        $sendDocument($isPacking ? 'arattai_packing_pdf' : 'arattai_challan_pdf', $caption, $url);
        return ['handled' => true, 'status' => 'document_sent', 'message' => $caption . ' sent.'];
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $session @param array<string,mixed> $data */
    private function acceptPurchase(
        array $payload,
        array $session,
        string $step,
        array $data,
        string $text,
        int $actorId,
        callable $sendText,
        callable $sendOptions
    ): array {
        switch ($step) {
            case 'erp_purchase_type':
                $type = ucfirst(strtolower($text));
                if (! in_array($type, ['Gold', 'Diamond', 'Stone'], true)) throw new RuntimeException('Select Gold, Diamond or Stone.');
                $data['purchase_type'] = $type;
                $data['items'] = [];
                return $this->advance($session, 'erp_purchase_vendor', $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_vendor':
                $row = $this->selected($text, $data, 'vendors', 'id', ['name']);
                if (! is_array($row)) throw new RuntimeException('Select an active vendor.');
                $data['vendor_id'] = (int) $row['id'];
                $data['vendor_name'] = (string) $row['name'];
                return $this->advance($session, 'erp_purchase_date', $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_date':
                $date = strcasecmp($text, 'Today') === 0 ? date('Y-m-d') : $this->dateOnly($text);
                if ($date === null) throw new RuntimeException('Enter a valid purchase date, for example 18/10/2026.');
                $data['purchase_date'] = $date;
                return $this->advance($session, 'erp_purchase_location', $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_location':
                $row = $this->selected($text, $data, 'inventory_locations', 'id', ['name', 'code']);
                if (! is_array($row)) throw new RuntimeException('Select an inventory location.');
                $data['location_id'] = (int) $row['id'];
                $data['location_name'] = (string) $row['name'];
                return $this->advance($session, 'erp_purchase_invoice', $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_invoice':
                if ($text === '' || mb_strlen($text) > 80) throw new RuntimeException('Enter an invoice number up to 80 characters.');
                $data['invoice_no'] = $text;
                $next = ($data['purchase_type'] ?? '') === 'Gold' ? 'erp_purchase_gst' : 'erp_purchase_due';
                return $this->advance($session, $next, $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_due':
                $date = $this->dateOnly($text);
                if ($date === null) throw new RuntimeException('Enter a valid payment due date.');
                $data['payment_due_date'] = $date;
                return $this->advance($session, 'erp_purchase_gst', $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_gst':
                $row = $this->selected($text, $data, 'gst_masters', 'id', ['name']);
                if (! is_array($row)) throw new RuntimeException('Select an active GST master.');
                $data['gst_master_id'] = (int) $row['id'];
                $data['gst_label'] = (string) $row['name'];
                return $this->advance($session, 'erp_purchase_taxable', $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_taxable':
                $amount = $this->positive($text);
                if ($amount === null) throw new RuntimeException('Enter taxable invoice amount greater than zero.');
                $data['taxable_amount'] = round($amount, 2);
                $data['round_off_amount'] = 0;
                return $this->advance($session, 'erp_purchase_material', $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_material':
                if ($text === '' || mb_strlen($text) > 120) throw new RuntimeException('Enter material name/shape up to 120 characters.');
                $data['pending_item'] = ['item_type' => (string) $data['purchase_type'], 'material_name' => $text];
                $next = match ((string) $data['purchase_type']) {
                    'Gold' => 'erp_purchase_purity',
                    'Diamond' => 'erp_purchase_diamond_details',
                    default => 'erp_purchase_stone_details',
                };
                return $this->advance($session, $next, $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_purity':
                $row = $this->selected($text, $data, 'gold_purities', 'id', ['purity_code']);
                if (! is_array($row)) throw new RuntimeException('Select a gold purity.');
                $data['pending_item']['gold_purity_id'] = (int) $row['id'];
                $data['pending_item']['purity_label'] = (string) $row['purity_code'];
                return $this->advance($session, 'erp_purchase_weight', $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_diamond_details':
                $parts = array_map('trim', explode(',', $text));
                $data['pending_item']['diamond_shape'] = (string) ($parts[0] ?? $data['pending_item']['material_name']);
                $data['pending_item']['diamond_sieve'] = (string) ($parts[1] ?? '');
                $data['pending_item']['diamond_color'] = (string) ($parts[2] ?? '');
                $data['pending_item']['diamond_clarity'] = (string) ($parts[3] ?? '');
                return $this->advance($session, 'erp_purchase_pcs', $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_stone_details':
                $parts = array_map('trim', explode(',', $text));
                $data['pending_item']['diamond_sieve'] = (string) ($parts[0] ?? '');
                $data['pending_item']['diamond_color'] = (string) ($parts[1] ?? '');
                $data['pending_item']['diamond_clarity'] = (string) ($parts[2] ?? '');
                return $this->advance($session, 'erp_purchase_pcs', $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_pcs':
                $pcs = $this->nonNegative($text);
                if ($pcs === null || floor($pcs) !== $pcs) throw new RuntimeException('Enter whole PCS, 0 allowed.');
                $data['pending_item']['pcs'] = (int) $pcs;
                return $this->advance($session, 'erp_purchase_cts', $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_weight':
            case 'erp_purchase_cts':
                $weight = $this->positive($text);
                if ($weight === null) throw new RuntimeException('Enter a weight greater than zero.');
                if ($step === 'erp_purchase_weight') $data['pending_item']['weight_gm'] = $weight;
                else $data['pending_item']['cts'] = $weight;
                $data['items'][] = $data['pending_item'];
                unset($data['pending_item']);
                return $this->advance($session, 'erp_purchase_more', $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_more':
                if (strcasecmp($text, 'Add Another Item') === 0) return $this->advance($session, 'erp_purchase_material', $data, $actorId, $sendText, $sendOptions);
                if (strcasecmp($text, 'Finish Items') !== 0) throw new RuntimeException('Select Add Another Item or Finish Items.');
                return $this->advance($session, 'erp_purchase_bill', $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_bill':
                $data['purchase_bill'] = (new ArattaiOrderConversationService())->storeIncomingAttachment($payload, 'purchase-bill');
                return $this->advance($session, 'erp_purchase_notes', $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_notes':
                $data['notes'] = in_array(mb_strtolower($text), ['skip', 'skip notes'], true) ? '' : mb_substr($text, 0, 1000);
                return $this->advance($session, 'erp_purchase_confirm', $data, $actorId, $sendText, $sendOptions);
            case 'erp_purchase_confirm':
                if (strcasecmp($text, 'Confirm Purchase') !== 0) throw new RuntimeException('Tap Confirm Purchase to create it.');
                if (! $this->claim($session)) {
                    $sendText('arattai_purchase_duplicate', 'This purchase was already created or is being processed.');
                    return ['handled' => true, 'status' => 'duplicate', 'message' => 'Purchase already processed.'];
                }
                try {
                    $result = (new ArattaiPurchaseService())->create($data, $actorId);
                    $data['purchase_id'] = (int) $result['id'];
                    $data['purchase_no'] = (string) $result['purchase_no'];
                    $this->complete($session, $data, null);
                    $sendText('arattai_purchase_created', 'Purchase ' . $data['purchase_no'] . ' created successfully with the attached bill.');
                    return ['handled' => true, 'status' => 'created', 'message' => 'Purchase created.'];
                } catch (Throwable $e) {
                    $this->release($session, $data);
                    throw $e;
                }
        }
        throw new RuntimeException('Invalid purchase step.');
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $data */
    private function acceptCustomChallan(
        array $session,
        string $step,
        array $data,
        string $text,
        int $actorId,
        callable $sendText,
        callable $sendOptions,
        callable $sendDocument
    ): array {
        switch ($step) {
            case 'erp_challan_mode':
                if (strcasecmp($text, 'From Received Order') === 0) return $this->advance($session, 'erp_challan_order', $data, $actorId, $sendText, $sendOptions);
                if (strcasecmp($text, 'Custom Delivery Challan') !== 0) throw new RuntimeException('Select received order challan or custom delivery challan.');
                $data['items'] = [];
                return $this->advance($session, 'erp_custom_challan_customer', $data, $actorId, $sendText, $sendOptions);
            case 'erp_custom_challan_customer':
                $row = $this->selected($text, $data, 'customers', 'id', ['name']);
                if (! is_array($row)) throw new RuntimeException('Select an active customer.');
                $data['customer_id'] = (int) $row['id'];
                $data['customer_name'] = (string) $row['name'];
                return $this->advance($session, 'erp_custom_challan_date', $data, $actorId, $sendText, $sendOptions);
            case 'erp_custom_challan_date':
                $date = strcasecmp($text, 'Today') === 0 ? date('Y-m-d') : $this->dateOnly($text);
                if ($date === null) throw new RuntimeException('Enter a valid challan date.');
                $data['challan_date'] = $date;
                return $this->advance($session, 'erp_custom_challan_branch', $data, $actorId, $sendText, $sendOptions);
            case 'erp_custom_challan_branch':
                $branch = ucfirst(strtolower($text));
                if (! in_array($branch, ['Mumbai', 'Hyderabad'], true)) throw new RuntimeException('Select Mumbai or Hyderabad.');
                $data['dispatch_from'] = $branch;
                return $this->advance($session, 'erp_custom_challan_type', $data, $actorId, $sendText, $sendOptions);
            case 'erp_custom_challan_type':
                $type = match (mb_strtolower($text)) {
                    'ornament' => 'ornament', 'loose diamond' => 'loose_diamond', 'loose gold' => 'loose_gold', default => '',
                };
                if ($type === '') throw new RuntimeException('Select Ornament, Loose Diamond or Loose Gold.');
                $data['pending_item'] = ['item_type' => $type];
                return $this->advance($session, 'erp_custom_challan_description', $data, $actorId, $sendText, $sendOptions);
            case 'erp_custom_challan_description':
                if ($text === '') throw new RuntimeException('Enter item description.');
                $data['pending_item']['description'] = mb_substr($text, 0, 255);
                $next = ($data['pending_item']['item_type'] ?? '') === 'ornament' ? 'erp_custom_challan_purity' : 'erp_custom_challan_pcs';
                return $this->advance($session, $next, $data, $actorId, $sendText, $sendOptions);
            case 'erp_custom_challan_purity':
                $purity = strtoupper($text);
                if (! in_array($purity, ['14 KT', '18 KT', '22 KT'], true)) throw new RuntimeException('Select 14 KT, 18 KT or 22 KT.');
                $data['pending_item']['purity'] = $purity;
                return $this->advance($session, 'erp_custom_challan_pcs', $data, $actorId, $sendText, $sendOptions);
            case 'erp_custom_challan_pcs':
                $pcs = $this->positive($text);
                if ($pcs === null || floor($pcs) !== $pcs) throw new RuntimeException('Enter whole PCS greater than zero.');
                $data['pending_item']['pcs'] = (int) $pcs;
                $next = match ((string) ($data['pending_item']['item_type'] ?? '')) {
                    'ornament' => 'erp_custom_challan_gross', 'loose_diamond' => 'erp_custom_challan_diamond', default => 'erp_custom_challan_net',
                };
                return $this->advance($session, $next, $data, $actorId, $sendText, $sendOptions);
            case 'erp_custom_challan_gross':
                $value = $this->positive($text);
                if ($value === null) throw new RuntimeException('Enter gross weight greater than zero.');
                $data['pending_item']['gross_weight_gm'] = $value;
                return $this->advance($session, 'erp_custom_challan_net', $data, $actorId, $sendText, $sendOptions);
            case 'erp_custom_challan_net':
                $value = $this->positive($text);
                if ($value === null) throw new RuntimeException('Enter net/gold weight greater than zero.');
                $data['pending_item']['net_weight_gm'] = $value;
                return $this->advance($session, 'erp_custom_challan_value', $data, $actorId, $sendText, $sendOptions);
            case 'erp_custom_challan_diamond':
                $value = $this->positive($text);
                if ($value === null) throw new RuntimeException('Enter diamond carat weight greater than zero.');
                $data['pending_item']['diamond_weight_cts'] = $value;
                return $this->advance($session, 'erp_custom_challan_value', $data, $actorId, $sendText, $sendOptions);
            case 'erp_custom_challan_value':
                $value = $this->nonNegative($text);
                if ($value === null) throw new RuntimeException('Enter a non-negative item value.');
                $data['pending_item']['value'] = round($value, 2);
                $data['items'][] = $data['pending_item'];
                unset($data['pending_item']);
                return $this->advance($session, 'erp_custom_challan_more', $data, $actorId, $sendText, $sendOptions);
            case 'erp_custom_challan_more':
                if (strcasecmp($text, 'Add Another Item') === 0) return $this->advance($session, 'erp_custom_challan_type', $data, $actorId, $sendText, $sendOptions);
                if (strcasecmp($text, 'Finish Items') !== 0) throw new RuntimeException('Select Add Another Item or Finish Items.');
                return $this->advance($session, 'erp_custom_challan_gst', $data, $actorId, $sendText, $sendOptions);
            case 'erp_custom_challan_gst':
                $rate = (float) str_replace('%', '', trim($text));
                if (! in_array($rate, [0.0, 3.0, 5.0, 12.0, 18.0], true)) throw new RuntimeException('Select a valid GST rate.');
                $data['tax_percent'] = $rate;
                return $this->advance($session, 'erp_custom_challan_notes', $data, $actorId, $sendText, $sendOptions);
            case 'erp_custom_challan_notes':
                $data['notes'] = in_array(mb_strtolower($text), ['skip', 'skip notes'], true) ? '' : mb_substr($text, 0, 1000);
                return $this->advance($session, 'erp_custom_challan_confirm', $data, $actorId, $sendText, $sendOptions);
            case 'erp_custom_challan_confirm':
                if (strcasecmp($text, 'Confirm Challan') !== 0) throw new RuntimeException('Tap Confirm Challan to continue.');
                if (! $this->claim($session)) {
                    $sendText('arattai_challan_duplicate', 'This challan was already submitted or is being processed.');
                    return ['handled' => true, 'status' => 'duplicate', 'message' => 'Challan already processed.'];
                }
                try {
                    $validated = (new DeliveryChallanService())->validatedPayload($data);
                    if ((new MobileUserPolicyService())->requiresApproval($actorId, 'delivery_challan')) {
                        $request = (new MobileApprovalService())->submit(
                            'delivery_challan', $validated, $actorId,
                            'Custom delivery challan for ' . (string) $data['customer_name']
                        );
                        $data['approval_request_id'] = (int) ($request['id'] ?? 0);
                        $this->complete($session, $data, null);
                        $sendText('arattai_challan_submitted', 'Custom delivery challan sent for approval. Request #' . $data['approval_request_id'] . '. PDF will be sent after approval.');
                        return ['handled' => true, 'status' => 'submitted', 'message' => 'Challan submitted for approval.'];
                    }
                    $challan = (new DeliveryChallanService())->create($validated, $actorId);
                    $id = (int) ($challan['id'] ?? 0);
                    $this->complete($session, $data, null);
                    $sendText('arattai_challan_created', 'Delivery Challan ' . (string) ($challan['challan_no'] ?? ('#' . $id)) . ' created successfully.');
                    $sendDocument('arattai_challan_pdf', 'Delivery Challan · ' . (string) ($challan['challan_no'] ?? $id), base_url('api/documents/delivery-challans/' . $id . '?download=1'));
                    return ['handled' => true, 'status' => 'created', 'message' => 'Custom challan created.'];
                } catch (Throwable $e) {
                    $this->release($session, $data);
                    throw $e;
                }
        }
        throw new RuntimeException('Invalid custom delivery challan step.');
    }

    /** @param array<string,mixed> $session */
    private function prompt(array $session, callable $sendText, callable $sendOptions): void
    {
        $step = (string) ($session['current_step'] ?? '');
        $data = $this->data($session);
        $actorId = (int) ($session['actor_admin_user_id'] ?? 0);
        $db = db_connect();

        switch ($step) {
            case 'erp_followup_order':
                $rows = $db->table('orders')->select('id, order_no, order_name, status, followup_assigned_to')
                    ->where('deleted_at', null)->where('followup_assigned_to', $actorId)
                    ->whereNotIn('status', ['Cancelled', 'Completed', 'Complete', 'Delivered'])
                    ->orderBy('id', 'DESC')->limit(8)->get()->getResultArray();
                $this->sendRows($session, 'orders', $rows, 'id', ['order_no', 'order_name'], 'Select order for follow-up:', $sendOptions);
                return;
            case 'erp_followup_stage':
                $sendOptions('arattai_followup_stage', 'Select current order stage:', array_slice(array_values(config(Jewellery::class)->orderStatuses), 0, 9));
                return;
            case 'erp_followup_description':
                $sendText('arattai_followup_note', 'Enter the follow-up/update note.');
                return;
            case 'erp_followup_date':
                $sendOptions('arattai_followup_date', 'Enter next follow-up date/time (example 25/10/2026 11:00 AM). Skip is allowed only for a terminal stage.', ['Skip', 'Cancel Current Process']);
                return;
            case 'erp_followup_image':
                $sendOptions('arattai_followup_image', 'Upload a follow-up image if available. Image is optional.', ['Skip Image', 'Cancel Current Process']);
                return;
            case 'erp_followup_confirm':
                $sendOptions('arattai_followup_confirm', $this->followupSummary($data), ['Confirm Follow-up', 'Cancel Current Process']);
                return;

            case 'erp_issue_karigar':
                $rows = $db->table('karigars')->select('id, name')->where('is_active', 1)->orderBy('name')->limit(8)->get()->getResultArray();
                $this->sendRows($session, 'karigars', $rows, 'id', ['name'], 'Select karigar for issuement:', $sendOptions);
                return;
            case 'erp_issue_location':
                $rows = $db->table('inventory_locations')->select('id, name, code')->where('is_active', 1)->orderBy('name')->limit(8)->get()->getResultArray();
                $this->sendRows($session, 'inventory_locations', $rows, 'id', ['name', 'code'], 'Select inventory location:', $sendOptions);
                return;
            case 'erp_issue_material':
            case 'erp_issue_more':
                $labels = ['Add Gold', 'Add Diamond', 'Add Stone'];
                if (($data['gold_lines'] ?? []) !== [] || ($data['diamond_lines'] ?? []) !== [] || ($data['stone_lines'] ?? []) !== []) $labels[] = 'Confirm Issuement';
                $labels[] = 'Cancel Current Process';
                $sendOptions('arattai_issue_material', $this->issueSummary($data) . "\nSelect material:", $labels);
                return;
            case 'erp_issue_gold_item':
                $rows = $db->table('gold_inventory_items gi')->select('gi.id, gi.purity_code, gi.purity_percent, gi.color_name, gi.form_type, COALESCE(gs.weight_balance_gm,0) AS balance', false)
                    ->join('gold_inventory_stock gs', 'gs.item_id = gi.id', 'left')->where('COALESCE(gs.weight_balance_gm,0) >', 0, false)
                    ->orderBy('gi.purity_percent', 'DESC')->limit(8)->get()->getResultArray();
                $this->sendRows($session, 'gold_inventory_items', $rows, 'id', ['purity_code', 'color_name', 'form_type'], 'Select gold stock item:', $sendOptions);
                return;
            case 'erp_issue_gold_weight':
                $sendText('arattai_issue_gold_weight', 'Enter gold issue weight in grams.');
                return;
            case 'erp_issue_diamond_bag':
                $rows = $db->table('diamond_bags b')->select('b.id, b.bag_no, b.order_id, o.order_no')
                    ->join('orders o', 'o.id = b.order_id', 'inner')
                    ->where('o.assigned_karigar_id', (int) ($data['karigar_id'] ?? 0))
                    ->where('b.inventory_location_id', (int) ($data['location_id'] ?? 0))
                    ->where("NOT EXISTS (SELECT 1 FROM issue_lines il WHERE il.bag_id = b.id)", null, false)
                    ->orderBy('b.id', 'DESC')->limit(8)->get()->getResultArray();
                $this->sendRows($session, 'diamond_bags', $rows, 'id', ['bag_no', 'order_no'], 'Select Diamond Bag. All available bag items will be included:', $sendOptions);
                return;
            case 'erp_issue_stone_order':
                $rows = (new \App\Services\StoneInventory\StoneIssueOrderService($db))->orderOptions();
                $rows = array_slice($rows, 0, 8);
                $this->sendRows($session, 'orders', $rows, 'id', ['order_no', 'order_name'], 'Select order for Stone Issuement:', $sendOptions);
                return;
            case 'erp_issue_stone_item':
                $rows = $db->table('stone_inventory_items si')->select('si.id, si.product_name, si.stone_type, COALESCE(ss.qty_balance,0) AS balance', false)
                    ->join('stone_inventory_stock ss', 'ss.item_id = si.id', 'left')->where('COALESCE(ss.qty_balance,0) >', 0, false)
                    ->orderBy('si.product_name')->limit(8)->get()->getResultArray();
                $this->sendRows($session, 'stone_inventory_items', $rows, 'id', ['product_name', 'stone_type'], 'Select stone stock item:', $sendOptions);
                return;
            case 'erp_issue_stone_pcs':
                $sendText('arattai_issue_stone_pcs', 'Enter stone PCS (enter 0 if not applicable).');
                return;
            case 'erp_issue_stone_weight':
                $sendText('arattai_issue_stone_weight', 'Enter stone weight/quantity.');
                return;
            case 'erp_issue_image':
                $sendText('arattai_issue_image', 'Upload Issuement Image. This image is compulsory; issuement cannot be submitted without it.');
                return;
            case 'erp_issue_confirm':
                $sendOptions('arattai_issue_confirm', $this->issueSummary($data), ['Confirm Issuement', 'Cancel Current Process']);
                return;

            case 'erp_return_bag':
                $rows = $this->returnBags();
                $this->sendRows($session, 'return_bags', $rows, 'bag_id', ['bag_no', 'voucher_no'], 'Select issued Diamond Bag for return:', $sendOptions);
                return;
            case 'erp_return_item':
                $rows = array_values(array_filter((new DiamondBagTraceService())->returnableIssueLines((int) ($data['issue_id'] ?? 0)),
                    static fn(array $row): bool => (int) ($row['bag_id'] ?? 0) === (int) ($data['bag_id'] ?? 0)));
                $selectedLineIds = array_fill_keys(array_map(
                    static fn(array $row): int => (int) ($row['issue_line_id'] ?? 0),
                    (array) ($data['return_lines'] ?? [])
                ), true);
                $rows = array_values(array_filter($rows, static fn(array $row): bool => ! isset($selectedLineIds[(int) ($row['issue_line_id'] ?? 0)])));
                $this->sendRows($session, 'return_lines', array_slice($rows, 0, 8), 'issue_line_id', ['label'], 'Select bag item to return:', $sendOptions);
                return;
            case 'erp_return_pcs':
                $line = (array) ($data['pending_return_line'] ?? []);
                $sendText('arattai_return_pcs', 'Enter returned PCS. Available: ' . number_format((float) ($line['available_pcs'] ?? 0), 0) . '.');
                return;
            case 'erp_return_weight':
                $line = (array) ($data['pending_return_line'] ?? []);
                $sendText('arattai_return_weight', 'Enter returned weight in CTS. Available: ' . number_format((float) ($line['available_cts'] ?? 0), 3) . ' CTS.');
                return;
            case 'erp_return_more':
                $sendOptions('arattai_return_more', $this->returnSummary($data), ['Add Another Item', 'Confirm Return', 'Cancel Current Process']);
                return;
            case 'erp_return_confirm':
                $sendOptions('arattai_return_confirm', $this->returnSummary($data), ['Confirm Return', 'Cancel Current Process']);
                return;

            case 'erp_bag_order':
                $canManage = (new RbacService())->userCan($actorId, 'diamond.inventory.manage');
                $rows = array_slice((new DiamondBagService())->availableOrders($actorId, $canManage), 0, 8);
                $this->sendRows($session, 'orders', $rows, 'id', ['order_no', 'order_name'], 'Select order for Diamond Bag creation:', $sendOptions);
                return;
            case 'erp_bag_group':
                $rows = $db->table('diamond_chalni_groups')->select('id, name, range_label')->where('is_active', 1)->orderBy('sort_order')->limit(8)->get()->getResultArray();
                $this->sendRows($session, 'diamond_chalni_groups', $rows, 'id', ['name', 'range_label'], 'Select Diamond Chalni Group:', $sendOptions);
                return;
            case 'erp_bag_chalni':
                $groupId = (int) ($data['pending_group']['id'] ?? 0);
                $rows = $db->table('diamond_size_masters sz')->select('sz.id, sz.shape_id, sz.size_label, sz.size_code')
                    ->join('diamond_chalni_group_sizes gs', 'gs.size_id = sz.id', 'inner')
                    ->where('gs.group_id', $groupId)->where('sz.is_active', 1)->orderBy('sz.sort_order')->limit(8)->get()->getResultArray();
                $this->sendRows($session, 'diamond_sizes', $rows, 'id', ['size_label', 'size_code'], 'Select Chalni/size:', $sendOptions);
                return;
            case 'erp_bag_pcs':
                $sendText('arattai_bag_pcs', 'Enter Diamond PCS.');
                return;
            case 'erp_bag_weight':
                $sendText('arattai_bag_weight', 'Enter Diamond weight in CTS.');
                return;
            case 'erp_bag_more':
                $sendOptions('arattai_bag_more', $this->bagSummary($data), ['Add Another Chalni', 'Confirm Bag', 'Cancel Current Process']);
                return;
            case 'erp_bag_confirm':
                $sendOptions('arattai_bag_confirm', $this->bagSummary($data), ['Confirm Bag', 'Cancel Current Process']);
                return;

            case 'erp_receive_order':
                $rows = $this->receivedDocumentOrders($actorId, true);
                $this->sendRows($session, 'orders', $rows, 'id', ['order_no', 'order_name'], 'Select Ready order for receiving:', $sendOptions);
                return;
            case 'erp_receive_gross':
                $sendText('arattai_receive_gross', 'Enter finished jewellery gross weight in grams.');
                return;
            case 'erp_receive_purity':
                $rows = $db->table('gold_purities')->select('id, purity_code, purity_percent')->where('is_active', 1)->orderBy('purity_percent', 'DESC')->limit(8)->get()->getResultArray();
                $this->sendRows($session, 'gold_purities', $rows, 'id', ['purity_code', 'purity_percent'], 'Select ornament purity:', $sendOptions);
                return;
            case 'erp_receive_gold_rate':
                $sendText('arattai_receive_gold_rate', 'Enter Gold rate per gram.');
                return;
            case 'erp_receive_labour_rate':
                $sendText('arattai_receive_labour_rate', 'Enter Labour rate per gram (0 allowed).');
                return;
            case 'erp_receive_wastage_type':
                $sendOptions('arattai_receive_wastage_type', 'Select wastage calculation type:', ['Normal', 'Direct', 'Cancel Current Process']);
                return;
            case 'erp_receive_wastage':
                $sendText('arattai_receive_wastage', 'Enter wastage percentage (0 allowed).');
                return;
            case 'erp_receive_other_choice':
                $sendOptions('arattai_receive_other_choice', 'Do you want to add Other Weight?', ['Add Other Weight', 'No Other Weight', 'Cancel Current Process']);
                return;
            case 'erp_receive_other_name':
                $sendText('arattai_receive_other_name', 'Enter other component name/details.');
                return;
            case 'erp_receive_other_pcs':
                $sendText('arattai_receive_other_pcs', 'Enter other component PCS (0 allowed).');
                return;
            case 'erp_receive_other_weight':
                $sendText('arattai_receive_other_weight', 'Enter other component weight in grams.');
                return;
            case 'erp_receive_other_rate':
                $sendText('arattai_receive_other_rate', 'Enter other component rate (0 allowed).');
                return;
            case 'erp_receive_confirm':
                $sendOptions('arattai_receive_confirm', $this->receivingSummary($data), ['Confirm Receiving', 'Cancel Current Process']);
                return;

            case 'erp_purchase_type':
                $sendOptions('arattai_purchase_type', 'Select purchase type:', ['Gold', 'Diamond', 'Stone', 'Cancel Current Process']);
                return;
            case 'erp_purchase_vendor':
                $rows = $db->table('vendors')->select('id, name')->where('is_active', 1)->orderBy('name')->limit(8)->get()->getResultArray();
                $this->sendRows($session, 'vendors', $rows, 'id', ['name'], 'Select purchase vendor:', $sendOptions);
                return;
            case 'erp_purchase_date':
                $sendOptions('arattai_purchase_date', 'Enter purchase date (DD/MM/YYYY) or choose Today:', ['Today', 'Cancel Current Process']);
                return;
            case 'erp_purchase_location':
                $rows = $db->table('inventory_locations')->select('id, name, code')->where('is_active', 1)->orderBy('name')->limit(8)->get()->getResultArray();
                $this->sendRows($session, 'inventory_locations', $rows, 'id', ['name', 'code'], 'Select inventory location:', $sendOptions);
                return;
            case 'erp_purchase_invoice':
                $sendText('arattai_purchase_invoice', 'Enter vendor invoice number.');
                return;
            case 'erp_purchase_due':
                $sendText('arattai_purchase_due', 'Enter payment due date (DD/MM/YYYY).');
                return;
            case 'erp_purchase_gst':
                $rows = array_slice((new TaxMasterService())->options(), 0, 8);
                $this->sendRows($session, 'gst_masters', $rows, 'id', ['name', 'total_percentage'], 'Select GST master:', $sendOptions);
                return;
            case 'erp_purchase_taxable':
                $sendText('arattai_purchase_taxable', 'Enter taxable invoice amount before GST.');
                return;
            case 'erp_purchase_material':
                $sendText('arattai_purchase_material', 'Enter material name' . (($data['purchase_type'] ?? '') === 'Diamond' ? '/diamond shape' : '') . '.');
                return;
            case 'erp_purchase_purity':
                $rows = $db->table('gold_purities')->select('id, purity_code, purity_percent')->where('is_active', 1)->orderBy('purity_percent', 'DESC')->limit(8)->get()->getResultArray();
                $this->sendRows($session, 'gold_purities', $rows, 'id', ['purity_code', 'purity_percent'], 'Select gold purity:', $sendOptions);
                return;
            case 'erp_purchase_diamond_details':
                $sendText('arattai_purchase_diamond_details', 'Enter Shape, Size, Color, Clarity separated by commas. Example: Round, 2.5 mm, EF, VVS.');
                return;
            case 'erp_purchase_stone_details':
                $sendText('arattai_purchase_stone_details', 'Enter Size, Color, Quality separated by commas.');
                return;
            case 'erp_purchase_pcs':
                $sendText('arattai_purchase_pcs', 'Enter item PCS (0 allowed).');
                return;
            case 'erp_purchase_weight':
                $sendText('arattai_purchase_weight', 'Enter gold weight in grams.');
                return;
            case 'erp_purchase_cts':
                $sendText('arattai_purchase_cts', 'Enter item weight in carats.');
                return;
            case 'erp_purchase_more':
                $sendOptions('arattai_purchase_more', $this->purchaseSummary($data), ['Add Another Item', 'Finish Items', 'Cancel Current Process']);
                return;
            case 'erp_purchase_bill':
                $sendText('arattai_purchase_bill', 'Upload the Purchase Bill now (PDF/JPG/PNG/WEBP, maximum 10 MB). Bill is compulsory.');
                return;
            case 'erp_purchase_notes':
                $sendOptions('arattai_purchase_notes', 'Enter purchase notes, or Skip.', ['Skip Notes', 'Cancel Current Process']);
                return;
            case 'erp_purchase_confirm':
                $sendOptions('arattai_purchase_confirm', $this->purchaseSummary($data) . "\nBill: Attached (compulsory)", ['Confirm Purchase', 'Cancel Current Process']);
                return;

            case 'erp_challan_mode':
                $sendOptions('arattai_challan_mode', 'Choose Delivery Challan type:', ['From Received Order', 'Custom Delivery Challan', 'Cancel Current Process']);
                return;
            case 'erp_custom_challan_customer':
                $rows = $db->table('customers')->select('id, name')->where('is_active', 1)->where('deleted_at', null)->orderBy('name')->limit(8)->get()->getResultArray();
                $this->sendRows($session, 'customers', $rows, 'id', ['name'], 'Select customer for custom challan:', $sendOptions);
                return;
            case 'erp_custom_challan_date':
                $sendOptions('arattai_custom_challan_date', 'Enter challan date (DD/MM/YYYY) or choose Today:', ['Today', 'Cancel Current Process']);
                return;
            case 'erp_custom_challan_branch':
                $sendOptions('arattai_custom_challan_branch', 'Select dispatch branch:', ['Mumbai', 'Hyderabad', 'Cancel Current Process']);
                return;
            case 'erp_custom_challan_type':
                $sendOptions('arattai_custom_challan_type', 'Select challan item type:', ['Ornament', 'Loose Diamond', 'Loose Gold', 'Cancel Current Process']);
                return;
            case 'erp_custom_challan_description':
                $sendText('arattai_custom_challan_description', 'Enter item description.');
                return;
            case 'erp_custom_challan_purity':
                $sendOptions('arattai_custom_challan_purity', 'Select ornament purity:', ['14 KT', '18 KT', '22 KT', 'Cancel Current Process']);
                return;
            case 'erp_custom_challan_pcs':
                $sendText('arattai_custom_challan_pcs', 'Enter whole PCS greater than zero.');
                return;
            case 'erp_custom_challan_gross':
                $sendText('arattai_custom_challan_gross', 'Enter gross weight in grams.');
                return;
            case 'erp_custom_challan_net':
                $sendText('arattai_custom_challan_net', 'Enter net/gold weight in grams.');
                return;
            case 'erp_custom_challan_diamond':
                $sendText('arattai_custom_challan_diamond', 'Enter diamond weight in carats.');
                return;
            case 'erp_custom_challan_value':
                $sendText('arattai_custom_challan_value', 'Enter item value (0 allowed).');
                return;
            case 'erp_custom_challan_more':
                $sendOptions('arattai_custom_challan_more', $this->challanSummary($data), ['Add Another Item', 'Finish Items', 'Cancel Current Process']);
                return;
            case 'erp_custom_challan_gst':
                $sendOptions('arattai_custom_challan_gst', 'Select GST rate:', ['0%', '3%', '5%', '12%', '18%', 'Cancel Current Process']);
                return;
            case 'erp_custom_challan_notes':
                $sendOptions('arattai_custom_challan_notes', 'Enter challan notes, or Skip.', ['Skip Notes', 'Cancel Current Process']);
                return;
            case 'erp_custom_challan_confirm':
                $sendOptions('arattai_custom_challan_confirm', $this->challanSummary($data), ['Confirm Challan', 'Cancel Current Process']);
                return;

            case 'erp_packing_order':
            case 'erp_challan_order':
                $rows = $this->receivedDocumentOrders($actorId, false);
                $title = $step === 'erp_packing_order' ? 'Select received order for Packing List:' : 'Select received order for Delivery Challan:';
                $this->sendRows($session, 'orders', $rows, 'id', ['order_no', 'order_name'], $title, $sendOptions);
                return;
        }
    }

    /** @param array<string,mixed> $session @param list<array<string,mixed>> $rows @param list<string> $columns */
    private function sendRows(array $session, string $key, array $rows, string $idColumn, array $columns, string $message, callable $sendOptions): void
    {
        if ($rows === []) {
            throw new RuntimeException('No eligible records are available for this step.');
        }
        $labels = [];
        $map = [];
        foreach (array_slice($rows, 0, 8) as $row) {
            $parts = [];
            foreach ($columns as $column) {
                $value = trim((string) ($row[$column] ?? ''));
                if ($value !== '') $parts[] = $value;
            }
            $label = mb_substr(implode(' · ', array_unique($parts)), 0, 76);
            if ($label === '') $label = '#' . (string) ($row[$idColumn] ?? '');
            if (isset($map[$label])) {
                $label = mb_substr($label, 0, 65) . ' · #' . (string) ($row[$idColumn] ?? '');
            }
            $labels[] = $label;
            $map[$label] = $row;
        }
        $labels[] = 'Cancel Current Process';
        $data = $this->data($session);
        $data['choice_key'] = $key;
        $data['choice_map'] = $map;
        $this->save($session, (string) $session['current_step'], $data, (int) ($session['actor_admin_user_id'] ?? 0));
        $sendOptions('arattai_' . (string) $session['current_step'], $message, $labels);
    }

    /** @param array<string,mixed> $data @param list<string> $fallbackColumns @return array<string,mixed>|null */
    private function selected(string $text, array $data, string $expectedKey, string $idColumn, array $fallbackColumns): ?array
    {
        $map = is_array($data['choice_map'] ?? null) ? $data['choice_map'] : [];
        foreach ($map as $label => $row) {
            if (strcasecmp(trim((string) $label), trim($text)) === 0 && is_array($row)) return $row;
        }
        foreach ($map as $row) {
            if (! is_array($row)) continue;
            if (ctype_digit(trim($text)) && (int) ($row[$idColumn] ?? 0) === (int) trim($text)) return $row;
            foreach ($fallbackColumns as $column) {
                if (strcasecmp(trim((string) ($row[$column] ?? '')), trim($text)) === 0) return $row;
            }
        }
        return null;
    }

    /** @return list<array<string,mixed>> */
    private function returnBags(): array
    {
        $byBagIssue = [];
        foreach ((new DiamondBagTraceService())->returnableIssueLines() as $row) {
            $bagId = (int) ($row['bag_id'] ?? 0);
            $issueId = (int) ($row['issue_id'] ?? 0);
            $key = $bagId . ':' . $issueId;
            if ($bagId <= 0 || $issueId <= 0 || isset($byBagIssue[$key])) continue;
            $byBagIssue[$key] = [
                'bag_id' => $bagId,
                'bag_no' => (string) ($row['bag_no'] ?? ('Bag #' . $bagId)),
                'issue_id' => (int) ($row['issue_id'] ?? 0),
                'voucher_no' => (string) ($row['voucher_no'] ?? ''),
                'karigar_id' => (int) ($row['karigar_id'] ?? 0),
            ];
        }
        return array_slice(array_values($byBagIssue), 0, 8);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function createDiamondReturn(array $data, int $actorId): array
    {
        $lines = array_values((array) ($data['return_lines'] ?? []));
        if ($lines === []) throw new RuntimeException('Add at least one return item.');
        $available = [];
        foreach ((new DiamondBagTraceService())->returnableIssueLines((int) $data['issue_id']) as $row) {
            $available[(int) $row['issue_line_id']] = $row;
        }
        foreach ($lines as $line) {
            $current = $available[(int) $line['issue_line_id']] ?? null;
            if (! is_array($current)
                || (float) $line['pcs'] > (float) $current['available_pcs']
                || (float) $line['carat'] > (float) $current['available_cts'] + 0.0005) {
                throw new RuntimeException('One selected diamond line is no longer available in the entered quantity.');
            }
        }
        $db = db_connect();
        $voucher = $this->nextVoucher('return_headers', 'RET');
        try {
            $db->transException(true)->transStart();
            $returnId = (int) (new ReturnHeaderModel())->insert([
                'voucher_no' => $voucher,
                'return_date' => date('Y-m-d'),
                'order_id' => (int) ($lines[0]['allocation_order_id'] ?? 0) ?: null,
                'issue_id' => (int) $data['issue_id'],
                'karigar_id' => (int) $data['karigar_id'],
                'return_from' => null,
                'purpose' => 'Production return',
                'notes' => 'Created from Arattai conversation',
                'created_by' => $actorId,
            ], true);
            $model = new ReturnLineModel();
            foreach ($lines as $line) {
                $model->insert([
                    'return_id' => $returnId,
                    'item_id' => (int) $line['item_id'],
                    'issue_line_id' => (int) $line['issue_line_id'],
                    'bag_id' => (int) $line['bag_id'],
                    'bag_item_id' => (int) $line['bag_item_id'],
                    'allocation_order_id' => (int) $line['allocation_order_id'] ?: null,
                    'is_broken' => 0,
                    'pcs' => (int) $line['pcs'],
                    'carat' => (float) $line['carat'],
                    'rate_per_carat' => $line['rate_per_carat'],
                    'line_value' => $line['line_value'],
                ]);
            }
            (new DiamondStockService($db))->applyReturn($returnId);
            (new DiamondBagTraceService($db))->applyReturn($returnId);
            (new KarigarMaterialAccountingService($db))->postInventoryHeader('diamond', 'return', $returnId);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            throw $e;
        }
        try {
            (new MobileNotificationEventService())->notifyInventoryTransactionCreated('return', 'Diamond', 'return_headers', $returnId, 'arattai');
        } catch (Throwable $e) {
            log_message('error', 'Arattai diamond return notification failed: {message}', ['message' => $e->getMessage()]);
        }
        return ['id' => $returnId, 'voucher_no' => $voucher, 'order_id' => (int) ($lines[0]['allocation_order_id'] ?? 0)];
    }

    /** @param array<string,mixed> $group @param array<string,mixed> $size @return array<string,mixed> */
    private function resolveBagRow(array $group, array $size, int $pcs, float $cts): array
    {
        $db = db_connect();
        $shapeId = (int) ($size['shape_id'] ?? 0);
        $shape = $db->table('diamond_shape_masters')->select('id, name')->where('id', $shapeId)->where('is_active', 1)->get()->getRowArray();
        if (! is_array($shape)) throw new RuntimeException('Selected Chalni has no active diamond shape.');
        $item = $db->table('items i')->select('i.*, COALESCE(s.carat_balance,0) AS carat_balance', false)
            ->join('stock s', 's.item_id = i.id', 'left')
            ->where('LOWER(TRIM(i.shape)) = ' . $db->escape(mb_strtolower(trim((string) $shape['name']))), null, false)
            ->where("LOWER(TRIM(i.diamond_type)) <> 'broken'", null, false)
            ->where('COALESCE(s.carat_balance,0) >=', $cts, false)
            ->orderBy('s.carat_balance', 'DESC')->get()->getRowArray();
        if (! is_array($item)) {
            throw new RuntimeException('No matching diamond stock is available for this Chalni/shape and weight.');
        }
        return [
            'inventory_item_id' => (int) $item['id'],
            'shape_master_id' => $shapeId,
            'size_master_id' => (int) $size['id'],
            'chalni_group_id' => (int) $group['id'],
            'pcs' => $pcs,
            'weight_cts' => $cts,
            'display_name' => (string) $group['name'] . ' · ' . (string) $size['size_label'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function stoneRowsForReceiving(int $orderId, int $karigarId): array
    {
        if ($orderId <= 0 || $karigarId <= 0) return [];
        $rows = db_connect()->table('stone_inventory_issue_lines sil')
            ->select('sil.id, sil.pcs, sil.qty, sil.rate, sii.product_name, sii.stone_type')
            ->join('stone_inventory_issue_headers sih', 'sih.id = sil.issue_id', 'inner')
            ->join('stone_inventory_items sii', 'sii.id = sil.item_id', 'left')
            ->where('sih.order_id', $orderId)->where('sih.karigar_id', $karigarId)->get()->getResultArray();
        return array_values(array_map(static function (array $row): array {
            $name = trim((string) (($row['product_name'] ?? '') . ' ' . ($row['stone_type'] ?? ''))) ?: 'Issued Stone';
            $qty = round((float) ($row['qty'] ?? 0), 3);
            $rate = round((float) ($row['rate'] ?? 0), 2);
            return [
                'name' => $name,
                'pcs' => round((float) ($row['pcs'] ?? 0), 3),
                'weight_cts' => $qty,
                'weight_gm' => 0,
                'rate' => $rate,
                'line_total' => round($qty * $rate, 2),
            ];
        }, array_filter($rows, static fn(array $row): bool => (float) ($row['qty'] ?? 0) > 0)));
    }

    /** @return list<array<string,mixed>> */
    private function receivedDocumentOrders(int $actorId, bool $readyOnly): array
    {
        $db = db_connect();
        if ($readyOnly) {
            $builder = $db->table('orders o')->select('o.id, o.order_no, o.order_name, o.assigned_karigar_id, o.created_by, o.followup_assigned_to')
                ->where('o.deleted_at', null)->where('o.status', 'Ready')
                ->where("NOT EXISTS (SELECT 1 FROM order_receive_summaries rs WHERE rs.order_id = o.id)", null, false);
            if (! (new RbacService())->userCan($actorId, 'orders.assign')) {
                $builder->groupStart()->where('o.created_by', $actorId)->orWhere('o.followup_assigned_to', $actorId)->groupEnd();
            }
            return $builder->orderBy('o.id', 'DESC')->limit(8)->get()->getResultArray();
        }
        $canManage = (new RbacService())->userCan($actorId, 'orders.assign');
        return array_slice((new BatchPackingListService($db))->eligibleOrders($actorId, $canManage), 0, 8);
    }

    /** @param array<string,mixed>|null $image @return array{0:?string,1:?string} */
    private function publishImage(mixed $image, string $subdirectory): array
    {
        if (! is_array($image)) return [null, null];
        $source = (string) ($image['temp_path'] ?? '');
        if ($source === '' || ! is_file($source) || ! str_starts_with($source, WRITEPATH . 'uploads/arattai-order-drafts')) return [null, null];
        $directory = FCPATH . 'uploads/' . trim($subdirectory, '/');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create transaction image storage.');
        }
        $extension = preg_replace('/[^a-z0-9]/i', '', (string) ($image['extension'] ?? 'jpg')) ?: 'jpg';
        $name = 'arattai_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
        if (! copy($source, $directory . DIRECTORY_SEPARATOR . $name)) {
            throw new RuntimeException('Could not save the Arattai transaction image.');
        }
        return [$name, 'uploads/' . trim($subdirectory, '/') . '/' . $name];
    }

    private function removePublishedImage(?string $relativePath): void
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '' || ! str_starts_with($relativePath, 'uploads/')) return;
        $absolute = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($absolute)) @unlink($absolute);
    }

    private function nextVoucher(string $table, string $prefix): string
    {
        $base = $prefix . '-' . date('Ymd') . '-';
        $row = db_connect()->table($table)->select('voucher_no')->like('voucher_no', $base, 'after')->orderBy('id', 'DESC')->get()->getRowArray();
        $last = (string) ($row['voucher_no'] ?? '');
        $sequence = preg_match('/(\d+)$/', $last, $match) ? (int) $match[1] + 1 : 1;
        return $base . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $data */
    private function advance(array $session, string $next, array $data, int $actorId, callable $sendText, callable $sendOptions): array
    {
        unset($data['choice_key'], $data['choice_map']);
        $this->save($session, $next, $data, $actorId);
        $session['current_step'] = $next;
        $session['draft_data'] = $this->encode($data);
        $this->prompt($session, $sendText, $sendOptions);
        return ['handled' => true, 'status' => 'advanced', 'message' => 'Moved to ' . $next . '.'];
    }

    /** @param array<string,mixed> $data */
    private function followupSummary(array $data): string
    {
        return "*Confirm Follow-up*\nOrder: " . ($data['order_no'] ?? '-')
            . "\nStage: " . ($data['stage'] ?? '-')
            . "\nNote: " . ($data['description'] ?? '-')
            . "\nNext: " . (($data['next_followup_date'] ?? '') ?: 'Not required')
            . "\nImage: " . (is_array($data['image'] ?? null) ? 'Attached' : 'Not attached (optional)');
    }

    /** @param array<string,mixed> $data */
    private function issueSummary(array $data): string
    {
        $gold = array_sum(array_map(static fn(array $r): float => (float) ($r['weight_gm'] ?? 0), (array) ($data['gold_lines'] ?? [])));
        $diamond = array_sum(array_map(static fn(array $r): float => (float) ($r['carat'] ?? 0), (array) ($data['diamond_lines'] ?? [])));
        $stone = array_sum(array_map(static fn(array $r): float => (float) ($r['qty'] ?? 0), (array) ($data['stone_lines'] ?? [])));
        return "*Issuement Draft*\nKarigar: " . ($data['karigar_name'] ?? '-')
            . "\nLocation: " . ($data['location_name'] ?? '-')
            . "\nGold: " . number_format($gold, 3) . ' gm'
            . "\nDiamond: " . number_format($diamond, 3) . ' cts'
            . "\nStone: " . number_format($stone, 3);
    }

    /** @param array<string,mixed> $data */
    private function returnSummary(array $data): string
    {
        $pcs = array_sum(array_map(static fn(array $r): float => (float) ($r['pcs'] ?? 0), (array) ($data['return_lines'] ?? [])));
        $cts = array_sum(array_map(static fn(array $r): float => (float) ($r['carat'] ?? 0), (array) ($data['return_lines'] ?? [])));
        return "*Diamond Return Draft*\nBag: " . ($data['bag_no'] ?? '-')
            . "\nItems: " . count((array) ($data['return_lines'] ?? []))
            . "\nPCS: " . number_format($pcs, 0)
            . "\nWeight: " . number_format($cts, 3) . ' CTS';
    }

    /** @param array<string,mixed> $data */
    private function bagSummary(array $data): string
    {
        $pcs = array_sum(array_map(static fn(array $r): float => (float) ($r['pcs'] ?? 0), (array) ($data['bag_rows'] ?? [])));
        $cts = array_sum(array_map(static fn(array $r): float => (float) ($r['weight_cts'] ?? 0), (array) ($data['bag_rows'] ?? [])));
        return "*Diamond Bag Draft*\nOrder: " . ($data['order_no'] ?? '-')
            . "\nRows: " . count((array) ($data['bag_rows'] ?? []))
            . "\nPCS: " . number_format($pcs, 0)
            . "\nWeight: " . number_format($cts, 3) . ' CTS';
    }

    /** @param array<string,mixed> $data */
    private function receivingSummary(array $data): string
    {
        $warnings = (array) ($data['warnings'] ?? []);
        return "*Confirm Receiving*\nOrder: " . ($data['order_no'] ?? '-')
            . "\nGross: " . number_format((float) ($data['gross_weight_gm'] ?? 0), 3) . ' gm'
            . "\nPurity: " . ($data['purity_label'] ?? '-')
            . "\nGold rate: " . number_format((float) ($data['gold_rate_per_gm'] ?? 0), 2)
            . "\nLabour rate: " . number_format((float) ($data['labour_rate_per_gm'] ?? 0), 2)
            . "\nWastage: " . ucfirst((string) ($data['wastage_type'] ?? 'normal')) . ' ' . number_format((float) ($data['wastage_percent'] ?? 0), 3) . '%'
            . "\nOther rows: " . count((array) ($data['others'] ?? []))
            . ($warnings !== [] ? "\n" . implode("\n", $warnings) : '');
    }

    /** @param array<string,mixed> $data */
    private function purchaseSummary(array $data): string
    {
        return "*Purchase Draft*\nType: " . ($data['purchase_type'] ?? '-')
            . "\nVendor: " . ($data['vendor_name'] ?? '-')
            . "\nInvoice: " . ($data['invoice_no'] ?? '-')
            . "\nLocation: " . ($data['location_name'] ?? '-')
            . "\nTaxable: " . number_format((float) ($data['taxable_amount'] ?? 0), 2)
            . "\nGST: " . ($data['gst_label'] ?? '-')
            . "\nItems: " . count((array) ($data['items'] ?? []));
    }

    /** @param array<string,mixed> $data */
    private function challanSummary(array $data): string
    {
        $value = array_sum(array_map(static fn(array $row): float => (float) ($row['value'] ?? 0), (array) ($data['items'] ?? [])));
        return "*Custom Delivery Challan*\nCustomer: " . ($data['customer_name'] ?? '-')
            . "\nDate: " . ($data['challan_date'] ?? '-')
            . "\nDispatch: " . ($data['dispatch_from'] ?? '-')
            . "\nItems: " . count((array) ($data['items'] ?? []))
            . "\nValue: " . number_format($value, 2)
            . "\nGST: " . number_format((float) ($data['tax_percent'] ?? 0), 2) . '%';
    }

    /** @param list<string> $labels */
    private function matchLabel(string $text, array $labels): string
    {
        foreach ($labels as $label) if (strcasecmp(trim($label), trim($text)) === 0) return $label;
        return '';
    }

    /** @param array<string,mixed> $item */
    private function goldLabel(array $item): string
    {
        return trim(implode(' · ', array_filter([
            (string) ($item['purity_code'] ?? ''), (string) ($item['color_name'] ?? ''), (string) ($item['form_type'] ?? ''),
        ]))) ?: 'Gold item #' . (int) ($item['id'] ?? 0);
    }

    private function terminalStage(string $stage): bool
    {
        return in_array($stage, ['Ready', 'Packed', 'Dispatched', 'Completed', 'Complete', 'Cancelled'], true);
    }

    private function positive(string $value): ?float
    {
        $number = is_numeric(trim($value)) ? round((float) trim($value), 3) : 0;
        return $number > 0 ? $number : null;
    }

    private function nonNegative(string $value): ?float
    {
        if (! is_numeric(trim($value))) return null;
        $number = round((float) trim($value), 3);
        return $number >= 0 ? $number : null;
    }

    private function dateTime(string $value): ?string
    {
        $value = trim($value);
        foreach (['d/m/Y h:i A', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date instanceof \DateTimeImmutable) return $date->format('Y-m-d H:i:s');
        }
        $time = strtotime($value);
        return $time === false ? null : date('Y-m-d H:i:s', $time);
    }

    private function dateOnly(string $value): ?string
    {
        $value = trim($value);
        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date instanceof \DateTimeImmutable && $date->format($format) === $value) return $date->format('Y-m-d');
        }
        return null;
    }

    /** @param array<string,mixed> $data */
    private function previous(string $step, array $data): string
    {
        return match ($step) {
            'erp_followup_stage' => 'erp_followup_order',
            'erp_followup_description' => 'erp_followup_stage',
            'erp_followup_date' => 'erp_followup_description',
            'erp_followup_image' => 'erp_followup_date',
            'erp_followup_confirm' => 'erp_followup_image',
            'erp_issue_location' => 'erp_issue_karigar',
            'erp_issue_material' => 'erp_issue_location',
            'erp_issue_confirm' => 'erp_issue_image',
            'erp_bag_group' => 'erp_bag_order',
            default => $step,
        };
    }

    /** @return array<string,mixed> */
    private function defaultInventoryLocation(): array
    {
        $location = db_connect()->table('inventory_locations')->select('id, name, code')
            ->where('is_active', 1)
            ->orderBy("CASE WHEN LOWER(TRIM(name)) = 'main production store' THEN 0 ELSE 1 END", 'ASC', false)
            ->orderBy('id', 'ASC')
            ->get()->getRowArray();
        if (! is_array($location)) {
            throw new RuntimeException('No active inventory location is configured for bag creation.');
        }
        return $location;
    }

    /** @return array<string,mixed> */
    private function receivingLocation(int $orderId, int $karigarId): array
    {
        $db = db_connect();
        $location = $db->table('issue_headers ih')->select('l.id, l.name, l.code')
            ->join('issue_lines il', 'il.issue_id = ih.id', 'inner')
            ->join('inventory_locations l', 'l.id = ih.location_id AND l.is_active = 1', 'inner')
            ->where('il.allocation_order_id', $orderId)->where('ih.karigar_id', $karigarId)
            ->orderBy('ih.id', 'DESC')->get()->getRowArray();
        if (! is_array($location)) {
            $location = $db->table('stone_inventory_issue_headers ih')->select('l.id, l.name, l.code')
                ->join('inventory_locations l', 'l.id = ih.location_id AND l.is_active = 1', 'inner')
                ->where('ih.order_id', $orderId)->where('ih.karigar_id', $karigarId)
                ->orderBy('ih.id', 'DESC')->get()->getRowArray();
        }
        return is_array($location) ? $location : $this->defaultInventoryLocation();
    }

    private function ready(): bool
    {
        return db_connect()->tableExists('arattai_order_conversations');
    }

    /** @return array<string,mixed>|null */
    private function session(string $userId): ?array
    {
        $row = $this->model->where('arattai_user_id', $userId)->first();
        if (! is_array($row)) return null;
        $expires = strtotime((string) ($row['expires_at'] ?? '')) ?: 0;
        if ((string) ($row['status'] ?? '') === 'active' && $expires > 0 && $expires < time()) {
            $this->finish($row, 'expired');
            return $this->model->find((int) $row['id']);
        }
        return $row;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function start(string $userId, int $actorId, string $step, array $data): array
    {
        $existing = $this->model->where('arattai_user_id', $userId)->first();
        $values = [
            'arattai_user_id' => $userId,
            'actor_admin_user_id' => $actorId,
            'current_step' => $step,
            'draft_data' => $this->encode($data),
            'status' => 'active',
            'order_id' => null,
            'expires_at' => date('Y-m-d H:i:s', time() + 7200),
        ];
        if (is_array($existing)) {
            $this->model->update((int) $existing['id'], $values);
            return $this->model->find((int) $existing['id']) ?? ($existing + $values);
        }
        $id = (int) $this->model->insert($values, true);
        return $this->model->find($id) ?? ($values + ['id' => $id]);
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $data */
    private function save(array $session, string $step, array $data, int $actorId): void
    {
        $this->model->update((int) $session['id'], [
            'actor_admin_user_id' => $actorId,
            'current_step' => $step,
            'draft_data' => $this->encode($data),
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', time() + 7200),
        ]);
    }

    /** @param array<string,mixed> $session */
    private function claim(array $session): bool
    {
        $updated = db_connect()->table('arattai_order_conversations')->where('id', (int) $session['id'])
            ->where('status', 'active')->update(['status' => 'processing', 'updated_at' => date('Y-m-d H:i:s')]);
        return $updated && db_connect()->affectedRows() === 1;
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $data */
    private function release(array $session, array $data): void
    {
        $this->model->update((int) $session['id'], [
            'status' => 'active', 'draft_data' => $this->encode($data),
            'expires_at' => date('Y-m-d H:i:s', time() + 7200),
        ]);
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $data */
    private function complete(array $session, array $data, ?int $orderId): void
    {
        $this->model->update((int) $session['id'], [
            'status' => 'completed', 'draft_data' => $this->encode($data),
            'order_id' => ($orderId ?? 0) > 0 ? $orderId : null,
            'expires_at' => null,
        ]);
    }

    /** @param array<string,mixed> $session */
    private function finish(array $session, string $status): void
    {
        $this->model->update((int) $session['id'], ['status' => $status, 'expires_at' => null]);
    }

    /** @param array<string,mixed>|null $session @return array<string,mixed> */
    private function data(?array $session): array
    {
        if (! is_array($session)) return [];
        $decoded = json_decode((string) ($session['draft_data'] ?? '{}'), true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $data */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @param array<string,mixed> $data */
    private function cleanupImages(array $data): void
    {
        foreach (['image'] as $key) {
            $path = is_array($data[$key] ?? null) ? (string) ($data[$key]['temp_path'] ?? '') : '';
            if ($path !== '' && is_file($path) && str_starts_with($path, WRITEPATH . 'uploads/arattai-order-drafts')) @unlink($path);
        }
        if (is_array($data['purchase_bill'] ?? null)) {
            (new ArattaiPurchaseService())->discardDraftBill($data['purchase_bill']);
        }
    }
}
