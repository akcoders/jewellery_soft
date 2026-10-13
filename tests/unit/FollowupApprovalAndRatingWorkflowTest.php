<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class FollowupApprovalAndRatingWorkflowTest extends CIUnitTestCase
{
    public function testEmployeePoliciesDefaultToApprovalAndCameraOnly(): void
    {
        $migration = $this->source('Database/Migrations/2026-10-08-000097_AddEmployeeMobileControlsAndOrderRatings.php');
        $policy = $this->source('Services/MobileUserPolicyService.php');
        $picker = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/lib/jewellery_pwa/services/app_image_picker.dart');

        $this->assertStringContainsString("'followup_requires_approval' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]", $migration);
        $this->assertStringContainsString("'issuement_requires_approval' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]", $migration);
        $this->assertStringContainsString("'delivery_challan_requires_approval' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]", $migration);
        $this->assertStringContainsString("'followup_gallery_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0]", $migration);
        $this->assertStringContainsString('public function requiresApproval', $policy);
        $this->assertStringContainsString(': ImageSource.camera', $picker);
    }

    public function testFollowupIsVisibleWhilePendingAndCanContinueScheduling(): void
    {
        $approval = $this->source('Services/MobileApprovalService.php');
        $orders = $this->source('Controllers/Api/Mobile/OrdersController.php');
        $followups = $this->source('Services/OrderFollowupService.php');
        $quality = $this->source('Services/FollowupQualityService.php');
        $migration = $this->source('Database/Migrations/2026-10-13-000102_AddFollowupApprovalLifecycle.php');
        $orderView = $this->source('Views/admin/orders/show.php');
        $events = $this->source('Services/MobileNotificationEventService.php');

        $this->assertStringContainsString("'followup' => \$this->approveFollowup", $approval);
        $this->assertStringContainsString("->where('status', 'pending')", $approval);
        $this->assertStringContainsString('reviewPending($followupId, false', $approval);
        $this->assertStringContainsString('createPendingApproval(', $orders);
        $this->assertStringContainsString("\$this->save(\$orderId, \$payload, \$submittedBy, \$imageName, \$imagePath, 'pending')", $followups);
        $this->assertStringContainsString('completeOrderFollowup(', $followups);
        $this->assertStringContainsString("'approval_status'", $migration);
        $this->assertStringContainsString('Pending approval', $orderView);
        $this->assertStringContainsString("'Disapproved follow-up', 'score_delta' => -2.0", $quality);
        $this->assertStringContainsString("'Approved follow-up', 'score_delta' => 2.0", $quality);
        $this->assertStringContainsString("'pending_followup_approval'", $orders);
        $this->assertStringContainsString("'Followup disapproved'", $events);
    }

    public function testApprovalDeskSupportsIssuementReapprovalAndFollowupContext(): void
    {
        $approval = $this->source('Services/MobileApprovalService.php');
        $webDesk = $this->source('Views/admin/mobile_approvals/index.php');
        $appDesk = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_pwa/screens/mobile_approvals_screen.dart'
        );

        $this->assertStringContainsString("\$status === 'rejected'", $approval);
        $this->assertStringContainsString("\$request['request_type'] ?? '') === 'issuement'", $approval);
        $this->assertStringContainsString("'can_reapprove'", $approval);
        $this->assertStringContainsString("'previous_followup'", $approval);
        $this->assertStringContainsString('Re-approve &amp; Process', $webDesk);
        $this->assertStringContainsString('Re-approve & Process', $appDesk);
        $this->assertStringContainsString('Previous follow-up', $appDesk);
        $this->assertStringContainsString('Next follow-up', $appDesk);
    }

    public function testMobileApprovalDeskGroupsFollowupsIntoOrderChains(): void
    {
        $approval = $this->source('Services/MobileApprovalService.php');
        $controller = $this->source('Controllers/Admin/MobileApprovalController.php');
        $webDesk = $this->source('Views/admin/mobile_approvals/index.php');
        $appDesk = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_pwa/screens/mobile_approvals_screen.dart'
        );
        $this->assertStringContainsString("\$request['followup_chain']", $approval);
        $this->assertStringContainsString("\$request['followup_pending_position']", $approval);
        $this->assertStringContainsString("\$request['followup_is_oldest_pending']", $approval);
        $this->assertStringContainsString("'followupGroups' => array_values(\$followupGroups)", $controller);
        $this->assertStringContainsString('Follow-up approval chain', $webDesk);
        $this->assertStringContainsString('Review first', $webDesk);
        $this->assertStringContainsString('Approve · +2', $webDesk);
        $this->assertStringContainsString('Disapprove · −2', $webDesk);
        $this->assertStringContainsString('FOLLOW-UP APPROVAL CHAINS', $appDesk);
        $this->assertStringContainsString('Review top to bottom to preserve the stage story.', $appDesk);
        $this->assertStringContainsString('Approve +2', $appDesk);
    }

    public function testChildOrderFollowupApprovalsAreHiddenFromWebAndMobileLists(): void
    {
        $approval = $this->source('Services/MobileApprovalService.php');
        $webDesk = $this->source('Controllers/Admin/MobileApprovalController.php');
        $mobileDesk = $this->source('Controllers/Api/Mobile/MobileApprovalsController.php');

        $this->assertStringContainsString('$this->withoutChildOrderFollowupApprovals($requests)', $approval);
        $this->assertStringContainsString("\$request['request_type'] ?? '') !== 'followup'", $approval);
        $this->assertStringContainsString("\$request['status'] ?? '') !== 'pending'", $approval);
        $this->assertStringContainsString("fieldExists('parent_order_id', 'orders')", $approval);
        $this->assertStringContainsString('$service->list(null, \'pending\')', $webDesk);
        $this->assertStringContainsString('(new MobileApprovalService())->list($isAdmin ? null : $userId)', $mobileDesk);
    }

    public function testNativeAndroidShellExposesApprovalDeskAndNotificationRoute(): void
    {
        $nativeShell = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/app_shell.dart'
        );

        $this->assertStringContainsString("case 'mobile_approvals':", $nativeShell);
        $this->assertStringContainsString("screen == 'approvals'", $nativeShell);
        $this->assertStringContainsString("'Approval Desk'", $nativeShell);
    }

    public function testApprovalDeskDefaultsToPendingAndKeepsReviewedRequestsInHistory(): void
    {
        $service = $this->source('Services/MobileApprovalService.php');
        $controller = $this->source('Controllers/Admin/MobileApprovalController.php');
        $webDesk = $this->source('Views/admin/mobile_approvals/index.php');
        $appDesk = (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_pwa/screens/mobile_approvals_screen.dart'
        );

        $this->assertStringContainsString("elseif (\$status === 'history')", $service);
        $this->assertStringContainsString("\$service->list(null, 'pending')", $controller);
        $this->assertStringContainsString("\$service->list(null, 'history')", $controller);
        $this->assertStringContainsString('pending-approvals-tab', $webDesk);
        $this->assertStringContainsString('class="table datatable', $webDesk);
        $this->assertStringContainsString('approvalHistoryModal', $webDesk);
        $this->assertStringContainsString("String _statusFilter = 'pending';", $appDesk);
        $this->assertStringContainsString('DataTable(', $appDesk);
        $this->assertStringContainsString('_showRequestDetails', $appDesk);
    }

    public function testCompletionRatingFeedsPerformanceAndDeliveryPdfIsSafe(): void
    {
        $performance = $this->source('Services/StaffPerformanceService.php');
        $orders = $this->source('Controllers/Api/Mobile/OrdersController.php');
        $pdf = $this->source('Views/pdf/delivery_challan.php');

        $this->assertStringContainsString('completion_rating', $orders);
        $this->assertStringContainsString('RATING_POINT_STEP', $performance);
        $this->assertStringContainsString('rating_average', $performance);
        $this->assertStringContainsString('Value (INR)', $pdf);
        $this->assertStringContainsString('INR <?= number_format($total,2) ?>', $pdf);
        $this->assertStringNotContainsString("Receiver's Signature", $pdf);
        $this->assertStringNotContainsString('₹', $pdf);
    }

    public function testPwaUpdatesDoNotForceReloadOnControllerChange(): void
    {
        $index = (string) file_get_contents(ROOTPATH . 'app_kit/FlutKit/web/index.html');

        $this->assertStringNotContainsString('controllerchange', $index);
        $this->assertStringNotContainsString('window.location.reload()', $index);
        $this->assertStringContainsString('aabhushanForcePwaUpdate', $index);
    }

    private function source(string $relativePath): string
    {
        return (string) file_get_contents(APPPATH . $relativePath);
    }
}
