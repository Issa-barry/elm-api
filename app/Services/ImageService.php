<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ImageService
{
    private const MAX_WIDTH    = 1200;
    private const QUALITY      = 80;
    private const WEBP_EXT     = 'webp';

    /**
     * Compresse, redimensionne et stocke une image en WebP.
     *
     * @return string  Le path stocké (relatif au disk)
     */
    public function storeCompressed(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $filename = self::WEBP_EXT === pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION)
            ? 'image.webp'
            : 'image.webp';

        $path = ltrim($directory, '/') . '/' . $filename;

        $image = Image::read($file->getRealPath());

        // Redimensionner uniquement si l'image est plus large que MAX_WIDTH
        if ($image->width() > self::MAX_WIDTH) {
            $image->scaleDown(width: self::MAX_WIDTH);
        }

        $encoded = $image->toWebp(quality: self::QUALITY);

        Storage::disk($disk)->put($path, (string) $encoded);

        return $path;
    }

    /**
     * Supprime une image depuis son URL publique stockée en DB.
     */
    public function deleteByUrl(string $imageUrl, string $disk = 'public'): void
    {
        $storageUrl = Storage::disk($disk)->url('');
        $path       = ltrim(str_replace(rtrim($storageUrl, '/'), '', $imageUrl), '/');

        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
