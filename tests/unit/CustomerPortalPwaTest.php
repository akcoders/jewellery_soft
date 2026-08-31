<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class CustomerPortalPwaTest extends CIUnitTestCase
{
    public function testCustomerManifestIsInstallableAndScopedToCustomerPortal(): void
    {
        $manifestPath = FCPATH . 'customer-portal-manifest.json';
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('./customer/', $manifest['id']);
        $this->assertSame('./customer/orders', $manifest['start_url']);
        $this->assertSame('./customer/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('#171d2c', $manifest['theme_color']);
        $this->assertNotEmpty($manifest['name']);
        $this->assertNotEmpty($manifest['short_name']);

        $expectedSizes = [
            'pwa/customer/icon-192.png' => [192, 192],
            'pwa/customer/icon-512.png' => [512, 512],
            'pwa/customer/icon-maskable-192.png' => [192, 192],
            'pwa/customer/icon-maskable-512.png' => [512, 512],
            'pwa/customer/apple-touch-icon.png' => [180, 180],
        ];
        foreach ($expectedSizes as $relativePath => $size) {
            $path = FCPATH . $relativePath;
            $this->assertFileExists($path);
            $imageSize = getimagesize($path);
            $this->assertIsArray($imageSize);
            $this->assertSame($size[0], $imageSize[0]);
            $this->assertSame($size[1], $imageSize[1]);
        }
    }

    public function testCustomerLayoutProvidesInstallFlowWithoutCachingPrivatePages(): void
    {
        $layout = (string) file_get_contents(APPPATH . 'Views/customer/layout.php');
        $worker = (string) file_get_contents(FCPATH . 'customer-portal-sw.js');

        $this->assertStringContainsString('rel="manifest"', $layout);
        $this->assertStringContainsString('apple-touch-icon', $layout);
        $this->assertStringContainsString('beforeinstallprompt', $layout);
        $this->assertStringContainsString('Install Customer App', $layout);
        $this->assertStringContainsString('Add to Home Screen', $layout);
        $this->assertStringContainsString('navigator.serviceWorker.register', $layout);
        $this->assertStringContainsString("base_url('customer/')", $layout);
        $this->assertStringContainsString('event.respondWith(fetch(event.request))', $worker);
        $this->assertStringNotContainsString('caches.open', $worker);
        $this->assertStringNotContainsString('cache.put', $worker);
        $this->assertStringNotContainsString('/customer/orders', $worker);
    }
}
