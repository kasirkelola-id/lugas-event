<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use CodeIgniter\Config\Services;

class PushTransport
{
    private static $serviceAccountPath = APPPATH . 'Config/firebase-service-account.json';
    private static $projectId = ''; // To be filled from service account

    public function send($deviceTokens, $title, $body, $data = [])
    {
        if (empty($deviceTokens)) return false;

        // Testing never reads service-account material or invokes Google auth.
        if (ENVIRONMENT === 'testing') {
            return getenv('FCM_MOCK') === 'true';
        }

        if (!file_exists(self::$serviceAccountPath)) {
            log_message('error', 'Firebase Service Account file not found.');
            return false;
        }

        try {
            $serviceAccount = json_decode(file_get_contents(self::$serviceAccountPath), true);
            self::$projectId = $serviceAccount['project_id'];

            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                self::$serviceAccountPath
            );

            $token = $credentials->fetchAuthToken(\Google\Auth\HttpHandler\HttpHandlerFactory::build(
                new \GuzzleHttp\Client(['timeout' => 5, 'connect_timeout' => 3]), false));
            if (!isset($token['access_token'])) {
                log_message('error', 'Failed to fetch FCM access token.');
                return false;
            }

            $accessToken = $token['access_token'];
            $url = 'https://fcm.googleapis.com/v1/projects/' . self::$projectId . '/messages:send';

            $client = Services::curlrequest();
            $successCount = 0;

            if (!is_array($deviceTokens)) {
                $deviceTokens = [$deviceTokens];
            }

            // Note: HTTP v1 API only allows sending 1 message per request natively,
            // but we can loop through the tokens.
            foreach ($deviceTokens as $deviceToken) {
                $payload = [
                    'message' => [
                        'token' => $deviceToken,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $data
                    ]
                ];

                $options = [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type'  => 'application/json'
                    ],
                    'json' => $payload,
                    'http_errors' => false,
                    'timeout' => 5, // MVP timeout (seconds)
                    'connect_timeout' => 3,
                ];

                $response = $client->post($url, $options);

                if ($response->getStatusCode() == 200) {
                    $successCount++;
                } else {
                    $body = $response->getBody();
                    log_message('error', 'FCM delivery failed', ['status' => (int)$response->getStatusCode()]);

                    // Cleanup invalid token
                    $jsonBody = json_decode($body, true);
                    if (self::isUnregistered((int)$response->getStatusCode(), $jsonBody ?? [])) {
                        $deviceModel = new \App\Models\UserDeviceModel();
                        $deviceModel->where('fcm_token', $deviceToken)->delete();
                        log_message('info', 'FCM token registration removed');
                    }
                }
            }

            return $successCount > 0;
        } catch (\Throwable $e) {
            log_message('error', 'FCM transport failed');
            return false;
        }
    }

    public static function isUnregistered(int $status, array $response): bool
    {
        if ($status !== 404) return false;
        foreach ($response['error']['details'] ?? [] as $detail) {
            if (($detail['@type'] ?? '') === 'type.googleapis.com/google.firebase.fcm.v1.FcmError'
                && ($detail['errorCode'] ?? '') === 'UNREGISTERED') return true;
        }
        // INVALID_ARGUMENT can describe a malformed payload, not a bad device.
        return false;
    }

}
