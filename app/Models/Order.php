<?php

namespace App\Models;

use App\Core\Database\Model;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;

/**
 * Order Model
 * 
 * E-commerce order with items, payments, shipping, and status management
 */
class Order extends Model
{
    protected $table = 'orders';
    
    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'payment_status',
        'shipping_status',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'notes',
        'billing_address',
        'shipping_address',
        'payment_method',
        'shipping_method',
        'tracking_number',
        'shipped_at',
        'delivered_at'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'billing_address' => 'json',
        'shipping_address' => 'json',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = [
        'status_label',
        'payment_status_label',
        'shipping_status_label'
    ];

    // Order statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    // Payment statuses
    const PAYMENT_PENDING = 'pending';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_FAILED = 'failed';
    const PAYMENT_REFUNDED = 'refunded';
    const PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';

    // Shipping statuses
    const SHIPPING_PENDING = 'pending';
    const SHIPPING_PROCESSING = 'processing';
    const SHIPPING_SHIPPED = 'shipped';
    const SHIPPING_DELIVERED = 'delivered';

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Order belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Order has many items
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Order has many payments
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Order has many shipments
     */
    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Order has many status histories
     */
    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Order belongs to many coupons
     */
    public function coupons()
    {
        return $this->belongsToMany(Coupon::class, 'order_coupons')
                    ->withPivot('discount_amount')
                    ->withTimestamps();
    }

    // ========================================
    // ACCESSORS & MUTATORS
    // ========================================

    /**
     * Get human-readable status label
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_SHIPPED => 'Shipped',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded'
        ];

        return $labels[$this->status] ?? 'Unknown';
    }

    /**
     * Get human-readable payment status label
     */
    public function getPaymentStatusLabelAttribute()
    {
        $labels = [
            self::PAYMENT_PENDING => 'Pending Payment',
            self::PAYMENT_PAID => 'Paid',
            self::PAYMENT_FAILED => 'Payment Failed',
            self::PAYMENT_REFUNDED => 'Refunded',
            self::PAYMENT_PARTIALLY_REFUNDED => 'Partially Refunded'
        ];

        return $labels[$this->payment_status] ?? 'Unknown';
    }

