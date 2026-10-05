<?php

namespace Tests;

use App\Services\AbuseLimiter;
use CodeIgniter\Test\CIUnitTestCase;

final class AbuseLimiterTest extends CIUnitTestCase
{
    public function testConcurrentProcessesCannotOverspendBucket(): void
    {
        $directory = sys_get_temp_dir() . '/kartar-abuse-test-' . bin2hex(random_bytes(8));
        $processes = []; $pipes = [];
        try {
            $code = 'require $argv[1]; echo (int)(new \\App\\Services\\AbuseLimiter($argv[2]))->consume("shared",3,60);';
            for ($i = 0; $i < 8; $i++) {
                $processes[$i] = proc_open([PHP_BINARY, '-r', $code, APPPATH . 'Services/AbuseLimiter.php', $directory],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i]);
                $this->assertIsResource($processes[$i]);
                fclose($pipes[$i][0]);
            }
            $accepted = 0;
            foreach ($processes as $i => $process) {
                $accepted += (int)stream_get_contents($pipes[$i][1]);
                $this->assertSame('', stream_get_contents($pipes[$i][2]));
                fclose($pipes[$i][1]); fclose($pipes[$i][2]);
                $this->assertSame(0, proc_close($process));
            }
            $this->assertSame(3, $accepted);
        } finally {
            foreach (glob($directory . '/*.json') ?: [] as $file) unlink($file);
            if (is_dir($directory)) rmdir($directory);
        }
    }

    /** @dataProvider mutationQuotas */
    public function testSensitiveMutationQuota(string $path, int $quota): void
    {
        $request = $this->getMockBuilder(\CodeIgniter\HTTP\IncomingRequest::class)
            ->disableOriginalConstructor()->onlyMethods(['getMethod', 'getUri', 'getIPAddress', 'hasHeader', 'getHeaderLine', 'getFiles'])->getMock();
        $request->method('getMethod')->willReturn('POST');
        $request->method('getUri')->willReturn(new \CodeIgniter\HTTP\URI('http://localhost/' . $path));
        $request->method('getIPAddress')->willReturn('127.0.0.1');
        $request->method('hasHeader')->willReturn(true);
        $namespace = bin2hex(random_bytes(16));
        $request->method('getHeaderLine')->willReturnCallback(static fn($name) => $name === 'X-RateLimit-Test' ? $namespace : '');
        $request->method('getFiles')->willReturn([]);
        \App\Services\AuthService::setUser(['id' => 999999]);
        try {
            $filter = new \App\Filters\AbuseFilter();
            for ($i = 0; $i < $quota; $i++) $this->assertNull($filter->before($request));
            $this->assertSame(429, $filter->before($request)->getStatusCode());
        } finally { \App\Services\AuthService::setUser(null); }
    }

    public static function mutationQuotas(): array
    {
        return [['api/users/1/reset-password', 10], ['api/absensi/checkin', 20],
            ['api/votings/1/vote', 20], ['api/profile/photo', 10], ['api/chats/messages', 60]];
    }

    public function testBucketsAreSharedExpireAndDoNotAdoptDifferentKeys(): void
    {
        $directory = sys_get_temp_dir() . '/kartar-abuse-test-' . bin2hex(random_bytes(8));
        try {
            $one = new AbuseLimiter($directory);
            $two = new AbuseLimiter($directory);
            $this->assertTrue($one->consume('user:one', 2, 60, 100));
            $this->assertTrue($two->consume('user:one', 2, 60, 101));
            $this->assertFalse($one->consume('user:one', 2, 60, 159));
            $this->assertTrue($two->consume('user:two', 2, 60, 159));
            $this->assertTrue($two->consume('user:one', 2, 60, 160));
        } finally {
            foreach (glob($directory . '/*.json') ?: [] as $file) unlink($file);
            if (is_dir($directory)) rmdir($directory);
        }
    }
}
