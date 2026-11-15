<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('priority', 10)->default('NORMAL')->after('status')->index(); // NORMAL | HIGH | URGENT
        });

        Schema::table('shipment_exceptions', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('exception_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_exception_id')->constrained('shipment_exceptions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['shipment_exception_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exception_notes');
        Schema::table('shipment_exceptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
        });
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['priority']);
            $table->dropColumn('priority');
        });
    }
};
