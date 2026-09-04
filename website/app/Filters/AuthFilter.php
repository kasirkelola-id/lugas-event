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

        $user = null;

        if ($tokenData['user_id'] == 0) {
            // It's a Superadmin
            $user = [
                'id' => 0,
                'karang_taruna_id' => $tokenData['karang_taruna_id'],
                'nama_lengkap' => 'Superadmin',
                'nama_panggilan' => 'Superadmin',
                'username' => 'superadmin',
                'no_whatsapp' => '-',
                'rt' => 1,
                'role_level' => 'superadmin',
                'status_aktif' => 1,
                'password_must_change' => false
            ];
        } else {
            $userModel = new UserModel();
            $user = $userModel->find($tokenData['user_id']);

            if (!$user || $user['status_aktif'] != 1) {
                return Services::response()
                    ->setJSON(['status' => false, 'message' => 'Unauthenticated'])
                    ->setStatusCode(401);
            }

            // --- ACTIVE TENANT MEMBERSHIP RESOLUTION ---
            
            // 1. Identify if this is a global endpoint or tenant endpoint
            $globalPaths = ['api/me', 'api/logout', 'api/profile', 'api/fcm-token', 'api/memberships'];
            $isGlobal = false;
            $currentPath = ltrim($request->getUri()->getPath(), '/');
            if (strpos($currentPath, 'index.php/') === 0) {
                $currentPath = substr($currentPath, 10);
            }
            foreach ($globalPaths as $path) {
                if (strpos($currentPath, $path) === 0) {
                    $isGlobal = true;
                    break;
                }
            }

            $headerTenantId = $request->getHeaderLine('X-Karang-Taruna-ID');
            if (empty($headerTenantId) && !empty($tokenData['karang_taruna_id'])) {
                $headerTenantId = $tokenData['karang_taruna_id'];
            }
            $memberModel = new \App\Models\OrganizationMemberModel();
            
            if (!empty($headerTenantId)) {
                $membership = $memberModel->where('user_id', $user['id'])
                                          ->where('karang_taruna_id', $headerTenantId)
                                          ->first();
                if (!$membership || (int)$membership['status_aktif'] !== 1) {
                    return Services::response()
                        ->setJSON(['status' => false, 'message' => 'Membership is inactive or denied'])
                        ->setStatusCode(403);
                }
                
                // Override legacy context
                $user['karang_taruna_id'] = $membership['karang_taruna_id'];
                $user['role_level'] = $membership['role_level'];
                if (!empty($membership['username'])) {
                    $user['username'] = $membership['username'];
                }
                
            } else {
                // Header is absent
                $activeMemberships = $memberModel->where('user_id', $user['id'])
                                                 ->where('status_aktif', 1)
                                                 ->findAll();
                                                 
                if (count($activeMemberships) === 1) {
                    // Auto-select single membership
                    $membership = $activeMemberships[0];
                    $user['karang_taruna_id'] = $membership['karang_taruna_id'];
                    $user['role_level'] = $membership['role_level'];
                    if (!empty($membership['username'])) {
                        $user['username'] = $membership['username'];
                    }
                } elseif (count($activeMemberships) > 1) {
                    // Ambiguous
                    if (!$isGlobal) {
                        return Services::response()
                            ->setJSON(['status' => false, 'message' => 'Active organization required. Please provide X-Karang-Taruna-ID header'])
                            ->setStatusCode(400);
                    }
                } else {
                    // count == 0. Safety fallback for legacy transition
                    if (empty($user['karang_taruna_id'])) {
                        // If the user has no memberships AND no legacy ID, block them unless it's a global endpoint (e.g. logout)
                        if (!$isGlobal) {
                            return Services::response()
                                ->setJSON(['status' => false, 'message' => 'No active organization memberships found'])
                                ->setStatusCode(403);
                        }
                    }
                    // If they have legacy karang_taruna_id, we let it pass for now using global legacy values.
                }
            }
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
