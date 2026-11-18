<?php

namespace App\Services;

use App\Models\Notice;
use App\Models\NoticeImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pictures for notices. Every upload is decoded, turned the right way up, flattened
 * onto white if it has transparency, shrunk, and saved again as a JPEG: a full-size one
 * and a thumbnail. That keeps the pages light and drops anything hidden in the original
 * (location data, odd formats, anything that isn't really a picture).
 */
final class NoticeImages
{
    private const TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

    /** Largest picture we'll try to open, in pixels (about a 50 megapixel camera) */
    private const MAX_PIXELS = 50_000_000;

    /**
     * Check and shrink an upload, without saving anything yet.
     *
     * @return array{full: string, thumb: string, width: int, height: int}
     */
    public function prepare(UploadedFile $file): array
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new ImageException('This server can’t handle pictures yet (PHP’s GD extension is missing).');
        }

        $bytes = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($bytes);
        $name = $file->getClientOriginalName();

        if (! $info || ! in_array($info[2], self::TYPES, true)) {
            throw new ImageException("{$name} isn’t a JPG, PNG, WebP or GIF picture.");
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new ImageException("{$name} is too many pixels to open. Try a smaller version.");
        }

        $this->makeRoom();
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            throw new ImageException("{$name} couldn’t be read as a picture.");
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $image = $this->upright($image, $bytes);
        } else {
            $image = $this->onWhite($image); // PNG, GIF and WebP can be see-through
        }

        $full = $this->shrink($image, (int) config('league.image_full_px'));
        $thumb = $this->shrink($full, (int) config('league.image_thumb_px'));

        return [
            'full' => $this->jpeg($full),
            'thumb' => $this->jpeg($thumb),
            'width' => imagesx($full),
            'height' => imagesy($full),
        ];
    }

    /**
     * Save a prepared picture against a notice, after its others.
     *
     * @param  array{full: string, thumb: string, width: int, height: int}  $prepared
     */
    public function attach(Notice $notice, array $prepared, ?string $caption = null): NoticeImage
    {
        $disk = Storage::disk(config('league.image_disk'));
        $name = Str::random(24);
        $path = "notices/{$notice->id}/{$name}.jpg";
        $thumb = "notices/{$notice->id}/{$name}-thumb.jpg";

        $disk->put($path, $prepared['full'], ['visibility' => 'public']);
        $disk->put($thumb, $prepared['thumb'], ['visibility' => 'public']);

        return $notice->images()->create([
            'path' => $path,
            'thumb_path' => $thumb,
            'width' => $prepared['width'],
            'height' => $prepared['height'],
            'caption' => $caption !== null && trim($caption) !== '' ? trim($caption) : null,
            'position' => (int) $notice->images()->max('position') + 1,
        ]);
    }

    /** Take a phone photo's orientation into account: phones save them sideways with a note saying how to turn them. */
    private function upright(\GdImage $image, string $bytes): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $bytes);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        $degrees = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $degrees ? (imagerotate($image, $degrees, 0) ?: $image) : $image;
    }

    private function onWhite(\GdImage $image): \GdImage
    {
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $canvas;
    }

    private function shrink(\GdImage $image, int $longest): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        if (max($w, $h) <= $longest) {
            return $image;
        }
        $scale = $longest / max($w, $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $small = imagecreatetruecolor($nw, $nh);
        if (! $small || ! imagecopyresampled($small, $image, 0, 0, 0, 0, $nw, $nh, $w, $h)) {
            throw new ImageException('That picture couldn’t be made smaller. Try a smaller one.');
        }

        return $small;
    }

    private function jpeg(\GdImage $image): string
    {
        imageinterlace($image, true); // loads in progressively
        ob_start();
        imagejpeg($image, null, 82);

        return (string) ob_get_clean();
    }

    /** A big photo needs a lot of memory to open: ask for more while it's being shrunk. */
    private function makeRoom(): void
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return;
        }
        $bytes = (int) $limit * match (strtoupper(substr($limit, -1))) {
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'K' => 1024,
            default => 1,
        };
        if ($bytes < 512 * 1024 ** 2) {
            @ini_set('memory_limit', '512M');
        }
    }
}
