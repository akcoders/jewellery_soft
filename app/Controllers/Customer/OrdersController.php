<?php

namespace App\Controllers\Customer;

use App\Controllers\BaseController;
use App\Models\CustomerUserModel;
use App\Models\OrderAttachmentModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\OrderStatusHistoryModel;
use App\Services\MobileNotificationEventService;
use App\Services\OrderCategoryService;
use App\Services\OrderNumberService;
use App\Services\OrderThumbnailService;
use Throwable;

class OrdersController extends BaseController
{
    public function index(): string
    {
        $customerId = (int) session('customer_id');
        $ordersQuery = (new OrderModel())
            ->select('orders.id, orders.order_no, orders.order_name, orders.order_type, orders.order_design_type, orders.status, orders.due_date, orders.created_at, oc.name AS order_category_name')
            ->join('order_categories oc', 'oc.id = orders.order_category_id', 'left')
            ->where('orders.customer_id', $customerId);
        if (session('customer_user_role') === 'sales_person') {
            $ordersQuery->where('orders.sales_person_user_id', (int) session('customer_user_id'));
        }
        $orders = $ordersQuery->orderBy('orders.id', 'DESC')->findAll();
        $thumbnailMap = (new OrderThumbnailService())->map(array_map(static fn(array $order): int => (int) ($order['id'] ?? 0), $orders), false);
        foreach ($orders as &$order) {
            $order['thumbnail_url'] = (string) ($thumbnailMap[(int) ($order['id'] ?? 0)] ?? '');
        }
        unset($order);
        $salesPeople = (new CustomerUserModel())->select('id, customer_id, name, mobile, email, role')
            ->where('customer_id', $customerId)
            ->where('role', 'sales_person')->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
        return view('customer/orders/index', [
            'title' => 'My Orders',
            'orders' => $orders,
            'salesPeople' => $salesPeople,
            'canManageSalesPeople' => session('customer_user_role') === 'customer_admin',
        ]);
    }

    public function create(): string
    {
        $customerId = (int) session('customer_id');
        $salesPeople = (new CustomerUserModel())->select('id, customer_id, name, mobile, email, role')
            ->where('customer_id', $customerId)
            ->where('role', 'sales_person')->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
        $designs = db_connect()->table('design_masters')
            ->select('id, design_code, name, category, subcategory, image_path, gross_weight_gm, net_gold_weight_gm, diamond_weight_cts')
            ->where('is_active', 1)->orderBy('design_code', 'ASC')->get()->getResultArray();
        $currentUser = (new CustomerUserModel())->select('id, name, mobile, role')
            ->where('id', (int) session('customer_user_id'))
            ->where('customer_id', $customerId)
            ->first();
        $customer = db_connect()->table('customers')->select('id, name, phone')
            ->where('id', $customerId)->get()->getRowArray();
        return view('customer/orders/create', [
            'title' => 'Create Order',
            'salesPeople' => $salesPeople,
            'designs' => $designs,
            'orderCategories' => (new OrderCategoryService())->options(),
            'isSalesPerson' => session('customer_user_role') === 'sales_person',
            'currentUser' => $currentUser,
            'customer' => $customer,
        ]);
    }

