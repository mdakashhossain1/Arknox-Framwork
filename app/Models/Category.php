<?php

namespace App\Models;

use App\Core\Database\Model;

/**
 * Category Model
 * 
 * Product categories with hierarchical support
 */
class Category extends Model
{
    protected $table = 'categories';
    
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'parent_id',
        'sort_order',
        'active'
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'sort_order' => 'integer',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Category has many products
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Category belongs to parent category
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Category has many child categories
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    /**
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for root categories (no parent)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Check if category is root
     */
    public function isRoot()
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if category has children
     */
    public function hasChildren()
    {
        return $this->children()->count() > 0;
    }

    /**
     * Get all descendants
     */
    public function getDescendants()
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }
        
        return $descendants;
    }

    /**
     * Get category path
     */
    public function getPath()
    {
        $path = collect([$this]);
        $parent = $this->parent;
        
        while ($parent) {
            $path->prepend($parent);
            $parent = $parent->parent;
        }
        
        return $path;
    }

    /**
     * Get breadcrumb string
     */
    public function getBreadcrumb($separator = ' > ')
    {
        return $this->getPath()->pluck('name')->implode($separator);
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
