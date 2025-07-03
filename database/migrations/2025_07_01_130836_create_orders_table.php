<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('order_type_id')->constrained()->onDelete('restrict');
            $table->foreignId('site_id')->nullable()->constrained()->onDelete('set null');
            
            // Client Information
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            
            // Order Details
            $table->string('article_name')->nullable();
            $table->string('post_url')->nullable();
            $table->enum('live_link_status', ['pending', 'rejected', 'live'])->default('pending');
            $table->string('live_link_url')->nullable();
            $table->text('notes')->nullable();
            
            // Financial Information
            $table->decimal('client_price', 10, 2);
            $table->decimal('admin_fee', 10, 2);
            $table->decimal('net_profit', 10, 2)->storedAs('client_price - admin_fee');
            
            // Invoice Information
            $table->string('admin_invoice_url')->nullable();
            $table->string('client_invoice_url')->nullable();
            $table->enum('invoice_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            
            // Status and Timestamps
            $table->enum('status', ['draft', 'in_progress', 'completed', 'rejected', 'cancelled'])->default('in_progress');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
