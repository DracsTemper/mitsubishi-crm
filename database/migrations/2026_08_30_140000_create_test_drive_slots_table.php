<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_drive_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->date('slot_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->unique(['vehicle_id', 'slot_date', 'start_time', 'end_time'], 'test_drive_slots_unique_window');
            $table->index(['vehicle_id', 'slot_date']);
        });

        Schema::table('test_drives', function (Blueprint $table): void {
            $table->foreignId('slot_id')->nullable()->after('salesman_id')->unique()->constrained('test_drive_slots')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('test_drives', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('slot_id');
        });
        Schema::dropIfExists('test_drive_slots');
    }
};
