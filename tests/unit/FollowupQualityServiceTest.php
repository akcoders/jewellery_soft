<?php

namespace Tests\Unit;

use App\Services\FollowupQualityService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\FollowupQuality;
use InvalidArgumentException;

final class FollowupQualityServiceTest extends CIUnitTestCase
{
    private function database()
    {
        $db = \Config\Database::connect(['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => ''], false);
        $db->query('CREATE TABLE order_followups (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, description TEXT, followup_taken_by INTEGER, followup_taken_on TEXT, normalized_fingerprint TEXT, duplicate_followup_id INTEGER, duplicate_reason TEXT, duplicate_override INTEGER, image_path TEXT, updated_at TEXT)');
        $db->query('CREATE TABLE followup_duplicate_override_audits (id INTEGER PRIMARY KEY AUTOINCREMENT, followup_id INTEGER, previous_override INTEGER, new_override INTEGER, reason TEXT, changed_by INTEGER, created_at TEXT)');
        return $db;
    }

    public function testNormalizationDetectsCasePunctuationSpacingDateAndReferenceChanges(): void
    {
        $db = $this->database();
        $service = new FollowupQualityService($db);
        $original = 'Ring polishing completed; dispatch planned 12/10/2026. Ref #ABC-123';
        $normalized = $service->normalize($original);
        $db->table('order_followups')->insert(['order_id' => 10, 'description' => $original, 'followup_taken_by' => 7, 'followup_taken_on' => '2026-10-10 10:00:00', 'normalized_fingerprint' => $service->fingerprint($normalized)]);

        $result = $service->inspect('  RING, polishing   completed! Dispatch planned 13/10/2026; reference XYZ-999. ', 10, 7, '2026-10-13 10:00:00');
        $this->assertTrue($result['is_duplicate']);
        $this->assertSame(1, $result['matched_followup_id']);
    }

    public function testDifferentContentAndUnrelatedOrdersAreNotDuplicates(): void
    {
        $db = $this->database();
        $service = new FollowupQualityService($db);
        $db->table('order_followups')->insert(['order_id' => 10, 'description' => 'Ring polishing completed', 'followup_taken_by' => 7, 'followup_taken_on' => '2026-10-10 10:00:00']);
        $this->assertFalse($service->inspect('Stone setting started today', 10, 7, '2026-10-13 10:00:00')['is_duplicate']);
        $this->assertFalse($service->inspect('Ring polishing completed', 11, 7, '2026-10-13 10:00:00')['is_duplicate']);
        $this->assertFalse($service->inspect('Ring polishing completed', 10, 8, '2026-10-13 10:00:00')['is_duplicate']);
    }

    public function testComparisonIsLimitedToConfiguredWindow(): void
    {
        $db = $this->database();
        $service = new FollowupQualityService($db);
        $db->table('order_followups')->insert(['order_id' => 10, 'description' => 'Ring polishing completed', 'followup_taken_by' => 7, 'followup_taken_on' => '2026-08-01 10:00:00']);
        $this->assertFalse($service->inspect('Ring polishing completed', 10, 7, '2026-10-13 10:00:00')['is_duplicate']);
    }

    public function testTrustedConfiguredRegexCanFlagDuplicatePattern(): void
    {
        $db = $this->database();
        $config = new FollowupQuality();
        $config->duplicateContentPatterns = ['~\bno new update\b~u'];
        $service = new FollowupQualityService($db, $config);
        $db->table('order_followups')->insert(['order_id' => 10, 'description' => 'Awaiting workshop confirmation', 'followup_taken_by' => 7, 'followup_taken_on' => '2026-10-10 10:00:00']);
        $result = $service->inspect('No new update from workshop', 10, 7, '2026-10-13 10:00:00');
        $this->assertTrue($result['is_duplicate']);
        $this->assertStringContainsString('configured', (string) $result['reason']);
    }

    public function testEmptyBoilerplateAndUnsafeConfigurationAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new FollowupQualityService($this->database()))->inspect('Customer contacted!!! 13/10/2026 ref #123', 1, 1);
    }

