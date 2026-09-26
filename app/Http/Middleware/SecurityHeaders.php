<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers the security scan found missing, chosen so that no page changes
 * how it works: another site may not show CapsTrack inside a frame (its own
 * PDF previews still can), the browser does not second-guess file types,
 * other sites are not told which page a link was followed from, and PHP's
 * version is not announced.
 */
class SecurityHeaders
{
    /**
     * Where a page may load from: CapsTrack itself, and the Google services
     * it uses - reCAPTCHA on the login page, Google sign-in and the Drive
     * picker on Resources, Drive previews in a frame. A script from anywhere
     * else is refused.
     *
     * The pages' own inline scripts and onclick handlers stay allowed
     * ('unsafe-inline'): there are hundreds of them, and a policy without it
     * would stop every one of those buttons until each was rewritten.
     *
     * @var array<string, list<string>>
     */
    private const POLICY = [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", "'unsafe-inline'", 'https://www.google.com', 'https://www.gstatic.com', 'https://apis.google.com', 'https://accounts.google.com'],
        'style-src' => ["'self'", "'unsafe-inline'", 'https://accounts.google.com', 'https://fonts.googleapis.com'],
        'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com'],
        'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
        'media-src' => ["'self'", 'data:', 'blob:'],
        'connect-src' => ["'self'", 'https://accounts.google.com', 'https://www.googleapis.com', 'https://content.googleapis.com', 'https://www.google.com'],
        'frame-src' => ["'self'", 'blob:', 'https://www.google.com', 'https://accounts.google.com', 'https://docs.google.com', 'https://drive.google.com', 'https://content.googleapis.com'],
        'worker-src' => ["'self'", 'blob:'],
        'object-src' => ["'none'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'", 'https://accounts.google.com'],
        'frame-ancestors' => ["'self'"],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');

        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => $this->policy(),
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=()',
        ];

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * The policy as a header. While `npm run dev` serves the scripts from its
     * own port, only framing is restricted, so the dev server is not refused.
     */
    private function policy(): string
    {
        if (Vite::isRunningHot()) {
            return "frame-ancestors 'self'";
        }

        return collect(self::POLICY)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', $sources))
            ->implode('; ');
    }
}
