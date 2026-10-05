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
        \App\Services\AuthService::setUser(null);
        \App\Services\AuthService::setToken(null);
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

        if (empty($tokenData['user_id']) || $tokenData['user_id'] == 0) {
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
            $currentPath = ltrim($request->getUri()->getPath(), '/');
            if (strpos($currentPath, 'index.php/') === 0) {
                $currentPath = substr($currentPath, 10);
            }
            // Membership administration requires tenant authorization. Only the
            // exact discovery path is global; profile subroutes are user-owned.
            $isGlobal = in_array($currentPath, $globalPaths, true)
                || strpos($currentPath, 'api/profile/') === 0;

            $headerTenantId = trim($request->getHeaderLine('X-Karang-Taruna-ID'));
            if ($headerTenantId === '' && !empty($tokenData['karang_taruna_id'])) {
                $headerTenantId = (string)$tokenData['karang_taruna_id'];
            }
            $memberModel = new \App\Models\OrganizationMemberModel();

            // Do not retain a legacy tenant/role when no eligible membership is
            // selected, including on global profile/logout/discovery endpoints.
            $user['karang_taruna_id'] = null;
            $user['role_level'] = null;
            $membership = null;

            if ($headerTenantId !== '') {
                $tenantId = filter_var($headerTenantId, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);
                $memberships = $tenantId === false
                    ? [] : $memberModel->getEligibleMemberships((int)$user['id'], $tenantId);
                $membership = $memberships[0] ?? null;
                if (!$membership) {
                    return Services::response()
                        ->setJSON([
                            'status' => false, 
                            'message' => 'Membership is inactive or denied',
                            'errorCode' => 'TENANT_ACCESS_REVOKED'
                        ])
                        ->setStatusCode(403);
                }
                
            } else {
                // Header is absent
                $activeMemberships = $memberModel->getEligibleMemberships((int)$user['id']);
                                                 
                if (count($activeMemberships) === 1) {
                    // Auto-select single membership
                    $membership = $activeMemberships[0];
                } elseif (count($activeMemberships) > 1) {
                    // Ambiguous
                    if (!$isGlobal) {
                        return Services::response()
                            ->setJSON(['status' => false, 'message' => 'Active organization required. Please provide X-Karang-Taruna-ID header'])
                            ->setStatusCode(400);
                    }
                } else {
                    // Legacy accounts are backfilled by the membership migration.
                    // Missing/invalid membership must fail closed on tenant APIs.
                    if (!$isGlobal) {
                        return Services::response()
                            ->setJSON([
                                'status' => false,
                                'message' => 'No active organization memberships found',
                                'errorCode' => 'ACTIVE_MEMBERSHIP_REVOKED'
                            ])
                            ->setStatusCode(403);
                    }
                }
            }

            if ($membership !== null) {
                $user['karang_taruna_id'] = $membership['karang_taruna_id'];
                $user['role_level'] = $membership['role_level'];
                if (!empty($membership['username'])) {
                    $user['username'] = $membership['username'];
                }
            }
        }

        // Force password change check
        if ((int)($user['password_must_change'] ?? 0) === 1) {
            // Allow only specific paths
            $allowedPaths = ['api/me' => ['GET'], 'api/logout' => ['POST'], 'api/profile/password' => ['POST', 'PATCH']];
            $currentPath = ltrim($request->getUri()->getPath(), '/');
            if (strpos($currentPath, 'index.php/') === 0) {
                $currentPath = substr($currentPath, 10);
            }
            $isAllowed = in_array(strtoupper($request->getMethod()), $allowedPaths[$currentPath] ?? [], true);
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
        return (new \App\Filters\AbuseFilter())->before($request);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}
