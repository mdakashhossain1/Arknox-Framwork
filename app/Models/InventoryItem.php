<?php

namespace App\Models;

use App\Core\Database\Model;

/**
 * Inventory Item Model
 * 
 * Product inventory tracking per warehouse
 */
class InventoryItem extends Model
{
    protected $table = 'inventory_items';
    
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'reserved_quantity',
        'reorder_level',
        'reorder_quantity',
        'cost_price',
        'last_restocked_at'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'reorder_level' => 'integer',
        'reorder_quantity' => 'integer',
        'cost_price' => 'decimal:2',
        'last_restocked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Inventory item belongs to a product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Inventory item belongs to a warehouse
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Inventory item has many movements
     */
    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    // ========================================
    // ACCESSORS & MUTATORS
    // ========================================

    /**
     * Get available quantity (total - reserved)
     */
    public function getAvailableQuantityAttribute()
    {
        return $this->quantity - $this->reserved_quantity;
    }

    /**
     * Check if item needs reordering
     */
    public function getNeedsReorderAttribute()
    {
        return $this->reorder_level && $this->available_quantity <= $this->reorder_level;
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Reserve quantity for an order
     */
    public function reserve($quantity, $orderId = null)
    {
        if ($this->available_quantity < $quantity) {
            throw new \Exception("Insufficient available inventory");
        }

        $this->reserved_quantity += $quantity;
        $this->save();

        // Log movement
        $this->movements()->create([
            'type' => 'reservation',
            'quantity' => $quantity,
            'order_id' => $orderId,
            'notes' => "Reserved {$quantity} units for order {$orderId}"
        ]);

        return true;
    }

    /**
     * Release reserved quantity
     */
    public function release($quantity, $orderId = null)
    {
        if ($this->reserved_quantity < $quantity) {
            throw new \Exception("Cannot release more than reserved quantity");
        }

        $this->reserved_quantity -= $quantity;
        $this->save();

        // Log movement
        $this->movements()->create([
            'type' => 'release',
            'quantity' => $quantity,
            'order_id' => $orderId,
            'notes' => "Released {$quantity} units from order {$orderId}"
        ]);

        return true;
    }

    /**
     * Fulfill order (reduce actual quantity)
     */
    public function fulfill($quantity, $orderId = null)
    {
        if ($this->reserved_quantity < $quantity) {
            throw new \Exception("Cannot fulfill more than reserved quantity");
        }

        $this->quantity -= $quantity;
        $this->reserved_quantity -= $quantity;
        $this->save();

        // Log movement
        $this->movements()->create([
            'type' => 'fulfillment',
            'quantity' => -$quantity,
            'order_id' => $orderId,
            'notes' => "Fulfilled {$quantity} units for order {$orderId}"
        ]);

        return true;
    }

    /**
     * Add stock (restock)
     */
    public function addStock($quantity, $costPrice = null, $notes = null)
    {
        $this->quantity += $quantity;
        
        if ($costPrice) {
            $this->cost_price = $costPrice;
        }
        
        $this->last_restocked_at = now();
        $this->save();

        // Log movement
        $this->movements()->create([
            'type' => 'restock',
            'quantity' => $quantity,
            'cost_price' => $costPrice,
            'notes' => $notes ?: "Added {$quantity} units to inventory"
        ]);

        return true;
    }

    /**
     * Adjust stock (manual adjustment)
     */
    public function adjustStock($newQuantity, $reason = null)
    {
        $difference = $newQuantity - $this->quantity;
        $oldQuantity = $this->quantity;
        
        $this->quantity = $newQuantity;
        $this->save();

        // Log movement
        $this->movements()->create([
            'type' => 'adjustment',
            'quantity' => $difference,
            'notes' => $reason ?: "Stock adjusted from {$oldQuantity} to {$newQuantity}"
        ]);

        return true;
    }

    /**
     * Get inventory value
     */
    public function getInventoryValue()
    {
        $costPrice = $this->cost_price ?: $this->product->cost_price ?: 0;
        return $this->quantity * $costPrice;
    }

    /**
     * Get movement history
     */
    public function getMovementHistory($limit = 50)
    {
        return $this->movements()
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get();
    }

    /**
     * Check if automatic reorder should be triggered
     */
    public function shouldTriggerReorder()
    {
        return $this->needs_reorder && 
               $this->reorder_quantity && 
               $this->warehouse->active;
    }

    /**
     * Create reorder request
     */
    public function createReorderRequest()
    {
        if (!$this->shouldTriggerReorder()) {
            return false;
        }

        return ReorderRequest::create([
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'current_quantity' => $this->quantity,
            'reorder_quantity' => $this->reorder_quantity,
            'status' => 'pending'
        ]);
    }
}
