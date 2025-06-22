<?php

namespace App\Models;

use App\Core\Database\Model;

/**
 * Product Review Model
 * 
 * Customer reviews and ratings for products
 */
class ProductReview extends Model
{
    protected $table = 'product_reviews';
    
    protected $fillable = [
        'product_id',
        'user_id',
        'rating',
        'title',
        'content',
        'verified',
        'helpful_count',
        'status'
    ];

    protected $casts = [
        'rating' => 'integer',
        'verified' => 'boolean',
        'helpful_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Review statuses
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Review belongs to a product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Review belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Review has many helpful votes
     */
    public function helpfulVotes()
    {
        return $this->hasMany(ReviewHelpfulVote::class, 'review_id');
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    /**
     * Scope for approved reviews
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for verified reviews
     */
    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    /**
     * Scope for reviews by rating
     */
    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    /**
     * Scope for reviews with minimum rating
     */
    public function scopeMinRating($query, $minRating)
    {
        return $query->where('rating', '>=', $minRating);
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Check if review is approved
     */
    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Approve the review
     */
    public function approve()
    {
        $this->status = self::STATUS_APPROVED;
        return $this->save();
    }

    /**
     * Reject the review
     */
    public function reject()
    {
        $this->status = self::STATUS_REJECTED;
        return $this->save();
    }

    /**
     * Mark review as helpful by a user
     */
    public function markHelpful($userId)
    {
        // Check if user already voted
        $existingVote = $this->helpfulVotes()
                            ->where('user_id', $userId)
                            ->first();
        
        if ($existingVote) {
            return false; // Already voted
        }
        
        // Create helpful vote
        $this->helpfulVotes()->create([
            'user_id' => $userId,
            'helpful' => true
        ]);
        
        // Increment helpful count
        $this->increment('helpful_count');
        
        return true;
    }

    /**
     * Get review rating as stars
     */
    public function getStarsAttribute()
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }

    /**
     * Get review excerpt
     */
    public function getExcerpt($length = 100)
    {
        if (strlen($this->content) <= $length) {
            return $this->content;
        }
        
        return substr($this->content, 0, $length) . '...';
    }

    /**
     * Check if user can review this product
     */
    public static function canUserReview($userId, $productId)
    {
        // Check if user already reviewed this product
        $existingReview = static::where('user_id', $userId)
                               ->where('product_id', $productId)
                               ->first();
        
        if ($existingReview) {
            return false;
        }
        
        // Check if user purchased this product
        $hasPurchased = OrderItem::whereHas('order', function($query) use ($userId) {
                                    $query->where('user_id', $userId)
                                          ->where('status', 'delivered');
                                })
                                ->where('product_id', $productId)
                                ->exists();
        
        return $hasPurchased;
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        // Set default status
        static::creating(function ($review) {
            if (empty($review->status)) {
                $review->status = self::STATUS_PENDING;
            }
        });
        
        // Update product average rating when review is saved
        static::saved(function ($review) {
            $review->updateProductRating();
        });
        
        // Update product average rating when review is deleted
        static::deleted(function ($review) {
            $review->updateProductRating();
        });
    }

    /**
     * Update product average rating
     */
    private function updateProductRating()
    {
        $product = $this->product;
        
        if ($product) {
            $averageRating = $product->reviews()
                                   ->approved()
                                   ->avg('rating');
            
            $reviewCount = $product->reviews()
                                 ->approved()
                                 ->count();
            
            $product->update([
                'average_rating' => $averageRating ?: 0,
                'review_count' => $reviewCount
            ]);
        }
    }
}
