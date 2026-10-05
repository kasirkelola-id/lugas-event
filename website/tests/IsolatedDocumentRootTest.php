<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

final class IsolatedDocumentRootTest extends CIUnitTestCase
{
    public function test_document_root_and_traversal_fixture_stay_inside_owned_temp_namespace(): void
    {
        $root = realpath(TEST_FIXTURE_ROOT);
        $this->assertSame(realpath(sys_get_temp_dir()), dirname($root));
        $this->assertMatchesRegularExpression('/\Akartar-phpunit-[a-f0-9]{16}\z/', basename($root));
        $this->assertSame($root, realpath(FCPATH . '../..'));
        $this->assertSame(realpath(FCPATH), realpath(PUBLICPATH));
        $this->assertNotSame(realpath(ROOTPATH), realpath(FCPATH));
    }
}
