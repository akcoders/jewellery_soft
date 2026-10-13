<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MobileAppRelease extends BaseConfig
{
    public string $androidVersion = '1.0.4';
    public int $androidVersionCode = 5;
    public string $androidDownloadUrl = 'https://github.com/akcoders/jewellery_soft/releases/download/android-v1.0.4-build5/aabhushan-erp-1.0.4-build5.apk';
    public string $androidSha256 = '00830d798dc1c81ec3054b25580335a18ef3c97cfbb0c6efe9877b9d1cb5fd1d';
    public string $androidSizeLabel = '100.5 MB';
    public bool $androidRequired = false;
    public string $androidMessage = 'Install the latest Aabhushan app for reliable custom notification sounds and automatic future update alerts.';
}
