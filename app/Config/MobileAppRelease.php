<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MobileAppRelease extends BaseConfig
{
    public string $androidVersion = '1.0.13';
    public int $androidVersionCode = 14;
    public string $androidDownloadUrl = 'https://github.com/akcoders/jewellery_soft/releases/download/android-v1.0.13-build14/aabhushan-erp-1.0.13-build14.apk';
    public string $androidSha256 = '73b4bb1bde02496f802c4c83cf0d0a693f215604809368e7afd450238922a539';
    public string $androidSizeLabel = '107.2 MB';
    public bool $androidRequired = true;
    public string $androidMessage = 'Install Aabhushan 1.0.13 for order receiving, completion rating, packing lists, delivery challans and customer/karigar requests in the app. If the install fails, uninstall the old Aabhushan app once and install this version again.';
}
