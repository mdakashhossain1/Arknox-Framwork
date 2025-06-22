<?php

namespace App\Core\GraphQL;

use App\Core\Request;
use App\Core\Response;

/**
 * GraphQL Server
 * 
 * Modern GraphQL implementation with schema definition,
 * resolvers, and advanced query capabilities
 */
class GraphQLServer
{
    protected $schema;
    protected $resolvers = [];
    protected $middleware = [];
    protected $context = [];

    public function __construct()
    {
        $this->loadSchema();
        $this->loadResolvers();
    }

    /**
     * Handle GraphQL request
     */
    public function handle(Request $request)
    {
        try {
            $input = $this->parseRequest($request);
            
            // Validate query
            $this->validateQuery($input['query']);
            
            // Execute query
            $result = $this->executeQuery(
                $input['query'],
                $input['variables'] ?? [],
                $input['operationName'] ?? null
            );
            
            return Response::json($result);
            
        } catch (\Exception $e) {
            return Response::json([
                'errors' => [
                    [
                        'message' => $e->getMessage(),
                        'extensions' => [
                            'code' => $e->getCode() ?: 'INTERNAL_ERROR'
                        ]
                    ]
                ]
            ], 400);
        }
    }

    /**
     * Parse GraphQL request
     */
    private function parseRequest(Request $request)
    {
        $contentType = $request->getHeader('Content-Type');
        
        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode($request->getContent(), true);
        } else {
            $input = [
                'query' => $request->input('query'),
                'variables' => $request->input('variables') ? json_decode($request->input('variables'), true) : null,
                'operationName' => $request->input('operationName')
            ];
        }
        
        if (!isset($input['query'])) {
            throw new \Exception('Query not provided');
        }
        
