<?php

use CodeIgniter\Test\CIUnitTestCase;

final class DiagnosticCredentialGuardTest extends CIUnitTestCase
{
    public function testDiagnosticBoundariesWithoutNetwork(): void
    {
        foreach (['test_form.php', 'test_request.php'] as $name) {
            $path = dirname(__DIR__, 2) . '/' . $name;
            $source = file_get_contents($path);
            $this->assertFalse((bool) preg_match('/Authorization:\s*Bearer\s+[A-Za-z0-9._~-]{16,}/', $source), 'No literal bearer allowed');
            foreach (['missing', 'empty', 'malformed', 'missing-url', 'remote', 'valid'] as $case) {
                $probe = tempnam(sys_get_temp_dir(), 'kartar-diagnostic-');
                $code = <<<'PHP'
<?php
$case = $argv[1];
$synthetic = 'synthetic_' . bin2hex(random_bytes(16));
putenv('KARTAR_DIAGNOSTIC_BEARER');
putenv('KARTAR_DIAGNOSTIC_BASE_URL');
putenv('KARTAR_DIAGNOSTIC_ALLOW_REMOTE');
putenv('KARTAR_DIAGNOSTIC_OLD_PASSWORD=synthetic-old');
putenv('KARTAR_DIAGNOSTIC_NEW_PASSWORD=synthetic-new');
if ($case !== 'missing') putenv('KARTAR_DIAGNOSTIC_BEARER=' . ($case === 'empty' ? '' : ($case === 'malformed' ? "invalid\r\nheader" : $synthetic)));
if ($case !== 'missing-url') putenv('KARTAR_DIAGNOSTIC_BASE_URL=' . ($case === 'remote' ? 'https://example.invalid' : 'http://127.0.0.1:12345'));
foreach (['CURLOPT_RETURNTRANSFER','CURLOPT_POST','CURLOPT_FOLLOWLOCATION','CURLOPT_CONNECTTIMEOUT','CURLOPT_TIMEOUT','CURLOPT_PROXY','CURLOPT_HTTPHEADER','CURLOPT_POSTFIELDS','CURLINFO_HTTP_CODE'] as $i => $name) define($name, $i + 1);
function curl_init($url) { if ($GLOBALS['case'] !== 'valid') { echo 'UNSAFE REQUEST'; exit(9); } return $url; }
function curl_setopt_array($ch, $options) {
    $ok = $options[CURLOPT_HTTPHEADER][0] === 'Authorization: Bearer ' . $GLOBALS['synthetic']
        && $options[CURLOPT_FOLLOWLOCATION] === false
        && $options[CURLOPT_TIMEOUT] === 10
        && str_contains($options[CURLOPT_POSTFIELDS], 'synthetic-new');
    if (!$ok) { echo 'BAD RUNTIME BOUNDARY'; exit(9); }
}
function curl_exec($ch) { return 'NEVER-PRINT-RESPONSE ' . $GLOBALS['synthetic']; }
function curl_getinfo($ch, $key) { return 200; }
function curl_close($ch) {}
require $argv[2];
PHP;
                file_put_contents($probe, $code);
                try {
                    $pipes = [];
                    $process = proc_open([PHP_BINARY, '-n', $probe, $case, $path], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
                    $this->assertIsResource($process);
                    fclose($pipes[0]);
                    $out = stream_get_contents($pipes[1]);
                    $err = stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    $exit = proc_close($process);
                    $this->assertSame($case === 'valid' ? 0 : 1, $exit);
                    $this->assertSame($case === 'valid' ? "HTTP status: 200\n" : '', $out);
                    $this->assertSame($case === 'valid' ? '' : "Diagnostic refused: explicit valid runtime inputs are required.\n", $err);
                } finally {
                    unlink($probe);
                }
            }
        }
    }
}
