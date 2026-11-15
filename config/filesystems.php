<?php

// FILES_ON_S3=true moves the two disks the app saves to ("local" for private
// files, "public" for files anyone with the link may open) onto S3-compatible
// storage such as Silo, so the code that names them needs no change. Each
// gets its own bucket: a public file is served from APP_URL/storage/..., and
// a separate bucket keeps that address from ever reaching a private file.
$s3 = [
    'driver' => 's3',
    'key' => env('SILO_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
    'secret' => env('SILO_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
    'region' => env('SILO_DEFAULT_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
    'bucket' => env('SILO_BUCKET', env('AWS_BUCKET')),
    'endpoint' => env('SILO_ENDPOINT', env('AWS_ENDPOINT')),
    'use_path_style_endpoint' => env('SILO_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
    // Hand big files over as they arrive; the default loads the whole file into memory first.
    'stream_reads' => true,
    'throw' => false,
    'report' => false,
];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => env('FILES_ON_S3', false) ? $s3 : [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => env('FILES_ON_S3', false) ? [
            'bucket' => env('SILO_PUBLIC_BUCKET', env('AWS_PUBLIC_BUCKET')),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
        ] + $s3 : [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => $s3,

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
