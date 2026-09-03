<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class RateLimitFilter implements FilterInterface
{
    /**
     * @param RequestInterface $request
     * @param array|null       $arguments [max_requests, seconds] e.g., ['5', '60']
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (ENVIRONMENT === 'testing' && !$request->hasHeader('X-RateLimit-Test')) {
            return;
        }

        $throttler = Services::throttler();

        // Default to 10 requests per minute if not provided
        $maxRequests = $arguments[0] ?? 10;
        $seconds     = $arguments[1] ?? 60;

        // Use IP address and Route URI to construct a unique key
        $rawKey = $request->getIPAddress() . '_' . current_url(true)->getPath();
        $key = md5($rawKey); // Avoid reserved characters {}()/\@: in Cache Key

        // Check if the rate limit is exceeded
        if ($throttler->check($key, $maxRequests, $seconds) === false) {
            $response = Services::response();
            return $response->setStatusCode(429)
                            ->setJSON([
                                'status'  => false,
                                'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.'
                            ]);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}
