<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Notification;
use App\Models\Product;
use App\Support\Tenant;

/**
 * Deliberately narrow: per the spec's own "avoid excessive notifications"
 * rule, we do NOT notify on every single stock_in/stock_out movement —
 * only on threshold crossings (low stock / out of stock) and on
 * noteworthy admin actions (adjustments, archive/trash/restore).
 */
class NotificationService
{
    /**
     * Call after any movement that changes a product's balance. Only
     * fires when the balance actually crosses a threshold, not on every
     * movement below/above it.
     */
    public function checkStockThresholds(Product $product, int $balanceBefore, int $balanceAfter): void
    {
        $settings = $this->settingsFor(Tenant::id());

        if ($balanceAfter <= 0 && $balanceBefore > 0) {
            if ($settings->out_of_stock_notifications_enabled) {
                $this->create($product, 'out_of_stock', "{$product->name} is now out of stock.");
            }

            return;
        }

        $threshold = $product->min_stock_level;
        if ($threshold > 0 && $balanceAfter <= $threshold && $balanceBefore > $threshold) {
            if ($settings->low_stock_notifications_enabled) {
                $this->create(
                    $product,
                    'low_stock',
                    "{$product->name} has fallen to {$balanceAfter} units (threshold: {$threshold})."
                );
            }
        }
    }

    public function adminActivity(Product $product, string $message): void
    {
        $this->create($product, 'admin_activity', $message);
    }

    private function create(Product $product, string $type, string $title): void
    {
        Notification::create([
            'business_id' => Tenant::id(),
            'user_id' => null, // broadcast to the whole business
            'type' => $type,
            'title' => $title,
            'data' => ['product_id' => $product->id],
        ]);
    }

    private function settingsFor(?int $businessId): BusinessSetting
    {
        return BusinessSetting::firstOrCreate(['business_id' => $businessId]);
    }
}
