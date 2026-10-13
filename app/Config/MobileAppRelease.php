<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MobileAppRelease extends BaseConfig
{
    public string $androidVersion = '1.0.8';
    public int $androidVersionCode = 9;
    public string $androidDownloadUrl = 'https://github.com/akcoders/jewellery_soft/releases/download/android-v1.0.8-build9/aabhushan-erp-1.0.8-build9.apk';
    public string $androidSha256 = '753ddd526ca3e8276e81537714f96c057cf4d7b4f5722f4169f5e6694196bc1d';
    public string $androidSizeLabel = '106.2 MB';
    public bool $androidRequired = true;
    public string $androidMessage = 'Install the latest Aabhushan app to see the permanent weight-only Broken Diamond inventory product.';
}
