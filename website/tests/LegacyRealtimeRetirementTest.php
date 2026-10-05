<?php

namespace Tests;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockInputOutput;
use Composer\InstalledVersions;

class LegacyRealtimeRetirementTest extends CIUnitTestCase
{
    public function testLegacyHandlerCannotBeLoaded(): void
    {
        $this->assertFileDoesNotExist(APPPATH . 'Libraries/ChatServer.php');
        $this->assertFalse(class_exists('App\\Libraries\\ChatServer'));
    }

    public function testSupportedCommandRefusesStartupWithNonzeroExit(): void
    {
        $io = new MockInputOutput();
        CLI::setInputOutput($io);
        try {
            // Exercise the actual command registry/dispatcher, not a source string.
            $exit = service('commands')->run('websocket:serve', ['--port', '8081']);
            $this->assertSame(EXIT_ERROR, $exit);
            $this->assertStringContainsString('is retired', $io->getOutput());
            $this->assertStringContainsString('chat-server/server.js', $io->getOutput());
        } finally {
            CLI::resetInputOutput();
        }
    }

    public function testRatchetIsAbsentFromDeclaredAndInstalledDependencies(): void
    {
        $manifest = json_decode(file_get_contents(ROOTPATH . 'composer.json'), true);
        $this->assertArrayNotHasKey('cboden/ratchet', $manifest['require']);
        $this->assertFalse(InstalledVersions::isInstalled('cboden/ratchet'));
        $this->assertFalse(InstalledVersions::isInstalled('ratchet/rfc6455'));
    }
}
