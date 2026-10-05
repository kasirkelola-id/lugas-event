<?php

namespace App\Services;

/** Local atomic fixed windows. Each process shares the same writable directory. */
final class AbuseLimiter
{
    public function __construct(private ?string $directory = null)
    {
        $this->directory ??= WRITEPATH . 'cache/abuse/';
    }

    public function consume(string $key, int $capacity, int $seconds, ?int $now = null): bool
    {
        $now ??= time();
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Abuse limit storage unavailable');
        }
        $file = @fopen($this->directory . '/' . hash('sha256', $key) . '.json', 'c+');
        if ($file === false) throw new \RuntimeException('Abuse limit storage unavailable');
        try {
            if (!flock($file, LOCK_EX)) throw new \RuntimeException('Abuse limit lock unavailable');
            $state = json_decode(stream_get_contents($file), true);
            if (!is_array($state) || ($state['until'] ?? 0) <= $now) $state = ['until' => $now + $seconds, 'count' => 0];
            if ($state['count'] >= $capacity) return false;
            $state['count']++;
            rewind($file);
            if (!ftruncate($file, 0) || fwrite($file, json_encode($state)) === false || !fflush($file)) {
                throw new \RuntimeException('Abuse limit write unavailable');
            }
            return true;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}
