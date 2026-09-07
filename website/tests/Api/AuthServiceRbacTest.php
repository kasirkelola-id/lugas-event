<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\AuthService;
use CodeIgniter\Exceptions\PageNotFoundException;

class AuthServiceRbacTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset user before each test
        AuthService::setUser(null);
    }

    public function testUnknownRoleIsDenied()
    {
        AuthService::setUser(['role_level' => 'unknown_role']);
        $this->assertFalse(AuthService::can('cash.view'));
    }

    public function testUnknownPermissionIsDenied()
    {
        AuthService::setUser(['role_level' => 'ketua']);
        $this->assertFalse(AuthService::can('unknown.permission'));
    }

    public function testNoRoleIsDenied()
    {
        AuthService::setUser(['role_level' => null]);
        $this->assertFalse(AuthService::can('cash.view'));
    }

    public function testKetuaPermissions()
    {
        AuthService::setUser(['role_level' => 'ketua']);
        $this->assertTrue(AuthService::can('cash.view'));
        $this->assertTrue(AuthService::can('cash.create'));
        $this->assertTrue(AuthService::can('inventory.approve'));
        $this->assertTrue(AuthService::can('members.change_role'));
        $this->assertTrue(AuthService::can('members.approve'));
    }

    public function testBendaharaPermissions()
    {
        AuthService::setUser(['role_level' => 'bendahara']);
        $this->assertTrue(AuthService::can('cash.view'));
        $this->assertTrue(AuthService::can('cash.create'));
        $this->assertFalse(AuthService::can('inventory.approve'));
        $this->assertFalse(AuthService::can('members.change_role'));
        $this->assertTrue(AuthService::can('members.approve'));
    }

    public function testSekretarisPermissions()
    {
        AuthService::setUser(['role_level' => 'sekretaris']);
        $this->assertTrue(AuthService::can('announcement.manage'));
        $this->assertFalse(AuthService::can('cash.create'));
        $this->assertTrue(AuthService::can('members.approve'));
    }

    public function testPengelolaPermissions()
    {
        AuthService::setUser(['role_level' => 'pengelola']);
        $this->assertTrue(AuthService::can('event.manage'));
        $this->assertFalse(AuthService::can('cash.create'));
        $this->assertFalse(AuthService::can('members.approve'));
    }

    public function testAnggotaPermissions()
    {
        AuthService::setUser(['role_level' => 'anggota']);
        $this->assertTrue(AuthService::can('cash.view'));
        $this->assertFalse(AuthService::can('cash.create'));
        $this->assertFalse(AuthService::can('inventory.approve'));
        $this->assertFalse(AuthService::can('members.approve'));
    }

    public function testAdminPermissions()
    {
        AuthService::setUser(['role_level' => 'admin']);
        $this->assertTrue(AuthService::can('members.manage'));
        $this->assertFalse(AuthService::can('members.approve'));
    }

    public function testSuperadminPermissions()
    {
        AuthService::setUser(['role_level' => 'superadmin']);
        $this->assertTrue(AuthService::can('members.approve'));
    }

    public function testSameUserDifferentMembershipRole()
    {
        // Simulate active membership Tenant A = ketua
        AuthService::setUser([
            'id' => 1,
            'karang_taruna_id' => 101,
            'role_level' => 'ketua'
        ]);
        $this->assertTrue(AuthService::can('inventory.approve'), "Tenant A (Ketua) should be able to approve inventory");

        // Simulate active membership Tenant B = anggota
        AuthService::setUser([
            'id' => 1,
            'karang_taruna_id' => 102,
            'role_level' => 'anggota'
        ]);
        $this->assertFalse(AuthService::can('inventory.approve'), "Tenant B (Anggota) should not be able to approve inventory");
    }

    public function testRequirePermissionSuccess()
    {
        AuthService::setUser(['role_level' => 'ketua']);
        // Should not throw exception
        AuthService::requirePermission('cash.create');
        $this->assertTrue(true);
    }

    public function testRequirePermissionThrowsException()
    {
        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionMessage('Forbidden: cash.create');

        AuthService::setUser(['role_level' => 'anggota']);
        AuthService::requirePermission('cash.create');
    }
}
