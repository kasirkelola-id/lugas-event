<?php

namespace Tests\Support;

use App\Models\UserModel;
use App\Models\UserTokenModel;

trait AuthTrait
{
    /**
     * @var array
     */
    protected $createdUsers = [];

    /**
     * @var array
     */
    protected $createdTokens = [];

    /**
     * Creates a dummy user for testing.
     */
    public function createTestUser($karangTarunaId, $roleLevel = 'anggota', $username = null)
    {
        $db = \Config\Database::connect();
        
        // Seed karang_taruna to satisfy FK if not exists
        $kt = $db->table('karang_taruna')->where('id', $karangTarunaId)->get()->getRowArray();
        if (!$kt) {
            $db->table('karang_taruna')->insert([
                'id' => $karangTarunaId,
                'nama_organisasi' => 'Test Karang Taruna ' . $karangTarunaId,
                'kode_pin' => rand(100000, 999999),
                'alamat_lengkap' => 'Test Alamat',
                'status_aktif' => 1
            ]);
        }

        $userModel = new UserModel();
        
        $username = $username ?? 'testuser_' . uniqid();
        $userData = [
            'karang_taruna_id' => $karangTarunaId,
            'nama_lengkap' => 'Test ' . ucfirst($roleLevel),
            'nama_panggilan' => 'Test',
            'username' => $username,
            'password' => password_hash('password123', PASSWORD_BCRYPT),
            'no_whatsapp' => '08123456789',
            'rt' => 1,
            'role_level' => $roleLevel,
            'status_aktif' => 1,
            'password_must_change' => 0
        ];

        $userId = $userModel->insert($userData);
        $userData['id'] = $userId;
        
        $this->createdUsers[] = $userId;

        // Ensure organization_members is populated
        $db->table('organization_members')->insert([
            'user_id' => $userId,
            'karang_taruna_id' => $karangTarunaId,
            'username' => $username,
            'role_level' => $roleLevel,
            'status_aktif' => 1,
            'joined_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $userData;
    }

    /**
     * Generates a bearer token for a given user.
     */
    public function generateTokenForUser($user)
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        
        $tokenModel = new UserTokenModel();
        $tokenId = $tokenModel->insert([
            'karang_taruna_id' => $user['karang_taruna_id'],
            'user_id' => $user['id'],
            'token_hash' => $tokenHash,
            'device_name' => 'TestUnit',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'last_used_at' => date('Y-m-d H:i:s'),
        ]);

        $this->createdTokens[] = $tokenId;

        return $token;
    }

    /**
     * Generates standard Auth headers for FeatureTest.
     */
    public function getAuthHeaders($token)
    {
        return [
            'Authorization' => 'Bearer ' . $token
        ];
    }

    /**
     * Clean up tokens and users after tests
     */
    protected function cleanUpAuth()
    {
        if (!empty($this->createdTokens)) {
            $tokenModel = new UserTokenModel();
            $tokenModel->whereIn('id', $this->createdTokens)->delete(null, true);
        }
        if (!empty($this->createdUsers)) {
            $userModel = new UserModel();
            $userModel->whereIn('id', $this->createdUsers)->delete(null, true);
        }
    }
}
