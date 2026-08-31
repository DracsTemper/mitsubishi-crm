<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->foreignId('dealer_id')->nullable()->change();
        });
        Schema::create('dealer_vehicle_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['dealer_id', 'vehicle_id']);
            $table->index(['dealer_id', 'status']);
        });

        $now = now();
        DB::table('vehicles')->whereNotNull('dealer_id')->orderBy('id')->each(function (object $vehicle) use ($now): void {
            DB::table('dealer_vehicle_allocations')->insertOrIgnore([
                'dealer_id' => $vehicle->dealer_id,
                'vehicle_id' => $vehicle->id,
                'quantity' => 1,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        Schema::table('test_drive_slots', function (Blueprint $table): void {
            $table->foreignId('dealer_vehicle_allocation_id')->nullable()->after('vehicle_id')->constrained()->restrictOnDelete();
            $table->index(['dealer_vehicle_allocation_id', 'slot_date'], 'td_slots_allocation_date_index');
        });

        DB::table('test_drive_slots')->orderBy('id')->each(function (object $slot): void {
            $allocationId = DB::table('dealer_vehicle_allocations')
                ->join('vehicles', 'vehicles.id', '=', 'dealer_vehicle_allocations.vehicle_id')
                ->where('dealer_vehicle_allocations.vehicle_id', $slot->vehicle_id)
                ->whereColumn('dealer_vehicle_allocations.dealer_id', 'vehicles.dealer_id')
                ->value('dealer_vehicle_allocations.id');
            if ($allocationId) {
                DB::table('test_drive_slots')->where('id', $slot->id)->update(['dealer_vehicle_allocation_id' => $allocationId]);
            }
        });

        Schema::table('test_drive_slots', function (Blueprint $table): void {
            $table->dropUnique('test_drive_slots_unique_window');
            $table->unique(['dealer_vehicle_allocation_id', 'slot_date', 'start_time', 'end_time'], 'td_slots_allocation_unique_window');
        });
    }

    public function down(): void
    {
        Schema::table('test_drive_slots', function (Blueprint $table): void {
            $table->dropUnique('td_slots_allocation_unique_window');
            $table->dropIndex('td_slots_allocation_date_index');
            $table->dropConstrainedForeignId('dealer_vehicle_allocation_id');
            $table->unique(['vehicle_id', 'slot_date', 'start_time', 'end_time'], 'test_drive_slots_unique_window');
        });
        Schema::dropIfExists('dealer_vehicle_allocations');
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->foreignId('dealer_id')->nullable(false)->change();
        });
    }
};
