<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkshopPhotoOptimizer
{
    private const MAX_DIMENSION = 1600;

    private const JPEG_QUALITY = 76;

    /**
     * Guarda una imagen redimensionada/comprimida en disco público.
     */
    public static function storeOptimized(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return $file->store($directory, $disk);
        }

        $contents = @file_get_contents($file->getRealPath());
        if ($contents === false || $contents === '') {
            return $file->store($directory, $disk);
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return $file->store($directory, $disk);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($image);

            return $file->store($directory, $disk);
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            $ratio = min(self::MAX_DIMENSION / $width, self::MAX_DIMENSION / $height);
            $newWidth = max(1, (int) round($width * $ratio));
            $newHeight = max(1, (int) round($height * $ratio));
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            if ($resized === false) {
                imagedestroy($image);

                return $file->store($directory, $disk);
            }
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
            $width = $newWidth;
            $height = $newHeight;
        }

        ob_start();
        $written = imagejpeg($image, null, self::JPEG_QUALITY);
        $jpegData = ob_get_clean();
        imagedestroy($image);

        if (! $written || ! is_string($jpegData) || $jpegData === '') {
            return $file->store($directory, $disk);
        }

        if (strlen($jpegData) >= $file->getSize()) {
            return $file->store($directory, $disk);
        }

        Storage::disk($disk)->makeDirectory($directory);
        $relativePath = $directory.'/'.Str::uuid()->toString().'.jpg';
        Storage::disk($disk)->put($relativePath, $jpegData);

        return $relativePath;
    }
}
