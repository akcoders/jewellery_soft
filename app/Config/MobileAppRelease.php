<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MobileAppRelease extends BaseConfig
{
    public string $androidVersion = '1.0.9';
    public int $androidVersionCode = 10;
    public string $androidDownloadUrl = 'https://github.com/akcoders/jewellery_soft/releases/download/android-v1.0.9-build10/aabhushan-erp-1.0.9-build10.apk';
    public string $androidSha256 = '60fdf57727d2cf45402de163907ef847d61ee6a6057250f83bcf4cc4a86cab9c';
    public string $androidSizeLabel = '105.7 MB';
    public bool $androidRequired = true;
    public string $androidMessage = 'Install the latest Aabhushan app for updated order-family follow-ups, approvals, and transaction details.';
}
