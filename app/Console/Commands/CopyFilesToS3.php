<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;

/**
 * Copy the files already in storage/app onto MinIO, once FILES_ON_S3=true
 * points the "local" and "public" disks there. Each keeps its path, so the
 * database rows naming them stay right. A file already on MinIO is skipped,
 * so a run cut short can simply be run again. Nothing on this server is
 * deleted - that is left for after the copies are checked.
 *
 * --check compares every file with its copy. --back is the way out: it brings
 * home what was uploaded while the site ran on the buckets, before FILES_ON_S3
 * is turned off again.
 */
class CopyFilesToS3 extends Command
{
    protected $signature = 'files:copy-to-s3
        {--check : Compare every file here with its copy on the bucket}
        {--back : Copy files that are only on the bucket back to this server}';

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

            $client = Storage::disk($disk)->getClient();
            $bucket = config("filesystems.disks.{$disk}.bucket");

            if (! $client->doesBucketExistV2($bucket)) {
                $client->createBucket(['Bucket' => $bucket]);
            }

            if ($this->option('check')) {
                if (! $this->check($disk, $root, $client, $bucket)) {
                    return self::FAILURE;
                }

                continue;
            }

            if ($this->option('back')) {
                $this->back($disk, $root);

                continue;
            }

            $copied = $skipped = 0;

            // Walked one file at a time - the whole list of ~24k files alone
            // would pass PHP's default 128MB.
            foreach (Finder::create()->files()->in($root) as $file) {
                $path = str_replace('\\', '/', $file->getRelativePathname());

                if (Storage::disk($disk)->exists($path)) {
                    $skipped++;

                    continue;
                }

                $stream = fopen($file->getPathname(), 'r');
                $written = $stream && Storage::disk($disk)->writeStream($path, $stream);

                if ($stream) {
                    fclose($stream);
                }

                if (! $written) {
                    $this->error("Could not copy {$path}");

                    return self::FAILURE;
                }
                $copied++;
            }

            $this->info("{$disk}: {$copied} copied, {$skipped} already there");
        }

        return self::SUCCESS;
    }

    /** Every file here has a copy of the same size and, unless it went up in parts, the same MD5. */
    private function check(string $disk, string $root, S3Client $client, string $bucket): bool
    {
        $copies = [];

        foreach ($client->getPaginator('ListObjectsV2', ['Bucket' => $bucket]) as $page) {
            foreach ($page['Contents'] ?? [] as $object) {
                $copies[$object['Key']] = [(int) $object['Size'], trim($object['ETag'], '"')];
            }
        }

        $same = $different = 0;

        foreach (Finder::create()->files()->in($root) as $file) {
            $path = str_replace('\\', '/', $file->getRelativePathname());
            [$size, $etag] = $copies[$path] ?? [null, ''];

            if ($size === $file->getSize() && (str_contains($etag, '-') || $etag === md5_file($file->getPathname()))) {
                $same++;
            } else {
                $different++;
                $this->error("Missing or different: {$path}");
            }
        }

        $this->info("{$disk}: {$same} match, {$different} do not");

        return $different === 0;
    }

    private function back(string $disk, string $root): void
    {
        $restored = 0;

        foreach (Storage::disk($disk)->allFiles() as $path) {
            if (! is_file("{$root}/{$path}")) {
                File::ensureDirectoryExists(dirname("{$root}/{$path}"));
                file_put_contents("{$root}/{$path}", Storage::disk($disk)->readStream($path));
                $restored++;
            }
        }

        $this->info("{$disk}: {$restored} brought back");
    }
}
