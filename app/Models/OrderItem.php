<?php

namespace App\Models;

use App\Core\Database\Model;

/**
 * Order Item Model
 * 
 * Individual items within an order
 */
class OrderItem extends Model
{
    protected $table = 'order_items';
    
    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'quantity',
        'price',
        'total'
    ];

    protected $casts = [
        'order_id' => 'integer',
        'product_id' => 'integer',
        'variant_id' => 'integer',
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Order item belongs to an order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Order item belongs to a product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Order item belongs to a product variant (optional)
     */
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Calculate total price
     */
    public function calculateTotal()
    {
        $this->total = $this->quantity * $this->price;
        return $this->total;
    }

    /**
     * Get display name for the item
     */
    public function getDisplayName()
    {
        $name = $this->product->name;
        
        if ($this->variant) {
            $name .= ' - ' . $this->variant->getDisplayName();
        }
        
        return $name;
    }

    /**
     * Fulfill from inventory
     */
    public function fulfillFromInventory()
    {
        if ($this->variant) {
            return $this->variant->reduceStock($this->quantity);
        }
        
        return $this->product->reduceStock($this->quantity);
    }

    /**
     * Initiate return request
     */
    public function initiateReturn($data)
    {
        return ReturnRequest::create([
            'order_item_id' => $this->id,
            'quantity' => $data['quantity'],
            'reason' => $data['reason'],
            'description' => $data['description'] ?? null,
            'status' => 'pending'
        ]);
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        // Auto-calculate total when saving
        static::saving(function ($orderItem) {
            $orderItem->calculateTotal();
        });
    }
}