    public function store()
    {
        $rules = [
            'order_name' => 'required|max_length[180]',
            'order_category_id' => 'required|integer',
            'new_order_category' => 'permit_empty|max_length[100]',
            'order_type' => 'required|in_list[Sales,Manufacturing,Repair]',
            'order_design_type' => 'required|in_list[Fresh,Repeat]',
            'order_received_date' => 'required|valid_date',
            'contact_number' => 'permit_empty|max_length[40]',
            'material_category' => 'required|in_list[Gold,Diamond,Jadau,Silver]',
            'certificate_requirement' => 'permit_empty|max_length[120]',
            'additional_details' => 'permit_empty|max_length[5000]',
            'gold_rate_block_status' => 'required|in_list[Not Fixed,Fixed]',
            'gold_rate_per_gm' => 'permit_empty|decimal|greater_than[0]',
            'approximate_price' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'advance_amount' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'sales_person_user_id' => 'permit_empty|integer',
            'design_id' => 'permit_empty|integer',
            'item_description' => 'required|max_length[500]',
            'size_label' => 'permit_empty|max_length[30]',
            'qty' => 'required|integer|greater_than[0]',
            'gold_required_gm' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'diamond_required_cts' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'due_date' => 'permit_empty|valid_date',
            'order_notes' => 'permit_empty',
        ];
        if (! $this->validate($rules)) {
            $errors = $this->validator?->getErrors() ?? [];
            return redirect()->back()->withInput()->with('error', (string) (array_values($errors)[0] ?? 'Invalid order details.'));
        }
        $customerId = (int) session('customer_id');
        $currentUserId = (int) session('customer_user_id');
        $designType = (string) $this->request->getPost('order_design_type');
        $designId = (int) $this->request->getPost('design_id');
        if ($designType === 'Repeat' && $designId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Select the unique design code for a repeat order.');
        }
        if ($designType === 'Fresh') {
            $designId = 0;
        } elseif (db_connect()->table('design_masters')->where('id', $designId)->where('is_active', 1)->countAllResults() === 0) {
            return redirect()->back()->withInput()->with('error', 'Selected repeat design is not available.');
        }
        $salesPersonId = session('customer_user_role') === 'sales_person'
            ? $currentUserId
            : (int) $this->request->getPost('sales_person_user_id');
        if ($salesPersonId > 0 && (new CustomerUserModel())->where('id', $salesPersonId)
            ->where('customer_id', $customerId)->where('role', 'sales_person')->where('is_active', 1)->countAllResults() === 0) {
            return redirect()->back()->withInput()->with('error', 'Selected sales person does not belong to your account.');
        }
        $customer = db_connect()->table('customers')->where('id', $customerId)->get()->getRowArray();
        if (! $customer) {
            return redirect()->back()->with('error', 'Customer account was not found.');
        }
        $detailedOrder = $this->collectDetailedOrderFields();
        if ($detailedOrder['gold_rate_block_status'] === 'Fixed' && $detailedOrder['gold_rate_per_gm'] === null) {
            return redirect()->back()->withInput()->with('error', 'Enter the fixed gold rate per gram.');
        }
        if ($detailedOrder['approximate_price'] !== null
            && $detailedOrder['advance_amount'] > $detailedOrder['approximate_price']) {
            return redirect()->back()->withInput()->with('error', 'Advance amount cannot exceed the approximate price.');
        }
        if ($detailedOrder['contact_number'] === null) {
            $detailedOrder['contact_number'] = trim((string) ($customer['phone'] ?? '')) ?: null;
        }

        $db = db_connect();
        $db->transException(true)->transStart();
        $moved = [];
        try {
            $category = (new OrderCategoryService($db))->resolve(
                (int) $this->request->getPost('order_category_id'),
                (string) $this->request->getPost('new_order_category')
            );
            $orderNo = (new OrderNumberService($db))->generate(
                $customerId,
                (string) $category['code'],
                $salesPersonId,
                (string) ($customer['name'] ?? '')
            );
            $orderId = (int) (new OrderModel())->insert([
                'order_no' => $orderNo,
                'order_name' => trim((string) $this->request->getPost('order_name')),
                'order_category_id' => (int) $category['id'],
                'order_type' => (string) $this->request->getPost('order_type'),
                'order_design_type' => $designType,
                'order_from' => (string) $customer['name'],
                'order_received_date' => $detailedOrder['order_received_date'],
                'contact_number' => $detailedOrder['contact_number'],
                'material_category' => $detailedOrder['material_category'],
                'certificate_requirement' => $detailedOrder['certificate_requirement'],
                'additional_details' => $detailedOrder['additional_details'],
                'gold_rate_block_status' => $detailedOrder['gold_rate_block_status'],
                'gold_rate_per_gm' => $detailedOrder['gold_rate_per_gm'],
                'approximate_price' => $detailedOrder['approximate_price'],
                'advance_amount' => $detailedOrder['advance_amount'],
                'customer_id' => $customerId,
                'sales_person_user_id' => $salesPersonId ?: null,
                'status' => 'Confirmed',
                'priority' => 'Medium',
                'due_date' => trim((string) $this->request->getPost('due_date')) ?: null,
                'order_notes' => trim((string) $this->request->getPost('order_notes')),
                'whatsapp_notification_number' => trim((string) ($customer['phone'] ?? '')) ?: null,
                'whatsapp_notify_order_created' => 0,
            ], true);
            $itemId = (int) (new OrderItemModel())->insert([
                'order_id' => $orderId,
                'design_id' => $designId ?: null,
                'item_description' => trim((string) $this->request->getPost('item_description')),
                'size_label' => trim((string) $this->request->getPost('size_label')) ?: null,
                'qty' => max(1, (int) $this->request->getPost('qty')),
                'gold_required_gm' => max(0, (float) $this->request->getPost('gold_required_gm')),
                'diamond_required_cts' => max(0, (float) $this->request->getPost('diamond_required_cts')),
                'item_status' => 'Confirmed',
            ], true);
            (new OrderStatusHistoryModel())->insert([
                'order_id' => $orderId,
                'from_status' => null,
                'to_status' => 'Confirmed',
                'remarks' => 'Order created from authenticated customer portal.',
            ]);
            $moved = $this->storeImages($orderId, $itemId);
        } catch (Throwable $e) {
            $db->transRollback();
            foreach ($moved as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            log_message('error', 'Customer portal order creation failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Could not create the order. Please try again.');
        }
        $db->transComplete();

        try {
            (new MobileNotificationEventService())->notifyOrderCreated($orderId, 'customer_portal');
        } catch (Throwable $e) {
            log_message('error', 'Customer order push notification failed: {message}', ['message' => $e->getMessage()]);
        }

        return redirect()->to(site_url('customer/orders'))->with('success', 'Order submitted successfully.');
    }

    public function storeSalesPerson()
    {
        if (session('customer_user_role') !== 'customer_admin') {
            return redirect()->back()->with('error', 'Only the customer administrator can add sales people.');
        }
        $rules = [
            'name' => 'required|min_length[2]|max_length[150]',
            'mobile' => 'required|max_length[30]',
            'email' => 'required|valid_email|is_unique[customer_users.email]',
            'password' => 'required|min_length[8]|max_length[72]',
            'password_confirm' => 'required|matches[password]',
        ];
        if (! $this->validate($rules)) {
            $errors = $this->validator?->getErrors() ?? [];
            return redirect()->back()->withInput()->with('error', (string) (array_values($errors)[0] ?? 'Invalid sales person details.'));
        }
        try {
            $userId = (new CustomerUserModel())->insert([
                'customer_id' => (int) session('customer_id'),
                'name' => trim((string) $this->request->getPost('name')),
                'mobile' => trim((string) $this->request->getPost('mobile')),
                'email' => strtolower(trim((string) $this->request->getPost('email'))),
                'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
                'role' => 'sales_person',
                'is_active' => 1,
            ], true);
            if (! $userId) {
                throw new \RuntimeException('Sales person user could not be saved.');
            }
        } catch (Throwable $e) {
            log_message('error', 'Customer sales person creation failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Sales person login could not be created. Please verify the email and try again.');
        }
        return redirect()->back()->with('success', 'Sales person login created.');
    }

    /** @return list<string> */
    private function storeImages(int $orderId, int $itemId): array
    {
        $files = $this->request->getFileMultiple('order_images');
        if (! is_array($files)) {
            return [];
        }
        $directory = FCPATH . 'uploads/orders';
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Order image directory could not be created.');
        }
        $moved = [];
        $model = new OrderAttachmentModel();
        foreach (array_slice($files, 0, 10) as $file) {
            if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (! $file->isValid() || ! str_starts_with(strtolower((string) $file->getMimeType()), 'image/') || $file->getSize() > 5 * 1024 * 1024) {
                throw new \RuntimeException('Only valid images up to 5 MB are allowed.');
            }
            $name = $file->getRandomName();
            $file->move($directory, $name);
            $moved[] = $directory . '/' . $name;
            $model->insert([
                'order_id' => $orderId,
                'order_item_id' => $itemId,
                'file_type' => 'reference',
                'file_name' => $file->getClientName(),
                'file_path' => 'uploads/orders/' . $name,
            ]);
        }
        return $moved;
    }

