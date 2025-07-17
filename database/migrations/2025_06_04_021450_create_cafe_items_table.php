<?php
// database/migrations/xxxx_create_cafe_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cafe_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('category', ['food', 'drink', 'snack', 'dessert']);
            $table->decimal('price', 8, 2);
            $table->string('image')->nullable();
            $table->boolean('is_available')->default(true);
            $table->integer('preparation_time')->default(5); // minutes
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cafe_items');
    }
};
