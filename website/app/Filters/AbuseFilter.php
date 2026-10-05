<?php

namespace App\Filters;

use App\Services\AbuseLimiter;
use App\Services\AuthService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

final class AbuseFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (ENVIRONMENT === 'testing' && !$request->hasHeader('X-RateLimit-Test')) return;
        if (!in_array(strtoupper($request->getMethod()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) return;
        $path = preg_replace('#^/?(?:index\.php/)?#', '', $request->getUri()->getPath());
        $category = 'mutation'; $limit = 60;
        if ($path === 'api/register') { $category = 'register'; $limit = 5; }
        elseif ($path === 'api/profile/password') { $category = 'password'; $limit = 5; }
        elseif (str_ends_with($path, '/reset-password')) { $category = 'reset'; $limit = 10; }
        elseif (str_starts_with($path, 'api/absensi/')) { $category = 'attendance'; $limit = 20; }
        elseif (preg_match('#^api/votings/\d+/vote$#', $path)) { $category = 'vote'; $limit = 20; }
        elseif ($path === 'api/profile/photo' || $request->getFiles() !== []) { $category = 'upload'; $limit = 10; }
        elseif ($path === 'api/chats/messages') { $category = 'chat'; $limit = 60; }
        if (str_contains(strtolower($request->getHeaderLine('Content-Type')), 'application/json')
            && strlen($request->getBody()) > 1048576) {
            return Services::response()->setStatusCode(413)->setJSON(['status' => false, 'message' => 'Payload too large']);
        }
        try {
            $limiter = new AbuseLimiter();
            $namespace = ENVIRONMENT === 'testing' ? 'testing:' . $request->getHeaderLine('X-RateLimit-Test') . ':' : '';
            // IP remains framework-derived: forwarded headers are not trusted by default.
            $ipLimit = $category === 'register' ? $limit : max(30, $limit * 4);
            $allowed = $limiter->consume($namespace . $category . ':ip:' . $request->getIPAddress(), $ipLimit, 60);
            $userId = $path !== 'api/register' && str_starts_with($path, 'api/') ? AuthService::getGlobalUserId() : null;
            if ($userId !== null) $allowed = $limiter->consume($namespace . $category . ':user:' . $userId, $limit, 60) && $allowed;
            if (!$allowed) return Services::response()->setStatusCode(429)->setHeader('Retry-After', '60')
                ->setJSON(['status' => false, 'message' => 'Too many requests']);
        } catch (\RuntimeException $exception) {
            log_message('error', 'Abuse limit storage unavailable');
            return Services::response()->setStatusCode(503)->setJSON(['status' => false, 'message' => 'Temporarily unavailable']);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
