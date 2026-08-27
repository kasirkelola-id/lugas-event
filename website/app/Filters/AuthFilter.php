<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use App\Models\UserTokenModel;
use App\Models\UserModel;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader)) {
            $authHeader = $request->getServer('HTTP_AUTHORIZATION');
        }
        
        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return Services::response()
                ->setJSON(['status' => false, 'message' => 'Unauthenticated'])
                ->setStatusCode(401);
        }

        $token = $matches[1];
        $tokenHash = hash('sha256', $token);

        $tokenModel = new UserTokenModel();
        $tokenData = $tokenModel->where('token_hash', $tokenHash)->first();

        if (!$tokenData) {
            return Services::response()
                ->setJSON(['status' => false, 'message' => 'Unauthenticated'])
                ->setStatusCode(401);
        }

        if ($tokenData['revoked_at'] !== null || strtotime($tokenData['expires_at']) < time()) {
            return Services::response()
                ->setJSON(['status' => false, 'message' => 'Unauthenticated'])
                ->setStatusCode(401);
        }

        $userModel = new UserModel();
        $user = $userModel->find($tokenData['user_id']);

        if (!$user || $user['status_aktif'] != 1) {
            return Services::response()
                ->setJSON(['status' => false, 'message' => 'Unauthenticated'])
                ->setStatusCode(401);
        }

        // Force password change check
        if ((int)($user['password_must_change'] ?? 0) === 1) {
            // Allow only specific paths
            $allowedPaths = ['api/me', 'api/logout', 'api/profile/password'];
            $currentPath = $request->getUri()->getPath();
            $isAllowed = false;
            foreach ($allowedPaths as $path) {
                if (preg_match('#' . preg_quote($path, '#') . '#i', $currentPath)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                return Services::response()
                    ->setJSON(['status' => false, 'message' => 'Ganti password diperlukan'])
                    ->setStatusCode(403);
            }
        }

        // Check Roles Authorization
        if (!empty($arguments)) {
            $allowedRoles = $arguments;
            if (!in_array($user['role_level'], $allowedRoles)) {
                return Services::response()
                    ->setJSON(['status' => false, 'message' => 'Unauthorized'])
                    ->setStatusCode(403);
            }
        }

        \App\Services\AuthService::setUser($user);
        \App\Services\AuthService::setToken($tokenData);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}
