<?php
// database/migrations/xxxx_create_cafe_stocks_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cafe_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_item_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->integer('minimum_stock')->default(5);
            $table->decimal('cost_price', 8, 2)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cafe_stocks');
    }
};
