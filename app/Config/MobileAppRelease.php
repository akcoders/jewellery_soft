<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MobileAppRelease extends BaseConfig
{
    public string $androidVersion = '1.0.6';
    public int $androidVersionCode = 7;
    public string $androidDownloadUrl = 'https://github.com/akcoders/jewellery_soft/releases/download/android-v1.0.6-build7/aabhushan-erp-1.0.6-build7.apk';
    public string $androidSha256 = '30746a5dac6deeac9c10a6e083bcc98c5022b49cd9531b91ac6bc7db2fdaab3f';
    public string $androidSizeLabel = '106.2 MB';
    public bool $androidRequired = true;
    public string $androidMessage = 'Install the latest Aabhushan app to choose and preview your personal notification sound.';
}
