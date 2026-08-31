<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImagePreviewGenerator
{
    /**
     * Compress an uploaded image into a lightweight WebP preview and store it on the given disk.
     *
     * The original upload is stored separately (untouched) so it stays downloadable at full
     * quality; this preview is only what gets rendered inline in the chat thread.
     *
     * @return string|null storage path of the generated preview, or null if the file isn't a
     *                     GD-readable image
     */
    public function generate(UploadedFile $file, string $directory = 'chat-attachments/previews', int $maxDimension = 1024, int $quality = 60): ?string
    {
        $source = @imagecreatefromstring(file_get_contents($file->getRealPath()));

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxDimension / max($width, $height));

        if ($scale < 1) {
            $targetWidth = (int) round($width * $scale);
            $targetHeight = (int) round($height * $scale);

            $resized = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        ob_start();
        imagewebp($source, null, $quality);
        $contents = ob_get_clean();
        imagedestroy($source);

        $path = $directory.'/'.uniqid('', true).'.webp';
        Storage::disk('s3')->put($path, $contents);

        return $path;
    }
}
