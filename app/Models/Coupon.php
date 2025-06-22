<?php

namespace App\Models;

use App\Core\Database\Model;

/**
 * Coupon Model
 * 
 * Discount coupons for e-commerce orders
 */
class Coupon extends Model
{
    protected $table = 'coupons';
    
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_amount',
        'max_discount',
        'usage_limit',
        'used_count',
        'starts_at',
        'expires_at',
        'active'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Coupon types
    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_FIXED = 'fixed';

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Coupon belongs to many orders
     */
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_coupons')
                    ->withPivot('discount_amount')
                    ->withTimestamps();
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    /**
     * Scope for active coupons
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for valid coupons (not expired)
     */
    public function scopeValid($query)
    {
        $now = now();
        return $query->where('active', true)
                    ->where(function($q) use ($now) {
                        $q->whereNull('starts_at')
                          ->orWhere('starts_at', '<=', $now);
                    })
                    ->where(function($q) use ($now) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>=', $now);
                    });
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Check if coupon is valid for use
     */
    public function isValid($orderAmount = null)
    {
        if (!$this->active) {
            return false;
        }

        $now = now();

        // Check start date
        if ($this->starts_at && $this->starts_at > $now) {
            return false;
        }

        // Check expiry date
        if ($this->expires_at && $this->expires_at < $now) {
            return false;
        }

        // Check usage limit
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        // Check minimum amount
        if ($orderAmount && $this->min_amount && $orderAmount < $this->min_amount) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount amount for given order amount
     */
    public function calculateDiscount($orderAmount)
    {
        if (!$this->isValid($orderAmount)) {
            return 0;
        }

        if ($this->type === self::TYPE_PERCENTAGE) {
            $discount = $orderAmount * ($this->value / 100);
            
            // Apply maximum discount limit if set
            if ($this->max_discount) {
                $discount = min($discount, $this->max_discount);
            }
            
            return $discount;
        } else {
            return min($this->value, $orderAmount);
        }
    }

    /**
     * Apply coupon to order
     */
    public function applyToOrder(Order $order)
    {
        if (!$this->isValid($order->subtotal)) {
            throw new \Exception("Coupon is not valid for this order");
        }

        $discountAmount = $this->calculateDiscount($order->subtotal);
        
        // Attach coupon to order with discount amount
        $order->coupons()->attach($this->id, [
            'discount_amount' => $discountAmount
        ]);

        // Increment usage count
        $this->increment('used_count');

        return $discountAmount;
    }

    /**
     * Generate unique coupon code
     */
    public static function generateCode($length = 8)
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        // Ensure uniqueness
        while (static::where('code', $code)->exists()) {
            $code = static::generateCode($length);
        }
        
        return $code;
    }
}
