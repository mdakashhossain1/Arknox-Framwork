<?php

namespace App\Models;

use App\Core\Database\Model;

/**
 * Shipment Model
 * 
 * Shipping information and tracking for orders
 */
class Shipment extends Model
{
    protected $table = 'shipments';
    
    protected $fillable = [
        'order_id',
        'tracking_number',
        'carrier',
        'service',
        'status',
        'shipped_at',
        'delivered_at',
        'estimated_delivery',
        'shipping_address',
        'weight',
        'dimensions',
        'notes'
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'estimated_delivery' => 'datetime',
        'shipping_address' => 'json',
        'weight' => 'decimal:2',
        'dimensions' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Shipment statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED_DELIVERY = 'failed_delivery';
    const STATUS_RETURNED = 'returned';

    // Carriers
    const CARRIER_UPS = 'ups';
    const CARRIER_FEDEX = 'fedex';
    const CARRIER_USPS = 'usps';
    const CARRIER_DHL = 'dhl';

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Shipment belongs to an order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Shipment has many tracking events
     */
    public function trackingEvents()
    {
        return $this->hasMany(ShipmentTrackingEvent::class)->orderBy('occurred_at', 'desc');
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    /**
     * Scope for shipped shipments
     */
    public function scopeShipped($query)
    {
        return $query->where('status', self::STATUS_SHIPPED);
    }

    /**
     * Scope for delivered shipments
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    /**
     * Scope for in transit shipments
     */
    public function scopeInTransit($query)
    {
        return $query->whereIn('status', [
            self::STATUS_SHIPPED,
            self::STATUS_IN_TRANSIT,
            self::STATUS_OUT_FOR_DELIVERY
        ]);
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Check if shipment is delivered
     */
    public function isDelivered()
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /**
     * Check if shipment is in transit
     */
    public function isInTransit()
    {
        return in_array($this->status, [
            self::STATUS_SHIPPED,
            self::STATUS_IN_TRANSIT,
            self::STATUS_OUT_FOR_DELIVERY
        ]);
    }

    /**
     * Update shipment status
     */
    public function updateStatus($newStatus, $location = null, $notes = null)
    {
        $oldStatus = $this->status;
        $this->status = $newStatus;

        // Set timestamps based on status
        switch ($newStatus) {
            case self::STATUS_SHIPPED:
                if (!$this->shipped_at) {
                    $this->shipped_at = now();
                }
                break;
            
            case self::STATUS_DELIVERED:
                if (!$this->delivered_at) {
                    $this->delivered_at = now();
                }
                break;
        }

        $this->save();

        // Create tracking event
        $this->trackingEvents()->create([
            'status' => $newStatus,
            'location' => $location,
            'description' => $this->getStatusDescription($newStatus),
            'notes' => $notes,
            'occurred_at' => now()
        ]);

        // Update order status if needed
        if ($newStatus === self::STATUS_DELIVERED) {
            $this->order->updateStatus('delivered', 'Package delivered');
        }

        return true;
    }

    /**
     * Get tracking URL for the carrier
     */
    public function getTrackingUrl()
    {
        if (!$this->tracking_number) {
            return null;
        }

        $urls = [
            self::CARRIER_UPS => "https://www.ups.com/track?tracknum={$this->tracking_number}",
            self::CARRIER_FEDEX => "https://www.fedex.com/apps/fedextrack/?tracknumbers={$this->tracking_number}",
            self::CARRIER_USPS => "https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1={$this->tracking_number}",
            self::CARRIER_DHL => "https://www.dhl.com/en/express/tracking.html?AWB={$this->tracking_number}"
        ];

        return $urls[$this->carrier] ?? null;
    }

    /**
     * Get estimated delivery date
     */
    public function getEstimatedDelivery()
    {
        if ($this->estimated_delivery) {
            return $this->estimated_delivery;
        }

        // Calculate based on shipping service and carrier
        $businessDays = $this->getEstimatedBusinessDays();
        
        if ($businessDays) {
            return $this->addBusinessDays($this->shipped_at ?: now(), $businessDays);
        }

        return null;
    }

    /**
     * Get status description
     */
    public function getStatusDescription($status = null)
    {
        $status = $status ?: $this->status;
        
        $descriptions = [
            self::STATUS_PENDING => 'Shipment is being prepared',
            self::STATUS_PROCESSING => 'Package is being processed',
            self::STATUS_SHIPPED => 'Package has been shipped',
            self::STATUS_IN_TRANSIT => 'Package is in transit',
            self::STATUS_OUT_FOR_DELIVERY => 'Package is out for delivery',
            self::STATUS_DELIVERED => 'Package has been delivered',
            self::STATUS_FAILED_DELIVERY => 'Delivery attempt failed',
            self::STATUS_RETURNED => 'Package has been returned'
        ];

        return $descriptions[$status] ?? 'Unknown status';
    }

    /**
     * Get carrier display name
     */
    public function getCarrierDisplayName()
    {
        $carriers = [
            self::CARRIER_UPS => 'UPS',
            self::CARRIER_FEDEX => 'FedEx',
            self::CARRIER_USPS => 'USPS',
            self::CARRIER_DHL => 'DHL'
        ];

        return $carriers[$this->carrier] ?? strtoupper($this->carrier);
    }

    /**
     * Get estimated business days for delivery
     */
    private function getEstimatedBusinessDays()
    {
        $serviceDays = [
            'ground' => 5,
            'standard' => 3,
            'express' => 2,
            'overnight' => 1,
            'same_day' => 0
        ];

        return $serviceDays[strtolower($this->service)] ?? 5;
    }

    /**
     * Add business days to a date
     */
    private function addBusinessDays($date, $businessDays)
    {
        $currentDate = clone $date;
        
        while ($businessDays > 0) {
            $currentDate->modify('+1 day');
            
            // Skip weekends
            if ($currentDate->format('N') < 6) {
                $businessDays--;
            }
        }
        
        return $currentDate;
    }

    /**
     * Generate tracking number
     */
    public static function generateTrackingNumber($carrier = null)
    {
        $prefix = strtoupper($carrier ?: 'TRK');
        $timestamp = time();
        $random = mt_rand(100000, 999999);
        
        return $prefix . $timestamp . $random;
    }
}
