<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MobileAppRelease extends BaseConfig
{
    public string $androidVersion = '1.0.7';
    public int $androidVersionCode = 8;
    public string $androidDownloadUrl = 'https://github.com/akcoders/jewellery_soft/releases/download/android-v1.0.7-build8/aabhushan-erp-1.0.7-build8.apk';
    public string $androidSha256 = '1c1dc0eed80338188db56746dd06c3caeb3b71c9b7c14ced688d25fbd9b854df';
    public string $androidSizeLabel = '106.2 MB';
    public bool $androidRequired = true;
    public string $androidMessage = 'Install the latest Aabhushan app for weight-only broken diamond returns and accurate broken inventory.';
}
