<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Uji keberadaan Security Headers (CSP ketat tanpa unsafe-inline, Anti-Clickjacking, X-Content-Type-Options, dll).
     */
    public function test_security_headers_are_present_on_normal_responses(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("style-src 'self'", $csp);
        // Pastikan tidak ada unsafe-inline pada style-src
        $this->assertStringNotContainsString("unsafe-inline", $csp);

        // Pastikan tidak ada kebocoran header versi server/PHP
        $this->assertFalse($response->headers->has('X-Powered-By'));
        $this->assertFalse($response->headers->has('Server'));
    }

    /**
     * Uji keberadaan Security Headers pada response error 404 (misal rute tidak terdaftar).
     */
    public function test_security_headers_are_present_on_404_responses(): void
    {
        $response = $this->get('/non-existent-endpoint-xyz');

        $response->assertStatus(404);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $this->assertFalse($response->headers->has('X-Powered-By'));
    }

    /**
     * Uji rute robots.txt, sitemap.xml, dan css memiliki header X-Content-Type-Options nosniff.
     */
    public function test_static_routes_have_nosniff_header(): void
    {
        $robots = $this->get('/robots.txt');
        $robots->assertStatus(200);
        $robots->assertHeader('X-Content-Type-Options', 'nosniff');
        $robots->assertHeader('Content-Type', 'text/plain; charset=utf-8');

        $sitemap = $this->get('/sitemap.xml');
        $sitemap->assertStatus(200);
        $sitemap->assertHeader('X-Content-Type-Options', 'nosniff');
        $sitemap->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $css = $this->get('/css/style.css');
        $css->assertStatus(200);
        $css->assertHeader('X-Content-Type-Options', 'nosniff');
        $css->assertHeader('Content-Type', 'text/css; charset=utf-8');
    }

    /**
     * Uji seluruh cookie (termasuk XSRF-TOKEN) wajib memiliki flag HttpOnly.
     */
    public function test_all_cookies_have_httponly_flag(): void
    {
        $response = $this->get('/');

        $cookies = $response->headers->getCookies();
        $this->assertNotEmpty($cookies);

        foreach ($cookies as $cookie) {
            $this->assertTrue(
                $cookie->isHttpOnly(),
                "Cookie '{$cookie->getName()}' harus memiliki flag HttpOnly."
            );
        }
    }

    /**
     * Uji mitigasi Big Redirect Detected: body redirect harus kosong (0 byte) untuk mencegah kebocoran informasi.
     */
    public function test_redirect_responses_have_zero_content_body(): void
    {
        $response = $this->get('/post');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
        $this->assertSame(0, strlen($response->getContent()));
    }

    /**
     * Uji kekebalan terhadap SQL Injection dan karakter wildcard LIKE pada pencarian proyek.
     */
    public function test_search_safely_handles_sql_injection_and_like_wildcards(): void
    {
        // Uji payload SQL Injection umum
        $payloads = [
            'ZAP AND 1=1',
            'ZAP AND 1=2',
            "' OR '1'='1",
            "admin'--",
            '1; DROP TABLE projects;--',
            '%',
            '_',
            '\\',
        ];

        foreach ($payloads as $payload) {
            $response = $this->get('/?q=' . urlencode($payload));
            $response->assertStatus(200);
            $response->assertSee('Daftar Proyek');
        }
    }

    /**
     * Uji penanganan SQL injection pada login: seluruh percobaan gagal menghasilkan respon 302 yang konsisten.
     */
    public function test_login_sql_injection_payloads_consistently_handled(): void
    {
        $payloads = [
            'ZAP',
            'ZAP AND 1=1 --',
            'ZAP OR 1=1 --',
            "' OR '1'='1",
            "admin' --",
        ];

        foreach ($payloads as $p) {
            $res = $this->from('/login')->post('/login', [
                'email'    => 'test@example.com',
                'password' => $p,
            ]);

            // Respon harus konsisten 302 redirect ke /login dengan flash error dan body kosong
            $res->assertStatus(302);
            $res->assertRedirect('/login');
            $this->assertSame(0, strlen($res->getContent()));
            $res->assertSessionHas('flash');
        }
    }

    /**
     * Uji penanganan variasi User-Agent (User Agent Fuzzer).
     */
    public function test_various_user_agents_handled_gracefully(): void
    {
        $userAgents = [
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 8_0_2 like Mac OS X)',
            'msnbot/1.1 (+http://search.msn.com/msnbot.htm)',
            'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)',
        ];

        foreach ($userAgents as $ua) {
            $response = $this->withHeaders(['User-Agent' => $ua])->get('/');
            $response->assertStatus(200);
        }
    }
}
