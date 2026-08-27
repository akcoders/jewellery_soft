<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class AdminApplicationTourTest extends CIUnitTestCase
{
    public function testTourPreferenceIsPerUserAndPermanentlyDismissible(): void
    {
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-08-28-000071_CreateAdminUserTourPreferences.php'
        );
        $service = (string) file_get_contents(APPPATH . 'Services/AdminApplicationTourService.php');
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/ApplicationTourController.php');

        $this->assertStringContainsString("createTable('admin_user_tour_preferences'", $migration);
        $this->assertStringContainsString("addUniqueKey(['admin_user_id', 'tour_key']", $migration);
        $this->assertStringContainsString("'dont_show_again'", $migration);
        $this->assertStringContainsString("'dismissed_at'", $migration);
        $this->assertStringContainsString("\$data['dont_show_again'] = 1", $service);
        $this->assertStringContainsString('session(\'admin_id\')', $controller);
        $this->assertStringNotContainsString("payload['admin_user_id']", $controller);
    }

    public function testTourRoutesAssetsAndManualReplayArePresent(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');
        $layout = (string) file_get_contents(APPPATH . 'Views/admin/layouts/main.php');
        $javascript = (string) file_get_contents(PUBLICPATH . 'template/assets/js/admin-application-tour.js');

        $this->assertStringContainsString("application-tour/state', 'Admin\\ApplicationTourController::state", $routes);
        $this->assertStringContainsString("ApplicationTourController::update', ['filter' => 'csrf']", $routes);
        $this->assertStringContainsString('data-app-tour-replay', $layout);
        $this->assertStringContainsString('admin-application-tour.js', $layout);
        $this->assertStringContainsString('Never show again', $javascript);
        $this->assertStringContainsString('sessionStorage', $javascript);
        $this->assertStringContainsString('shouldAutoStart', $javascript);
    }
}
