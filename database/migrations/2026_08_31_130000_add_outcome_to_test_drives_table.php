<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_drives', function (Blueprint $table): void {
            $table->string('outcome', 30)->nullable()->after('status');
            $table->date('follow_up_date')->nullable()->after('outcome');
            $table->text('outcome_notes')->nullable()->after('follow_up_date');
            $table->string('loss_reason', 40)->nullable()->after('outcome_notes');
            $table->timestamp('decided_at')->nullable()->after('loss_reason');
            $table->index(['salesman_id', 'outcome', 'follow_up_date'], 'test_drives_follow_up_index');
        });

        DB::table('test_drives')->whereExists(function ($query): void {
            $query->selectRaw('1')->from('bookings')->whereColumn('bookings.test_drive_id', 'test_drives.id');
        })->update(['outcome' => 'booking', 'decided_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('test_drives', function (Blueprint $table): void {
            $table->dropIndex('test_drives_follow_up_index');
            $table->dropColumn(['outcome', 'follow_up_date', 'outcome_notes', 'loss_reason', 'decided_at']);
        });
    }
};
