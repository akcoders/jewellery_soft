<?php

namespace App\Services;

use App\Models\ArattaiOrderConversationModel;
use RuntimeException;
use Throwable;

class ArattaiOrderConversationService
{
    private const OPTIONAL_STEPS = [
        'size_label',
        'gold_required_gm',
        'diamond_required_cts',
        'item_description',
        'additional_details',
        'gold_rate_block_status',
        'approximate_price',
        'advance_amount',
        'due_date',
    ];

    public function __construct(private ?ArattaiOrderConversationModel $model = null)
    {
        $this->model ??= new ArattaiOrderConversationModel();
    }

    /**
     * @param array<string,mixed> $payload
     * @param callable(string,string):void $sendText
     * @param callable(string,string,list<string>):void $sendOptions
     * @return array{handled:bool,status:string,message:string,order_id?:int}
     */
    public function handle(
        array $payload,
        string $userId,
        string $text,
        int $actorAdminUserId,
        callable $sendText,
        callable $sendOptions
    ): array {
        if (! $this->ready()) {
            return ['handled' => false, 'status' => 'unavailable', 'message' => 'Arattai order conversation migration is pending.'];
        }

        $text = trim($text);
        $command = mb_strtolower($text);
        $session = $this->session($userId);

        if (in_array($command, ['hi', 'hello', 'hey', 'start', 'menu'], true)) {
            $this->sendMenu($userId, $sendOptions);
            return ['handled' => true, 'status' => 'menu', 'message' => 'Arattai menu sent.'];
        }
        if ($command === 'order status') {
            $data = $this->data($session);
            if (is_array($session) && (string) ($session['status'] ?? '') === 'active') {
                $data['resume_step'] = (string) ($session['current_step'] ?? 'order_from');
                $this->saveSession($session, 'order_status', $data, $actorAdminUserId);
            } else {
                $session = $this->startSession($userId, $actorAdminUserId, 'order_status', []);
            }
            $sendText('order_status_prompt', 'Please send your Order ID or Order Number to check its current status.');
            return ['handled' => true, 'status' => 'prompted', 'message' => 'Asked the user for an order ID.'];
        }
        if ($command !== 'create order' && is_array($session) && (string) ($session['current_step'] ?? '') === 'order_status') {
            $data = $this->data($session);
            $resume = trim((string) ($data['resume_step'] ?? ''));
            unset($data['resume_step']);
            if ($resume !== '') {
                $this->saveSession($session, $resume, $data, $actorAdminUserId);
            } else {
                $this->finishSession($session, 'cancelled');
            }
            return ['handled' => false, 'status' => 'order_status_query', 'message' => 'Forward the message to order status lookup.'];
        }
        if ($command === 'create order') {
            if (is_array($session) && (string) ($session['status'] ?? '') === 'active'
                && (string) ($session['current_step'] ?? '') === 'order_status') {
                $data = $this->data($session);
                $resume = trim((string) ($data['resume_step'] ?? '')) ?: 'order_from';
                unset($data['resume_step']);
                $this->saveSession($session, $resume, $data, $actorAdminUserId);
                $session['current_step'] = $resume;
                $session['draft_data'] = $this->encode($data);
            } elseif (! is_array($session) || (string) ($session['status'] ?? '') !== 'active') {
                $session = $this->startSession($userId, $actorAdminUserId, 'order_from', []);
            }
            $this->prompt($session, $sendText, $sendOptions);
            return ['handled' => true, 'status' => 'order_draft', 'message' => 'Order conversation started or resumed.'];
        }
        if ($command === 'confirm order' && is_array($session) && (string) ($session['status'] ?? '') === 'completed') {
            $orderId = (int) ($session['order_id'] ?? 0);
            $order = $orderId > 0
                ? db_connect()->table('orders')->select('order_no')->where('id', $orderId)->get()->getRowArray()
                : null;
            $sendText(
                'arattai_order_already_created',
                'Action already taken: This order was already created as '
                    . (string) (($order['order_no'] ?? '') ?: ('#' . $orderId)) . '.'
            );
            return ['handled' => true, 'status' => 'already_created', 'message' => 'Order confirmation was already processed.', 'order_id' => $orderId];
        }
        if ($command === 'confirm order' && is_array($session) && (string) ($session['status'] ?? '') === 'approval_pending') {
            $sendText('arattai_order_approval_pending', 'Action already taken: This order is already waiting for admin approval.');
            return ['handled' => true, 'status' => 'approval_pending', 'message' => 'Order approval was already submitted.'];
        }
        if (is_array($session) && (string) ($session['status'] ?? '') === 'processing') {
            $sendText('arattai_order_processing', 'Order creation is already being processed. Please wait for the confirmation message.');
            return ['handled' => true, 'status' => 'processing', 'message' => 'Order creation is already in progress.'];
        }
        if (! is_array($session) || (string) ($session['status'] ?? '') !== 'active') {
            return ['handled' => false, 'status' => 'no_order_draft', 'message' => 'No active Arattai order draft.'];
        }

        if (in_array($command, ['cancel', 'cancel order'], true)) {
            $this->cleanupDraftImages($this->data($session));
            $this->finishSession($session, 'cancelled');
            $sendText('arattai_order_cancelled', 'Order creation cancelled. Send Hi to start again.');
            return ['handled' => true, 'status' => 'cancelled', 'message' => 'Order draft cancelled.'];
        }
        if ($command === 'back') {
            $step = $this->previousStep((string) ($session['current_step'] ?? ''), $this->data($session));
            $this->saveSession($session, $step, $this->data($session), $actorAdminUserId);
            $session['current_step'] = $step;
            $this->prompt($session, $sendText, $sendOptions);
            return ['handled' => true, 'status' => 'back', 'message' => 'Moved to the previous order field.'];
        }
        if ($command === 'show summary' || $command === 'view summary') {
            $sendText('arattai_order_summary', $this->summary($this->data($session)));
            $this->prompt($session, $sendText, $sendOptions);
            return ['handled' => true, 'status' => 'summary', 'message' => 'Order draft summary sent.'];
        }

        return $this->acceptStep($payload, $session, $text, $actorAdminUserId, $sendText, $sendOptions);
    }

