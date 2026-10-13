<?php

namespace App\Controllers\Api\Mobile;

use Config\MobileAppRelease;

class AppUpdateController extends MobileBaseController
{
    public function status()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $platform = strtolower(trim((string) $this->request->getGet('platform')));
        if ($platform === 'android') {
            return $this->androidStatus();
        }

        $db = db_connect();
        if (! $db->tableExists('pwa_update_releases')) {
            return $this->ok(['update' => null]);
        }

        $release = $db->table('pwa_update_releases')
            ->select('version, message, created_at')
            ->orderBy('id', 'DESC')
            ->get(1)
            ->getRowArray();

        return $this->ok(['update' => is_array($release) ? $release : null]);
    }

    private function androidStatus()
    {
        /** @var MobileAppRelease $release */
        $release = config(MobileAppRelease::class);
        $installedVersionCode = max(0, (int) $this->request->getGet('version_code'));
        $downloadUrl = trim($release->androidDownloadUrl);
        $available = $release->androidVersionCode > $installedVersionCode
            && filter_var($downloadUrl, FILTER_VALIDATE_URL) !== false
            && str_starts_with(strtolower($downloadUrl), 'https://');

        if (! $available) {
            return $this->ok([
                'update' => null,
                'latest_version' => $release->androidVersion,
                'latest_version_code' => $release->androidVersionCode,
            ]);
        }

        return $this->ok([
            'update' => [
                'platform' => 'android',
                'version' => $release->androidVersion,
                'version_code' => $release->androidVersionCode,
                'download_url' => $downloadUrl,
                'sha256' => $release->androidSha256,
                'size_label' => $release->androidSizeLabel,
                'required' => $release->androidRequired,
                'message' => $release->androidMessage,
            ],
        ]);
    }
}
