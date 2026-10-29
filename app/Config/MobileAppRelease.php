<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MobileAppRelease extends BaseConfig
{
    public string $androidVersion = '1.0.15';
    public int $androidVersionCode = 16;
    public string $androidDownloadUrl = 'https://github.com/akcoders/jewellery_soft/releases/download/android-v1.0.15-build16/aabhushan-erp-1.0.15-build16.apk';
    public string $androidSha256 = 'bd61a24ffab320bef3ddd04c9b1eca79dcf2597b5cac34b87936da16bc834d0d';
    public string $androidSizeLabel = '68.8 MB';
    public bool $androidRequired = true;
    public string $androidMessage = 'Install Aabhushan 1.0.15 for the new ice-blue, lavender and sapphire workspace, refined forms and photo controls, clearer cards, and smooth accessible transitions. Follow-ups finish at Ready; orders complete after receiving.';
}
