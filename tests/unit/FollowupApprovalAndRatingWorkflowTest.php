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

    public function testFollowupRejectionKeepsWorkPendingAndNotifiesEmployee(): void
    {
        $approval = $this->source('Services/MobileApprovalService.php');
        $orders = $this->source('Controllers/Api/Mobile/OrdersController.php');
        $events = $this->source('Services/MobileNotificationEventService.php');

        $this->assertStringContainsString("'followup' => \$this->approveFollowup", $approval);
        $this->assertStringContainsString("->where('status', 'pending')", $approval);
        $this->assertStringContainsString("'pending_followup_approval'", $orders);
        $this->assertStringContainsString("'Followup disapproved'", $events);
    }

    public function testCompletionRatingFeedsPerformanceAndDeliveryPdfIsSafe(): void
    {
        $performance = $this->source('Services/StaffPerformanceService.php');
        $orders = $this->source('Controllers/Api/Mobile/OrdersController.php');
        $pdf = $this->source('Views/pdf/delivery_challan.php');

        $this->assertStringContainsString('completion_rating', $orders);
        $this->assertStringContainsString('RATING_POINT_STEP', $performance);
        $this->assertStringContainsString('rating_average', $performance);
        $this->assertStringContainsString('Value (Rs.)', $pdf);
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
