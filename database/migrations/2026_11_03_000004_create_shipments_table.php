<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number', 30)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('carrier_id')->constrained('carriers')->restrictOnDelete();
            $table->foreignId('origin_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('destination_id')->constrained('locations')->restrictOnDelete();
            $table->string('cargo_type', 100);
            $table->decimal('weight_kg', 12, 2);
            $table->decimal('volume_cbm', 12, 3)->nullable();
            $table->dateTime('expected_departure_at');
            $table->dateTime('expected_arrival_at');
            $table->dateTime('estimated_arrival_at')->nullable(); // current ETA reported by carrier/operator
            $table->dateTime('delivered_at')->nullable();
            $table->string('status', 20)->default('CREATED');
            // Delay snapshot, recalculated by EtaService (kept for fast filtering/dashboard).
            $table->boolean('is_delayed')->default(false);
            $table->decimal('delay_hours', 8, 2)->default(0);
            $table->string('delay_severity', 10)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['status', 'is_delayed']);
            $table->index(['customer_id', 'status']);
            $table->index('expected_arrival_at');
            $table->index(['carrier_id', 'is_delayed']);
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('weight_kg', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
    }
};
