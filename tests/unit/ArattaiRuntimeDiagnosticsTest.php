<?php

namespace Tests\Unit;

use App\Models\CompanySettingModel;
use App\Services\ArattaiOAuthService;
use App\Services\ArattaiUserCaptureService;
use App\Services\AttraiIntegrationService;
use App\Services\WhatsappSenderQueueService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use Config\Services;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

/** Behavioral diagnostics using temporary storage, fake settings and no network. */
final class ArattaiRuntimeDiagnosticsTest extends CIUnitTestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function testDocumentedIncomingEnvelopePersistsAndDeduplicatesUser(): void
    {
        $path = $this->temporaryPath();
        $capture = new ArattaiUserCaptureService($path);
        $envelope = $this->incomingEnvelope();

        $row = $capture->capture($envelope);
        $this->assertSame('970123457', $row['id']);
        $this->assertSame('Demo Admin', $row['name']);
        $this->assertSame('text', $row['message_type']);
        $this->assertNotEmpty($row['last_seen_at']);

        $reloaded = new ArattaiUserCaptureService($path);
        $this->assertSame([$row], $reloaded->recent());

        $envelope['entity']['user']['name'] = 'Updated Demo Admin';
        $capture->capture($envelope);
        $this->assertCount(1, $reloaded->recent());
        $this->assertSame('Updated Demo Admin', $reloaded->recent()[0]['name']);
    }

    public function testUnrelatedEventDoesNotCreateAnIncomingUser(): void
    {
        $capture = new ArattaiUserCaptureService($this->temporaryPath());
        $envelope = $this->incomingEnvelope();
        $envelope['event'] = 'chat.message.delivered';

        $this->assertNull($capture->capture($envelope));
        $this->assertSame([], $capture->recent());
    }

    public function testDocumentedBase64SignatureAcceptsExactRawBody(): void
    {
        $body = json_encode($this->incomingEnvelope(), JSON_THROW_ON_ERROR);
        $service = $this->integration(['attrai_webhook_secret' => 'diagnostic-only-secret']);
        $signature = 'sha256=' . base64_encode(hash_hmac('sha256', $body, 'diagnostic-only-secret', true));

        $service->verifyWebhookSignature($body, $signature);
        $this->addToAssertionCount(1);
    }

    public function testMissingSignatureIsRejectedBeforeCaptureCanRun(): void
    {
        $service = $this->integration(['attrai_webhook_secret' => 'diagnostic-only-secret']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Arattai webhook signature or Shared Secret is missing.');

        $service->verifyWebhookSignature('{}', '');
    }

    public function testTamperedBodyDoesNotMatchOriginalSignature(): void
    {
        $body = json_encode($this->incomingEnvelope(), JSON_THROW_ON_ERROR);
        $service = $this->integration(['attrai_webhook_secret' => 'diagnostic-only-secret']);
        $signature = 'sha256=' . base64_encode(hash_hmac('sha256', $body, 'diagnostic-only-secret', true));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Arattai webhook signature.');

        $service->verifyWebhookSignature($body . ' ', $signature);
    }

    public function testConversationalDemoUsesMessagesEndpointWithoutApprovedTemplate(): void
    {
        // providerSettings constructs OAuth internally. Supply its settings through
        // an isolated in-memory tests connection and a non-expired dummy token.
        $this->assertSame('testing', ENVIRONMENT);
        $db = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $db->query('CREATE TABLE company_settings (id INTEGER PRIMARY KEY, attrai_access_token TEXT, attrai_token_expires_at TEXT)');
        $db->table('company_settings')->insert([
            'id' => 1,
            'attrai_access_token' => 'diagnostic-only-not-a-real-token',
            'attrai_token_expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        $instances = new ReflectionProperty(\CodeIgniter\Database\Config::class, 'instances');
        $previousConnections = $instances->getValue();
        $instances->setValue(null, ['tests' => $db]);
        $http = $this->getMockBuilder(\CodeIgniter\HTTP\CURLRequest::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $http->expects($this->never())->method('request');
        Services::injectMock('curlrequest', $http);

        try {
            $queue = $this->getMockBuilder(WhatsappSenderQueueService::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock();
            $conversation = json_encode([
                'category' => 'display', 'type' => 'text', 'user_id' => '53817425',
                'content' => ['body' => 'Diagnostic demo'],
            ], JSON_THROW_ON_ERROR);
            $settings = (new ReflectionMethod(WhatsappSenderQueueService::class, 'providerSettings'))->invoke(
                $queue,
                ['attrai_enabled' => 1, 'attrai_company_id' => 'test-company', 'attrai_business_number_id' => 'test-number'],
                'attrai',
                ['event_key' => 'attrai_demo', 'request_payload' => $conversation]
            );
            $this->assertSame(ArattaiOAuthService::API_ROOT . '/messages', $settings['whatsapp_api_url']);

            $payloadMethod = new ReflectionMethod(WhatsappSenderQueueService::class, 'buildRequestPayload');
            $row = ['event_key' => 'attrai_demo', 'recipient_number' => '53817425', 'message_text' => 'Diagnostic demo', 'request_payload' => $conversation];
            $payload = $payloadMethod->invoke($queue, $settings, $row);
            $body = json_decode($payload['request_options']['body'], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('display', $body['category']);
            $this->assertSame('53817425', $body['user_id']);
            $this->assertSame('Diagnostic demo', $body['content']['body']);
        } finally {
            $instances->setValue(null, $previousConnections);
            $db->close();
        }
    }

    public function testDemoExposesSanitizedProviderFailure(): void
    {
        $queue = $this->getMockBuilder(WhatsappSenderQueueService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['enqueue', 'dispatchById'])
            ->getMock();
        $queue->expects($this->once())->method('enqueue')
            ->with($this->callback(static fn (array $data): bool => $data['event_key'] === 'attrai_demo'
                && $data['recipient_number'] === '9999999999'))
            ->willReturn(777);
        $queue->expects($this->once())->method('dispatchById')->with(777)->willReturn([
            'status' => 'failed',
            'queue_id' => 777,
            'error_message' => 'Specific provider rejection for diagnosis',
        ]);
        Services::injectMock('session', new class {
            public function get(?string $key = null): mixed
            {
                return null;
            }
        });
        $service = $this->integration([
            'attrai_enabled' => 1,
            'attrai_api_url' => ArattaiOAuthService::API_ROOT . '/transactions',
        ], $queue);

        try {
            $service->sendTestMessage('9999999999');
            $this->fail('The failed dispatch should raise an error.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Demo message could not be sent:', $exception->getMessage());
            $this->assertStringContainsString('Specific provider rejection', $exception->getMessage());
            $this->assertStringNotContainsString('777', $exception->getMessage());
        }
    }

    /** @param array<string,mixed> $settings */
    private function integration(array $settings, ?WhatsappSenderQueueService $queue = null): AttraiIntegrationService
    {
        $model = new class ($settings) extends CompanySettingModel {
            public function __construct(private array $fixture)
            {
            }

            public function orderBy(mixed $field, string $direction = 'ASC'): self
            {
                return $this;
            }

            public function first()
            {
                return $this->fixture;
            }
        };
        $queue ??= $this->getMockBuilder(WhatsappSenderQueueService::class)
            ->disableOriginalConstructor()
            ->getMock();

        return new AttraiIntegrationService($model, $queue);
    }

    /** @return array<string,mixed> */
    private function incomingEnvelope(): array
    {
        return [
            'event' => 'chat.message.received',
            'version' => '1',
            'attempt' => 1,
            'event_time' => 1786709296458,
            'company_id' => 'test-company',
            'business_id' => 'test-business',
            'phonenumber_id' => 'test-number',
            'entity' => [
                'content' => 'Hi',
                'user' => ['name' => 'Demo Admin', 'id' => '970123457'],
                'type' => 'text',
                'ack_key' => 'diagnostic-fixture',
            ],
        ];
    }

    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'arattai-diagnostic-');
        if ($path === false) {
            throw new RuntimeException('Could not allocate temporary diagnostic storage.');
        }
        $this->temporaryFiles[] = $path;
        return $path;
    }
}
