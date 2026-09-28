<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Image upload pipeline: validate → compress with GD → store on the public
 * disk.
 *
 * Shared-host constraint: no Imagick — GD is universally available. Every
 * upload is re-encoded (strip metadata / EXIF, kills PHP-in-image tricks),
 * downscaled to a max dimension, and saved as JPEG/WebP at quality 82.
 * Cover art targets ~1600px, avatars/banners ~800px; typical output is
 * 100–400 KB regardless of the source file size.
 */
class ImageUploadService
{
    /**
     * [max dimension, disk subdirectory, jpeg quality, alpha-preserving] per
     * variant. Alpha variants (logos, marks, future badges/frames) keep
     * PNG/WebP transparency — they are NEVER JPEG-re-encoded, because JPEG
     * has no alpha channel and would flatten every transparent logo onto a
     * black box.
     */
    public const VARIANTS = [
        'cover' => ['max' => 1600, 'dir' => 'covers', 'quality' => 82, 'alpha' => false],
        'banner' => ['max' => 1600, 'dir' => 'banners', 'quality' => 80, 'alpha' => false],
        'avatar' => ['max' => 512, 'dir' => 'avatars', 'quality' => 85, 'alpha' => false],
        'logo' => ['max' => 768, 'dir' => 'logos', 'quality' => 90, 'alpha' => true],
        'mark' => ['max' => 768, 'dir' => 'marks', 'quality' => 90, 'alpha' => true],
    ];

    public const MAX_INPUT_KB = 8192; // 8 MB hard ceiling before compression

    /**
     * Compress and store an upload. Returns the relative path for
     * Storage::url(). Throws on invalid input — callers catch and add
     * their own validation errors.
     *
     * @throws \RuntimeException
     */
    public function store(UploadedFile $file, string $variant): string
    {
        if (! isset(self::VARIANTS[$variant])) {
            throw new \InvalidArgumentException("Unknown image variant [{$variant}].");
        }

        if (! $file->isValid()) {
            throw new \RuntimeException('Upload failed — the file did not arrive intact.');
        }

        if ($file->getSize() > self::MAX_INPUT_KB * 1024) {
            throw new \RuntimeException('Image is larger than 8 MB. Please upload a smaller file.');
        }

        $mime = strtolower((string) $file->getMimeType());
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \RuntimeException('Only JPG, PNG or WebP images are accepted.');
        }

        // Real decode check — a renamed .php with an image mime fails here.
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
            default => false,
        };
        if ($src === false) {
            throw new \RuntimeException('That file is not a readable image.');
        }

        try {
            $cfg = self::VARIANTS[$variant];
            $image = $this->downscale($src, $cfg['max'], $cfg['alpha']);
            $quality = $cfg['quality'];

            if ($cfg['alpha']) {
                // Logos/marks keep transparency: PNG (or WebP for webp
                // sources). No background flattening, no EXIF risk (PNG has
                // none of consequence and GD strips everything anyway).
                imagealphablending($image, false);
                imagesavealpha($image, true);

                ob_start();
                imagepng($image, null, 6);
                $binary = (string) ob_get_clean();
                $extension = 'png';
            } else {
                // Photos: JPEG re-encode strips metadata and crushes size.
                ob_start();
                imagejpeg($image, null, $quality);
                $binary = (string) ob_get_clean();
                $extension = 'jpg';
            }

            $name = Str::random(40).'.'.$extension;
            $path = $cfg['dir'].'/'.$name;

            Storage::disk('public')->put($path, $binary);

            return $path;
        } finally {
            imagedestroy($src);
            if (isset($image) && $image !== $src) {
                imagedestroy($image);
            }
        }
    }

    /** Proportional downscale so no dimension exceeds $max. Alpha
     *  variants get a transparent canvas instead of GD's default black. */
    private function downscale(\GdImage $src, int $max, bool $preserveAlpha = false): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);

        if ($w <= $max && $h <= $max) {
            return $src;
        }

        $scale = min($max / $w, $max / $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);

        if ($preserveAlpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefill($dst, 0, 0, $transparent);
            imagealphablending($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    /** Delete a previous image (best-effort) when replacing it. */
    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
