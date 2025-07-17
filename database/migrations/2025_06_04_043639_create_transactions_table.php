<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->enum('type', ['debit', 'credit']);
            $table->decimal('amount', 15, 2);
            $table->string('description');
            $table->enum('category', ['rental', 'cafe', 'stock', 'manual', 'other']);
            $table->string('reference_type')->nullable(); // 'rental', 'cafe_order', 'cafe_stock', 'manual'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('transaction_date');
            $table->string('created_by')->nullable(); // For manual entries
            $table->timestamps();
            
            $table->index(['transaction_date', 'type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('category');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
