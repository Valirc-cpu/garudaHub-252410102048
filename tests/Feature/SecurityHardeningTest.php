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
     * Uji keberadaan Security Headers (CSP, Anti-Clickjacking, X-Content-Type-Options, dll).
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

        // Pastikan tidak ada kebocoran header versi server/PHP
        $this->assertFalse($response->headers->has('X-Powered-By'));
        $this->assertFalse($response->headers->has('Server'));
    }

    /**
     * Uji keberadaan Security Headers pada response error 404 (misal /sitemap.xml, /robots.txt).
     */
    public function test_security_headers_are_present_on_404_responses(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(404);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $this->assertFalse($response->headers->has('X-Powered-By'));
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
            // Tidak terjadi crash 500 dan response aman
            $response->assertSee('Daftar Proyek');
        }
    }

    /**
     * Uji rate limiting (throttling) pada endpoint otentikasi login untuk cegah brute-force.
     */
    public function test_login_brute_force_rate_limiting(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $res = $this->post('/login', [
                'email'    => 'attacker@example.com',
                'password' => 'wrongpass',
            ]);
            $this->assertNotEquals(429, $res->getStatusCode());
        }

        // Percobaan ke-11 harus diblokir dengan 429 Too Many Requests
        $blocked = $this->post('/login', [
            'email'    => 'attacker@example.com',
            'password' => 'wrongpass',
        ]);
        $this->assertSame(429, $blocked->getStatusCode());
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
