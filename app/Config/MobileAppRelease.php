<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MobileAppRelease extends BaseConfig
{
    public string $androidVersion = '1.0.11';
    public int $androidVersionCode = 12;
    public string $androidDownloadUrl = 'https://github.com/akcoders/jewellery_soft/releases/download/android-v1.0.11-build12/aabhushan-erp-1.0.11-build12.apk';
    public string $androidSha256 = 'c3c99e6b53d88c8d085c029502838d803a4159e3c7804268e27fbc3bf7c1023a';
    public string $androidSizeLabel = '106.5 MB';
    public bool $androidRequired = true;
    public string $androidMessage = 'Install the latest Aabhushan app for order-wise stone issuement and complete order details.';
}
