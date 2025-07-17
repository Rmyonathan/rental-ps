<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB; // Add this line

use Illuminate\Support\Facades\Schema;

class UpdateRentalsStatusEnum extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE rentals MODIFY COLUMN status ENUM('active', 'completed', 'pending', 'expired') NOT NULL DEFAULT 'active'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE rentals MODIFY COLUMN status ENUM('active', 'completed', 'pending') NOT NULL DEFAULT 'active'");
    }
}
