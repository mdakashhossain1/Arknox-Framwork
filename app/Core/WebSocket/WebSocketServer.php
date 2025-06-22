<?php

namespace App\Core\WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\Core\Events\EventDispatcher;

/**
 * WebSocket Server
 * 
 * Real-time WebSocket server with room management,
 * authentication, and event broadcasting
 */
class WebSocketServer implements MessageComponentInterface
{
    protected $clients;
    protected $rooms = [];
    protected $userConnections = [];
    protected $eventDispatcher;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->eventDispatcher = new EventDispatcher();
        
        $this->registerEventHandlers();
    }

    /**
     * Start the WebSocket server
     */
    public static function start($port = 8080)
    {
        $server = new static();
        
        $wsServer = new WsServer($server);
        $wsServer->disableVersion(0); // Disable older WebSocket versions
        
        $httpServer = new HttpServer($wsServer);
        $ioServer = IoServer::factory($httpServer, $port);
        
        echo "🚀 WebSocket server started on port {$port}\n";
        echo "📡 Listening for connections...\n";
        
        $ioServer->run();
    }

    /**
     * Handle new connection
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        
        echo "📱 New connection: {$conn->resourceId}\n";
        
        // Send welcome message
        $this->sendToConnection($conn, [
            'type' => 'connection',
            'message' => 'Connected to WebSocket server',
            'connectionId' => $conn->resourceId
        ]);
    }

    /**
     * Handle incoming message
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        try {
            $data = json_decode($msg, true);
            
            if (!$data || !isset($data['type'])) {
                $this->sendError($from, 'Invalid message format');
                return;
            }
            
            echo "📨 Message from {$from->resourceId}: {$data['type']}\n";
            
            switch ($data['type']) {
                case 'auth':
                    $this->handleAuthentication($from, $data);
                    break;
                    
                case 'join_room':
                    $this->handleJoinRoom($from, $data);
                    break;
                    
                case 'leave_room':
                    $this->handleLeaveRoom($from, $data);
                    break;
                    
                case 'message':
                    $this->handleMessage($from, $data);
                    break;
                    
                case 'ping':
                    $this->handlePing($from);
                    break;
                    
                case 'subscribe':
                    $this->handleSubscription($from, $data);
                    break;
                    
                case 'unsubscribe':
                    $this->handleUnsubscription($from, $data);
                    break;
                    
                default:
                    $this->sendError($from, 'Unknown message type');
            }
            
        } catch (\Exception $e) {
            echo "❌ Error handling message: {$e->getMessage()}\n";
            $this->sendError($from, 'Internal server error');
        }
    }

    /**
     * Handle connection close
     */
    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        
        // Remove from rooms
        foreach ($this->rooms as $roomName => $connections) {
            if (isset($connections[$conn->resourceId])) {
                unset($this->rooms[$roomName][$conn->resourceId]);
                
                // Notify room members
                $this->broadcastToRoom($roomName, [
                    'type' => 'user_left',
                    'connectionId' => $conn->resourceId
                ], $conn);
            }
        }
        
        // Remove user connection mapping
        foreach ($this->userConnections as $userId => $connectionId) {
            if ($connectionId === $conn->resourceId) {
                unset($this->userConnections[$userId]);
                break;
            }
        }
        
        echo "📱 Connection closed: {$conn->resourceId}\n";
    }

    /**
     * Handle connection error
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "❌ Connection error: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Handle user authentication
     */
    private function handleAuthentication(ConnectionInterface $conn, array $data)
    {
        if (!isset($data['token'])) {
            $this->sendError($conn, 'Authentication token required');
            return;
        }
        
        // Validate JWT token
        $user = $this->validateToken($data['token']);
        
        if (!$user) {
            $this->sendError($conn, 'Invalid authentication token');
            return;
        }
        
        // Store user connection
        $conn->user = $user;
        $this->userConnections[$user['id']] = $conn->resourceId;
        
        $this->sendToConnection($conn, [
            'type' => 'auth_success',
            'user' => $user
        ]);
        
        echo "🔐 User authenticated: {$user['name']} ({$user['id']})\n";
    }

    /**
     * Handle joining a room
     */
    private function handleJoinRoom(ConnectionInterface $conn, array $data)
    {
        if (!isset($data['room'])) {
            $this->sendError($conn, 'Room name required');
            return;
        }
        
        $roomName = $data['room'];
        
        // Check if user has permission to join room
        if (!$this->canJoinRoom($conn, $roomName)) {
            $this->sendError($conn, 'Permission denied');
            return;
        }
        
        // Add to room
        if (!isset($this->rooms[$roomName])) {
            $this->rooms[$roomName] = [];
        }
        
        $this->rooms[$roomName][$conn->resourceId] = $conn;
        
        $this->sendToConnection($conn, [
            'type' => 'room_joined',
            'room' => $roomName
        ]);
        
        // Notify other room members
        $this->broadcastToRoom($roomName, [
            'type' => 'user_joined',
            'room' => $roomName,
            'user' => $conn->user ?? ['id' => $conn->resourceId],
            'connectionId' => $conn->resourceId
        ], $conn);
        
        echo "🏠 User joined room: {$roomName}\n";
    }

    /**
     * Handle leaving a room
     */
    private function handleLeaveRoom(ConnectionInterface $conn, array $data)
    {
        if (!isset($data['room'])) {
            $this->sendError($conn, 'Room name required');
            return;
        }
        
        $roomName = $data['room'];
        
        if (isset($this->rooms[$roomName][$conn->resourceId])) {
            unset($this->rooms[$roomName][$conn->resourceId]);
            
            $this->sendToConnection($conn, [
                'type' => 'room_left',
                'room' => $roomName
            ]);
            
            // Notify other room members
            $this->broadcastToRoom($roomName, [
                'type' => 'user_left',
                'room' => $roomName,
                'user' => $conn->user ?? ['id' => $conn->resourceId],
                'connectionId' => $conn->resourceId
            ], $conn);
            
            echo "🏠 User left room: {$roomName}\n";
        }
    }

    /**
     * Handle chat message
     */
    private function handleMessage(ConnectionInterface $from, array $data)
    {
        if (!isset($data['room']) || !isset($data['message'])) {
            $this->sendError($from, 'Room and message required');
            return;
        }
        
        $roomName = $data['room'];
        
        // Check if user is in room
        if (!isset($this->rooms[$roomName][$from->resourceId])) {
            $this->sendError($from, 'Not in room');
            return;
        }
        
        // Broadcast message to room
        $this->broadcastToRoom($roomName, [
            'type' => 'message',
            'room' => $roomName,
            'message' => $data['message'],
            'user' => $from->user ?? ['id' => $from->resourceId],
            'timestamp' => time()
        ]);
        
        echo "💬 Message in {$roomName}: {$data['message']}\n";
    }

    /**
     * Handle ping for keepalive
     */
    private function handlePing(ConnectionInterface $conn)
    {
        $this->sendToConnection($conn, [
            'type' => 'pong',
            'timestamp' => time()
        ]);
    }

    /**
     * Handle event subscription
     */
    private function handleSubscription(ConnectionInterface $conn, array $data)
    {
        if (!isset($data['event'])) {
            $this->sendError($conn, 'Event name required');
            return;
        }
        
        $eventName = $data['event'];
        $filters = $data['filters'] ?? [];
        
        // Store subscription
        if (!isset($conn->subscriptions)) {
            $conn->subscriptions = [];
        }
        
        $conn->subscriptions[$eventName] = $filters;
        
        $this->sendToConnection($conn, [
            'type' => 'subscribed',
            'event' => $eventName
        ]);
        
        echo "📡 Subscribed to event: {$eventName}\n";
    }

    /**
     * Handle event unsubscription
     */
    private function handleUnsubscription(ConnectionInterface $conn, array $data)
    {
        if (!isset($data['event'])) {
            $this->sendError($conn, 'Event name required');
            return;
        }
        
        $eventName = $data['event'];
        
        if (isset($conn->subscriptions[$eventName])) {
            unset($conn->subscriptions[$eventName]);
        }
        
        $this->sendToConnection($conn, [
            'type' => 'unsubscribed',
            'event' => $eventName
        ]);
        
        echo "📡 Unsubscribed from event: {$eventName}\n";
    }

    /**
     * Broadcast message to all connections in a room
     */
    public function broadcastToRoom($roomName, array $message, ConnectionInterface $exclude = null)
    {
        if (!isset($this->rooms[$roomName])) {
            return;
        }
        
        foreach ($this->rooms[$roomName] as $conn) {
            if ($exclude && $conn === $exclude) {
                continue;
            }
            
            $this->sendToConnection($conn, $message);
        }
    }

    /**
     * Broadcast event to subscribed connections
     */
    public function broadcastEvent($eventName, array $data)
    {
        foreach ($this->clients as $conn) {
            if (!isset($conn->subscriptions[$eventName])) {
                continue;
            }
            
            // Check filters
            $filters = $conn->subscriptions[$eventName];
            if (!$this->matchesFilters($data, $filters)) {
                continue;
            }
            
            $this->sendToConnection($conn, [
                'type' => 'event',
                'event' => $eventName,
                'data' => $data,
                'timestamp' => time()
            ]);
        }
    }

    /**
     * Send message to specific user
     */
    public function sendToUser($userId, array $message)
    {
        if (!isset($this->userConnections[$userId])) {
            return false;
        }
        
        $connectionId = $this->userConnections[$userId];
        
        foreach ($this->clients as $conn) {
            if ($conn->resourceId === $connectionId) {
                $this->sendToConnection($conn, $message);
                return true;
            }
        }
        
        return false;
    }

    /**
     * Send message to specific connection
     */
    private function sendToConnection(ConnectionInterface $conn, array $message)
    {
        try {
            $conn->send(json_encode($message));
        } catch (\Exception $e) {
            echo "❌ Error sending message: {$e->getMessage()}\n";
        }
    }

    /**
     * Send error message
     */
    private function sendError(ConnectionInterface $conn, $error)
    {
        $this->sendToConnection($conn, [
            'type' => 'error',
            'error' => $error
        ]);
    }

    /**
     * Validate JWT token
     */
    private function validateToken($token)
    {
        // Implementation would validate JWT token
        // For now, return mock user data
        return [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com'
        ];
    }

    /**
     * Check if user can join room
     */
    private function canJoinRoom(ConnectionInterface $conn, $roomName)
    {
        // Implementation would check permissions
        // For now, allow all authenticated users
        return isset($conn->user);
    }

    /**
     * Check if data matches filters
     */
    private function matchesFilters(array $data, array $filters)
    {
        foreach ($filters as $key => $value) {
            if (!isset($data[$key]) || $data[$key] !== $value) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Register event handlers
     */
    private function registerEventHandlers()
    {
        // Listen for application events and broadcast to WebSocket clients
        $this->eventDispatcher->listen('order.status.changed', function($event) {
            $this->broadcastEvent('order_status_changed', [
                'order_id' => $event->order->id,
                'old_status' => $event->oldStatus,
                'new_status' => $event->newStatus,
                'user_id' => $event->order->user_id
            ]);
        });
        
        $this->eventDispatcher->listen('product.stock.changed', function($event) {
            $this->broadcastEvent('product_stock_changed', [
                'product_id' => $event->product->id,
                'old_stock' => $event->oldStock,
                'new_stock' => $event->newStock
            ]);
        });
    }
}
