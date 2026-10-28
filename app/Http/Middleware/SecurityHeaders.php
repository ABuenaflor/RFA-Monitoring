<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side hardening sent with every page:
 *
 * - the system cannot be framed by another site (clickjacking)
 * - responses are never content-sniffed into a different type
 * - full URLs (with case filters) are not leaked to other sites
 * - HTTPS is remembered once the site is served over it
 */
class SecurityHeaders
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $response = $next($request);

        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }

        $headers = $response->headers;

        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
