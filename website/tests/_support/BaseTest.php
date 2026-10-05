<?php

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;

use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use Config\Services;

abstract class BaseTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $migrateOnce = true;
    protected $refresh = false;
    private array $unexpectedHttpRequests = [];
    private $previousFcmMock;

    protected function setUp(): void
    {
        if (ENVIRONMENT !== 'testing') {
            throw new \RuntimeException('Backend tests require the testing environment');
        }
        parent::setUp();

        $this->unexpectedHttpRequests = [];
        $this->previousFcmMock = getenv('FCM_MOCK');
        putenv('FCM_MOCK=true');
        Services::injectMock('curlrequest', $this->isolatedCurlClient(function (string $url): void {
            $this->unexpectedHttpRequests[] = $url;
        }));
        
        // Only run truncation if we are using the test database
        $db = \Config\Database::connect();
        
        if ($db->DBDriver === 'SQLite3') {
            $db->disableForeignKeyChecks();
            
            $tables = $db->listTables();
            foreach ($tables as $table) {
                if ($table !== 'migrations') {
                    $db->table($table)->emptyTable();
                }
            }
            
            $db->enableForeignKeyChecks();
            // Historical SQLite Forge compatibility attempts may leave a failed
            // transaction status. This newly emptied testing fixture starts clean;
            // controller/service transaction checks remain active during each test.
            $db->resetTransStatus();
        }
    }

    /** No cURL constructor or real request is ever invoked by this test client. */
    protected function isolatedCurlClient(callable $onBlocked): CURLRequest
    {
        $client = $this->getMockBuilder(CURLRequest::class)
            ->disableOriginalConstructor()->onlyMethods(['request'])->getMock();
        $client->method('request')->willReturnCallback(static function ($method, string $url, array $options = []) use ($onBlocked) {
            // Wheel regression tests require this exact loopback integration;
            // respond in memory instead of allowing even a localhost socket.
            $uri = parse_url($url);
            if (strtoupper($method) === 'POST'
                && ($uri['scheme'] ?? '') === 'http'
                && in_array($uri['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true)
                && ($uri['path'] ?? '') === '/internal/wheel-event') {
                return (new Response(config('App')))->setJSON(['status' => true]);
            }
            $onBlocked($url);
            throw new \RuntimeException('Test HTTP transport blocked before I/O: ' . $url);
        });
        return $client;
    }

    protected function tearDown(): void
    {
        try {
            // Controllers may catch transport errors; that must not hide a test
            // accidentally selecting production or another external endpoint.
            $this->assertSame([], $this->unexpectedHttpRequests, 'Unexpected backend HTTP request; use a fake transport');
        } finally {
            putenv($this->previousFcmMock === false ? 'FCM_MOCK' : 'FCM_MOCK=' . $this->previousFcmMock);
            parent::tearDown();
        }
    }
}
