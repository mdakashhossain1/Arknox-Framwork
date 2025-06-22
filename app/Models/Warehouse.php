<?php

namespace App\Models;

use App\Core\Database\Model;

/**
 * Warehouse Model
 * 
 * Inventory management across multiple warehouses
 */
class Warehouse extends Model
{
    protected $table = 'warehouses';
    
    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'phone',
        'email',
        'manager_name',
        'capacity',
        'active'
    ];

    protected $casts = [
        'capacity' => 'integer',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Warehouse has many inventory items
     */
    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }

    /**
     * Warehouse has many shipments
     */
    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    /**
     * Scope for active warehouses
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Get total inventory value
     */
    public function getTotalInventoryValue()
    {
        return $this->inventoryItems()
                   ->join('products', 'inventory_items.product_id', '=', 'products.id')
                   ->selectRaw('SUM(inventory_items.quantity * products.cost_price) as total_value')
                   ->value('total_value') ?: 0;
    }

    /**
     * Get capacity utilization
     */
    public function getCapacityUtilization()
    {
        if (!$this->capacity) {
            return 0;
        }

        $totalItems = $this->inventoryItems()->sum('quantity');
        return ($totalItems / $this->capacity) * 100;
    }

    /**
     * Check if warehouse has capacity for additional items
     */
    public function hasCapacity($quantity = 1)
    {
        if (!$this->capacity) {
            return true; // Unlimited capacity
        }

        $currentItems = $this->inventoryItems()->sum('quantity');
        return ($currentItems + $quantity) <= $this->capacity;
    }

    /**
     * Get low stock products in this warehouse
     */
    public function getLowStockProducts($threshold = 10)
    {
        return $this->inventoryItems()
                   ->where('quantity', '<=', $threshold)
                   ->with('product')
                   ->get();
    }

    /**
     * Transfer inventory to another warehouse
     */
    public function transferInventory($productId, $quantity, $destinationWarehouse)
    {
        $sourceItem = $this->inventoryItems()
                          ->where('product_id', $productId)
                          ->first();

        if (!$sourceItem || $sourceItem->available_quantity < $quantity) {
            throw new \Exception("Insufficient inventory for transfer");
        }

        if (!$destinationWarehouse->hasCapacity($quantity)) {
            throw new \Exception("Destination warehouse has insufficient capacity");
        }

        // Reduce from source
        $sourceItem->quantity -= $quantity;
        $sourceItem->save();

        // Add to destination
        $destinationItem = $destinationWarehouse->inventoryItems()
                                               ->where('product_id', $productId)
                                               ->first();

        if ($destinationItem) {
            $destinationItem->quantity += $quantity;
            $destinationItem->save();
        } else {
            $destinationWarehouse->inventoryItems()->create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'reserved_quantity' => 0
            ]);
        }

        // Log transfer
        InventoryTransfer::create([
            'product_id' => $productId,
            'from_warehouse_id' => $this->id,
            'to_warehouse_id' => $destinationWarehouse->id,
            'quantity' => $quantity,
            'status' => 'completed'
        ]);

        return true;
    }

    /**
     * Get full address string
     */
    public function getFullAddress()
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->zip,
            $this->country
        ]);

        return implode(', ', $parts);
    }
}
