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
        // C3 (v1.4.4): QR codes MUST keep alpha and stay PNG — JPEG blocks
        // flatten the quiet zone and kill scannability. Proofs are photos
        // (JPEG fine) but land on the PRIVATE proofs disk, not public.
        'qr' => ['max' => 1024, 'dir' => 'qr', 'quality' => 90, 'alpha' => true],
        'proof' => ['max' => 1600, 'dir' => 'proofs', 'quality' => 82, 'alpha' => false, 'disk' => 'proofs'],
        // G1 (v1.7.0): gamification art is CONTENT imagery with transparency
        // — badges and frames sit over avatars/profiles, so JPEG (no alpha
        // channel) is never acceptable. Same alpha logic as the QR variant.
        'badge' => ['max' => 512, 'dir' => 'badges', 'quality' => 90, 'alpha' => true],
        'frame' => ['max' => 512, 'dir' => 'frames', 'quality' => 90, 'alpha' => true],
        'pack_hero' => ['max' => 1600, 'dir' => 'pack_heroes', 'quality' => 82, 'alpha' => false],
        // S9 (v1.8.0): the Sikka unit mark — a brand asset uploaded from
        // Admin → Brand (never shipped in the zip). PNG/WebP only, alpha
        // preserved (blending OFF + save-alpha ON, the W2 rule), <=512px.
        'sikka' => ['max' => 512, 'dir' => 'sikka', 'quality' => 90, 'alpha' => true],
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

        $cfg = self::VARIANTS[$variant];

        if (! $file->isValid()) {
            throw new \RuntimeException('Upload failed — the file did not arrive intact.');
        }

        if ($file->getSize() > self::MAX_INPUT_KB * 1024) {
            throw new \RuntimeException('Image is larger than 8 MB. Please upload a smaller file.');
        }

        $mime = strtolower((string) $file->getMimeType());
        // W3 (v1.7.3): image/gif is allowlisted ONLY for the animated
        // pass-through lane below; a STATIC gif falls through to the decode
        // check and is rejected with a clear message (GD re-encode would
        // destroy its frames, so it never enters the pipeline).
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            throw new \RuntimeException('Only JPG, PNG or WebP images are accepted.');
        }

        // G1 (v1.7.0): badge/frame uploads reject JPEG sources outright —
        // these variants EXIST to carry transparency; a JPEG source has
        // none, and silently flattening it onto a black box is worse than
        // a clear rejection at the desk. S9 (v1.8.0) adds the Sikka mark to
        // the same rule: the unit mark is an alpha asset by contract.
        if (in_array($variant, ['badge', 'frame', 'sikka'], true) && $mime === 'image/jpeg') {
            throw new \RuntimeException('Badges, frames and Sikka icons must be PNG or WebP (transparency required) — JPEG is not accepted.');
        }

        // W3 (v1.7.3): animated GIF/WebP pass-through lane — GD cannot
        // preserve animation, so animated uploads skip it ENTIRELY. Gate:
        // header-parsed ≤512×512, ≤2 MB, then stored byte-identical (the
        // hash-equality test locks "stored == uploaded"). Alpha variants
        // only — covers/banners never take this lane.
        if (in_array($variant, ['badge', 'frame'], true)
            && in_array($mime, ['image/gif', 'image/webp'], true)
            && $this->isAnimated($file->getRealPath(), $mime)) {
            [$width, $height] = $this->animatedDimensions($file->getRealPath(), $mime);

            if ($width > 512 || $height > 512) {
                throw new \RuntimeException('Animated frames must be 512×512 or smaller.');
            }

            $extension = $mime === 'image/gif' ? 'gif' : 'webp';
            $path = $cfg['dir'].'/'.Str::random(40).'.'.$extension;
            Storage::disk('public')->put($path, (string) file_get_contents($file->getRealPath()));

            return $path;
        }

        // Real decode check — a renamed .php with an image mime fails here.
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
            // Static GIF: the animated lane above didn't claim it, so it
            // can't be stored losslessly — reject rather than flatten.
            'image/gif' => false,
            default => false,
        };
        if ($src === false) {
            throw new \RuntimeException('That file is not a readable image.');
        }

        try {
            $image = $this->downscale($src, $cfg['max'], $cfg['alpha']);
            $quality = $cfg['quality'];

            if ($cfg['alpha']) {
                // W2 (v1.7.3) ALPHA TRUTH: blending OFF + save-alpha ON is
                // set on the FINAL image before EVERY encode — this is the
                // exact pair that prevents GD compositing transparent
                // corners onto black. Re-asserted after downscale because
                // imagecopyresampled onto a fresh canvas resets nothing but
                // the flags live per-resource; being explicit twice is the
                // contract the pixel tests lock.
                imagealphablending($image, false);
                imagesavealpha($image, true);

                if ($mime === 'image/webp') {
                    // WebP sources keep WebP output (alpha supported) —
                    // never routed through the JPEG-flattening path.
                    ob_start();
                    imagewebp($image, null, $quality);
                    $binary = (string) ob_get_clean();
                    $extension = 'webp';
                } else {
                    ob_start();
                    imagepng($image, null, 6);
                    $binary = (string) ob_get_clean();
                    $extension = 'png';
                }
            } else {
                // Photos: JPEG re-encode strips metadata and crushes size.
                ob_start();
                imagejpeg($image, null, $quality);
                $binary = (string) ob_get_clean();
                $extension = 'jpg';
            }

            $name = Str::random(40).'.'.$extension;
            $path = $cfg['dir'].'/'.$name;

            // Proof variant targets the private proofs disk (C3): never
            // web-reachable by path — served through the owner/staff route.
            $disk = $cfg['disk'] ?? 'public';
            Storage::disk($disk)->put($path, $binary);

            return $path;
        } finally {
            imagedestroy($src);
            if (isset($image) && $image !== $src) {
                imagedestroy($image);
            }
        }
    }

    /**
     * W3: does this GIF/WebP contain multiple frames (i.e. is animated)?
     * GIF: multiple image descriptors follow the first — a second 0x2C
     * image-separator byte before the trailer means animated. WebP: the
     * 'ANIM' chunk in the RIFF payload. Static files return false and
     * take the normal GD path.
     */
    private function isAnimated(string $realPath, string $mime): bool
    {
        $bytes = (string) file_get_contents($realPath);

        if ($mime === 'image/gif') {
            // Count 0x2C image separators (cheap + dependency-free); >1 = animated.
            return substr_count(substr($bytes, 0, 512 * 1024), "\x2C") > 1
                || (strlen($bytes) > 512 * 1024 && substr_count($bytes, "\x2C") > 1);
        }

        // WebP: RIFF container — animated frames carry an ANIM chunk.
        return str_contains($bytes, 'ANIM');
    }

    /** @return array{0: int, 1: int} width/height of an animated upload. */
    private function animatedDimensions(string $realPath, string $mime): array
    {
        $data = getimagesize($realPath);

        return [(int) ($data[0] ?? 0), (int) ($data[1] ?? 0)];
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
            // W2 alpha truth: transparent canvas BEFORE the resample, and
            // blending OFF for the fill itself (ON during the copy would
            // composite the source over black — the founder's black-corner
            // bug). The copy below runs with blending OFF so source alpha
            // is REPLACED, not composited.
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefill($dst, 0, 0, $transparent);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    /** Delete a previous image (best-effort) when replacing it. Uses the
     *  private proofs disk for proof paths. */
    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $disk = str_starts_with($path, 'proofs/') ? 'proofs' : 'public';

        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
