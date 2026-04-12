<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaManifestTest extends TestCase
{
    public function test_storefront_manifest_is_served_on_app_host(): void
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $this->assertIsString($host);
        $this->assertNotSame('', $host);

        $response = $this->get('https://'.$host.'/manifest.webmanifest');
        $response->assertOk();
        $this->assertStringContainsString('manifest+json', strtolower((string) $response->headers->get('Content-Type')));

        $json = $response->json();
        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('icons', $json);
        $this->assertNotEmpty($json['icons']);
    }

    public function test_admin_manifest_when_admin_domain_set(): void
    {
        $admin = (string) config('panels.admin.domain');
        if ($admin === '') {
            $this->markTestSkipped('ADMIN_PANEL_DOMAIN not set');
        }

        $json = $this->get('https://'.$admin.'/manifest.webmanifest')->json();
        $this->assertSame(config('pwa.apps.admin.manifest_id'), $json['id']);
    }
}
