<?php

namespace App\Models;

use App\Core\Database\Model;

/**
 * Product Variant Model
 * 
 * Product variations (size, color, etc.) with individual pricing and inventory
 */
class ProductVariant extends Model
{
    protected $table = 'product_variants';
    
    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'price',
        'cost_price',
        'stock_quantity',
        'weight',
        'dimensions',
        'attributes',
        'active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'stock_quantity' => 'integer',
        'dimensions' => 'json',
        'attributes' => 'json',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Variant belongs to a product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variant has many order items
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }

    /**
     * Variant has many cart items
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class, 'variant_id');
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    /**
     * Scope for active variants
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for variants in stock
     */
    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    /**
     * Scope for variants by attribute
     */
    public function scopeByAttribute($query, $key, $value)
    {
        return $query->whereJsonContains('attributes->' . $key, $value);
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Check if variant is in stock
     */
    public function isInStock()
    {
        return $this->stock_quantity > 0;
    }

    /**
     * Check if variant can be purchased
     */
    public function canBePurchased($quantity = 1)
    {
        if (!$this->active) {
            return false;
        }
        
        if (!$this->isInStock()) {
            return false;
        }
        
        if ($this->stock_quantity < $quantity) {
            return false;
        }
        
        return true;
    }

    /**
     * Reduce stock quantity
     */
    public function reduceStock($quantity)
    {
        if ($this->stock_quantity < $quantity) {
            throw new \Exception("Insufficient stock for variant: {$this->name}");
        }
        
        $this->stock_quantity -= $quantity;
        return $this->save();
    }

    /**
     * Increase stock quantity
     */
    public function increaseStock($quantity)
    {
        $this->stock_quantity += $quantity;
        return $this->save();
    }

    /**
     * Get variant display name
     */
    public function getDisplayName()
    {
        if ($this->name) {
            return $this->name;
        }
        
        $attributes = [];
        foreach ($this->attributes as $key => $value) {
            $attributes[] = ucfirst($key) . ': ' . $value;
        }
        
        return implode(', ', $attributes);
    }

    /**
     * Get effective price (variant price or product price)
     */
    public function getEffectivePrice()
    {
        return $this->price ?: $this->product->price;
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        // Auto-generate SKU if not provided
        static::creating(function ($variant) {
            if (empty($variant->sku)) {
                $variant->sku = $variant->generateSKU();
            }
        });
    }

    /**
     * Generate unique SKU for variant
     */
    private function generateSKU()
    {
        $productSku = $this->product->sku ?? 'PRD';
        $timestamp = time();
        $random = mt_rand(100, 999);
        
        return $productSku . '-VAR-' . $timestamp . $random;
    }
}