    /** @return array<string, mixed> */
    private function collectDetailedOrderFields(): array
    {
        $goldRateStatus = trim((string) $this->request->getPost('gold_rate_block_status'));
        $goldRateStatus = $goldRateStatus === 'Fixed' ? 'Fixed' : 'Not Fixed';
        $goldRate = trim((string) $this->request->getPost('gold_rate_per_gm'));
        $approximatePrice = trim((string) $this->request->getPost('approximate_price'));
        $advanceAmount = trim((string) $this->request->getPost('advance_amount'));

        return [
            'order_received_date' => date('Y-m-d', strtotime((string) $this->request->getPost('order_received_date'))),
            'contact_number' => trim((string) $this->request->getPost('contact_number')) ?: null,
            'material_category' => trim((string) $this->request->getPost('material_category')),
            'certificate_requirement' => trim((string) $this->request->getPost('certificate_requirement')) ?: null,
            'additional_details' => trim((string) $this->request->getPost('additional_details')) ?: null,
            'gold_rate_block_status' => $goldRateStatus,
            'gold_rate_per_gm' => $goldRateStatus === 'Fixed' && $goldRate !== '' ? (float) $goldRate : null,
            'approximate_price' => $approximatePrice === '' ? null : (float) $approximatePrice,
            'advance_amount' => $advanceAmount === '' ? 0.0 : (float) $advanceAmount,
        ];
    }
}
