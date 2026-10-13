<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\FollowupQuality;
use InvalidArgumentException;

class FollowupQualityService
{
    public function __construct(private ?BaseConnection $db = null, private ?FollowupQuality $config = null)
    {
        $this->db ??= db_connect();
        $this->config ??= config(FollowupQuality::class);
        $this->validateTrustedPatterns($this->config->removalPatterns);
        $this->validateTrustedPatterns($this->config->duplicateContentPatterns);
    }

    public function normalize(string $text): string
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower(trim($text), 'UTF-8') : strtolower(trim($text));
        foreach ($this->config->removalPatterns as $pattern) {
            $text = $this->safeReplace($pattern, ' ', $text);
        }
        foreach ($this->config->boilerplatePhrases as $phrase) {
            $quoted = preg_quote(function_exists('mb_strtolower') ? mb_strtolower($phrase, 'UTF-8') : strtolower($phrase), '~');
            $text = $this->safeReplace('~(?<![\pL\pN])' . $quoted . '(?![\pL\pN])~u', ' ', $text);
        }
        $text = $this->safeReplace('~[^\pL\pN]+~u', ' ', $text);
        return trim($this->safeReplace('~\s+~u', ' ', $text));
    }

    public function fingerprint(string $normalized): string
    {
        return hash('sha256', $normalized);
    }

    /** @return array{normalized:string,fingerprint:string,is_duplicate:bool,matched_followup_id:?int,reason:?string} */
    public function inspect(string $text, int $orderId, int $employeeId, ?string $at = null): array
    {
        $normalized = $this->normalize($text);
        $length = function_exists('mb_strlen') ? mb_strlen($normalized, 'UTF-8') : strlen($normalized);
        if ($length < $this->config->minimumNormalizedLength) {
            throw new InvalidArgumentException('Follow-up must contain a meaningful update beyond dates, references, and boilerplate.');
        }
        $fingerprint = $this->fingerprint($normalized);
        $at ??= date('Y-m-d H:i:s');
        $from = date('Y-m-d H:i:s', strtotime($at . ' -' . max(1, $this->config->duplicateWindowDays) . ' days'));
        $rows = $this->db->table('order_followups')
            ->select('id, description, normalized_fingerprint')
            ->where('order_id', $orderId)->where('followup_taken_by', $employeeId)
            ->where('followup_taken_on >=', $from)->where('followup_taken_on <=', $at)
            ->orderBy('followup_taken_on', 'DESC')->orderBy('id', 'DESC')->get()->getResultArray();
        foreach ($rows as $row) {
            $previous = $this->normalize((string) ($row['description'] ?? ''));
            $previousFingerprint = (string) ($row['normalized_fingerprint'] ?? '');
            if ($previousFingerprint === '') $previousFingerprint = $this->fingerprint($previous);
            if (hash_equals($previousFingerprint, $fingerprint)) {
                return compact('normalized', 'fingerprint') + ['is_duplicate' => true, 'matched_followup_id' => (int) $row['id'], 'reason' => 'Normalized text matches an earlier follow-up.'];
            }
        }
        foreach ($this->config->duplicateContentPatterns as $pattern) {
            if ($rows === []) break;
            if ($this->safeMatch($pattern, $normalized)) {
                $matchedId = (int) $rows[0]['id'];
                return compact('normalized', 'fingerprint') + ['is_duplicate' => true, 'matched_followup_id' => $matchedId, 'reason' => 'Matched configured duplicate-content rule.'];
            }
        }
        return compact('normalized', 'fingerprint') + ['is_duplicate' => false, 'matched_followup_id' => null, 'reason' => null];
    }

    public function hasValidImage(array $followup): bool
    {
        $path = trim((string) ($followup['image_path'] ?? ''));
        if ($path === '' || str_contains($path, "\0") || preg_match('~^(?:https?:)?//~i', $path)) return false;
        $publicRoot = realpath(FCPATH);
        $absolute = realpath(FCPATH . ltrim($path, '/'));
        if ($publicRoot === false || $absolute === false || ! str_starts_with($absolute, $publicRoot . DIRECTORY_SEPARATOR)
            || ! is_file($absolute) || filesize($absolute) <= 0 || @getimagesize($absolute) === false) return false;
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolute);
        return is_string($mime) && in_array($mime, $this->config->allowedImageMimeTypes, true);
    }

    public function isDuplicate(array $followup): bool
    {
        if (array_key_exists('duplicate_override', $followup) && $followup['duplicate_override'] !== null) {
            return (int) $followup['duplicate_override'] === 1;
        }
        return (int) ($followup['duplicate_followup_id'] ?? 0) > 0 || trim((string) ($followup['duplicate_reason'] ?? '')) !== '';
    }

    /** @return list<array{type:string,score_delta:float,reason:string}> */
    public function scoreEvents(array $followup): array
    {
        $approvalStatus = strtolower(trim((string) ($followup['approval_status'] ?? 'approved')));
        if ($approvalStatus === 'pending') {
            return [];
        }
        if ($approvalStatus === 'rejected') {
            $reason = 'Follow-up disapproved by administrator.';
            if (trim((string) ($followup['review_note'] ?? '')) !== '') {
                $reason .= ' ' . trim((string) $followup['review_note']);
            }
            return [['type' => 'Disapproved follow-up', 'score_delta' => -2.0, 'reason' => $reason]];
        }

        $events = [];
        if ($this->isDuplicate($followup)) {
            $matchedId = (int) ($followup['duplicate_followup_id'] ?? 0);
            $reason = (string) (($followup['duplicate_reason'] ?? '') ?: 'Duplicate confirmed by administrator.');
            if ($matchedId > 0) $reason .= ' Matched follow-up #' . $matchedId . '.';
            $events[] = ['type' => 'Duplicate follow-up', 'score_delta' => -1.0, 'reason' => $reason];
        } else {
            $events[] = ['type' => 'Approved follow-up', 'score_delta' => 2.0, 'reason' => 'Follow-up approved by administrator.'];
            if ($this->hasValidImage($followup)) $events[] = ['type' => 'Meaningful update', 'score_delta' => 0.25, 'reason' => 'Valid unique update with image evidence.'];
        }
        if (! $this->hasValidImage($followup)) {
            $events[] = ['type' => 'Image evidence missing', 'score_delta' => -0.5, 'reason' => 'Image evidence missing'];
        }
        return $events;
    }

    public function overrideDuplicate(int $followupId, bool $isDuplicate, int $adminId, string $reason): void
    {
        $reason = trim($reason);
        if ($followupId <= 0 || $adminId <= 0 || $reason === '') throw new InvalidArgumentException('Follow-up, administrator, and override reason are required.');
        $row = $this->db->table('order_followups')->select('id, duplicate_override')->where('id', $followupId)->get()->getRowArray();
        if (! is_array($row)) throw new InvalidArgumentException('Follow-up not found.');
        $newValue = $isDuplicate ? 1 : 0;
        if ($row['duplicate_override'] !== null && (int) $row['duplicate_override'] === $newValue) return;
        $this->db->transException(true)->transStart();
        $this->db->table('order_followups')->where('id', $followupId)->update(['duplicate_override' => $newValue, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->db->table('followup_duplicate_override_audits')->insert([
            'followup_id' => $followupId, 'previous_override' => $row['duplicate_override'], 'new_override' => $newValue,
            'reason' => $reason, 'changed_by' => $adminId, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->transComplete();
    }

    /** @param list<string> $patterns */
    private function validateTrustedPatterns(array $patterns): void
    {
        foreach ($patterns as $pattern) {
            if (! is_string($pattern) || strlen($pattern) > $this->config->maximumRegexLength
                || $this->containsUnsafeRegexToken($pattern)) {
                throw new InvalidArgumentException('Unsafe follow-up regex configuration.');
            }
            set_error_handler(static fn() => true);
            $valid = preg_match($pattern, '') !== false;
            restore_error_handler();
            if (! $valid) throw new InvalidArgumentException('Invalid follow-up regex configuration.');
        }
    }

    private function containsUnsafeRegexToken(string $pattern): bool
    {
        foreach (['(?R', '(?0', '(?1', '(?2', '(?3', '(?4', '(?5', '(?6', '(?7', '(?8', '(?9', '(?&', '\\g<', '\\g{', "\\g'", '\\k<', '\\k{', "\\k'"] as $token) {
            if (str_contains($pattern, $token)) return true;
        }
        return false;
    }

    private function safeMatch(string $pattern, string $subject): bool
    {
        $oldBacktrack = ini_set('pcre.backtrack_limit', (string) $this->config->regexBacktrackLimit);
        $oldRecursion = ini_set('pcre.recursion_limit', (string) $this->config->regexRecursionLimit);
        try { return preg_match($pattern, $subject) === 1; }
        finally {
            if ($oldBacktrack !== false) ini_set('pcre.backtrack_limit', $oldBacktrack);
            if ($oldRecursion !== false) ini_set('pcre.recursion_limit', $oldRecursion);
        }
    }

    private function safeReplace(string $pattern, string $replacement, string $subject): string
    {
        $result = preg_replace($pattern, $replacement, $subject);
        if ($result === null) throw new InvalidArgumentException('Follow-up text could not be normalized safely.');
        return $result;
    }
}
