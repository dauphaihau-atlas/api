<?php

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
    | Avatars Disk
    |--------------------------------------------------------------------------
    |
    | Disk used for user avatar uploads (e.g. "public" for local storage
    | with storage:link, or "minio" for S3-compatible storage).
    |
    */

    'avatars_disk' => env('AVATARS_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Imports Disk
    |--------------------------------------------------------------------------
    |
    | Disk used for uploaded import files (e.g. CSV). "local" stores under
    | storage/app/private; "minio" for S3-compatible audit storage.
    |
    */

    'imports_disk' => env('IMPORTS_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Exports Disk
    |--------------------------------------------------------------------------
    |
    | Disk used for generated export files (e.g. CSV). "local" stores under
    | storage/app/private; "minio" for S3-compatible storage.
    |
    */

    'exports_disk' => env('EXPORTS_DISK', 'local'),

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

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
        | MinIO disk (S3-compatible). Uses MINIO_* env when set, otherwise
        | falls back to AWS_* so the same vars can drive both s3 and minio.
        */
        'minio' => [
            'driver' => 's3',
            'key' => env('MINIO_ACCESS_KEY', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('MINIO_SECRET_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('MINIO_BUCKET', env('AWS_BUCKET')),
            'url' => env('MINIO_URL', env('AWS_URL')),
            'endpoint' => env('MINIO_ENDPOINT', env('AWS_ENDPOINT')),
            'temporary_url' => env('MINIO_TEMPORARY_URL', env('MINIO_URL')),
            'use_path_style_endpoint' => true,
            'throw' => false,
            'report' => false,
        ],

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
