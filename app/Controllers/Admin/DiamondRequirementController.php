<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminUserModel;
use App\Services\DiamondRequirementService;
use App\Services\MobileNotificationEventService;
use App\Services\RbacService;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

class DiamondRequirementController extends BaseController
{
    private DiamondRequirementService $requirements;
    private MobileNotificationEventService $notifications;
    private RbacService $rbac;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->requirements = new DiamondRequirementService();
        $this->notifications = new MobileNotificationEventService();
        $this->rbac = new RbacService();
    }

    public function index(): string
    {
        return view('admin/diamond_requirements/index', [
            'title' => 'Diamond Requirements',
            'requirements' => $this->requirements->forAdmin(),
            'staff' => (new AdminUserModel())->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
        ]);
    }

    public function store(int $orderId)
    {
        $userId = (int) session('admin_id');
        $canManage = $this->rbac->userCan($userId, 'diamond.inventory.manage');
        try {
            $row = $this->requirements->raise(
                $orderId,
                $userId,
                (string) $this->request->getPost('requirement_note'),
                (string) $this->request->getPost('required_by'),
                $canManage
            );
            try {
                $this->notifications->notifyDiamondRequirementRaised((int) ($row['id'] ?? 0));
            } catch (Throwable $e) {
                log_message('error', 'Diamond requirement notification failed: {message}', ['message' => $e->getMessage()]);
            }
            return redirect()->back()->with('success', 'Diamond requirement raised for admin approval.');
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function approve(int $id)
    {
        try {
            $row = $this->requirements->approveAndAssign(
                $id,
                (int) session('admin_id'),
                (int) $this->request->getPost('assigned_to'),
                (string) $this->request->getPost('preparation_due_at'),
                (string) $this->request->getPost('approval_note')
            );
            try {
                $this->notifications->notifyDiamondRequirementAssigned((int) ($row['id'] ?? 0));
            } catch (Throwable $e) {
                log_message('error', 'Diamond bag assignment notification failed: {message}', ['message' => $e->getMessage()]);
            }
            return redirect()->back()->with('success', 'Requirement approved and bag preparation assigned.');
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function reject(int $id)
    {
        try {
            $this->requirements->reject($id, (int) session('admin_id'), (string) $this->request->getPost('rejection_reason'));
            return redirect()->back()->with('success', 'Diamond requirement rejected.');
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int $id): string
    {
        $row = $this->requirements->findDetailed($id);
        if (! is_array($row)) {
            throw PageNotFoundException::forPageNotFound('Diamond requirement not found.');
        }
        return view('admin/diamond_requirements/show', [
            'title' => 'Diamond Requirement Details',
            'requirement' => $row,
            'bagItems' => $this->requirements->bagItems($id),
            'staff' => (new AdminUserModel())->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
        ]);
    }
}
