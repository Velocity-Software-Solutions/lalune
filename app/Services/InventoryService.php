<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockReservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Request;

class InventoryService
{
    /** Mark expired holds inactive (idempotent). */
    public static function expireOldReservations(): int
    {
        return StockReservation::query()
            ->where('status', true)
            ->where('expires_at', '<=', now())
            ->update(['status' => false]);
    }

    /**
     * Create/refresh reservations for the current cart.
     * - Locks each stock row while computing availability (no double-reserve).
     * - Clamps quantities if needed; removes lines that hit 0.
     * - Returns [reservation_ids, changes, cartModified, cart].
     */
    public static function reserveCart(array $cart, string $sessionKey, ?int $userId, int $ttlMinutes = 15): array
    {
        $reservationIds = [];
        $changes = [];
        $modified = false;
        $expiresAt = now()->addMinutes($ttlMinutes);

        // Clean up old holds first
        self::expireOldReservations();

        // Group product ids to eager load
        $productIds = array_values(array_unique(array_map(fn($r) => (int) $r['product_id'], $cart)));
        /** @var \Illuminate\Support\Collection<int,Product> $products */
        $products = Product::with(['stock', 'colors', 'sizes'])
            ->whereIn('id', $productIds)->get()->keyBy('id');

        DB::transaction(function () use (&$cart, $products, $sessionKey, $userId, $expiresAt, &$reservationIds, &$changes, &$modified) {
            foreach ($cart as $key => &$line) {
                $pid = (int) ($line['product_id'] ?? 0);
                $quantity = max(1, (int) ($line['quantity'] ?? 1));
                $p = $products->get($pid);

                if (!$p || (int) $p->status !== 1) {
                    unset($cart[$key]);
                    $modified = true;
                    $changes[] = "Removed “{$line['name']}” (unavailable).";
                    continue;
                }

                $hasColors = $p->colors->isNotEmpty();
                $hasSizes = $p->sizes->isNotEmpty();
                $hex = isset($line['color']) && $line['color'] ? strtoupper((string) $line['color']) : null;
                $size = isset($line['size']) && $line['size'] ? (string) $line['size'] : null;

                // Find variant row if applicable
                $variant = null;
                if (!empty($line['product_stock_id'])) {
                    $variant = $p->stock->firstWhere('id', (int) $line['product_stock_id']);
                } elseif ($hasColors || $hasSizes) {
                    $colorId = $hasColors && $hex ? optional($p->colors->firstWhere('color_code', $hex))->id : null;
                    $sizeId = $hasSizes && $size ? optional($p->sizes->firstWhere('size', $size))->id : null;
                    $variant = $p->stock->first(fn($row) => (int) $row->color_id === (int) $colorId && (int) $row->size_id === (int) $sizeId);
                }

                if ($variant) {
                    // Lock the variant row to serialize reservations for this SKU
                    $locked = DB::table('product_stocks')->where('id', $variant->id)->lockForUpdate()->first();
                    if (!$locked) {
                        unset($cart[$key]);
                        $modified = true;
                        $changes[] = "Removed “{$line['name']}” (variant gone).";
                        continue;
                    }

                    $reserved = DB::table('stock_reservations')
                        ->where('product_stock_id', $variant->id)
                        ->where('status', true)
                        ->where('expires_at', '>', now())
                        ->sum('quantity');

                    $available = max(0, (int) $locked->quantity_on_hand - (int) $reserved);

                    if ($available <= 0) {
                        unset($cart[$key]);
                        $modified = true;
                        $changes[] = "Removed “{$line['name']}” (out of stock).";
                        continue;
                    }

                    if ($quantity > $available) {
                        $quantity = $line['quantity'] = $available;
                        $modified = true;
                        $changes[] = "Updated “{$line['name']}” to {$available} (limited stock).";
                    }

                    $res = StockReservation::create([
                        'product_id' => null,
                        'product_stock_id' => $variant->id,
                        'quantity' => $quantity,
                        'session_key' => $sessionKey,
                        'user_id' => $userId,
                        'expires_at' => $expiresAt,
                        'status' => true,
                    ]);
                    $reservationIds[] = $res->id;

                    // Ensure we keep the variant id in cart
                    if (empty($line['product_stock_id']))
                        $line['product_stock_id'] = $variant->id;
                } else {
                    // Product-level stock only
                    $locked = DB::table('products')->where('id', $p->id)->lockForUpdate()->first();
                    if (!$locked) {
                        unset($cart[$key]);
                        $modified = true;
                        $changes[] = "Removed “{$line['name']}” (product gone).";
                        continue;
                    }

                    $reserved = DB::table('stock_reservations')
                        ->where('product_id', $p->id)
                        ->where('status', true)
                        ->where('expires_at', '>', now())
                        ->sum('quantity');

                    $available = max(0, (int) $locked->stock_quantity - (int) $reserved);

                    if ($available <= 0) {
                        unset($cart[$key]);
                        $modified = true;
                        $changes[] = "Removed “{$line['name']}” (out of stock).";
                        continue;
                    }

                    if ($quantity > $available) {
                        $quantity = $line['quantity'] = $available;
                        $modified = true;
                        $changes[] = "Updated “{$line['name']}” to {$available} (limited stock).";
                    }

                    $res = StockReservation::create([
                        'product_id' => $p->id,
                        'product_stock_id' => null,
                        'quantity' => $quantity,
                        'session_key' => $sessionKey,
                        'user_id' => $userId,
                        'expires_at' => $expiresAt,
                        'status' => true,
                    ]);
                    $reservationIds[] = $res->id;
                }
            }
        });

        // Remove empties
        $cart = array_filter($cart, fn($r) => isset($r['quantity']) && (int) $r['quantity'] > 0);

        return [
            'reservation_ids' => $reservationIds,
            'changes' => $changes,
            'cartModified' => $modified,
            'cart' => $cart,
            'expires_at' => $expiresAt,
        ];
    }

