<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;

final class SafeImageUpload
{
    public static function store(UploadedFile $file, string $directory, bool $crop, ?string $root = null): string
    {
        if (!in_array($directory, ['uploads/users/profile/', 'uploads/karang_taruna/logos/'], true)) {
            throw new \InvalidArgumentException('Invalid image destination');
        }
        $source = $file->getTempName();
        $size = @getimagesize($source);
        if ($size === false || !in_array($size['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)
            || $size[0] < 1 || $size[1] < 1 || $size[0] > 8192 || $size[1] > 8192
            || $size[0] * $size[1] > 16000000 || filesize($source) > 5 * 1024 * 1024) {
            throw new \RuntimeException('Image cannot be processed');
        }
        $path = ($root ?? FCPATH) . $directory;
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            throw new \RuntimeException('Image destination unavailable');
        }
        // The client filename/extension never determines stored format.
        $relative = $directory . bin2hex(random_bytes(16)) . '.png';
        $target = ($root ?? FCPATH) . $relative;
        try {
            $image = \Config\Services::image()->withFile($source);
            $image = $crop ? $image->fit(512, 512, 'center') : $image->resize(512, 512, true, 'auto');
            if (!$image->convert(IMAGETYPE_PNG)->save($target, 85)) {
                throw new \RuntimeException('Image encoding failed');
            }
            $output = @getimagesize($target);
            if ($output === false || ($output['mime'] ?? '') !== 'image/png'
                || $output[0] > 512 || $output[1] > 512) {
                throw new \RuntimeException('Image encoding failed');
            }
            return $relative;
        } catch (\Throwable $error) {
            if (is_file($target)) @unlink($target);
            throw new \RuntimeException('Image cannot be processed');
        }
    }
}
