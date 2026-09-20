<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Print a VAPID key pair to paste into .env.
 *
 * These are what a browser checks a push against, so the pair must stay the
 * same for the life of an installation: change them and every browser that
 * already allowed notifications is orphaned and has to allow them again.
 * That is why this prints rather than writes - nobody should be able to
 * regenerate them by accident.
 */
class GenerateVapidKeys extends Command
{
    protected $signature = 'capstrack:vapid-keys';

    protected $description = 'Generate the VAPID key pair web push notifications are signed with';

    public function handle(): int
    {
        $keys = $this->createKeys();

        if (! $keys) {
            $this->error('OpenSSL could not create a key. Check that the openssl extension is enabled.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Paste these into your .env, then restart the queue worker:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->warn('Keep the private key secret, and do not regenerate these once people have allowed notifications.');

        return self::SUCCESS;
    }

    /**
     * A P-256 pair in the shape the push protocol wants: the public key is
     * the uncompressed point 0x04 || X || Y, the private key is D, both
     * base64url with no padding.
     *
     * web-push ships VAPID::createVapidKeys() for this, but it calls OpenSSL
     * without naming a config file. WAMP keeps openssl.cnf beside php.exe and
     * does not put it on the default path, so that call fails on Windows with
     * nothing but "Unable to create the key". Naming the file is the whole
     * difference, and it costs a dozen lines to do it here.
     *
     * @return array{publicKey: string, privateKey: string}|null
     */
    private function createKeys(): ?array
    {
        $options = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];

        $bundled = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'
            .DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';

        if (! getenv('OPENSSL_CONF') && is_file($bundled)) {
            $options['config'] = $bundled;
        }

        $key = openssl_pkey_new($options);

        if (! $key) {
            return null;
        }

        $ec = openssl_pkey_get_details($key)['ec'] ?? null;

        if (! $ec) {
            return null;
        }

        // OpenSSL drops leading zero bytes, so each part is padded back to the
        // 32 bytes the curve is defined over.
        $pad = fn (string $part) => str_pad($part, 32, "\0", STR_PAD_LEFT);

        return [
            'publicKey' => $this->base64url("\x04".$pad($ec['x']).$pad($ec['y'])),
            'privateKey' => $this->base64url($pad($ec['d'])),
        ];
    }

    private function base64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
