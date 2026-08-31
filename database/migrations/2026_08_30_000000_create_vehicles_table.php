<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('variant');
            $table->unsignedSmallInteger('model_year');
            $table->string('color');
            $table->decimal('price', 12, 2);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('status', 30)->default('available');
            $table->timestamps();
            $table->index(['dealer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
