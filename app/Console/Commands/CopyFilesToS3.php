<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Copy the files already in storage/app onto MinIO, once FILES_ON_S3=true
 * points the "local" and "public" disks there. Each keeps its path, so the
 * database rows naming them stay right. A file already on MinIO is skipped,
 * so a run cut short can simply be run again. Nothing on this server is
 * deleted - that is left for after the copies are checked.
 */
class CopyFilesToS3 extends Command
{
    protected $signature = 'files:copy-to-s3';

    protected $description = 'Copy the uploaded files in storage/app onto the MinIO buckets';

    public function handle(): int
    {
        if (config('filesystems.disks.local.driver') !== 's3') {
            $this->error('Set FILES_ON_S3=true (and the AWS_* settings) first.');

            return self::FAILURE;
        }

        foreach (['local' => storage_path('app/private'), 'public' => storage_path('app/public')] as $disk => $root) {
            if (! is_dir($root)) {
                continue;
            }

            $copied = $skipped = 0;

            foreach (File::allFiles($root) as $file) {
                $path = str_replace('\\', '/', $file->getRelativePathname());

                if ($path === '.gitignore' || Storage::disk($disk)->exists($path)) {
                    $skipped++;

                    continue;
                }

                $stream = fopen($file->getPathname(), 'r');

                if (! Storage::disk($disk)->writeStream($path, $stream)) {
                    $this->error("Could not copy {$path}");

                    return self::FAILURE;
                }

                fclose($stream);
                $copied++;
            }

            $this->info("{$disk}: {$copied} copied, {$skipped} already there");
        }

        return self::SUCCESS;
    }
}