    /** @param callable(string,string,list<string>):void $sendOptions */
    private function sendMenu(string $userId, callable $sendOptions): void
    {
        $sendOptions('arattai_main_menu', "Welcome to Aabhushan ERP.\nWhat would you like to do?", [
            'Create Order',
            'Order Status',
            'Follow-up',
            'Issuement',
            'Return',
            'Bag Creation',
            'Purchase',
            'Receiving',
            'Packing List',
            'Delivery Challan',
        ]);
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $session
     * @param callable(string,string):void $sendText
     * @param callable(string,string,list<string>):void $sendOptions
     * @return array{handled:bool,status:string,message:string,order_id?:int}
     */
    private function acceptStep(
        array $payload,
        array $session,
        string $text,
        int $actorAdminUserId,
        callable $sendText,
        callable $sendOptions
    ): array {
        $step = (string) ($session['current_step'] ?? 'order_from');
        $data = $this->data($session);
        $normalized = mb_strtolower(trim($text));

        if ($normalized === 'skip') {
            if (! in_array($step, self::OPTIONAL_STEPS, true)) {
                $sendText('arattai_order_validation', 'This field is required and cannot be skipped.');
                $this->prompt($session, $sendText, $sendOptions);
                return ['handled' => true, 'status' => 'validation_failed', 'message' => 'Required order field was skipped.'];
            }
            $data[$step] = $step === 'gold_rate_block_status' ? 'Not Fixed' : null;
            $next = $this->nextStep($step, $data);
            return $this->advance($session, $next, $data, $actorAdminUserId, $sendText, $sendOptions);
        }

        try {
            switch ($step) {
                case 'order_from':
                    if ($normalized === 'more customers') {
                        $data['customer_page'] = ((int) ($data['customer_page'] ?? 0)) + 1;
                        $this->saveSession($session, $step, $data, $actorAdminUserId);
                        $session['draft_data'] = $this->encode($data);
                        $this->prompt($session, $sendText, $sendOptions);
                        return ['handled' => true, 'status' => 'customer_page', 'message' => 'Displayed more customers.'];
                    }
                    $customer = $this->selectedRow('customers', $text, $data, ['id', 'name'], 'name');
                    if (! is_array($customer)) {
                        throw new RuntimeException('Please select a customer shown under Order From.');
                    }
                    $data['customer_id'] = (int) $customer['id'];
                    $data['order_from_name'] = (string) $customer['name'];
                    unset($data['customer_page'], $data['choice_map']);
                    break;

                case 'order_received_date':
                case 'due_date':
                    $date = $this->dateValue($text);
                    if ($date === null) {
                        throw new RuntimeException('Enter date as DD/MM/YYYY, for example 25/10/2026.');
                    }
                    $data[$step] = $date;
                    break;

                case 'end_customer_name':
                case 'sales_executive_name':
                    if (trim($text) === '' || mb_strlen(trim($text)) > 180) {
                        throw new RuntimeException('Enter a valid name up to 180 characters.');
                    }
                    $data[$step] = trim($text);
                    break;

                case 'contact_number':
                    $mobile = preg_replace('/\D+/', '', $text) ?? '';
                    if (strlen($mobile) < 10 || strlen($mobile) > 15) {
                        throw new RuntimeException('Enter a valid 10 to 15 digit mobile number.');
                    }
                    $data[$step] = $mobile;
                    break;

                case 'material_category':
                    $category = ucfirst($normalized);
                    if (! in_array($category, ['Gold', 'Diamond', 'Jadau', 'Silver'], true)) {
                        throw new RuntimeException('Select Gold, Diamond, Jadau or Silver.');
                    }
                    $data[$step] = $category;
                    break;

                case 'order_category_id':
                    if ($normalized === 'more sub categories' || $normalized === 'more categories') {
                        $data['subcategory_page'] = ((int) ($data['subcategory_page'] ?? 0)) + 1;
                        $this->saveSession($session, $step, $data, $actorAdminUserId);
                        $session['draft_data'] = $this->encode($data);
                        $this->prompt($session, $sendText, $sendOptions);
                        return ['handled' => true, 'status' => 'subcategory_page', 'message' => 'Displayed more sub categories.'];
                    }
                    $category = $this->selectedRow('order_categories', $text, $data, ['id', 'name', 'code'], 'name');
                    if (! is_array($category)) {
                        throw new RuntimeException('Please select a sub category shown in the list.');
                    }
                    $data['order_category_id'] = (int) $category['id'];
                    $data['order_category_name'] = (string) $category['name'];
                    unset($data['subcategory_page'], $data['choice_map']);
                    break;

                case 'qty':
                    if (! ctype_digit(trim($text)) || (int) $text < 1 || (int) $text > 9999) {
                        throw new RuntimeException('Enter a valid number of pieces from 1 to 9999.');
                    }
                    $data[$step] = (int) $text;
                    break;

                case 'gold_required_gm':
                case 'diamond_required_cts':
                    $number = $this->positiveNumber($text);
                    if ($number === null) {
                        throw new RuntimeException('Enter a valid weight, or tap Skip.');
                    }
                    $data[$step] = $number;
                    break;

                case 'gold_rate_block_status':
                    if (! in_array($normalized, ['fixed', 'not fixed'], true)) {
                        throw new RuntimeException('Select Fixed, Not Fixed or Skip.');
                    }
                    $data[$step] = $normalized === 'fixed' ? 'Fixed' : 'Not Fixed';
                    break;

                case 'gold_rate_per_gm':
                case 'approximate_price':
                case 'advance_amount':
                    $amount = $this->positiveNumber($text, true);
                    if ($amount === null) {
                        throw new RuntimeException('Enter a valid amount, or tap Skip where available.');
                    }
                    $data[$step] = $amount;
                    if ($step === 'advance_amount' && isset($data['approximate_price'])
                        && (float) $data['approximate_price'] > 0 && $amount > (float) $data['approximate_price']) {
                        throw new RuntimeException('Advance amount cannot exceed the approximate price.');
                    }
                    break;

                case 'certificate_requirement':
                    $certificate = match ($normalized) {
                        'igi' => 'IGI',
                        'kalasha' => 'Kalasha',
                        'not required', 'none' => 'Not Required',
                        default => '',
                    };
                    if ($certificate === '') {
                        throw new RuntimeException('Select IGI, Kalasha or Not Required.');
                    }
                    $data[$step] = $certificate;
                    break;

                case 'reference_image':
                case 'signed_order_image':
                    $image = $this->storeIncomingImage($payload, $step);
                    $this->cleanupDraftImage(is_array($data[$step] ?? null) ? $data[$step] : []);
                    $data[$step] = $image;
                    break;

                case 'confirm':
                    if ($normalized === 'start again') {
                        $this->cleanupDraftImages($data);
                        $session = $this->startSession((string) $session['arattai_user_id'], $actorAdminUserId, 'order_from', []);
                        $this->prompt($session, $sendText, $sendOptions);
                        return ['handled' => true, 'status' => 'restarted', 'message' => 'Order draft restarted.'];
                    }
                    if ($normalized !== 'confirm order') {
                        throw new RuntimeException('Tap Confirm Order, Start Again or Cancel Order.');
                    }
                    if (! $this->claimConfirmation($session)) {
                        $sendText('arattai_order_processing', 'Order creation is already being processed. Please wait for the confirmation message.');
                        return ['handled' => true, 'status' => 'processing', 'message' => 'Order creation is already in progress.'];
                    }
                    $creation = new ArattaiOrderCreationService();
                    $prepared = null;
                    try {
                        $prepared = $creation->prepareForApproval($data);
                        $prepared['draft']['_arattai_user_id'] = (string) ($session['arattai_user_id'] ?? '');
                        $approval = (new MobileApprovalService())->submit(
                            'order_create',
                            (array) $prepared['draft'],
                            $actorAdminUserId,
                            'Create order for ' . trim((string) ($data['end_customer_name'] ?? 'customer')),
                            (string) $prepared['attachment_name'],
                            (string) $prepared['attachment_path']
                        );
                    } catch (Throwable $e) {
                        if (is_array($prepared['draft'] ?? null)) {
                            $creation->discardApprovalDraft((array) $prepared['draft']);
                        }
                        $this->saveSession($session, 'confirm', $data, $actorAdminUserId);
                        throw $e;
                    }
                    $this->cleanupDraftImages($data);
                    $this->finishSession($session, 'approval_pending');
                    $sendText('arattai_order_approval_submitted', "Order sent for admin approval successfully.\nRequest: #"
                        . (int) ($approval['id'] ?? 0)
                        . "\nCustomer: " . (string) ($data['end_customer_name'] ?? '-')
                        . "\nBoth required images have been saved with the approval request.");
                    return [
                        'handled' => true,
                        'status' => 'approval_pending',
                        'message' => 'Order creation submitted for approval.',
                    ];

                default:
                    if (! in_array($step, ['size_label', 'item_description', 'additional_details'], true)) {
                        throw new RuntimeException('This order step is not supported. Send Create Order to resume.');
                    }
                    if (mb_strlen(trim($text)) > 5000) {
                        throw new RuntimeException('This value is too long.');
                    }
                    $data[$step] = trim($text);
                    break;
            }
        } catch (RuntimeException $e) {
            $sendText('arattai_order_validation', $e->getMessage());
            $this->prompt($session, $sendText, $sendOptions);
            return ['handled' => true, 'status' => 'validation_failed', 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            log_message('error', 'Arattai order wizard failed: {message}', ['message' => $e->getMessage()]);
            $sendText('arattai_order_error', 'The order could not be processed. Your draft is saved; please try this step again.');
            return ['handled' => true, 'status' => 'failed', 'message' => $e->getMessage()];
        }

        $next = $this->nextStep($step, $data);
        return $this->advance($session, $next, $data, $actorAdminUserId, $sendText, $sendOptions);
    }

    /**
     * @param array<string,mixed> $session
     * @param array<string,mixed> $data
     * @param callable(string,string):void $sendText
     * @param callable(string,string,list<string>):void $sendOptions
     * @return array{handled:bool,status:string,message:string}
     */
    private function advance(array $session, string $next, array $data, int $actorId, callable $sendText, callable $sendOptions): array
    {
        $this->saveSession($session, $next, $data, $actorId);
        $session['current_step'] = $next;
        $session['draft_data'] = $this->encode($data);
        $this->prompt($session, $sendText, $sendOptions);
        return ['handled' => true, 'status' => 'step_saved', 'message' => 'Order field saved.'];
    }

    /**
     * @param array<string,mixed> $session
     * @param callable(string,string):void $sendText
     * @param callable(string,string,list<string>):void $sendOptions
     */
    private function prompt(array $session, callable $sendText, callable $sendOptions): void
    {
        $step = (string) ($session['current_step'] ?? 'order_from');
        $data = $this->data($session);
        $optional = in_array($step, self::OPTIONAL_STEPS, true);
        $hint = $optional ? "\nYou may tap Skip." : '';
        $footer = "\nCommands: Back, Show Summary, Cancel Order";

        switch ($step) {
            case 'order_from':
                $this->promptRows($session, $data, 'customers', 'customer_page', 'Select Order From customer:', 'More customers', $sendOptions);
                return;
            case 'order_received_date':
                $sendOptions('arattai_order_date', 'Enter Order Received Date (DD/MM/YYYY):', ['Today', 'Cancel Order']);
                return;
            case 'end_customer_name':
                $sendText('arattai_order_customer_name', 'Enter Customer Name:' . $footer);
                return;
            case 'contact_number':
                $sendText('arattai_order_mobile', 'Enter Customer Mobile Number:' . $footer);
                return;
            case 'sales_executive_name':
                $sendText('arattai_order_sales_executive', 'Enter Sales Executive Name:' . $footer);
                return;
            case 'material_category':
                $sendOptions('arattai_order_material', 'Select Category:', ['Gold', 'Diamond', 'Jadau', 'Silver', 'Cancel Order']);
                return;
            case 'order_category_id':
                $this->promptRows($session, $data, 'order_categories', 'subcategory_page', 'Select Sub Category:', 'More sub categories', $sendOptions);
                return;
            case 'qty':
                $sendText('arattai_order_qty', 'Enter Number of Pieces:' . $footer);
                return;
            case 'size_label':
                $sendOptions('arattai_order_size', 'Enter Size / Length:' . $hint, ['Skip', 'Cancel Order']);
                return;
            case 'gold_required_gm':
                $sendOptions('arattai_order_gold_weight', 'Enter Gold Weight in grams:' . $hint, ['Skip', 'Cancel Order']);
                return;
            case 'diamond_required_cts':
                $sendOptions('arattai_order_diamond_weight', 'Enter Diamond Weight in carats:' . $hint, ['Skip', 'Cancel Order']);
                return;
            case 'certificate_requirement':
                $sendOptions('arattai_order_certificate', 'Select Certificate Requirement:', ['IGI', 'Kalasha', 'Not Required', 'Cancel Order']);
                return;
            case 'item_description':
                $sendOptions('arattai_order_description', 'Enter Description of Item:' . $hint, ['Skip', 'Cancel Order']);
                return;
            case 'additional_details':
                $sendOptions('arattai_order_details', 'Enter Additional Details:' . $hint, ['Skip', 'Cancel Order']);
                return;
            case 'gold_rate_block_status':
                $sendOptions('arattai_order_gold_rate_status', 'Gold Rate Block:', ['Fixed', 'Not Fixed', 'Skip', 'Cancel Order']);
                return;
            case 'gold_rate_per_gm':
                $sendText('arattai_order_gold_rate', 'Enter fixed Gold Rate per gram:' . $footer);
                return;
            case 'approximate_price':
                $sendOptions('arattai_order_price', 'Enter Approximate Price:' . $hint, ['Skip', 'Cancel Order']);
                return;
            case 'advance_amount':
                $sendOptions('arattai_order_advance', 'Enter Advance Amount:' . $hint, ['Skip', 'Cancel Order']);
                return;
            case 'due_date':
                $sendOptions('arattai_order_delivery', 'Enter Approx. Client Delivery Date (DD/MM/YYYY):' . $hint, ['Skip', 'Cancel Order']);
                return;
            case 'reference_image':
                $sendText('arattai_order_reference_image', 'Upload Order Reference Image. This image is required; order cannot be created without it.');
                return;
            case 'signed_order_image':
                $sendText('arattai_order_signed_image', 'Upload Signed Order Image. This image is required; order cannot be created without it.');
                return;
            case 'confirm':
                $sendOptions('arattai_order_confirm', $this->summary($data), ['Confirm Order', 'Start Again', 'Cancel Order']);
                return;
            default:
                $sendText('arattai_order_error', 'Unknown order step. Send Create Order to resume.');
        }
    }

    /**
     * @param array<string,mixed> $session
     * @param array<string,mixed> $data
     * @param callable(string,string,list<string>):void $sendOptions
     */
    private function promptRows(
        array $session,
        array $data,
        string $table,
        string $pageKey,
        string $message,
        string $moreLabel,
        callable $sendOptions
    ): void {
        $db = db_connect();
        $page = max(0, (int) ($data[$pageKey] ?? 0));
        $total = $db->table($table)->where('is_active', 1)->countAllResults();
        $pages = max(1, (int) ceil($total / 8));
        if ($page >= $pages) {
            $page = 0;
        }
        $rows = $db->table($table)->select('id, name')->where('is_active', 1)
            ->orderBy('name', 'ASC')->limit(8, $page * 8)->get()->getResultArray();
        if ($rows === []) {
            $sendOptions('arattai_order_options', 'No active options are configured. Contact ERP admin.', ['Cancel Order']);
            return;
        }
        $labels = [];
        $map = [];
        foreach ($rows as $row) {
            $label = mb_substr(trim((string) ($row['name'] ?? '')), 0, 80);
            if ($label === '') {
                continue;
            }
            $labels[] = $label;
            $map[mb_strtolower($label)] = (int) $row['id'];
        }
        if ($total > 8) {
            $labels[] = $moreLabel;
        }
        $labels[] = 'Cancel Order';
        $data[$pageKey] = $page;
        $data['choice_map'] = $map;
        $this->saveSession($session, (string) $session['current_step'], $data, (int) ($session['actor_admin_user_id'] ?? 0));
        $sendOptions('arattai_order_options', $message . ($total > 8 ? ' (page ' . ($page + 1) . '/' . $pages . ')' : ''), $labels);
    }

    /** @param array<string,mixed> $data @param list<string> $columns @return array<string,mixed>|null */
    private function selectedRow(string $table, string $text, array $data, array $columns, string $nameColumn): ?array
    {
        $selectedId = (int) (($data['choice_map'][mb_strtolower(trim($text))] ?? 0));
        $builder = db_connect()->table($table)->select(implode(', ', $columns))->where('is_active', 1);
        if ($selectedId > 0) {
            $row = $builder->where('id', $selectedId)->get()->getRowArray();
            return is_array($row) ? $row : null;
        }
        $row = $builder->where('LOWER(' . $nameColumn . ')', mb_strtolower(trim($text)))->get()->getRowArray();
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $payload @return array<string,string> */
    public function storeIncomingImage(array $payload, string $step): array
    {
        $entity = is_array($payload['entity'] ?? null) ? $payload['entity'] : [];
        $type = mb_strtolower(trim((string) ($entity['type'] ?? $payload['type'] ?? '')));
        $content = $entity['content'] ?? ($payload['content'] ?? null);
        $contentType = mb_strtolower($this->nestedScalar($content, [
            'type', 'mime_type', 'mimeType', 'content_type', 'contentType',
        ]));
        // Arattai sends customer images as entity.type="file" and places the
        // real media type (for example image/jpeg) in entity.content.type.
        $isImage = in_array($type, ['image', 'photo', 'media'], true)
            || ($type === 'file' && str_starts_with($contentType, 'image/'));
        if (! $isImage) {
            throw new RuntimeException('Please upload an image for this step.');
        }

        $base64 = $this->nestedScalar($content, ['base64', 'data', 'file_base64']);
        $url = $this->nestedScalar($content, ['link', 'url', 'download_url', 'downloadUrl', 'media_url', 'mediaUrl']);
        if (is_scalar($content) && filter_var(trim((string) $content), FILTER_VALIDATE_URL)) {
            $url = trim((string) $content);
        }
        $originalName = $this->nestedScalar($content, ['file_name', 'fileName', 'name', 'filename']);
        $mime = $contentType;
        if ($url === '') {
            $url = $this->nestedScalar($entity, ['link', 'url', 'download_url', 'downloadUrl', 'media_url', 'mediaUrl']);
        }
        if ($originalName === '') {
            $originalName = $this->nestedScalar($entity, ['file_name', 'fileName', 'name', 'filename']);
        }
        if ($mime === '') {
            $mime = $this->nestedScalar($entity, ['type', 'mime_type', 'mimeType', 'content_type', 'contentType']);
        }

        $binary = '';
        if ($base64 !== '' && strlen($base64) > 100) {
            if (str_contains($base64, ',')) {
                $base64 = substr($base64, strpos($base64, ',') + 1);
            }
            $decoded = base64_decode(str_replace(' ', '+', $base64), true);
            $binary = $decoded === false ? '' : $decoded;
        } elseif ($url !== '') {
            $binary = $this->downloadImage($url, $mime);
        }
        if ($binary === '' || strlen($binary) > 10 * 1024 * 1024) {
            throw new RuntimeException('The image could not be downloaded or is larger than 10 MB. Please upload it again.');
        }

        $extension = $this->imageExtension($mime, $url, $originalName, $binary);
        $directory = WRITEPATH . 'uploads/arattai-order-drafts';
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create temporary image storage.');
        }
        $name = $step . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (file_put_contents($path, $binary) === false) {
            throw new RuntimeException('Could not save the uploaded image.');
        }
        return [
            'temp_path' => $path,
            'extension' => $extension,
            'original_name' => $originalName !== '' ? mb_substr($originalName, 0, 255) : $name,
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,string|int> */
    public function storeIncomingAttachment(array $payload, string $step): array
    {
        $entity = is_array($payload['entity'] ?? null) ? $payload['entity'] : [];
        $content = $entity['content'] ?? ($payload['content'] ?? null);
        $type = mb_strtolower(trim((string) ($entity['type'] ?? $payload['type'] ?? '')));
        $mime = mb_strtolower($this->nestedScalar($content, [
            'type', 'mime_type', 'mimeType', 'content_type', 'contentType',
        ]));
        if ($mime !== '' && ! str_contains($mime, '/')) $mime = '';
        $allowedType = in_array($type, ['image', 'photo', 'media', 'file', 'document'], true);
        if (! $allowedType) {
            throw new RuntimeException('Please upload the purchase bill as an image or PDF file.');
        }

        $base64 = $this->nestedScalar($content, ['base64', 'data', 'file_base64']);
        $url = $this->nestedScalar($content, ['link', 'url', 'download_url', 'downloadUrl', 'media_url', 'mediaUrl']);
        if (is_scalar($content) && filter_var(trim((string) $content), FILTER_VALIDATE_URL)) {
            $url = trim((string) $content);
        }
        $originalName = $this->nestedScalar($content, ['file_name', 'fileName', 'name', 'filename']);
        if ($url === '') $url = $this->nestedScalar($entity, ['link', 'url', 'download_url', 'downloadUrl', 'media_url', 'mediaUrl']);
        if ($originalName === '') $originalName = $this->nestedScalar($entity, ['file_name', 'fileName', 'name', 'filename']);
        if ($mime === '') $mime = mb_strtolower($this->nestedScalar($entity, ['mime_type', 'mimeType', 'content_type', 'contentType']));

        $binary = '';
        if ($base64 !== '' && strlen($base64) > 100) {
            if (str_contains($base64, ',')) $base64 = substr($base64, strpos($base64, ',') + 1);
            $decoded = base64_decode(str_replace(' ', '+', $base64), true);
            $binary = $decoded === false ? '' : $decoded;
        } elseif ($url !== '') {
            $binary = $this->downloadAttachment($url, $mime);
        }
        if ($binary === '' || strlen($binary) > 10 * 1024 * 1024) {
            throw new RuntimeException('The purchase bill could not be downloaded or is larger than 10 MB. Please upload it again.');
        }

        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/jpeg', 'image/jpg' => 'jpg',
            default => strtolower(pathinfo((string) (parse_url($url, PHP_URL_PATH) ?: $originalName), PATHINFO_EXTENSION)),
        };
        if ($extension === '' && str_starts_with($binary, '%PDF-')) $extension = 'pdf';
        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('Purchase bill must be a PDF, JPG, PNG or WEBP file.');
        }
        if ($mime === '') {
            $mime = $extension === 'pdf' ? 'application/pdf' : 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension);
        }

        $directory = WRITEPATH . 'uploads/arattai-purchase-drafts';
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create temporary purchase bill storage.');
        }
        $name = $step . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (file_put_contents($path, $binary) === false) {
            throw new RuntimeException('Could not save the uploaded purchase bill.');
        }
        return [
            'temp_path' => $path,
            'extension' => $extension,
            'original_name' => $originalName !== '' ? mb_substr($originalName, 0, 255) : $name,
            'mime_type' => $mime,
            'file_size' => strlen($binary),
        ];
    }

