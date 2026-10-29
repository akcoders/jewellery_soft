<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MobileAppRelease extends BaseConfig
{
    public string $androidVersion = '1.0.14';
    public int $androidVersionCode = 15;
    public string $androidDownloadUrl = 'https://github.com/akcoders/jewellery_soft/releases/download/android-v1.0.14-build15/aabhushan-erp-1.0.14-build15.apk';
    public string $androidSha256 = '4eb90b72f3c9b464e21b6c6b87be4f7545c70a2809c60b582b14804b96c4922c';
    public string $androidSizeLabel = '107.4 MB';
    public bool $androidRequired = true;
    public string $androidMessage = 'Install Aabhushan 1.0.14 for camera and gallery photo selection, clearer order and material cards, easier navigation, and material issuement on completed orders until receiving is recorded.';
}
