<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class NoticeImage extends Model
{
    protected $fillable = ['notice_id', 'path', 'thumb_path', 'width', 'height', 'caption', 'position'];

    protected static function booted(): void
    {
        // The files go when the picture does
        static::deleting(function (self $image) {
            Storage::disk(config('league.image_disk'))->delete([$image->path, $image->thumb_path]);
        });
    }

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }

    /** The picture shown large */
    public function url(): string
    {
        return $this->urlFor($this->path);
    }

    /** The smaller one for the gallery grid */
    public function thumbUrl(): string
    {
        return $this->urlFor($this->thumb_path);
    }

    private function urlFor(string $path): string
    {
        $disk = config('league.image_disk');

        // On the server's own disk the web server hands the file out through the storage link, on whatever address
        // the site is being viewed at. On a cloud disk (Spaces, S3) the disk knows its own address.
        return config("filesystems.disks.{$disk}.driver") === 'local'
            ? asset('storage/'.$path)
            : Storage::disk($disk)->url($path);
    }
}
