<?php

namespace App\Models;

use App\Core\Database\Model;

/**
 * Payment Model
 * 
 * Payment records for orders
 */
class Payment extends Model
{
    protected $table = 'payments';
    
    protected $fillable = [
        'order_id',
        'amount',
        'method',
        'status',
        'transaction_id',
        'gateway_response',
        'failure_reason',
        'processed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'json',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Payment statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    // Payment methods
    const METHOD_CREDIT_CARD = 'credit_card';
    const METHOD_DEBIT_CARD = 'debit_card';
    const METHOD_PAYPAL = 'paypal';
    const METHOD_STRIPE = 'stripe';
    const METHOD_BANK_TRANSFER = 'bank_transfer';

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Payment belongs to an order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Payment has many refunds
     */
    public function refunds()
    {
        return $this->hasMany(PaymentRefund::class);
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    /**
     * Scope for successful payments
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed payments
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Check if payment is successful
     */
    public function isSuccessful()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if payment failed
     */
    public function isFailed()
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if payment is pending
     */
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Mark payment as completed
     */
    public function markCompleted($transactionId = null, $gatewayResponse = null)
    {
        $this->status = self::STATUS_COMPLETED;
        $this->processed_at = now();
        
        if ($transactionId) {
            $this->transaction_id = $transactionId;
        }
        
        if ($gatewayResponse) {
            $this->gateway_response = $gatewayResponse;
        }
        
        return $this->save();
    }

    /**
     * Mark payment as failed
     */
    public function markFailed($reason = null, $gatewayResponse = null)
    {
        $this->status = self::STATUS_FAILED;
        $this->failure_reason = $reason;
        
        if ($gatewayResponse) {
            $this->gateway_response = $gatewayResponse;
        }
        
        return $this->save();
    }

    /**
     * Process refund
     */
    public function refund($amount = null, $reason = null)
    {
        if (!$this->isSuccessful()) {
            throw new \Exception("Cannot refund a payment that is not completed");
        }

        $refundAmount = $amount ?: $this->amount;
        
        if ($refundAmount > $this->amount) {
            throw new \Exception("Refund amount cannot exceed payment amount");
        }

        // Create refund record
        $refund = $this->refunds()->create([
            'amount' => $refundAmount,
            'reason' => $reason,
            'status' => 'pending'
        ]);

        // Process refund through payment gateway
        $result = $this->processRefundThroughGateway($refund);

        if ($result['success']) {
            $refund->update([
                'status' => 'completed',
                'transaction_id' => $result['transaction_id'],
                'processed_at' => now()
            ]);

            // Update payment status
            $totalRefunded = $this->refunds()->where('status', 'completed')->sum('amount');
            
            if ($totalRefunded >= $this->amount) {
                $this->status = self::STATUS_REFUNDED;
            }
            
            $this->save();
        } else {
            $refund->update([
                'status' => 'failed',
                'failure_reason' => $result['error']
            ]);
        }

        return $refund;
    }

    /**
     * Get total refunded amount
     */
    public function getTotalRefunded()
    {
        return $this->refunds()->where('status', 'completed')->sum('amount');
    }

    /**
     * Get remaining refundable amount
     */
    public function getRefundableAmount()
    {
        return $this->amount - $this->getTotalRefunded();
    }

    /**
     * Process refund through payment gateway
     */
    private function processRefundThroughGateway($refund)
    {
        // This would integrate with actual payment gateways
        // For demo purposes, we'll simulate success
        return [
            'success' => true,
            'transaction_id' => 'refund_' . uniqid()
        ];
    }

    /**
     * Get payment method display name
     */
    public function getMethodDisplayName()
    {
        $methods = [
            self::METHOD_CREDIT_CARD => 'Credit Card',
            self::METHOD_DEBIT_CARD => 'Debit Card',
            self::METHOD_PAYPAL => 'PayPal',
            self::METHOD_STRIPE => 'Stripe',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer'
        ];

        return $methods[$this->method] ?? ucfirst(str_replace('_', ' ', $this->method));
    }

    /**
     * Get status display name
     */
    public function getStatusDisplayName()
    {
        $statuses = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded'
        ];

        return $statuses[$this->status] ?? ucfirst($this->status);
    }
}
