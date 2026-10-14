<?php

namespace Tests\Unit;

use App\Services\StaffPerformanceService;
use CodeIgniter\Test\CIUnitTestCase;

final class PerformanceTargetRewardTest extends CIUnitTestCase
{
    public function testMonthlyTargetIsTwoHundredAndExtraPointsBecomeRewards(): void
    {
        $service = new StaffPerformanceService();

        $below = $service->targetProgress(150.0);
        $this->assertSame(200.0, $below['monthly_target']);
        $this->assertSame(75.0, $below['target_progress']);
        $this->assertSame(50.0, $below['points_to_target']);
        $this->assertFalse($below['target_achieved']);
        $this->assertSame(0.0, $below['reward_points']);

        $exact = $service->targetProgress(200.0);
        $this->assertTrue($exact['target_achieved']);
        $this->assertSame(0.0, $exact['reward_points']);

        $above = $service->targetProgress(237.5);
        $this->assertSame(100.0, $above['target_progress']);
        $this->assertSame(0.0, $above['points_to_target']);
        $this->assertSame(37.5, $above['reward_points']);

        $rules = $service->scoreRules();
        $this->assertSame(200.0, $rules['monthly_target']);
        $this->assertSame(1.0, $rules['reward_per_extra_point']);
    }

    public function testTeamPerformanceApiIsAdminOnly(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Api/Mobile/PerformanceController.php');
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertStringContainsString("performance/team', 'Api\\Mobile\\PerformanceController::team'", $routes);
        $this->assertStringContainsString("performance/team/(:num)', 'Api\\Mobile\\PerformanceController::member/\$1'", $routes);
        $this->assertStringContainsString('isAdminUser(', $controller);
    }
}
