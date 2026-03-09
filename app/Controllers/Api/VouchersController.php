<?php

namespace App\Controllers\Api;

use App\Services\PostingService;
use RuntimeException;

class VouchersController extends ApiBaseController
{
    public function create()
    {
        $payload = $this->payload();
        $header = (array) ($payload['header'] ?? []);
        $lines = (array) ($payload['lines'] ?? []);

        try {
            $header['created_by'] = (int) ($header['created_by'] ?? (session('admin_id') ?: 0));
            $result = (new PostingService())->postVoucher($header, $lines);
            return $this->ok($result, 'Voucher posted.');
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->fail('Voucher posting failed.', 500, $e->getMessage());
        }
    }

    public function reverse(int $voucherId)
    {
        $payload = $this->payload();
        $reason = trim((string) ($payload['reason'] ?? 'Correction'));

        try {
            $result = (new PostingService())->reverseVoucher($voucherId, $reason, (int) (session('admin_id') ?: 0));
            return $this->ok($result, 'Voucher reversed.');
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->fail('Voucher reversal failed.', 500, $e->getMessage());
        }
    }

    public function correct(int $voucherId)
    {
        $payload = $this->payload();
        $reason = trim((string) ($payload['reason'] ?? 'Correction'));
        $header = (array) ($payload['new_header'] ?? []);
        $lines = (array) ($payload['new_lines'] ?? []);

        try {
            $header['created_by'] = (int) ($header['created_by'] ?? (session('admin_id') ?: 0));
            $result = (new PostingService())->reverseAndRepost($voucherId, $reason, $header, $lines);
            return $this->ok($result, 'Voucher corrected with reversal + repost.');
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->fail('Voucher correction failed.', 500, $e->getMessage());
        }
    }
}
