<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Menangani request yang masuk dan menerapkan kontrol keamanan pada response.
     * Mengatasi kerentanan OWASP ZAP:
     * - Missing Anti-clickjacking Header (X-Frame-Options, frame-ancestors)
     * - Content Security Policy (CSP) Header Not Set
     * - Cookie No HttpOnly Flag (memaksa HttpOnly pada XSRF-TOKEN & semua cookies)
     * - Big Redirect Detected (mengosongkan body pada response redirect)
     * - X-Content-Type-Options Header Missing (nosniff)
     * - Server Leaks Information via X-Powered-By / PHP Version Disclosure
     * - User Agent Fuzzer protection
     *
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Proteksi User-Agent abnormal / buffer overflow dari scanner
        $userAgent = $request->header('User-Agent');
        if ($userAgent && strlen($userAgent) > 1024) {
            abort(400, 'Bad Request: User-Agent header exceeds permissible length.');
        }

        // Hapus header versi PHP sebelum output dimulai
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }

        $response = $next($request);

        return self::applyHeaders($response, $request);
    }

    /**
     * Terapkan header keamanan dan kontrol cookie pada response.
     */
    public static function applyHeaders(Response $response, ?Request $request = null): Response
    {
        // 2. Cegah kebocoran informasi pada Big Redirect (OWASP ZAP "Big Redirect Detected")
        // Response redirect (3xx) dikosongkan bodynya agar tidak membocorkan data sensitif.
        if ($response->isRedirection()) {
            $response->setContent('');
        }

        // 3. Pasang Header Keamanan (Security Headers)
        $headers = $response->headers;

        // Anti-Clickjacking: X-Frame-Options & frame-ancestors di CSP
        $headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Mencegah MIME Sniffing
        $headers->set('X-Content-Type-Options', 'nosniff');

        // Content Security Policy (CSP)
        $csp = "default-src 'self'; "
            . "script-src 'self'; "
            . "style-src 'self'; "
            . "img-src 'self' data:; "
            . "font-src 'self'; "
            . "connect-src 'self'; "
            . "frame-ancestors 'self'; "
            . "form-action 'self'; "
            . "base-uri 'self'; "
            . "object-src 'none'";
        $headers->set('Content-Security-Policy', $csp);

        // Referrer & Permissions Policy
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $headers->set('X-XSS-Protection', '0');

        $isSecure = ($request && $request->isSecure()) || app()->isProduction();

        // Strict-Transport-Security jika berjalan di HTTPS / environment production
        if ($isSecure) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Hapus header info kebocoran server & versi PHP
        $headers->remove('X-Powered-By');
        $headers->remove('Server');

        // 4. Pengerasan Cookie (OWASP ZAP "Cookie No HttpOnly Flag")
        // Memastikan seluruh cookie (termasuk XSRF-TOKEN) berstatus HttpOnly
        foreach ($headers->getCookies() as $cookie) {
            $isHttpOnly = true;
            $secureFlag = $cookie->isSecure() || $isSecure;

            if (!$cookie->isHttpOnly() || ($isSecure && !$cookie->isSecure())) {
                $headers->setCookie(
                    $cookie->withHttpOnly($isHttpOnly)->withSecure($secureFlag)
                );
            }
        }

        return $response;
    }
}
