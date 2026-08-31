<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->decimal('booking_amount', 12, 2)->default(0)->after('expected_delivery_date');
            $table->decimal('total_paid', 12, 2)->default(0)->after('booking_amount');
            $table->decimal('due_amount', 12, 2)->default(0)->after('total_paid');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['booking_amount', 'total_paid', 'due_amount']);
        });
    }
};
