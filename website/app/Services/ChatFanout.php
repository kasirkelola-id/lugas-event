<?php

namespace App\Services;

class ChatFanout
{
    public function send(int $chatId): bool
    {
        if (ENVIRONMENT === 'testing') return getenv('FCM_MOCK') === 'true';
        $base = rtrim((string)env('NODE_INTERNAL_URL', 'http://127.0.0.1:3000'), '/');
        $uri = parse_url($base);
        if (!$uri || ($uri['scheme'] ?? '') !== 'http' || !in_array($uri['host'] ?? '', ['127.0.0.1', 'localhost', '[::1]'], true)
            || isset($uri['user']) || isset($uri['pass']) || isset($uri['query']) || isset($uri['fragment']) || !empty($uri['path'])) return false;
        $secret = InternalSecret::configured();
        if (!$secret) return false;
        try {
            $response = \Config\Services::curlrequest()->post($base . '/internal/chat-event', [
                'headers' => ['X-Internal-Secret' => $secret], 'json' => ['chat_id' => $chatId],
                'http_errors' => false, 'timeout' => 3, 'connect_timeout' => 2,
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Throwable $error) {
            return false;
        }
    }
}
