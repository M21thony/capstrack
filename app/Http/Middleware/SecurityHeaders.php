<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
     * The pages' own <script> and <style> blocks carry this response's nonce,
     * so a block someone manages to slip into a page does not run. Onclick
     * and style="" attributes stay allowed (script-src-attr / style-src-attr):
     * there are hundreds of them, and an attribute cannot load anything.
     *
     * @var array<string, list<string>>
     */
    private const POLICY = [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", 'https://www.google.com', 'https://www.gstatic.com', 'https://apis.google.com', 'https://accounts.google.com'],
        'script-src-attr' => ["'unsafe-inline'"],
        'style-src' => ["'self'", 'https://accounts.google.com', 'https://fonts.googleapis.com'],
        'style-src-attr' => ["'unsafe-inline'"],
        'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com'],
        'img-src' => ["'self'", 'data:', 'blob:', 'https://lh3.googleusercontent.com', 'https://lh4.googleusercontent.com', 'https://lh5.googleusercontent.com', 'https://lh6.googleusercontent.com'],
        'media-src' => ["'self'", 'data:', 'blob:'],
        'connect-src' => ["'self'", 'https://accounts.google.com', 'https://www.googleapis.com', 'https://content.googleapis.com', 'https://www.google.com'],
        'frame-src' => ["'self'", 'blob:', 'https://www.google.com', 'https://recaptcha.google.com', 'https://accounts.google.com', 'https://docs.google.com', 'https://drive.google.com', 'https://content.googleapis.com'],
        'worker-src' => ["'self'", 'blob:'],
        'object-src' => ["'none'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'", 'https://accounts.google.com'],
        'frame-ancestors' => ["'self'"],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        // A redirect needs only its Location header. The "Redirecting to..."
        // page Laravel puts in the body is never shown, and a scan reads a
        // redirect carrying a page as one that might be leaking it.
        if ($response instanceof RedirectResponse) {
            $response->setContent('');
        }

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

        $nonce = "'nonce-".Vite::cspNonce()."'";

        return collect(self::POLICY)
            ->map(fn (array $sources, string $directive): array => in_array($directive, ['script-src', 'style-src'], true) ? [...$sources, $nonce] : $sources)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', $sources))
            ->implode('; ');
    }
}
