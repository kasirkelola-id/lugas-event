<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use CodeIgniter\Config\Services;

class NotificationService
{
    private static $serviceAccountPath = APPPATH . 'Config/firebase-service-account.json';
    private static $projectId = ''; // To be filled from service account

    public static function sendPushNotification($deviceTokens, $title, $body, $data = [])
    {
        if (empty($deviceTokens)) return false;

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

            // Mock auth token for testing
            if (ENVIRONMENT === 'testing' && getenv('FCM_MOCK') === 'true') {
                $token = ['access_token' => 'mock_token'];
            } else {
                $token = $credentials->fetchAuthToken();
            }
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

                // Mock injection for testing
                if (ENVIRONMENT === 'testing' && getenv('FCM_MOCK') === 'true') {
                    $response = Services::response()->setStatusCode(200);
                } else {
                    $response = $client->post($url, $options);
                }

                if ($response->getStatusCode() == 200) {
                    $successCount++;
                } else {
                    $body = $response->getBody();
                    log_message('error', 'FCM Send Error: ' . $body);
                    
                    // Cleanup invalid token
                    $jsonBody = json_decode($body, true);
                    $errorCode = $jsonBody['error']['details'][0]['errorCode'] ?? null;
                    if ($response->getStatusCode() == 404 || $response->getStatusCode() == 400) {
                        // In FCM HTTP v1, UNREGISTERED or INVALID_ARGUMENT might indicate a bad token
                        if (strpos($body, 'UNREGISTERED') !== false || strpos($body, 'INVALID_ARGUMENT') !== false) {
                            $deviceModel = new \App\Models\UserDeviceModel();
                            $deviceModel->where('fcm_token', $deviceToken)->delete();
                            log_message('info', 'FCM token removed due to invalid/unregistered: ' . $deviceToken);
                        }
                    }
                }
            }

            return $successCount > 0;
        } catch (\Exception $e) {
            log_message('error', 'FCM Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Resolves active members of a tenant and returns their FCM tokens.
     */
    public static function getTokensForTenant(int $tenantId, array $excludeUserIds = []): array
    {
        $db = \Config\Database::connect();
        
        // 1. Get all active members for this tenant
        $builder = $db->table('organization_members');
        $builder->select('user_id');
        $builder->where('karang_taruna_id', $tenantId);
        $builder->where('status_aktif', 1);
        if (!empty($excludeUserIds)) {
            $builder->whereNotIn('user_id', $excludeUserIds);
        }
        $members = $builder->get()->getResultArray();
        
        $userIds = array_column($members, 'user_id');
        if (empty($userIds)) {
            return [];
        }

        // 2. Get tokens for these users
        $devices = $db->table('user_devices')
                      ->whereIn('user_id', $userIds)
                      ->where('fcm_token !=', null)
                      ->where('fcm_token !=', '')
                      ->get()->getResultArray();
                      
        return array_values(array_unique(array_filter(array_column($devices, 'fcm_token'))));
    }
}
