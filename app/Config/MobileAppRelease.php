<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MobileAppRelease extends BaseConfig
{
    public string $androidVersion = '1.0.12';
    public int $androidVersionCode = 13;
    public string $androidDownloadUrl = 'https://github.com/akcoders/jewellery_soft/releases/download/android-v1.0.12-build13/aabhushan-erp-1.0.12-build13.apk';
    public string $androidSha256 = '9ae9f2427d72ef3a18fce82263cafd9df2fd11da0ab90a071ae2424410db09b7';
    public string $androidSizeLabel = '106.8 MB';
    public bool $androidRequired = true;
    public string $androidMessage = 'Install Aabhushan 1.0.12 for team performance, monthly targets and order-wise stone issues. If the install fails, uninstall the old Aabhushan app once and install this version again.';
}
