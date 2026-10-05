<?php

namespace App\Support;

/**
 * The address of a file served straight from public/ - the portals'
 * stylesheets - with a short fingerprint of its contents on the end.
 *
 * These are not built by Vite, so nothing names them differently when they
 * change, and a browser kept the copy it had: after a deploy the pages were
 * new and their styles were not, and a fix looked as though it had not been
 * made. A new ?v= is a new address, so the browser fetches the file again
 * exactly when it has changed, and keeps using its copy until then. A
 * fingerprint rather than the time it changed: a security scan reads a
 * timestamp in an address as leaking when the server was last updated.
 */
class PublicAsset
{
    public static function url(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.substr(md5_file($file), 0, 8) : '');
    }
}
