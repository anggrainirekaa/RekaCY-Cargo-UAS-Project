<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Files live on the PRIVATE disk and are served only through an authorised controller.
        Schema::create('shipment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);            // POD | INVOICE | BILL_OF_LADING | CUSTOMS | OTHER
            $table->string('title', 150);
            $table->string('original_name', 255);
            $table->string('path', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            // Proof-of-delivery metadata
            $table->string('recipient_name', 120)->nullable();
            $table->dateTime('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_documents');
    }
};
