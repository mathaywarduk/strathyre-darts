<?php

return [

    // Hour (24h, UK time) on a league night after which Home shows "Results pending"
    'pending_hour' => 22,

    // A winning checkout (the score they finished from, with three darts to go)
    'min_checkout' => 2,
    'max_checkout' => 170,

    'password_min' => 8,

    // Whether the public Notices page and its nav tab show to everyone. While this is off,
    // only a logged-in admin sees them (so a new feature can be tried on the live site).
    'notices_public' => env('NOTICES_PUBLIC', false),

    // Pictures on notices. They're stored on this disk: 'public' is the server's own disk
    // (storage/app/public). To use DigitalOcean Spaces later, define an S3 disk in
    // config/filesystems.php and set IMAGE_DISK to its name.
    'image_disk' => env('IMAGE_DISK', 'public'),
    'images_per_notice' => 12,
    'image_max_kb' => 15360,     // the biggest upload accepted, before it is resized
    'image_full_px' => 1600,     // longest side of the picture shown large
    'image_thumb_px' => 640,     // longest side of a gallery thumbnail

];
