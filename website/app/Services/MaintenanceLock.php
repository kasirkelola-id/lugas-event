<?php

namespace App\Services;

/** Local single-instance maintenance lock; no claim of distributed exclusion. */
final class MaintenanceLock
{
    private function __construct(private $handle) {}

    public static function acquire(string $scope): ?self
    {
        $directory = WRITEPATH . 'cache';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new \RuntimeException('Maintenance lock directory unavailable');
        $path = $directory . DIRECTORY_SEPARATOR . 'maintenance-' . hash('sha256', $scope) . '.lock';
        if (is_link($path)) throw new \RuntimeException('Maintenance lock path unavailable');
        $handle = fopen($path, 'c');
        if (!$handle) throw new \RuntimeException('Maintenance lock unavailable');
        if (!flock($handle, LOCK_EX | LOCK_NB)) { fclose($handle); return null; }
        return new self($handle);
    }

    public function release(): void
    {
        if (is_resource($this->handle)) { flock($this->handle, LOCK_UN); fclose($this->handle); }
        $this->handle = null;
    }

    public function __destruct() { $this->release(); }
}