    public function testRecursiveOrOversizedRegexCannotBeConfigured(): void
    {
        $config = new FollowupQuality();
        $config->duplicateContentPatterns = ['~(?R)~'];
        $this->expectException(InvalidArgumentException::class);
        new FollowupQualityService($this->database(), $config);
    }

    public function testAdminOverrideIsIdempotentAndAudited(): void
    {
        $db = $this->database();
        $db->table('order_followups')->insert(['order_id' => 10, 'description' => 'Ring polishing completed', 'followup_taken_by' => 7, 'followup_taken_on' => '2026-10-10 10:00:00', 'duplicate_followup_id' => 4, 'duplicate_reason' => 'Incorrect automatic match']);
        $service = new FollowupQualityService($db);
        $service->overrideDuplicate(1, false, 99, 'Verified as a separate production milestone');
        $service->overrideDuplicate(1, false, 99, 'Repeated request must remain idempotent');
        $row = $db->table('order_followups')->where('id', 1)->get()->getRowArray();
        $this->assertSame(0, (int) $row['duplicate_override']);
        $this->assertSame(1, $db->table('followup_duplicate_override_audits')->countAllResults());
        $this->assertSame([2.0, -0.5], array_column($service->scoreEvents($row), 'score_delta'));
    }

    public function testScoringContainsAllRequiredCombinationsAndDynamicImageCheck(): void
    {
        $service = new FollowupQualityService($this->database());
        $missing = $service->scoreEvents(['image_path' => null]);
        $this->assertSame([2.0, -0.5], array_column($missing, 'score_delta'));
        $this->assertNotContains('Meaningful update', array_column($missing, 'type'));

        $duplicateMissing = $service->scoreEvents([
            'image_path' => null, 'duplicate_followup_id' => 12,
            'duplicate_reason' => 'Normalized text matches an earlier follow-up.', 'duplicate_override' => null,
        ]);
        $this->assertSame(-1.5, array_sum(array_column($duplicateMissing, 'score_delta')));
        $this->assertNotContains('Approved follow-up', array_column($duplicateMissing, 'type'));
        $this->assertNotContains('Meaningful update', array_column($duplicateMissing, 'type'));
        $this->assertStringContainsString('#12', $duplicateMissing[0]['reason']);

        $directory = FCPATH . 'uploads/orders/followups';
        if (! is_dir($directory)) mkdir($directory, 0775, true);
        $name = 'quality-test-' . bin2hex(random_bytes(4)) . '.png';
        file_put_contents($directory . '/' . $name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        try {
            $withImage = $service->scoreEvents(['image_path' => 'uploads/orders/followups/' . $name]);
            $this->assertSame([2.0, 0.25], array_column($withImage, 'score_delta'));
            $this->assertNotContains('Image evidence missing', array_column($withImage, 'type'));
        } finally {
            @unlink($directory . '/' . $name);
        }
    }

    public function testPendingScoresZeroAndDisapprovedScoresOnlyMinusTwo(): void
    {
        $service = new FollowupQualityService($this->database());

        $this->assertSame([], $service->scoreEvents([
            'approval_status' => 'pending',
            'image_path' => null,
            'duplicate_followup_id' => 12,
        ]));

        $disapproved = $service->scoreEvents([
            'approval_status' => 'rejected',
            'review_note' => 'Update did not match the work completed.',
            'image_path' => null,
            'duplicate_followup_id' => 12,
        ]);
        $this->assertSame(['Disapproved follow-up'], array_column($disapproved, 'type'));
        $this->assertSame([-2.0], array_column($disapproved, 'score_delta'));
        $this->assertStringContainsString('Update did not match', $disapproved[0]['reason']);
    }
}
