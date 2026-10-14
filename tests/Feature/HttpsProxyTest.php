<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class HttpsProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/_proxy-test', fn (Request $request) => response()->json([
            'secure' => $request->isSecure(),
            'host' => $request->getHost(),
            'port' => $request->getPort(),
            'ip' => $request->ip(),
            'login' => route('login'),
        ]));
    }

    public function test_https_tunnel_keeps_login_assets_redirects_and_session_cookie_secure(): void
    {
        config(['smartaulaues.trusted_proxies' => '*', 'session.secure' => true]);
        $this->withServerVariables(['REMOTE_ADDR' => '172.22.0.10']);
        $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9']);

        $response = $this->get('http://mobile.example.test/login')->assertOk()
            ->assertSee('action="https://mobile.example.test/login"', false)
            ->assertSee('href="https://mobile.example.test/favicon.svg?', false);
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->get('http://mobile.example.test/dashboard')->assertRedirect('https://mobile.example.test/login');
    }

    public function test_only_configured_proxy_ips_can_supply_https_and_client_address(): void
    {
        config(['smartaulaues.trusted_proxies' => '172.22.0.0/16']);
        $this->withHeaders([
            'X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9',
            'X-Forwarded-Host' => 'other.example.test', 'X-Forwarded-Port' => '9443',
        ]);
        $this->withServerVariables(['REMOTE_ADDR' => '172.22.0.10'])->get('http://mobile.example.test/_proxy-test')
            ->assertOk()->assertExactJson(['secure' => true, 'host' => 'mobile.example.test', 'port' => 443, 'ip' => '203.0.113.9', 'login' => 'https://mobile.example.test/login']);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])->get('http://mobile.example.test/_proxy-test')
            ->assertOk()->assertExactJson(['secure' => false, 'host' => 'mobile.example.test', 'port' => 80, 'ip' => '198.51.100.10', 'login' => 'http://mobile.example.test/login']);
    }

    public function test_local_http_ignores_forwarded_headers_when_proxy_trust_is_disabled(): void
    {
        config(['smartaulaues.trusted_proxies' => '']);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9'])
            ->get('http://mobile.example.test/_proxy-test')->assertOk()
            ->assertExactJson(['secure' => false, 'host' => 'mobile.example.test', 'port' => 80, 'ip' => '198.51.100.10', 'login' => 'http://mobile.example.test/login']);
    }
}
