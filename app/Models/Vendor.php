<?php

namespace App\Models;

use App\Core\Database\Model;

/**
 * Vendor Model
 * 
 * Multi-vendor marketplace vendor management
 */
class Vendor extends Model
{
    protected $table = 'vendors';
    
    protected $fillable = [
        'name',
        'slug',
        'description',
        'email',
        'phone',
        'website',
        'logo',
        'banner',
        'address',
        'commission_rate',
        'status',
        'verified_at',
        'settings'
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'verified_at' => 'datetime',
        'address' => 'json',
        'settings' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Vendor statuses
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_REJECTED = 'rejected';

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Vendor has many products
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Vendor has many orders through products
     */
    public function orders()
    {
        return $this->hasManyThrough(Order::class, Product::class);
    }

    /**
     * Vendor has many vendor orders (split orders)
     */
    public function vendorOrders()
    {
        return $this->hasMany(VendorOrder::class);
    }

    /**
     * Vendor has many payouts
     */
    public function payouts()
    {
        return $this->hasMany(VendorPayout::class);
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    /**
     * Scope for active vendors
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for verified vendors
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Check if vendor is active
     */
    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if vendor is verified
     */
    public function isVerified()
    {
        return !is_null($this->verified_at);
    }

    /**
     * Calculate commission for an amount
     */
    public function calculateCommission($amount)
    {
        return $amount * ($this->commission_rate / 100);
    }

    /**
     * Get vendor earnings for a period
     */
    public function getEarnings($startDate = null, $endDate = null)
    {
        $query = $this->vendorOrders()
                     ->where('status', 'completed');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $totalSales = $query->sum('total_amount');
        $commission = $this->calculateCommission($totalSales);
        $earnings = $totalSales - $commission;

        return [
            'total_sales' => $totalSales,
            'commission' => $commission,
            'earnings' => $earnings
        ];
    }

    /**
     * Get vendor performance metrics
     */
    public function getPerformanceMetrics()
    {
        $totalProducts = $this->products()->count();
        $activeProducts = $this->products()->where('status', 'active')->count();
        $totalOrders = $this->vendorOrders()->count();
        $completedOrders = $this->vendorOrders()->where('status', 'completed')->count();
        
        $averageRating = $this->products()
                             ->whereHas('reviews')
                             ->avg('average_rating');

        return [
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'completion_rate' => $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0,
            'average_rating' => round($averageRating, 2)
        ];
    }

    /**
     * Verify vendor
     */
    public function verify()
    {
        $this->verified_at = now();
        $this->status = self::STATUS_ACTIVE;
        return $this->save();
    }

    /**
     * Suspend vendor
     */
    public function suspend($reason = null)
    {
        $this->status = self::STATUS_SUSPENDED;
        
        if ($reason) {
            $settings = $this->settings ?: [];
            $settings['suspension_reason'] = $reason;
            $this->settings = $settings;
        }
        
        return $this->save();
    }

    /**
     * Generate unique slug
     */
    public static function generateSlug($name)
    {
        $slug = strtolower(str_replace(' ', '-', $name));
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        
        $originalSlug = $slug;
        $counter = 1;
        
        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
}
