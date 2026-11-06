<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/** Saves an uploaded picture under public/uploads as a web-sized WebP (falls back to the original file when GD cannot). */
class Img
{
    public static function save(UploadedFile $f, string $folder, string $prefix, int $max = 900): string
    {
        $dir = public_path('uploads/'.$folder);
        if (! is_dir($dir)) mkdir($dir, 0775, true);
        $name = preg_replace('/[^a-z0-9-]+/i', '-', $prefix).'-'.now()->format('YmdHis').'-'.substr(bin2hex(random_bytes(3)), 0, 6);
        $ext = strtolower($f->extension() ?: $f->getClientOriginalExtension());
        $src = null;
        if (function_exists('imagewebp')) {
            $src = match ($ext) {
                'png' => @imagecreatefrompng($f->getRealPath()),
                'jpg', 'jpeg' => @imagecreatefromjpeg($f->getRealPath()),
                'webp' => @imagecreatefromwebp($f->getRealPath()),
                default => null,
            };
        }
        if ($src) {
            imagepalettetotruecolor($src);
            $w = imagesx($src); $h = imagesy($src);
            $k = min(1, $max / max($w, $h));
            if ($k < 1) {
                $nw = (int) round($w * $k); $nh = (int) round($h * $k);
                $dst = imagecreatetruecolor($nw, $nh);
                imagealphablending($dst, false); imagesavealpha($dst, true);
                imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($src); $src = $dst;
            }
            imagealphablending($src, true); imagesavealpha($src, true);
            imagewebp($src, $dir.'/'.$name.'.webp', 84);
            imagedestroy($src);
            return '/uploads/'.$folder.'/'.$name.'.webp';
        }
        $f->move($dir, $name.'.'.$ext);
        return '/uploads/'.$folder.'/'.$name.'.'.$ext;
    }

    /** Remove a file this helper saved (only inside public/uploads). */
    public static function delete(?string $path): void
    {
        if (! $path || ! str_starts_with($path, '/uploads/') || str_contains($path, '..')) return;
        $full = public_path(ltrim($path, '/'));
        if (is_file($full)) @unlink($full);
    }
}
