<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Supplier;
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

    /**
     * A new supplier is a rare, genuinely actionable event (unlike a
     * stock_in movement, which happens constantly) so it's fine to
     * notify on every occurrence.
     */
    public function supplierAdded(Supplier $supplier): void
    {
        Notification::create([
            'business_id' => Tenant::id(),
            'user_id' => null,
            'type' => 'supplier_added',
            'title' => "New supplier added: {$supplier->name}",
            'data' => ['supplier_id' => $supplier->id],
        ]);
    }

    /**
     * Fires only when a product is first linked to a supplier while
     * previously having none, or when checked explicitly (e.g. from a
     * dashboard alert scan) — never on every product view, to avoid spam.
     */
    public function productMissingSupplier(Product $product): void
    {
        if (Notification::where('business_id', Tenant::id())
            ->where('type', 'product_missing_supplier')
            ->whereJsonContains('data->product_id', $product->id)
            ->exists()) {
            return;
        }

        $this->create($product, 'product_missing_supplier', "{$product->name} has no supplier assigned.");
    }

    public function productMissingPrice(Product $product): void
    {
        if (Notification::where('business_id', Tenant::id())
            ->where('type', 'product_missing_price')
            ->whereJsonContains('data->product_id', $product->id)
            ->exists()) {
            return;
        }

        $this->create($product, 'product_missing_price', "{$product->name} has no reference price set.");
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
