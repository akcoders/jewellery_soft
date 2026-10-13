<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class FollowupQuality extends BaseConfig
{
    public int $duplicateWindowDays = 30;
    public int $minimumNormalizedLength = 8;
    public int $maximumRegexLength = 300;
    public int $regexBacktrackLimit = 100000;
    public int $regexRecursionLimit = 10000;

    /** @var list<string> Trusted, application-owned PCRE patterns only. */
    public array $duplicateContentPatterns = [];

    /** @var list<string> */
    public array $removalPatterns = [
        '~\b(?:\d{1,2}[/-]){2}\d{2,4}\b~u',
        '~\b\d{4}-\d{1,2}-\d{1,2}\b~u',
        '~\b\d{1,2}:\d{2}(?::\d{2})?\s*(?:am|pm)?\b~iu',
        '~(?:\+?\d[\d\s().-]{7,}\d)~u',
        '~\b(?:(?:ref(?:erence)?|ticket|case|sr|id)\s*[:#-]?\s*[a-z0-9][a-z0-9/-]*|no\.?\s*[:#-]?\s*\d[a-z0-9/-]*)\b~iu',
    ];

    /** @var list<string> */
    public array $boilerplatePhrases = [
        'customer contacted',
        'follow up done',
        'followup done',
        'called customer',
        'spoke to customer',
    ];

    /** @var list<string> */
    public array $allowedImageMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
}