        return $input;
    }

    /**
     * Execute GraphQL query
     */
    private function executeQuery($query, $variables = [], $operationName = null)
    {
        $parser = new GraphQLParser();
        $ast = $parser->parse($query);
        
        $executor = new GraphQLExecutor($this->schema, $this->resolvers);
        $executor->setContext($this->context);
        
        return $executor->execute($ast, $variables, $operationName);
    }

    /**
     * Validate GraphQL query
     */
    private function validateQuery($query)
    {
        $validator = new GraphQLValidator($this->schema);
        $errors = $validator->validate($query);
        
        if (!empty($errors)) {
            throw new \Exception('Query validation failed: ' . implode(', ', $errors));
        }
    }

    /**
     * Load GraphQL schema
     */
    private function loadSchema()
    {
        $schemaFile = __DIR__ . '/schema.graphql';
        
        if (file_exists($schemaFile)) {
            $this->schema = file_get_contents($schemaFile);
        } else {
            $this->schema = $this->getDefaultSchema();
        }
    }

    /**
     * Load GraphQL resolvers
     */
    private function loadResolvers()
    {
        $this->resolvers = [
            'Query' => [
                'products' => [ProductResolver::class, 'getProducts'],
                'product' => [ProductResolver::class, 'getProduct'],
                'orders' => [OrderResolver::class, 'getOrders'],
                'order' => [OrderResolver::class, 'getOrder'],
                'users' => [UserResolver::class, 'getUsers'],
                'user' => [UserResolver::class, 'getUser'],
            ],
            'Mutation' => [
                'createProduct' => [ProductResolver::class, 'createProduct'],
                'updateProduct' => [ProductResolver::class, 'updateProduct'],
                'deleteProduct' => [ProductResolver::class, 'deleteProduct'],
                'createOrder' => [OrderResolver::class, 'createOrder'],
                'updateOrderStatus' => [OrderResolver::class, 'updateOrderStatus'],
                'createUser' => [UserResolver::class, 'createUser'],
                'updateUser' => [UserResolver::class, 'updateUser'],
            ],
            'Subscription' => [
                'orderStatusChanged' => [OrderResolver::class, 'subscribeToOrderStatus'],
                'productStockChanged' => [ProductResolver::class, 'subscribeToStockChanges'],
            ],
            'Product' => [
                'category' => [ProductResolver::class, 'getCategory'],
                'reviews' => [ProductResolver::class, 'getReviews'],
                'variants' => [ProductResolver::class, 'getVariants'],
            ],
            'Order' => [
                'user' => [OrderResolver::class, 'getUser'],
                'items' => [OrderResolver::class, 'getItems'],
                'payments' => [OrderResolver::class, 'getPayments'],
            ]
        ];
    }

    /**
     * Get default GraphQL schema
     */
    private function getDefaultSchema()
    {
        return '
            type Query {
                products(
                    first: Int = 10
                    page: Int = 1
                    search: String
                    category: ID
                    priceMin: Float
                    priceMax: Float
                    inStock: Boolean
                ): ProductConnection!
                
                product(id: ID, slug: String): Product
                
                orders(
                    first: Int = 10
                    page: Int = 1
                    status: OrderStatus
                    userId: ID
                ): OrderConnection!
                
                order(id: ID!): Order
                
                users(
                    first: Int = 10
                    page: Int = 1
                    search: String
                ): UserConnection!
                
                user(id: ID!): User
            }
            
            type Mutation {
                createProduct(input: CreateProductInput!): Product!
                updateProduct(id: ID!, input: UpdateProductInput!): Product!
                deleteProduct(id: ID!): Boolean!
                
                createOrder(input: CreateOrderInput!): Order!
                updateOrderStatus(id: ID!, status: OrderStatus!): Order!
                
                createUser(input: CreateUserInput!): User!
                updateUser(id: ID!, input: UpdateUserInput!): User!
            }
            
            type Subscription {
                orderStatusChanged(orderId: ID): Order!
                productStockChanged(productId: ID): Product!
            }
            
            type Product {
                id: ID!
                name: String!
                slug: String!
                description: String
                price: Float!
                salePrice: Float
                displayPrice: Float!
                isOnSale: Boolean!
                sku: String!
                stockQuantity: Int!
                inStock: Boolean!
                featured: Boolean!
                status: ProductStatus!
                category: Category
                brand: Brand
                images: [ProductImage!]!
                reviews: [ProductReview!]!
                variants: [ProductVariant!]!
                averageRating: Float!
                reviewCount: Int!
                createdAt: DateTime!
                updatedAt: DateTime!
            }
            
            type Order {
                id: ID!
                orderNumber: String!
                status: OrderStatus!
                paymentStatus: PaymentStatus!
                shippingStatus: ShippingStatus!
                subtotal: Float!
                taxAmount: Float!
                shippingAmount: Float!
                discountAmount: Float!
                totalAmount: Float!
                currency: String!
                user: User!
                items: [OrderItem!]!
                payments: [Payment!]!
                billingAddress: Address
                shippingAddress: Address
                createdAt: DateTime!
                updatedAt: DateTime!
            }
            
            type User {
                id: ID!
                name: String!
                email: String!
                emailVerifiedAt: DateTime
                orders: [Order!]!
                createdAt: DateTime!
                updatedAt: DateTime!
            }
            
            type ProductConnection {
                data: [Product!]!
                paginatorInfo: PaginatorInfo!
            }
            
            type OrderConnection {
                data: [Order!]!
                paginatorInfo: PaginatorInfo!
            }
            
            type UserConnection {
                data: [User!]!
                paginatorInfo: PaginatorInfo!
            }
            
            type PaginatorInfo {
                count: Int!
                currentPage: Int!
                firstItem: Int
                hasMorePages: Boolean!
                lastItem: Int
                lastPage: Int!
                perPage: Int!
                total: Int!
            }
            
            enum ProductStatus {
                ACTIVE
                INACTIVE
                DRAFT
            }
            
            enum OrderStatus {
                PENDING
                PROCESSING
                SHIPPED
                DELIVERED
                CANCELLED
                REFUNDED
            }
            
            enum PaymentStatus {
                PENDING
                PAID
                FAILED
                REFUNDED
                PARTIALLY_REFUNDED
            }
            
            enum ShippingStatus {
                PENDING
                PROCESSING
                SHIPPED
                DELIVERED
            }
            
            input CreateProductInput {
                name: String!
                description: String
                price: Float!
                salePrice: Float
                sku: String
                stockQuantity: Int
                categoryId: ID
                brandId: ID
                featured: Boolean = false
                status: ProductStatus = ACTIVE
            }
            
            input UpdateProductInput {
                name: String
                description: String
                price: Float
                salePrice: Float
                sku: String
                stockQuantity: Int
                categoryId: ID
                brandId: ID
                featured: Boolean
                status: ProductStatus
            }
            
            input CreateOrderInput {
                userId: ID!
                items: [OrderItemInput!]!
                billingAddress: AddressInput!
                shippingAddress: AddressInput
                paymentMethod: String!
                shippingMethod: String!
            }
            
            input OrderItemInput {
                productId: ID!
                quantity: Int!
                price: Float!
            }
            
            input CreateUserInput {
                name: String!
                email: String!
                password: String!
            }
            
            input UpdateUserInput {
                name: String
                email: String
                password: String
            }
            
            input AddressInput {
                firstName: String!
                lastName: String!
                company: String
                address1: String!
                address2: String
                city: String!
                state: String!
                postalCode: String!
                country: String!
                phone: String
            }
            
            scalar DateTime
        ';
    }

    /**
     * Set context for resolvers
     */
    public function setContext(array $context)
    {
        $this->context = $context;
    }

    /**
     * Add middleware
     */
    public function addMiddleware(callable $middleware)
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Get GraphQL playground HTML
     */
    public function getPlaygroundHtml()
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <title>GraphQL Playground</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/graphql-playground-react/build/static/css/index.css" />
        </head>
        <body>
            <div id="root">
                <style>
                    body { margin: 0; font-family: "Open Sans", sans-serif; overflow: hidden; }
                    #root { height: 100vh; }
                </style>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/graphql-playground-react/build/static/js/middleware.js"></script>
            <script>
                window.addEventListener("load", function (event) {
                    GraphQLPlayground.init(document.getElementById("root"), {
                        endpoint: "/graphql",
                        settings: {
                            "editor.theme": "dark",
                            "editor.fontSize": 14,
                            "editor.fontFamily": "\'Source Code Pro\', monospace",
                            "request.credentials": "include"
                        }
                    });
                });
            </script>
        </body>
        </html>';
    }
}
