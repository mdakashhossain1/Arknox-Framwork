<?php

namespace App\Models;

use App\Core\Database\Model;

/**
 * Wishlist Item Model
 * 
 * Items saved to user wishlists
 */
class WishlistItem extends Model
{
    protected $table = 'wishlist_items';
    
    protected $fillable = [
        'user_id',
        'product_id',
        'variant_id',
        'notes'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Wishlist item belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Wishlist item belongs to a product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Wishlist item belongs to a product variant (optional)
     */
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Move item to cart
     */
    public function moveToCart($quantity = 1)
    {
        $cartItem = $this->user->cartItems()->create([
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'quantity' => $quantity,
            'price' => $this->getPrice()
        ]);

        // Remove from wishlist
        $this->delete();

        return $cartItem;
    }

    /**
     * Get the price for this wishlist item
     */
    public function getPrice()
    {
        if ($this->variant) {
            return $this->variant->getEffectivePrice();
        }
        
        return $this->product->display_price;
    }

    /**
     * Check if item is still available
     */
    public function isAvailable()
    {
        if ($this->variant) {
            return $this->variant->canBePurchased();
        }
        
        return $this->product->canBePurchased();
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
}
