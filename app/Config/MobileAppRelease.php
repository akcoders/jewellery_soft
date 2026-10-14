<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MobileAppRelease extends BaseConfig
{
    public string $androidVersion = '1.0.10';
    public int $androidVersionCode = 11;
    public string $androidDownloadUrl = 'https://github.com/akcoders/jewellery_soft/releases/download/android-v1.0.10-build11/aabhushan-erp-1.0.10-build11.apk';
    public string $androidSha256 = '26c42a7b0aa409dbedc6daae3fbd5d3e96ccff3321756a0967d044480a9b84b2';
    public string $androidSizeLabel = '105.7 MB';
    public bool $androidRequired = true;
    public string $androidMessage = 'Install the latest Aabhushan app for precise performance points and organized PDF downloads.';
}
