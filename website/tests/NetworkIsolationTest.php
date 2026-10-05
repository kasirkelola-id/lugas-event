<?php

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;

class NetworkIsolationTest extends \Tests\Support\BaseTest
{
    protected $migrate = true;
    protected $namespace = 'App';

    public static function externalUrls(): array
    {
        return [
            ['https://kartar.kelolakasir.id/api/me'],
            ['https://fcm.googleapis.com/v1/projects/test/messages:send'],
            ['https://example.com/internal/wheel-event'],
        ];
    }

    #[DataProvider('externalUrls')]
    public function testExternalTransportIsBlockedBeforeIo(string $url): void
    {
        $blocked = [];
        $client = $this->isolatedCurlClient(static function (string $target) use (&$blocked): void {
            $blocked[] = $target;
        });
        try {
            $client->post($url);
            $this->fail('External transport unexpectedly succeeded');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('blocked before I/O', $error->getMessage());
        }
        $this->assertSame([$url], $blocked);
    }

    public function testWheelLoopbackIsHandledByAnInMemoryResponse(): void
    {
        $response = \Config\Services::curlrequest()->post('http://localhost:3000/internal/wheel-event');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['status' => true], json_decode($response->getBody(), true));
    }

    public function testFcmAlwaysUsesTestingMockMode(): void
    {
        $this->assertSame('testing', ENVIRONMENT);
        $this->assertSame('true', getenv('FCM_MOCK'));
    }
}
