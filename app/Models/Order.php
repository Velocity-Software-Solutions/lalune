<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use App\Models\OrderItem;
use App\Models\ShippingOptions;
use App\Models\Coupon;
class Order extends Model
{
    use HasFactory;

    /**
     * Mass-assignable columns matching your orders table.
     */
    protected $fillable = [
        'user_id',
        'email',
        'full_name',
        'order_number',
        'total_amount',
        'payment_status',
        'order_status',
        'shipping_address',
        'billing_address',
        'payment_method',
        'notes',
        'coupon_id',
        'shipping_option_id',
    ];

    /**
     * Casts.
     */
    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    /**
     * Status constants (keeps controller/view code DRY).
     */
    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_PAID    = 'paid';
    public const PAYMENT_FAILED  = 'failed';

    public const ORDER_PENDING     = 'pending';
    public const ORDER_PROCESSING  = 'processing';
    public const ORDER_SHIPPED     = 'shipped';
    public const ORDER_DELIVERED   = 'delivered';
    public const ORDER_CANCELLED   = 'cancelled';

    public const PAYMENT_STATUSES = [
        self::PAYMENT_PENDING,
        self::PAYMENT_PAID,
        self::PAYMENT_FAILED,
    ];

    public const ORDER_STATUSES = [
        self::ORDER_PENDING,
        self::ORDER_PROCESSING,
        self::ORDER_SHIPPED,
        self::ORDER_DELIVERED,
        self::ORDER_CANCELLED,
    ];

    /**
     * Automatically generate an order_number if not supplied.
     */
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateNumber();
            }
        });
    }

    /**
     * Generate a unique-ish order number.
     *
     * Format example: ORD-20250722-ABCD12
     */
    public static function generateNumber(): string
    {
        return 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    /* -----------------------------------------------------------------
     |  Relationships
     | -----------------------------------------------------------------
     */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function shippingOption()
    {
        return $this->belongsTo(ShippingOptions::class);
    }

    /* -----------------------------------------------------------------
     |  Helpers
     | -----------------------------------------------------------------
     */

    /**
     * Recalculate total_amount from items table.
     * Call after adding/removing items.
     */
    public function recalcTotals(): void
    {
        $total = $this->items()->sum('subtotal');
        $this->update(['total_amount' => $total]);
    }

    /**
     * Simple badge helpers (optional for Blade).
     */
    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    public function isFailed(): bool
    {
        return $this->payment_status === self::PAYMENT_FAILED;
    }

    public function isPendingPayment(): bool
    {
        return $this->payment_status === self::PAYMENT_PENDING;
    }


}
