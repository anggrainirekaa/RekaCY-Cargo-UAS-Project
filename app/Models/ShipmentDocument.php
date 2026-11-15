<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentDocument extends Model
{
    protected $fillable = [
        'type', 'title', 'original_name', 'path', 'mime_type', 'size',
        'recipient_name', 'received_at', 'notes', 'uploaded_by',
    ];

    protected $hidden = ['path']; // never leak the storage path through JSON

    protected function casts(): array
    {
        return ['type' => DocumentType::class, 'received_at' => 'datetime'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        return $this->size >= 1048576 ? number_format($this->size / 1048576, 1).' MB' : number_format($this->size / 1024, 0).' KB';
    }
}
