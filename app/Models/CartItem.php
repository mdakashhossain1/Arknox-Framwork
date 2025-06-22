<?php

namespace App\Models;

use App\Core\Database\Model;

/**
 * Cart Item Model
 * 
 * Items in user shopping carts
 */
class CartItem extends Model
{
    protected $table = 'cart_items';
    
    protected $fillable = [
        'user_id',
        'product_id',
        'variant_id',
        'quantity',
        'price'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Cart item belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cart item belongs to a product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Cart item belongs to a product variant (optional)
     */
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Get the total price for this cart item
     */
    public function getTotalPrice()
    {
        return $this->quantity * $this->price;
    }

    /**
     * Update quantity
     */
    public function updateQuantity($quantity)
    {
        if ($quantity <= 0) {
            return $this->delete();
        }

        // Check availability
        if (!$this->canPurchaseQuantity($quantity)) {
            throw new \Exception("Insufficient stock for requested quantity");
        }

        $this->quantity = $quantity;
        return $this->save();
    }

    /**
     * Check if the requested quantity can be purchased
     */
    public function canPurchaseQuantity($quantity)
    {
        if ($this->variant) {
            return $this->variant->canBePurchased($quantity);
        }
        
        return $this->product->canBePurchased($quantity);
    }

    /**
     * Move item to wishlist
     */
    public function moveToWishlist()
    {
        $wishlistItem = $this->user->wishlist()->create([
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id
        ]);

        // Remove from cart
        $this->delete();

        return $wishlistItem;
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
     * Check if item is still available
     */
    public function isAvailable()
    {
        return $this->canPurchaseQuantity($this->quantity);
    }

    /**
     * Get current price (may differ from stored price)
     */
    public function getCurrentPrice()
    {
        if ($this->variant) {
            return $this->variant->getEffectivePrice();
        }
        
        return $this->product->display_price;
    }

    /**
     * Check if price has changed since adding to cart
     */
    public function hasPriceChanged()
    {
        return $this->price != $this->getCurrentPrice();
    }

    /**
     * Update price to current price
     */
    public function updatePrice()
    {
        $this->price = $this->getCurrentPrice();
        return $this->save();
    }
}
