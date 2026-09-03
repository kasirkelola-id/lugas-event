<?php

namespace Tests\Api {

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\AuthTrait;
use App\Models\UserModel;

class ProfilePhotoTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use AuthTrait;
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = false;
    protected $refresh = true;
    protected $namespace = null;

    private $user;
    private $token;
    private $tenantId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->ignore(true)->insert([
            'id' => $this->tenantId,
            'nama_organisasi' => 'KT Test',
            'kode_pin' => '111111',
            'status_aktif' => 1
        ]);
        
        $this->user = $this->createTestUser($this->tenantId, 'anggota', 'photouser');
        $this->token = $this->generateTokenForUser($this->user);
    }

    public function testUploadPhotoAndReplaceOldPhoto()
    {
        // 1. Create a fake old photo file
        $oldPhotoPath = 'uploads/users/profile/old_test_photo.jpg';
        $fullOldPath = FCPATH . $oldPhotoPath;
        
        if (!is_dir(dirname($fullOldPath))) {
            mkdir(dirname($fullOldPath), 0777, true);
        }
        file_put_contents($fullOldPath, 'fake-old-image-content');
        $this->assertFileExists($fullOldPath);
        
        // 2. Set DB to point to old photo
        $userModel = new UserModel();
        $userModel->update($this->user['id'], ['profile_photo' => $oldPhotoPath]);
        
        // 3. Create a fake new photo upload file
        $tempUploadFile = tempnam(sys_get_temp_dir(), 'test_img');
        
        // Since we need it to pass validation, let's mock a valid small image (1x1 PNG)
        $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==';
        file_put_contents($tempUploadFile, base64_decode($pngBase64));

        $_FILES = [
            'photo' => [
                'name'     => 'new_photo.png',
                'type'     => 'image/png',
                'tmp_name' => $tempUploadFile,
                'error'    => 0,
                'size'     => filesize($tempUploadFile)
            ]
        ];

        $superglobals = \Config\Services::superglobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES, $_REQUEST, false);
        \Config\Services::injectMock('superglobals', $superglobals);

        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Karang-Taruna-ID' => (string)$this->tenantId
        ])->call('POST', '/api/profile/photo');

        $res->assertStatus(200);
        $json = json_decode($res->getJSON(), true);
        
        $this->assertNotNull($json['data']['profile_photo_url']);
        
        // 4. Verify old file is deleted
        $this->assertFileDoesNotExist($fullOldPath);
        
        // 5. Verify new file exists (and delete it to clean up)
        $newUrl = $json['data']['profile_photo_url'];
        $newPath = parse_url($newUrl, PHP_URL_PATH);
        $newPath = ltrim($newPath, '/');
        
        $this->assertFileExists(FCPATH . $newPath);
        @unlink(FCPATH . $newPath);
    }

    public function testOldFileMissingStillSucceeds()
    {
        // DB points to a file that doesn't exist
        $userModel = new UserModel();
        $userModel->update($this->user['id'], ['profile_photo' => 'uploads/users/profile/missing_file.jpg']);
        
        $tempUploadFile = tempnam(sys_get_temp_dir(), 'test_img');
        $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==';
        file_put_contents($tempUploadFile, base64_decode($pngBase64));

        $_FILES = [
            'photo' => [
                'name'     => 'new_photo.png',
                'type'     => 'image/png',
                'tmp_name' => $tempUploadFile,
                'error'    => 0,
                'size'     => filesize($tempUploadFile)
            ]
        ];

        $superglobals = \Config\Services::superglobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES, $_REQUEST, false);
        \Config\Services::injectMock('superglobals', $superglobals);

        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Karang-Taruna-ID' => (string)$this->tenantId
        ])->call('POST', '/api/profile/photo');

        $res->assertStatus(200);
        
        // Clean up
        $json = json_decode($res->getJSON(), true);
        $newUrl = $json['data']['profile_photo_url'];
        $newPath = parse_url($newUrl, PHP_URL_PATH);
        $newPath = ltrim($newPath, '/');
        @unlink(FCPATH . $newPath);
    }

    public function testDeletePhotoRemovesFile()
    {
        $photoPath = 'uploads/users/profile/to_be_deleted.jpg';
        $fullPath = FCPATH . $photoPath;
        
        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }
        file_put_contents($fullPath, 'fake-old-image-content');
        
        $userModel = new UserModel();
        $userModel->update($this->user['id'], ['profile_photo' => $photoPath]);
        
        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Karang-Taruna-ID' => (string)$this->tenantId
        ])->delete('/api/profile/photo');

        $res->assertStatus(200);
        
        // Verify DB is null
        $user = $userModel->find($this->user['id']);
        $this->assertNull($user['profile_photo']);
        
        // Verify file is deleted
        $this->assertFileDoesNotExist($fullPath);
    }
    
    public function testMaliciousOldPathNotDeleted()
    {
        // Malicious old path
        $maliciousPath = '../../important_file.txt';
        $fullPath = FCPATH . $maliciousPath;
        file_put_contents($fullPath, 'important content');
        
        $userModel = new UserModel();
        $userModel->update($this->user['id'], ['profile_photo' => $maliciousPath]);
        
        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Karang-Taruna-ID' => (string)$this->tenantId
        ])->delete('/api/profile/photo');

        $res->assertStatus(200);
        
        // The file should NOT be deleted!
        $this->assertFileExists($fullPath);
        
        @unlink($fullPath); // Clean up test
    }
}
}

namespace CodeIgniter\HTTP\Files {
    function is_uploaded_file(string $filename): bool
    {
        return file_exists($filename);
    }

    function move_uploaded_file(string $filename, string $destination): bool
    {
        return rename($filename, $destination);
    }
}
