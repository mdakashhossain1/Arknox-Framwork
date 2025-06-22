<?php

namespace App\GraphQL\Resolvers;

use App\Models\Product;
use App\Models\Category;
use App\Models\ProductReview;
use App\Core\GraphQL\BaseResolver;

/**
 * Product GraphQL Resolver
 * 
 * Handles all product-related GraphQL queries and mutations
 */
class ProductResolver extends BaseResolver
{
    /**
     * Get paginated products with filtering
     */
    public function getProducts($root, array $args, $context)
    {
        $query = Product::query()->active();
        
        // Apply filters
        if (isset($args['search'])) {
            $query->search($args['search']);
        }
        
        if (isset($args['category'])) {
            $query->byCategory($args['category']);
        }
        
        if (isset($args['priceMin']) || isset($args['priceMax'])) {
            $min = $args['priceMin'] ?? 0;
            $max = $args['priceMax'] ?? PHP_FLOAT_MAX;
            $query->priceRange($min, $max);
        }
        
        if (isset($args['inStock']) && $args['inStock']) {
            $query->inStock();
        }
        
        // Apply sorting
        $sortBy = $args['sortBy'] ?? 'created_at';
        $sortOrder = $args['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        // Paginate
        $perPage = min($args['first'] ?? 10, 50); // Max 50 items
        $page = $args['page'] ?? 1;
        
        $products = $query->paginate($perPage, $page);
        
        return [
            'data' => $products['data'],
            'paginatorInfo' => [
                'count' => count($products['data']),
                'currentPage' => $products['current_page'],
                'firstItem' => $products['from'],
                'hasMorePages' => $products['has_more_pages'],
                'lastItem' => $products['to'],
                'lastPage' => $products['last_page'],
                'perPage' => $products['per_page'],
                'total' => $products['total']
            ]
        ];
    }

    /**
     * Get single product by ID or slug
     */
    public function getProduct($root, array $args, $context)
    {
        if (isset($args['id'])) {
            return Product::find($args['id']);
        }
        
        if (isset($args['slug'])) {
            return Product::where('slug', $args['slug'])->first();
        }
        
        throw new \Exception('Either id or slug must be provided');
    }

    /**
     * Create new product
     */
    public function createProduct($root, array $args, $context)
    {
        $this->authorize('create', Product::class);
        
        $input = $args['input'];
        
        // Validate input
        $this->validate($input, [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'sku' => 'string|unique:products,sku',
            'stockQuantity' => 'integer|min:0'
        ]);
        
        $product = Product::create($input);
        
        // Handle image uploads if provided
        if (isset($input['images'])) {
            $this->handleImageUploads($product, $input['images']);
        }
        
        return $product;
    }

    /**
     * Update existing product
     */
    public function updateProduct($root, array $args, $context)
    {
        $product = Product::findOrFail($args['id']);
        
        $this->authorize('update', $product);
        
        $input = $args['input'];
        
        // Validate input
        $this->validate($input, [
            'name' => 'string|max:255',
            'price' => 'numeric|min:0',
            'sku' => 'string|unique:products,sku,' . $product->id,
            'stockQuantity' => 'integer|min:0'
        ]);
        
        $product->update($input);
        
        // Handle image uploads if provided
        if (isset($input['images'])) {
            $this->handleImageUploads($product, $input['images']);
        }
        
        return $product->fresh();
    }

    /**
     * Delete product
     */
    public function deleteProduct($root, array $args, $context)
    {
        $product = Product::findOrFail($args['id']);
        
        $this->authorize('delete', $product);
        
        // Check if product has orders
        if ($product->orderItems()->exists()) {
            throw new \Exception('Cannot delete product with existing orders');
        }
        
        $product->delete();
        
        return true;
    }

    /**
     * Get product category
     */
    public function getCategory($product, array $args, $context)
    {
        return $product->category;
    }

    /**
     * Get product reviews with pagination
     */
    public function getReviews($product, array $args, $context)
    {
        $query = $product->reviews()->with('user');
        
        // Apply filters
        if (isset($args['rating'])) {
            $query->where('rating', $args['rating']);
        }
        
        if (isset($args['verified'])) {
            $query->where('verified', $args['verified']);
        }
        
        // Apply sorting
        $sortBy = $args['sortBy'] ?? 'created_at';
        $sortOrder = $args['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        // Paginate
        $perPage = min($args['first'] ?? 10, 20);
        $page = $args['page'] ?? 1;
        
        return $query->paginate($perPage, $page);
    }

    /**
     * Get product variants
     */
    public function getVariants($product, array $args, $context)
    {
        return $product->variants()->active()->get();
    }

    /**
     * Subscribe to product stock changes
     */
    public function subscribeToStockChanges($root, array $args, $context)
    {
        $productId = $args['productId'] ?? null;
        
        return $this->createSubscription('product.stock.changed', function($payload) use ($productId) {
            if ($productId && $payload['product_id'] != $productId) {
                return false;
            }
            
            return Product::find($payload['product_id']);
        });
    }

    /**
     * Get featured products
     */
    public function getFeaturedProducts($root, array $args, $context)
    {
        $limit = min($args['limit'] ?? 8, 20);
        
        return Product::featured()
                     ->active()
                     ->inStock()
                     ->limit($limit)
                     ->get();
    }

    /**
     * Get related products
     */
    public function getRelatedProducts($product, array $args, $context)
    {
        $limit = min($args['limit'] ?? 4, 10);
        
        return Product::where('category_id', $product->category_id)
                     ->where('id', '!=', $product->id)
                     ->active()
                     ->inStock()
                     ->limit($limit)
                     ->get();
    }

    /**
     * Search products with advanced filters
     */
    public function searchProducts($root, array $args, $context)
    {
        $query = Product::query()->active();
        
        // Text search
        if (isset($args['query'])) {
            $query->search($args['query']);
        }
        
        // Category filter
        if (isset($args['categories'])) {
            $query->whereIn('category_id', $args['categories']);
        }
        
        // Brand filter
        if (isset($args['brands'])) {
            $query->whereIn('brand_id', $args['brands']);
        }
        
        // Price range
        if (isset($args['priceRange'])) {
            $query->priceRange($args['priceRange']['min'], $args['priceRange']['max']);
        }
        
        // Rating filter
        if (isset($args['minRating'])) {
            $query->whereHas('reviews', function($q) use ($args) {
                $q->havingRaw('AVG(rating) >= ?', [$args['minRating']]);
            });
        }
        
        // Availability filter
        if (isset($args['inStock']) && $args['inStock']) {
            $query->inStock();
        }
        
        if (isset($args['onSale']) && $args['onSale']) {
            $query->onSale();
        }
        
        // Sorting
        $this->applySorting($query, $args);
        
        // Facets for filtering UI
        $facets = $this->calculateFacets($query, $args);
        
        // Paginate results
        $perPage = min($args['first'] ?? 12, 50);
        $page = $args['page'] ?? 1;
        
        $products = $query->paginate($perPage, $page);
        
        return [
            'products' => $products,
            'facets' => $facets,
            'totalCount' => $products['total']
        ];
    }

    /**
     * Apply sorting to query
     */
    private function applySorting($query, $args)
    {
        $sortBy = $args['sortBy'] ?? 'relevance';
        
        switch ($sortBy) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'rating':
                $query->orderByRaw('(SELECT AVG(rating) FROM product_reviews WHERE product_id = products.id) DESC');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            default: // relevance
                $query->orderBy('featured', 'desc')
                      ->orderBy('created_at', 'desc');
        }
    }

    /**
     * Calculate facets for search results
     */
    private function calculateFacets($query, $args)
    {
        // Clone query for facet calculations
        $baseQuery = clone $query;
        
        return [
            'categories' => $this->getCategoryFacets($baseQuery),
            'brands' => $this->getBrandFacets($baseQuery),
            'priceRanges' => $this->getPriceRangeFacets($baseQuery),
            'ratings' => $this->getRatingFacets($baseQuery)
        ];
    }

    /**
     * Handle image uploads for product
     */
    private function handleImageUploads($product, $images)
    {
        foreach ($images as $index => $imageData) {
            // Process image upload
            $imagePath = $this->uploadImage($imageData);
            
            $product->images()->create([
                'path' => $imagePath,
                'alt_text' => $imageData['alt'] ?? $product->name,
                'sort_order' => $index
            ]);
        }
    }

    /**
     * Upload and process product image
     */
    private function uploadImage($imageData)
    {
        // Implementation would handle file upload, resizing, optimization
        // For now, return a placeholder path
        return '/uploads/products/' . uniqid() . '.jpg';
    }
}
