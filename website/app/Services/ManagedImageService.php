<?php

namespace App\Services;

/** Database changes commit before managed files are retired. */
final class ManagedImageService
{
    private const COLUMNS = ['users' => 'profile_photo', 'karang_taruna' => 'logo_path'];

    public static function change(string $table, int $id, array $data, bool $delete = false): void
    {
        $column = self::COLUMNS[$table] ?? throw new \InvalidArgumentException('Invalid image owner');
        $db = \Config\Database::connect();
        if ($db->transDepth !== 0 || !$db->transStatus() || !$db->transBegin()) throw new \RuntimeException('Image transaction unavailable');
        try {
            $row = $db->query('SELECT * FROM ' . $table . ' WHERE id = ?' . ($db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : ''), [$id])->getRowArray();
            if (!$row) throw new \RuntimeException('Image owner unavailable');
            if (!$delete && array_key_exists('updated_at', $row)) $data['updated_at'] = date('Y-m-d H:i:s');
            $builder = $db->table($table)->where('id', $id);
            if (!($delete ? $builder->delete() : $builder->update($data)) || !$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('Image write failed');
            }
        } catch (\Throwable $error) {
            if ($db->transDepth > 0) $db->transRollback();
            throw $error;
        }
        if ($delete || (array_key_exists($column, $data) && $data[$column] !== $row[$column])) self::retire($row[$column]);
    }

    public static function createOrganization(array $data): void
    {
        $db = \Config\Database::connect();
        if ($db->transDepth !== 0 || !$db->transStatus() || !$db->transBegin()) throw new \RuntimeException('Organization transaction unavailable');
        try {
            $id = (new \App\Models\KarangTarunaModel())->insert($data);
            if (!$id || !(new \App\Models\ChatRoomModel())->insert([
                'karang_taruna_id' => $id, 'name' => 'Forum ' . $data['nama_organisasi'],
                'type' => 'default', 'created_at' => date('Y-m-d H:i:s'),
            ]) || !$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Organization write failed');
        } catch (\Throwable $error) {
            if ($db->transDepth > 0) $db->transRollback();
            throw $error;
        }
    }

    /** Failed cleanup leaves an orphan for reconciliation, never undoes a committed row. */
    public static function retire(?string $path): void
    {
        if (!$path || !preg_match('~\Auploads/(?:users/profile|karang_taruna/logos)/[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp)\z~D', $path)) return;
        try {
            $db = \Config\Database::connect();
            foreach (self::COLUMNS as $table => $column) {
                if ($db->table($table)->where($column, $path)->countAllResults() > 0) return;
            }
            $root = realpath(FCPATH);
            $directory = realpath(FCPATH . dirname($path));
            $target = realpath(FCPATH . $path);
            if (!$root || !$directory || !$target || is_link(FCPATH . $path)) return;
            $normalize = static fn(string $value): string => DIRECTORY_SEPARATOR === '\\'
                ? strtolower(str_replace('\\', '/', $value)) : $value;
            $expected = rtrim($normalize($root), '/') . '/' . dirname($path);
            if ($normalize($directory) !== $expected || $normalize(dirname($target)) !== $expected || !is_file($target)) return;
            @unlink($target);
        } catch (\Throwable $error) {
            // A database or filesystem failure must preserve potentially referenced files.
        }
    }
}
