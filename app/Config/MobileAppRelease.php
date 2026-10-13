<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MobileAppRelease extends BaseConfig
{
    public string $androidVersion = '1.0.5';
    public int $androidVersionCode = 6;
    public string $androidDownloadUrl = 'https://github.com/akcoders/jewellery_soft/releases/download/android-v1.0.5-build6/aabhushan-erp-1.0.5-build6.apk';
    public string $androidSha256 = '9abeeae69488de92c27d3ca33948a8cd3abe5cf16e286683b31245b3a44d5dcd';
    public string $androidSizeLabel = '105.4 MB';
    public bool $androidRequired = true;
    public string $androidMessage = 'Install the latest Aabhushan app for required updates, employee camera controls, and the new notification sound.';
}