    /**
     * Get human-readable shipping status label
     */
    public function getShippingStatusLabelAttribute()
    {
        $labels = [
            self::SHIPPING_PENDING => 'Pending Shipment',
            self::SHIPPING_PROCESSING => 'Processing Shipment',
            self::SHIPPING_SHIPPED => 'Shipped',
            self::SHIPPING_DELIVERED => 'Delivered'
        ];

        return $labels[$this->shipping_status] ?? 'Unknown';
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    /**
     * Scope for orders by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for pending orders
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for processing orders
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * Scope for shipped orders
     */
    public function scopeShipped($query)
    {
        return $query->where('status', self::STATUS_SHIPPED);
    }

    /**
     * Scope for delivered orders
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    /**
     * Scope for paid orders
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_PAID);
    }

    /**
     * Scope for orders within date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Calculate order totals
     */
    public function calculateTotals()
    {
        $this->subtotal = $this->items->sum(function($item) {
            return $item->quantity * $item->price;
        });

        // Calculate tax (assuming 10% tax rate)
        $this->tax_amount = $this->subtotal * 0.10;

        // Apply discounts
        $this->discount_amount = $this->calculateDiscounts();

        // Calculate total
        $this->total_amount = $this->subtotal + $this->tax_amount + $this->shipping_amount - $this->discount_amount;

        return $this;
    }

    /**
     * Calculate discounts from coupons
     */
    private function calculateDiscounts()
    {
        $totalDiscount = 0;

        foreach ($this->coupons as $coupon) {
            if ($coupon->type === 'percentage') {
                $discount = $this->subtotal * ($coupon->value / 100);
                $discount = min($discount, $coupon->max_discount ?? $discount);
            } else {
                $discount = $coupon->value;
            }

            $totalDiscount += $discount;
        }

        return $totalDiscount;
    }

    /**
     * Update order status
     */
    public function updateStatus($newStatus, $notes = null)
    {
        $oldStatus = $this->status;
        
        if ($oldStatus === $newStatus) {
            return false;
        }

        // Validate status transition
        if (!$this->canTransitionTo($newStatus)) {
            throw new \Exception("Cannot transition from {$oldStatus} to {$newStatus}");
        }

        $this->status = $newStatus;
        $this->save();

        // Record status history
        $this->statusHistories()->create([
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
            'changed_by' => auth()->id() ?? null
        ]);

        // Fire event
        event(new OrderStatusChanged($this, $oldStatus, $newStatus));

        // Handle status-specific logic
        $this->handleStatusChange($newStatus);

        return true;
    }

    /**
     * Check if order can transition to new status
     */
    private function canTransitionTo($newStatus)
    {
        $allowedTransitions = [
            self::STATUS_PENDING => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
            self::STATUS_PROCESSING => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
            self::STATUS_SHIPPED => [self::STATUS_DELIVERED],
            self::STATUS_DELIVERED => [self::STATUS_REFUNDED],
            self::STATUS_CANCELLED => [],
            self::STATUS_REFUNDED => []
        ];

        return in_array($newStatus, $allowedTransitions[$this->status] ?? []);
    }

    /**
     * Handle status-specific logic
     */
    private function handleStatusChange($status)
    {
        switch ($status) {
            case self::STATUS_PROCESSING:
                $this->reserveInventory();
                break;
            
            case self::STATUS_SHIPPED:
                $this->shipping_status = self::SHIPPING_SHIPPED;
                $this->shipped_at = now();
                $this->save();
                break;
            
            case self::STATUS_DELIVERED:
                $this->shipping_status = self::SHIPPING_DELIVERED;
                $this->delivered_at = now();
                $this->save();
                break;
            
            case self::STATUS_CANCELLED:
                $this->releaseInventory();
                break;
        }
    }

    /**
     * Reserve inventory for order items
     */
    private function reserveInventory()
    {
        foreach ($this->items as $item) {
            $item->product->reduceStock($item->quantity);
        }
    }

    /**
     * Release inventory for order items
     */
    private function releaseInventory()
    {
        foreach ($this->items as $item) {
            $item->product->increaseStock($item->quantity);
        }
    }

    /**
     * Process payment
     */
    public function processPayment($paymentData)
    {
        // Create payment record
        $payment = $this->payments()->create([
            'amount' => $this->total_amount,
            'method' => $paymentData['method'],
            'status' => 'pending',
            'transaction_id' => $paymentData['transaction_id'] ?? null,
            'gateway_response' => $paymentData['gateway_response'] ?? null
        ]);

        // Process payment through gateway
        $paymentProcessor = app('PaymentProcessor');
        $result = $paymentProcessor->process($payment, $paymentData);

        if ($result['success']) {
            $payment->update([
                'status' => 'completed',
                'processed_at' => now()
            ]);
            
            $this->payment_status = self::PAYMENT_PAID;
            $this->save();
            
            // Auto-transition to processing if payment successful
            if ($this->status === self::STATUS_PENDING) {
                $this->updateStatus(self::STATUS_PROCESSING, 'Payment received');
            }
        } else {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => $result['error']
            ]);
            
            $this->payment_status = self::PAYMENT_FAILED;
            $this->save();
        }

        return $result;
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Check if order can be refunded
     */
    public function canBeRefunded()
    {
        return $this->status === self::STATUS_DELIVERED && 
               $this->payment_status === self::PAYMENT_PAID;
    }

    /**
     * Generate order number
     */
    public static function generateOrderNumber()
    {
        $prefix = 'ORD';
        $timestamp = date('Ymd');
        $sequence = str_pad(static::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT);
        
        return $prefix . $timestamp . $sequence;
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        // Auto-generate order number
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
            
            // Set default statuses
            $order->status = $order->status ?? self::STATUS_PENDING;
            $order->payment_status = $order->payment_status ?? self::PAYMENT_PENDING;
            $order->shipping_status = $order->shipping_status ?? self::SHIPPING_PENDING;
        });

        // Fire order created event
        static::created(function ($order) {
            event(new OrderCreated($order));
        });
    }
}
