<?php

namespace App\Models;

use App\Core\Database\Model;

/**
 * User Model
 * 
 * User management with authentication and relationships
 */
class User extends Model
{
    protected $table = 'users';
    
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'email_verified_at',
        'loyalty_points',
        'status'
    ];

    protected $hidden = [
        'password',
        'remember_token'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'loyalty_points' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // User statuses
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * User has many orders
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * User has many wishlist items
     */
    public function wishlist()
    {
        return $this->hasMany(WishlistItem::class);
    }

    /**
     * User has many cart items
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * User has many product views
     */
    public function productViews()
    {
        return $this->hasMany(ProductView::class);
    }

    /**
     * User has many subscriptions
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * User has many reviews
     */
    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Get wishlist products through wishlist items
     */
    public function wishlistProducts()
    {
        return $this->belongsToMany(Product::class, 'wishlist_items');
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for verified users
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Check if user is active
     */
    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if email is verified
     */
    public function isEmailVerified()
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Redeem loyalty points
     */
    public function redeemLoyaltyPoints($points)
    {
        if ($this->loyalty_points < $points) {
            throw new \Exception("Insufficient loyalty points");
        }

        $this->loyalty_points -= $points;
        $this->save();

        // Convert points to discount (e.g., 10 points = $1)
        $discount = $points / 10;

        return $discount;
    }

    /**
     * Award loyalty points
     */
    public function awardLoyaltyPoints($points)
    {
        $this->loyalty_points += $points;
        return $this->save();
    }

    /**
     * Get customer analytics
     */
    public static function getCustomerAnalytics()
    {
        $totalCustomers = static::count();
        $activeCustomers = static::active()->count();
        $verifiedCustomers = static::verified()->count();
        
        $repeatCustomers = static::whereHas('orders', function($query) {
            $query->havingRaw('COUNT(*) > 1');
        })->count();

        $averageOrderValue = Order::where('status', 'completed')
                                 ->avg('total_amount');

        $customerLifetimeValue = Order::where('status', 'completed')
                                     ->sum('total_amount') / max($totalCustomers, 1);

        return [
            'total_customers' => $totalCustomers,
            'active_customers' => $activeCustomers,
            'verified_customers' => $verifiedCustomers,
            'repeat_customers' => $repeatCustomers,
            'repeat_rate' => $totalCustomers > 0 ? ($repeatCustomers / $totalCustomers) * 100 : 0,
            'average_order_value' => round($averageOrderValue, 2),
            'customer_lifetime_value' => round($customerLifetimeValue, 2)
        ];
    }

    /**
     * Get user's order history
     */
    public function getOrderHistory($limit = 10)
    {
        return $this->orders()
                   ->with(['items.product'])
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get();
    }

    /**
     * Get cart total
     */
    public function getCartTotal()
    {
        return $this->cartItems->sum(function($item) {
            return $item->quantity * $item->price;
        });
    }

    /**
     * Clear cart
     */
    public function clearCart()
    {
        return $this->cartItems()->delete();
    }

    /**
     * Get full name
     */
    public function getFullNameAttribute()
    {
        return $this->name;
    }
}
