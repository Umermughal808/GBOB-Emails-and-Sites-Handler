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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('client_invoice_file')->nullable()->after('client_invoice_url');
            $table->string('client_invoice_picture')->nullable()->after('client_invoice_file');
            $table->string('admin_invoice_file')->nullable()->after('admin_invoice_url');
            $table->string('admin_invoice_picture')->nullable()->after('admin_invoice_file');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'client_invoice_file',
                'client_invoice_picture',
                'admin_invoice_file',
                'admin_invoice_picture'
            ]);
        });
    }
};