    /** Release all active reservations for a session (e.g., cancel). */
    public static function releaseBySession(string $sessionKey): int
    {
        return StockReservation::query()
            ->where('session_key', $sessionKey)
            ->where('status', true)
            ->update(['status' => false]);
    }

    /**
     * Commit reservations (decrement stock atomically) then mark them inactive.
     * Throw if any decrement fails (wrap caller in try/catch and refund/cancel if needed).
     */
    public static function commitReservations(array $reservationIds): void
    {
        if (empty($reservationIds))
            return;

        DB::transaction(function () use ($reservationIds) {
            $rows = StockReservation::query()
                ->whereIn('id', $reservationIds)
                ->where('status', true)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->get();

            // Group by target
            $byVariant = $rows->whereNotNull('product_stock_id')->groupBy('product_stock_id');
            foreach ($byVariant as $stockId => $group) {
                $need = (int) $group->sum('quantity');
                // lock & decrement atomically
                $affected = DB::table('product_stocks')
                    ->where('id', $stockId)
                    ->where('quantity_on_hand', '>=', $need)
                    ->update(['quantity_on_hand' => DB::raw('quantity_on_hand - ' . $need)]);

                if ($affected === 0) {
                    throw new \RuntimeException('Stock conflict on variant ' . $stockId);
                }
            }

            $byProduct = $rows->whereNull('product_stock_id')->groupBy('product_id');
            foreach ($byProduct as $productId => $group) {
                $need = (int) $group->sum('quantity');
                $affected = DB::table('products')
                    ->where('id', $productId)
                    ->where('stock_quantity', '>=', $need)
                    ->update(['stock_quantity' => DB::raw('stock_quantity - ' . $need)]);
                if ($affected === 0) {
                    throw new \RuntimeException('Stock conflict on product ' . $productId);
                }
            }

            // Mark all processed reservations inactive
            StockReservation::query()
                ->whereIn('id', $rows->pluck('id'))
                ->update(['status' => false]);
        });
    }
    public function cancel(Request $request)
    {
        InventoryService::releaseBySession($request->session()->getId());
        return redirect()->route('cart.index')->with('info', 'Your reservation was released.');
    }
}
