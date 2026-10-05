<?php

namespace App\Services;

/** Read-only candidates; never deletes or reads file contents. */
final class OrphanUploadReport
{
    public function run(int $limit = 100): array
    {
        $limit = max(1, min($limit, 1000));
        $root = realpath(FCPATH);
        if (!$root || dirname($root) === $root) throw new \RuntimeException('Upload report requires a real application document root');
        $normalize = static fn(string $path): string => DIRECTORY_SEPARATOR === '\\' ? strtolower(str_replace('\\', '/', $path)) : $path;
        $paths = []; $visited = 0; $truncated = false;
        foreach (['uploads/users/profile', 'uploads/karang_taruna/logos'] as $relative) {
            $directory = realpath(FCPATH . $relative);
            if (!$directory) continue;
            if ($normalize($directory) !== rtrim($normalize($root), '/') . '/' . $relative) throw new \RuntimeException('Upload directory boundary failed');
            foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $entry) {
                if ($visited >= $limit) { $truncated = true; break 2; }
                $visited++;
                if ($entry->isLink() || !$entry->isFile() || !preg_match('/\A[a-f0-9]{32}\.png\z/', $entry->getFilename())) continue;
                if ($entry->getMTime() > time() - 86400) continue; // In-flight/recent uploads are not candidates.
                $paths[] = $relative . '/' . $entry->getFilename();
            }
        }
        $referenced = [];
        if ($paths) {
            $db = \Config\Database::connect();
            foreach (['users' => 'profile_photo', 'karang_taruna' => 'logo_path'] as $table => $column) {
                foreach ($db->table($table)->select($column)->whereIn($column, $paths)->get()->getResultArray() as $row) $referenced[$row[$column]] = true;
            }
        }
        return ['read_only' => true, 'visited' => $visited, 'truncated' => $truncated,
            'candidates' => array_values(array_filter($paths, static fn($path) => !isset($referenced[$path]))), 'deleted' => 0];
    }
}
