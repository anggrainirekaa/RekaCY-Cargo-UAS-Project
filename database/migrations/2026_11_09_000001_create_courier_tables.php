<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phone', 30)->nullable();
            $table->string('vehicle_type', 30)->default('motorcycle');
            $table->string('vehicle_plate', 20)->nullable();
            $table->string('availability', 20)->default('OFFLINE'); // OFFLINE | AVAILABLE | ON_DELIVERY
            $table->boolean('is_active')->default(true);
            $table->boolean('is_tracking')->default(false);        // true only after the courier consents/starts GPS
            $table->dateTime('last_seen_at')->nullable();           // last GPS point received
            $table->timestamps();

            $table->index(['availability', 'is_active']);
        });

        Schema::create('delivery_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained('couriers')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('ASSIGNED');
            $table->dateTime('assigned_at');
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('picked_up_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('recipient_name', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'status']);
            $table->index(['shipment_id', 'status']);
        });

        // Location history. Append-only; rows are never updated.
        Schema::create('courier_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained('couriers')->cascadeOnDelete();
            $table->foreignId('delivery_assignment_id')->constrained('delivery_assignments')->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();   // metres, from the device
            $table->decimal('speed_kmh', 6, 2)->nullable();
            $table->decimal('heading', 5, 1)->nullable();
            $table->string('source', 10)->default('device'); // device | demo (clearly labelled in the UI)
            $table->dateTime('recorded_at');                  // device timestamp
            $table->timestamp('created_at')->useCurrent();    // server receive time

            $table->index(['delivery_assignment_id', 'recorded_at']);
            $table->index(['shipment_id', 'recorded_at']);
            $table->index(['courier_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_locations');
        Schema::dropIfExists('delivery_assignments');
        Schema::dropIfExists('couriers');
    }
};
