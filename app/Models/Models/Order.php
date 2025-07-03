<?php

namespace App\Models\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Http;

class Order extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'order_number',
        'order_type_id',
        'site_id',
        'client_name',
        'client_email',
        'client_phone',
        'article_name',
        'post_url',
        'live_link_status',
        'live_link_url',
        'notes',
        'client_price',
        'admin_fee',
        'net_profit',
        'admin_invoice_url',
        'admin_invoice_file',
        'admin_invoice_picture',
        'client_invoice_url',
        'client_invoice_file',
        'client_invoice_picture',
        'invoice_status',
        'status',
        'completed_at',
    ];
    
    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    
    public const INVOICE_STATUS_UNPAID = 'unpaid';
    public const INVOICE_STATUS_PARTIAL = 'partial';
    public const INVOICE_STATUS_PAID = 'paid';

    protected $casts = [
        'client_price' => 'decimal:2',
        'admin_fee' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            // Generate order number if not set
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
            
            // Set default status if not set
            if (empty($order->status)) {
                $order->status = static::STATUS_IN_PROGRESS;
            }
            
            // Set default values for prices if not set
            if (!is_numeric($order->client_price)) {
                $order->client_price = 0;
            }
            
            if (!is_numeric($order->admin_fee)) {
                $order->admin_fee = 0;
            }
            
            // Note: net_profit is calculated by the database as a generated column
        });

        static::updating(function ($order) {
            if ($order->isDirty('status') && $order->status === self::STATUS_COMPLETED && empty($order->completed_at)) {
                $order->completed_at = now();
            }
        });
    }



    public function orderType(): BelongsTo
    {
        return $this->belongsTo(OrderType::class);
    }
    
    /**
     * Get the available status options for orders
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }
    
    /**
     * Get the available invoice status options
     */
    public static function getInvoiceStatusOptions(): array
    {
        return [
            self::INVOICE_STATUS_UNPAID => 'Unpaid',
            self::INVOICE_STATUS_PARTIAL => 'Partially Paid',
            self::INVOICE_STATUS_PAID => 'Paid',
        ];
    }
    
    /**
     * Generate a unique order number
     */
    protected static function generateOrderNumber(): string
    {
        $prefix = 'GBOB-' . now()->format('Ym') . '-';
        $lastOrder = static::withTrashed()
            ->where('order_number', 'like', $prefix . '%')
            ->orderBy('order_number', 'desc')
            ->first();
            
        if ($lastOrder && is_numeric(substr($lastOrder->order_number, -5))) {
            $lastNumber = (int) substr($lastOrder->order_number, -5);
            $nextNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
        } else {
            // If no orders yet or invalid format, start with 00001
            $nextNumber = '00001';
        }
        
        return $prefix . $nextNumber;
    }
    
    /**
     * Scope a query to only include completed orders
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }
    
    /**
     * Check if the order is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
    
    /**
     * Check if the order is paid
     */
    public function isPaid(): bool
    {
        return $this->invoice_status === self::INVOICE_STATUS_PAID;
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

}