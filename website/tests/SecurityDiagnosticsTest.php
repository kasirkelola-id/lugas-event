<?php

namespace Tests;

use App\Services\InternalSecret;
use App\Services\NotificationService;
use CodeIgniter\Test\CIUnitTestCase;

final class DiagnosticCapture implements \CodeIgniter\Log\Handlers\HandlerInterface
{
    public static array $messages = [];
    public function __construct(array $config) {}
    public function handle($level, $message): bool { self::$messages[] = $message; return true; }
    public function canHandle(string $level): bool { return true; }
    public function setDateFormat(string $format) { return $this; }
}

final class SecurityDiagnosticsTest extends CIUnitTestCase
{
    public function test_logger_discards_framework_payloads_and_preserves_safe_metadata(): void
    {
        $config = new \Config\Logger();
        $config->handlers = [DiagnosticCapture::class => ['handles' => ['error']]];
        DiagnosticCapture::$messages = [];
        $logger = new \App\Log\SafeLogger($config);
        $logger->error('SQL INSERT synthetic-secret {post_vars} {session_vars} {env:SECRET}', [
            'exception' => new \RuntimeException('provider synthetic-secret'),
            'token' => 'synthetic-secret', 'status' => 503, 'tenant_id' => 101,
            'correlation_id' => '0123456789abcdef',
        ]);
        $logger->error('Wheel operation failed', ['correlation_id' => '0123456789abcdef', 'status' => 500]);
        $log = implode('\n', DiagnosticCapture::$messages);
        $this->assertCount(2, DiagnosticCapture::$messages);
        $this->assertStringNotContainsString('synthetic-secret', $log);
        $this->assertStringNotContainsString('INSERT', $log);
        $this->assertStringNotContainsString('{post_vars}', $log);
        $this->assertStringContainsString('correlation_id=0123456789abcdef', $log);
        $this->assertStringContainsString('status=503 tenant_id=101', $log);
        $this->assertStringContainsString('wheel.operation_failed', $log);
        $this->assertInstanceOf(\App\Log\SafeLogger::class, \Config\Services::logger(false));
    }

    public function test_production_internal_secret_fails_closed(): void
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/_support/production_internal_secret_probe.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $error);
        $this->assertSame([false, false, false, true, false, true], json_decode($output, true));
        foreach ([null, '', ' ', 'default_internal_secret_for_dev'] as $value) {
            $this->assertNull(InternalSecret::resolve($value, 'production'));
        }
        $this->assertSame('synthetic-private-internal-value', InternalSecret::resolve('synthetic-private-internal-value', 'production'));
        $old = getenv('INTERNAL_API_SECRET');
        putenv('INTERNAL_API_SECRET=synthetic-private-internal-value');
        try {
            $this->assertFalse(InternalSecret::accepts(''));
            $this->assertFalse(InternalSecret::accepts('synthetic-wrong-value'));
            $this->assertTrue(InternalSecret::accepts('synthetic-private-internal-value'));
        } finally {
            putenv($old === false ? 'INTERNAL_API_SECRET' : 'INTERNAL_API_SECRET=' . $old);
        }
    }

    public function test_fcm_mock_does_not_require_credentials_and_bad_payload_does_not_delete_token(): void
    {
        \Config\Services::resetSingle('pushTransport');
        $old = getenv('FCM_MOCK');
        try {
            putenv('FCM_MOCK=true');
            $this->assertTrue(NotificationService::sendPushNotification(['synthetic-device'], 'Synthetic', 'Synthetic'));
            putenv('FCM_MOCK=false');
            $this->assertFalse(NotificationService::sendPushNotification(['synthetic-device'], 'Synthetic', 'Synthetic'));
        } finally {
            putenv($old === false ? 'FCM_MOCK' : 'FCM_MOCK=' . $old);
        }
        $this->assertFalse(NotificationService::isUnregistered(400, ['error' => ['details' => [
            ['@type' => 'type.googleapis.com/google.rpc.BadRequest', 'errorCode' => 'INVALID_ARGUMENT'],
        ]]]));
        $this->assertFalse(NotificationService::isUnregistered(404, ['error' => ['message' => 'UNREGISTERED']]));
        $this->assertTrue(NotificationService::isUnregistered(404, ['error' => ['details' => [
            ['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED'],
        ]]]));
    }
}
