<?php

namespace App\Libraries;

/** Presentation only: retain the detailed receiving label in storage for traceability. */
final class DiamondDisplay
{
    /** @param array<string,mixed> $component */
    public static function componentName(array $component): string
    {
        $type = strtolower(trim((string) ($component['component_type'] ?? $component['type'] ?? '')));
        $name = trim((string) ($component['component_name'] ?? $component['name'] ?? ''));
        if ($type !== 'diamond') {
            return $name !== '' ? $name : ucfirst($type !== '' ? $type : 'Stone');
        }

        $product = trim((string) ($component['diamond_type'] ?? ''));
        if ($product !== '') {
            return $product;
        }

        // Receiving stores either "product / shape / size / ..." (legacy)
        // or "bag / product / shape / size / ... / Order ..." (bag based).
        // Split only the spaced separator so product codes such as SI/IJ survive.
        $parts = explode(' / ', $name);
        $last = trim((string) end($parts));
        $hasBagReference = count($parts) >= 2 && ((int) ($component['diamond_bag_item_id'] ?? 0) > 0
            || (int) ($component['diamond_issue_line_id'] ?? 0) > 0
            || (count($parts) >= 3 && ($last === 'Unallocated' || str_starts_with($last, 'Order '))));
        $product = trim((string) ($parts[$hasBagReference ? 1 : 0] ?? ''));

        return $product !== '' ? $product : 'Diamond';
    }
}