    private function downloadImage(string $url, string &$mime): string
    {
        $parts = parse_url(trim($url));
        if (($parts['scheme'] ?? '') !== 'https' || trim((string) ($parts['host'] ?? '')) === '') {
            throw new RuntimeException('Arattai image URL is invalid.');
        }
        $host = strtolower(trim((string) $parts['host']));
        if ($host === 'localhost' || str_ends_with($host, '.localhost')
            || (filter_var($host, FILTER_VALIDATE_IP)
                && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            throw new RuntimeException('Arattai image URL host is not allowed.');
        }
        $options = ['timeout' => 15, 'connect_timeout' => 5, 'http_errors' => false];
        $response = service('curlrequest')->get($url, $options);
        if ($response->getStatusCode() === 401 || $response->getStatusCode() === 403) {
            $settings = db_connect()->table('company_settings')
                ->select('attrai_company_id, attrai_business_number_id')->orderBy('id', 'ASC')->get()->getRowArray();
            $options['headers'] = [
                'Authorization' => 'Bearer ' . (new ArattaiOAuthService())->accessToken(),
                'Arattai-CompanyId' => trim((string) ($settings['attrai_company_id'] ?? '')),
                'Arattai-BusinessNumberId' => trim((string) ($settings['attrai_business_number_id'] ?? '')),
            ];
            $response = service('curlrequest')->get($url, $options);
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RuntimeException('Arattai image download failed with HTTP ' . $response->getStatusCode() . '.');
        }
        $header = trim($response->getHeaderLine('Content-Type'));
        if ($header !== '') {
            $mime = strtolower(trim(explode(';', $header)[0]));
        }
        if ($mime !== '' && ! str_starts_with($mime, 'image/')) {
            throw new RuntimeException('The uploaded Arattai file is not an image.');
        }
        return (string) $response->getBody();
    }

    private function downloadAttachment(string $url, string &$mime): string
    {
        $parts = parse_url(trim($url));
        if (($parts['scheme'] ?? '') !== 'https' || trim((string) ($parts['host'] ?? '')) === '') {
            throw new RuntimeException('Arattai purchase bill URL is invalid.');
        }
        $host = strtolower(trim((string) $parts['host']));
        if ($host === 'localhost' || str_ends_with($host, '.localhost')
            || (filter_var($host, FILTER_VALIDATE_IP)
                && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            throw new RuntimeException('Arattai purchase bill URL host is not allowed.');
        }
        $options = ['timeout' => 15, 'connect_timeout' => 5, 'http_errors' => false];
        $response = service('curlrequest')->get($url, $options);
        if (in_array($response->getStatusCode(), [401, 403], true)) {
            $settings = db_connect()->table('company_settings')
                ->select('attrai_company_id, attrai_business_number_id')->orderBy('id', 'ASC')->get()->getRowArray();
            $options['headers'] = [
                'Authorization' => 'Bearer ' . (new ArattaiOAuthService())->accessToken(),
                'Arattai-CompanyId' => trim((string) ($settings['attrai_company_id'] ?? '')),
                'Arattai-BusinessNumberId' => trim((string) ($settings['attrai_business_number_id'] ?? '')),
            ];
            $response = service('curlrequest')->get($url, $options);
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RuntimeException('Arattai purchase bill download failed with HTTP ' . $response->getStatusCode() . '.');
        }
        $header = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0] ?? ''));
        if ($header !== '') $mime = $header;
        if ($mime !== '' && $mime !== 'application/pdf' && ! str_starts_with($mime, 'image/')) {
            throw new RuntimeException('Purchase bill must be an image or PDF file.');
        }
        return (string) $response->getBody();
    }

    private function imageExtension(string $mime, string $url, string $name, string $binary): string
    {
        $extension = match (strtolower(trim($mime))) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => strtolower(pathinfo((string) (parse_url($url, PHP_URL_PATH) ?: $name), PATHINFO_EXTENSION)),
        };
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $info = function_exists('getimagesizefromstring') ? @getimagesizefromstring($binary) : false;
            $extension = match (is_array($info) ? (string) ($info['mime'] ?? '') : '') {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
        }
        return $extension;
    }

    private function nextStep(string $step, array $data): string
    {
        $map = [
            'order_from' => 'order_received_date',
            'order_received_date' => 'end_customer_name',
            'end_customer_name' => 'contact_number',
            'contact_number' => 'sales_executive_name',
            'sales_executive_name' => 'material_category',
            'material_category' => 'order_category_id',
            'order_category_id' => 'qty',
            'qty' => 'size_label',
            'size_label' => 'gold_required_gm',
            'gold_required_gm' => 'diamond_required_cts',
            'diamond_required_cts' => 'certificate_requirement',
            'certificate_requirement' => 'item_description',
            'item_description' => 'additional_details',
            'additional_details' => 'gold_rate_block_status',
            'gold_rate_per_gm' => 'approximate_price',
            'approximate_price' => 'advance_amount',
            'advance_amount' => 'due_date',
            'due_date' => 'reference_image',
            'reference_image' => 'signed_order_image',
            'signed_order_image' => 'confirm',
        ];
        if ($step === 'gold_rate_block_status') {
            return (string) ($data['gold_rate_block_status'] ?? '') === 'Fixed' ? 'gold_rate_per_gm' : 'approximate_price';
        }
        return $map[$step] ?? 'confirm';
    }

    private function previousStep(string $step, array $data): string
    {
        $steps = [
            'order_from', 'order_received_date', 'end_customer_name', 'contact_number', 'sales_executive_name',
            'material_category', 'order_category_id', 'qty', 'size_label', 'gold_required_gm',
            'diamond_required_cts', 'certificate_requirement', 'item_description', 'additional_details',
            'gold_rate_block_status',
        ];
        if ((string) ($data['gold_rate_block_status'] ?? '') === 'Fixed') {
            $steps[] = 'gold_rate_per_gm';
        }
        $steps = array_merge($steps, ['approximate_price', 'advance_amount', 'due_date', 'reference_image', 'signed_order_image', 'confirm']);
        $index = array_search($step, $steps, true);
        return is_int($index) && $index > 0 ? $steps[$index - 1] : 'order_from';
    }

    /** @param array<string,mixed> $data */
    private function summary(array $data): string
    {
        return "*Review Order*"
            . "\nOrder From: " . (string) ($data['order_from_name'] ?? '-')
            . "\nOrder Date: " . $this->displayDate((string) ($data['order_received_date'] ?? ''))
            . "\nCustomer: " . (string) ($data['end_customer_name'] ?? '-')
            . "\nMobile: " . (string) ($data['contact_number'] ?? '-')
            . "\nSales Executive: " . (string) ($data['sales_executive_name'] ?? '-')
            . "\nCategory: " . (string) ($data['material_category'] ?? '-')
            . "\nSub Category: " . (string) ($data['order_category_name'] ?? '-')
            . "\nPieces: " . (string) ($data['qty'] ?? '-')
            . "\nSize / Length: " . ((string) ($data['size_label'] ?? '') ?: '-')
            . "\nGold Weight: " . (isset($data['gold_required_gm']) ? (string) $data['gold_required_gm'] . ' gm' : '-')
            . "\nDiamond Weight: " . (isset($data['diamond_required_cts']) ? (string) $data['diamond_required_cts'] . ' cts' : '-')
            . "\nCertificate: " . (string) ($data['certificate_requirement'] ?? '-')
            . "\nDescription: " . ((string) ($data['item_description'] ?? '') ?: '-')
            . "\nAdditional Details: " . ((string) ($data['additional_details'] ?? '') ?: '-')
            . "\nGold Rate Block: " . (string) ($data['gold_rate_block_status'] ?? 'Not Fixed')
            . "\nApprox. Price: " . $this->displayMoney($data['approximate_price'] ?? null)
            . "\nAdvance: " . $this->displayMoney($data['advance_amount'] ?? null)
            . "\nDelivery Date: " . $this->displayDate((string) ($data['due_date'] ?? ''))
            . "\nReference Image: " . (is_array($data['reference_image'] ?? null) ? 'Received' : 'Missing')
            . "\nSigned Order Image: " . (is_array($data['signed_order_image'] ?? null) ? 'Received' : 'Missing')
            . "\n\nConfirm only after checking all details.";
    }

    private function dateValue(string $value): ?string
    {
        if (mb_strtolower(trim($value)) === 'today') {
            return date('Y-m-d');
        }
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, trim($value));
            if ($date !== false && $date->format($format) === trim($value)) {
                return $date->format('Y-m-d');
            }
        }
        return null;
    }

    private function positiveNumber(string $value, bool $allowZero = false): ?float
    {
        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', trim($value))) ?? '';
        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }
        $number = round((float) $clean, 3);
        if ($number < 0 || (! $allowZero && $number <= 0)) {
            return null;
        }
        return $number;
    }

    private function displayDate(string $date): string
    {
        $time = strtotime($date);
        return $time === false ? '-' : date('d/m/Y', $time);
    }

    private function displayMoney(mixed $amount): string
    {
        return $amount === null || $amount === '' ? '-' : '₹' . number_format((float) $amount, 2);
    }

    private function ready(): bool
    {
        $db = db_connect();
        return $db->tableExists('arattai_order_conversations')
            && $db->fieldExists('end_customer_name', 'orders')
            && $db->fieldExists('sales_executive_name', 'orders');
    }

    /** @return array<string,mixed>|null */
    private function session(string $userId): ?array
    {
        $row = $this->model->where('arattai_user_id', $userId)->first();
        if (! is_array($row)) {
            return null;
        }
        if ((string) ($row['status'] ?? '') === 'active' && strtotime((string) ($row['expires_at'] ?? '')) < time()) {
            $this->cleanupDraftImages($this->data($row));
            $this->finishSession($row, 'expired');
            return null;
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function startSession(string $userId, int $actorId, string $step, array $data): array
    {
        $existing = $this->model->where('arattai_user_id', $userId)->first();
        $values = [
            'arattai_user_id' => $userId,
            'actor_admin_user_id' => $actorId > 0 ? $actorId : null,
            'current_step' => $step,
            'draft_data' => $this->encode($data),
            'status' => 'active',
            'order_id' => null,
            'expires_at' => date('Y-m-d H:i:s', time() + 172800),
        ];
        if (is_array($existing)) {
            $this->model->update((int) $existing['id'], $values);
            return $values + ['id' => (int) $existing['id']];
        }
        $id = (int) $this->model->insert($values, true);
        return $values + ['id' => $id];
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $data */
    private function saveSession(array $session, string $step, array $data, int $actorId): void
    {
        $this->model->update((int) $session['id'], [
            'actor_admin_user_id' => $actorId > 0 ? $actorId : ($session['actor_admin_user_id'] ?? null),
            'current_step' => $step,
            'draft_data' => $this->encode($data),
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', time() + 172800),
        ]);
    }

    /** @param array<string,mixed> $session */
    private function finishSession(array $session, string $status, ?int $orderId = null): void
    {
        $this->model->update((int) $session['id'], [
            'status' => $status,
            'current_step' => $status,
            'order_id' => $orderId,
            'expires_at' => null,
        ]);
    }

    /** @param array<string,mixed> $session */
    private function claimConfirmation(array $session): bool
    {
        $db = db_connect();
        $db->table('arattai_order_conversations')
            ->where('id', (int) $session['id'])
            ->where('status', 'active')
            ->where('current_step', 'confirm')
            ->update([
                'status' => 'processing',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        return $db->affectedRows() === 1;
    }

    /** @param array<string,mixed>|null $session @return array<string,mixed> */
    private function data(?array $session): array
    {
        if (! is_array($session)) {
            return [];
        }
        $decoded = json_decode((string) ($session['draft_data'] ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $data */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function nestedScalar(mixed $value, array $keys): string
    {
        if (! is_array($value)) {
            return '';
        }
        foreach ($keys as $key) {
            if (isset($value[$key]) && is_scalar($value[$key])) {
                $candidate = trim((string) $value[$key]);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }
        foreach ($value as $child) {
            if (is_array($child)) {
                $candidate = $this->nestedScalar($child, $keys);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }
        return '';
    }

    /** @param array<string,mixed> $data */
    private function cleanupDraftImages(array $data): void
    {
        foreach (['reference_image', 'signed_order_image'] as $key) {
            $this->cleanupDraftImage(is_array($data[$key] ?? null) ? $data[$key] : []);
        }
    }

    /** @param array<string,mixed> $image */
    private function cleanupDraftImage(array $image): void
    {
        $path = (string) ($image['temp_path'] ?? '');
        if ($path !== '' && is_file($path) && str_starts_with($path, WRITEPATH . 'uploads/arattai-order-drafts')) {
            @unlink($path);
        }
    }
}
