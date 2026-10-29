<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MobileAppRelease extends BaseConfig
{
    public string $androidVersion = '1.0.14';
    public int $androidVersionCode = 15;
    public string $androidDownloadUrl = 'https://github.com/akcoders/jewellery_soft/releases/download/android-v1.0.14-build15/aabhushan-erp-1.0.14-build15.apk';
    public string $androidSha256 = '71a47cd7f3bc805e8bb2873dba374d5b11f341d287d550025184dfd1328f6dbd';
    public string $androidSizeLabel = '68.3 MB';
    public bool $androidRequired = true;
    public string $androidMessage = 'Install Aabhushan 1.0.14 for camera and gallery photo selection, clearer order and material cards, easier navigation, and material issuement on completed orders until receiving is recorded.';
}
