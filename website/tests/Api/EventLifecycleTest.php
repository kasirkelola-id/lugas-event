<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class EventLifecycleTest extends \Tests\Support\BaseTest
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use \Tests\Support\AuthTrait;

    protected $migrateOnce = true;
    protected $refresh = false;
    protected $migrate = true;
    protected $namespace = 'App';

    protected $ketuaUser;
    protected $anggotaUser;
    protected $ketuaToken;
    protected $anggotaToken;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->ketuaUser = $this->createTestUser(1, 'ketua');
        $this->anggotaUser = $this->createTestUser(1, 'anggota');

        $this->ketuaToken = $this->generateTokenForUser($this->ketuaUser);
        $this->anggotaToken = $this->generateTokenForUser($this->anggotaUser);
    }

    public function testUnauthorizedCloseRejected()
    {
        $token = $this->anggotaToken;
        
        $this->db->table('events')->insert([
            'id' => 1,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event To Close',
            'tanggal_acara' => date('Y-m-d'),
            'waktu_mulai' => date('H:i:s'),
            'waktu_selesai' => date('H:i:s', strtotime('+2 hours')),
            'status_aktif' => 'aktif',
            'dibuat_oleh' => $this->ketuaUser['id'],
            'kode_qr' => 'TESTQRCLOSE1'
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->withBodyFormat('json')
                         ->patch('api/events/1/status', [
                             'status_aktif' => 0
                         ]);

        $response->assertStatus(403);
    }

    public function testAuthorizedCloseIdempotent()
    {
        $token = $this->ketuaToken;
        
        $this->db->table('events')->insert([
            'id' => 2,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event To Close 2',
            'tanggal_acara' => date('Y-m-d'),
            'waktu_mulai' => date('H:i:s'),
            'waktu_selesai' => date('H:i:s', strtotime('+2 hours')),
            'status_aktif' => 'aktif',
            'dibuat_oleh' => $this->ketuaUser['id'],
            'kode_qr' => 'TESTQRCLOSE2'
        ]);

        // First close
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->withBodyFormat('json')
                         ->patch('api/events/2/status', [
                             'status_aktif' => 0
                         ]);
        $response1->assertStatus(200);

        // Check DB
        $event = $this->db->table('events')->where('id', 2)->get()->getRowArray();
        $this->assertEquals('selesai', $event['status_aktif']);

        // Second close (idempotent)
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->withBodyFormat('json')
                         ->patch('api/events/2/status', [
                             'status_aktif' => 0
                         ]);
        $response2->assertStatus(200);
    }

    public function testManualReopen()
    {
        $token = $this->ketuaToken;
        
        $this->db->table('events')->insert([
            'id' => 3,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event To Reopen',
            'tanggal_acara' => date('Y-m-d'),
            'waktu_mulai' => date('H:i:s'),
            'waktu_selesai' => date('H:i:s', strtotime('+2 hours')),
            'status_aktif' => 'selesai',
            'dibuat_oleh' => $this->ketuaUser['id'],
            'kode_qr' => 'TESTQRREOPEN'
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->withBodyFormat('json')
                         ->patch('api/events/3/reopen', []);
                         
        $response->assertStatus(200);

        // Check DB
        $event = $this->db->table('events')->where('id', 3)->get()->getRowArray();
        $this->assertEquals('aktif', $event['status_aktif']);
    }

    public function testUnauthorizedReopen()
    {
        $token = $this->anggotaToken; // Anggota cannot reopen
        
        $this->db->table('events')->insert([
            'id' => 4,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event To Reopen Unauthorized',
            'tanggal_acara' => date('Y-m-d'),
            'waktu_mulai' => date('H:i:s'),
            'waktu_selesai' => date('H:i:s', strtotime('+2 hours')),
            'status_aktif' => 'selesai',
            'dibuat_oleh' => $this->ketuaUser['id'],
            'kode_qr' => 'TESTQRREOPENUNAUTH'
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->withBodyFormat('json')
                         ->patch('api/events/4/reopen', []);
                         
        $response->assertStatus(403);
    }

}
