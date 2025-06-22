<?php

namespace App\Models;

use App\Core\Database\Model;

/**
 * Product Model
 * 
 * E-commerce product with categories, variants, inventory, and reviews
 */
class Product extends Model
{
    protected $table = 'products';
    
    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'sku',
        'price',
        'sale_price',
        'cost_price',
        'weight',
        'dimensions',
        'stock_quantity',
        'manage_stock',
        'stock_status',
        'featured',
        'status',
        'meta_title',
        'meta_description',
        'category_id',
        'brand_id'
    ];

    protected $hidden = [
        'cost_price'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'dimensions' => 'json',
        'stock_quantity' => 'integer',
        'manage_stock' => 'boolean',
        'featured' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = [
        'display_price',
        'is_on_sale',
        'average_rating',
        'review_count'
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Product belongs to a category
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Product belongs to a brand
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Product has many variants
     */
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Product has many images
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Product has many reviews
     */
    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Product has many attributes
     */
    public function attributes()
    {
        return $this->belongsToMany(ProductAttribute::class, 'product_attribute_values')
                    ->withPivot('value')
                    ->withTimestamps();
    }

    /**
     * Product belongs to many tags
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'product_tags');
    }

    /**
     * Product has many order items
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Product has many cart items
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Product has many wishlist items
     */
    public function wishlistItems()
    {
        return $this->hasMany(WishlistItem::class);
    }

    // ========================================
    // ACCESSORS & MUTATORS
    // ========================================

    /**
     * Get the display price (sale price if available, otherwise regular price)
     */
    public function getDisplayPriceAttribute()
    {
        return $this->sale_price ?: $this->price;
    }

    /**
     * Check if product is on sale
     */
    public function getIsOnSaleAttribute()
    {
        return $this->sale_price && $this->sale_price < $this->price;
    }

    /**
     * Get average rating
     */
    public function getAverageRatingAttribute()
    {
        return $this->reviews()->avg('rating') ?: 0;
    }

    /**
     * Get review count
     */
    public function getReviewCountAttribute()
    {
        return $this->reviews()->count();
    }

    /**
     * Set slug from name if not provided
     */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        
        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = $this->generateSlug($value);
        }
    }

    /**
     * Ensure slug is unique
     */
    public function setSlugAttribute($value)
    {
        $this->attributes['slug'] = $this->generateUniqueSlug($value);
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    /**
     * Scope for active products
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for featured products
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    /**
     * Scope for products on sale
     */
    public function scopeOnSale($query)
    {
        return $query->whereNotNull('sale_price')
                    ->whereColumn('sale_price', '<', 'price');
    }

    /**
     * Scope for products in stock
     */
    public function scopeInStock($query)
    {
        return $query->where(function($q) {
            $q->where('manage_stock', false)
              ->orWhere(function($q2) {
                  $q2->where('manage_stock', true)
                     ->where('stock_quantity', '>', 0);
              });
        });
    }

    /**
     * Scope for products by category
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope for products by brand
     */
    public function scopeByBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    /**
     * Scope for price range
     */
    public function scopePriceRange($query, $min, $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    /**
     * Scope for search
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('name', 'LIKE', "%{$term}%")
              ->orWhere('description', 'LIKE', "%{$term}%")
              ->orWhere('sku', 'LIKE', "%{$term}%");
        });
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Check if product is in stock
     */
    public function isInStock()
    {
        if (!$this->manage_stock) {
            return $this->stock_status === 'in_stock';
        }
        
        return $this->stock_quantity > 0;
    }

    /**
     * Check if product can be purchased
     */
    public function canBePurchased($quantity = 1)
    {
        if ($this->status !== 'active') {
            return false;
        }
        
        if (!$this->isInStock()) {
            return false;
        }
        
        if ($this->manage_stock && $this->stock_quantity < $quantity) {
            return false;
        }
        
        return true;
    }

    /**
     * Reduce stock quantity
     */
    public function reduceStock($quantity)
    {
        if (!$this->manage_stock) {
            return true;
        }
        
        if ($this->stock_quantity < $quantity) {
            throw new \Exception("Insufficient stock for product: {$this->name}");
        }
        
        $this->stock_quantity -= $quantity;
        
        if ($this->stock_quantity <= 0) {
            $this->stock_status = 'out_of_stock';
        }
        
        return $this->save();
    }

    /**
     * Increase stock quantity
     */
    public function increaseStock($quantity)
    {
        if (!$this->manage_stock) {
            return true;
        }
        
        $this->stock_quantity += $quantity;
        $this->stock_status = 'in_stock';
        
        return $this->save();
    }

    /**
     * Get main product image
     */
    public function getMainImage()
    {
        return $this->images()->first();
    }

    /**
     * Calculate profit margin
     */
    public function getProfitMargin()
    {
        if (!$this->cost_price || $this->cost_price <= 0) {
            return 0;
        }
        
        $sellingPrice = $this->display_price;
        return (($sellingPrice - $this->cost_price) / $sellingPrice) * 100;
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Generate slug from string
     */
    private function generateSlug($string)
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
        return trim($slug, '-');
    }

    /**
     * Generate unique slug
     */
    private function generateUniqueSlug($slug)
    {
        $originalSlug = $this->generateSlug($slug);
        $slug = $originalSlug;
        $counter = 1;
        
        while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        // Auto-generate SKU if not provided
        static::creating(function ($product) {
            if (empty($product->sku)) {
                $product->sku = $product->generateSKU();
            }
        });
    }

    /**
     * Generate unique SKU
     */
    private function generateSKU()
    {
        $prefix = 'PRD';
        $timestamp = time();
        $random = mt_rand(100, 999);
        
        return $prefix . $timestamp . $random;
    }
}
