<?php

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

final class PurchaseTermsService
{
    public const MAX_TERMS_DAYS = 36500;

    /**
     * @return array{payment_terms_days: int|null, due_date: string|null}
     */
    public function resolve(string $purchaseDate, mixed $terms): array
    {
        $rawTerms = trim((string) ($terms ?? ''));
        if ($rawTerms === '') {
            return [
                'payment_terms_days' => null,
                'due_date' => null,
            ];
        }

        if (! ctype_digit($rawTerms)) {
            throw new InvalidArgumentException('Terms must be a whole number of days.');
        }

        $termsDays = (int) $rawTerms;
        if ($termsDays > self::MAX_TERMS_DAYS) {
            throw new InvalidArgumentException('Terms cannot exceed ' . self::MAX_TERMS_DAYS . ' days.');
        }

        $date = $this->parseDate($purchaseDate, 'A valid purchase date is required to calculate the due date.');

        return [
            'payment_terms_days' => $termsDays,
            'due_date' => $date->add(new DateInterval('P' . $termsDays . 'D'))->format('Y-m-d'),
        ];
    }

    /**
     * Supports mobile clients released before the terms field was introduced.
     *
     * @return array{payment_terms_days: int|null, due_date: string|null}
     */
    public function resolveLegacyDueDate(string $purchaseDate, mixed $dueDate): array
    {
        $rawDueDate = trim((string) ($dueDate ?? ''));
        if ($rawDueDate === '') {
            return $this->resolve($purchaseDate, null);
        }

        $purchase = $this->parseDate($purchaseDate, 'A valid purchase date is required to calculate the due date.');
        $due = $this->parseDate($rawDueDate, 'Due date must be a valid date.');
        if ($due < $purchase) {
            throw new InvalidArgumentException('Due date cannot be earlier than the purchase date.');
        }

        return $this->resolve($purchaseDate, (string) $purchase->diff($due)->days);
    }

    private function parseDate(string $value, string $errorMessage): DateTimeImmutable
    {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($errorMessage);
        }

        return $date;
    }
}
