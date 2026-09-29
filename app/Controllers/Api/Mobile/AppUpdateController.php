<?php

namespace App\Controllers\Api\Mobile;

class AppUpdateController extends MobileBaseController
{
    public function status()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
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
}
