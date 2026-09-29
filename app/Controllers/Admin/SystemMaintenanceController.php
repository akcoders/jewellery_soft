<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminUserModel;
use App\Services\MobileNotificationEventService;
use App\Services\MonthlyTestDataCleanupService;
use RuntimeException;
use Throwable;

class SystemMaintenanceController extends BaseController
{
    public function index(): string
    {
        $db = db_connect();
        $month = trim((string) $this->request->getGet('month')) ?: date('Y-m');
        $preview = null;
        $previewError = '';

        try {
            $preview = (new MonthlyTestDataCleanupService($db))->preview($month);
        } catch (Throwable $e) {
            $previewError = $e->getMessage();
        }

        $latestRelease = null;
        if ($db->tableExists('pwa_update_releases')) {
            $latestRelease = $db->table('pwa_update_releases r')
                ->select('r.*, au.name AS released_by_name')
                ->join('admin_users au', 'au.id = r.released_by', 'left')
                ->orderBy('r.id', 'DESC')
                ->get(1)
                ->getRowArray();
        }

        $audits = [];
        if ($db->tableExists('monthly_data_cleanup_audits')) {
            $audits = $db->table('monthly_data_cleanup_audits a')
                ->select('a.*, au.name AS requested_by_name')
                ->join('admin_users au', 'au.id = a.requested_by', 'left')
                ->orderBy('a.id', 'DESC')
                ->get(10)
                ->getResultArray();
        }

        return view('admin/system/maintenance', [
            'title' => 'System Maintenance',
            'selectedMonth' => $month,
            'preview' => $preview,
            'previewError' => $previewError,
            'latestRelease' => $latestRelease,
            'cleanupAudits' => $audits,
            'maintenanceReady' => $db->tableExists('pwa_update_releases')
                && $db->tableExists('monthly_data_cleanup_audits'),
        ]);
    }

    public function publishPwaUpdate()
    {
        $db = db_connect();
        if (! $db->tableExists('pwa_update_releases')) {
            return redirect()->to(site_url('admin/system/database-update'))
                ->with('warning', 'Run Database Update before publishing a PWA refresh.');
        }

        $version = date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $message = trim((string) $this->request->getPost('message'));
        if ($message === '') {
            $message = 'A new Aabhushan ERP version is available. Clear the old cache and relaunch now.';
        }
        if (mb_strlen($message) > 255) {
            return redirect()->back()->withInput()->with('error', 'Update message cannot exceed 255 characters.');
        }

        try {
            $db->table('pwa_update_releases')->insert([
                'version' => $version,
                'message' => $message,
                'released_by' => (int) session('admin_id') ?: null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $releaseId = (int) $db->insertID();
        } catch (Throwable $e) {
            log_message('error', 'PWA update publish failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', 'PWA update could not be published: ' . $e->getMessage());
        }

        $recipients = 0;
        $pushMessage = '';
        try {
            $summary = (new MobileNotificationEventService())->notifyPwaUpdateReleased($releaseId, $version);
            $recipients = (int) ($summary['queued_count'] ?? 0);
        } catch (Throwable $e) {
            $pushMessage = ' Push delivery could not be queued, but devices will still detect the update in-app.';
            log_message('error', 'PWA update push failed: {message}', ['message' => $e->getMessage()]);
        }

        return redirect()->to(site_url('admin/system/maintenance'))
            ->with('success', 'PWA update published. Relaunch prompt is active; push queued for '
                . $recipients . ' user(s).' . $pushMessage);
    }

    public function cleanupMonth()
    {
        $month = trim((string) $this->request->getPost('month'));
        $confirmation = trim((string) $this->request->getPost('confirmation'));
        $password = (string) $this->request->getPost('password');
        if ($confirmation !== 'CLEAR ' . $month) {
            return redirect()->to(site_url('admin/system/maintenance?month=' . rawurlencode($month)))
                ->with('error', 'Type CLEAR ' . $month . ' exactly to confirm.');
        }

        $adminId = (int) session('admin_id');
        $admin = (new AdminUserModel())->find($adminId);
        if (! is_array($admin) || (int) ($admin['is_active'] ?? 0) !== 1
            || ! password_verify($password, (string) ($admin['password_hash'] ?? ''))) {
            return redirect()->to(site_url('admin/system/maintenance?month=' . rawurlencode($month)))
                ->with('error', 'Administrator password is incorrect.');
        }

        try {
            $result = (new MonthlyTestDataCleanupService())->cleanup(
                $month,
                $adminId,
                $this->request->getIPAddress()
            );
            return redirect()->to(site_url('admin/system/maintenance?month=' . rawurlencode($month)))
                ->with('success', sprintf(
                    '%s cleanup completed. %d operational record(s) deleted and stock balances rolled back.',
                    $month,
                    (int) ($result['records_deleted'] ?? 0)
                ));
        } catch (RuntimeException $e) {
            return redirect()->to(site_url('admin/system/maintenance?month=' . rawurlencode($month)))
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'Monthly cleanup failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(site_url('admin/system/maintenance?month=' . rawurlencode($month)))
                ->with('error', 'Cleanup failed and was rolled back: ' . $e->getMessage());
        }
    }
}
