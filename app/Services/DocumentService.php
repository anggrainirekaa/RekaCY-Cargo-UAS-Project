<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentService
{
    public function __construct(private AuditLogger $audit)
    {
    }

    /** Stores the file on the private disk under a random name; the client file name is kept only as metadata. */
    public function store(Shipment $shipment, UploadedFile $file, array $meta, User $actor): ShipmentDocument
    {
        return DB::transaction(function () use ($shipment, $file, $meta, $actor) {
            $path = $file->store("documents/{$shipment->id}", 'local');

            $doc = $shipment->documents()->create([
                'type' => $meta['type'],
                'title' => $meta['title'],
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                'path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'recipient_name' => $meta['recipient_name'] ?? null,
                'received_at' => $meta['received_at'] ?? null,
                'notes' => $meta['notes'] ?? null,
                'uploaded_by' => $actor->id,
            ]);

            $this->audit->log('document.uploaded', $doc, null, ['shipment_id' => $shipment->id, 'type' => $meta['type']], $actor);

            return $doc;
        });
    }

    public function delete(ShipmentDocument $doc, User $actor): void
    {
        DB::transaction(function () use ($doc, $actor) {
            $this->audit->log('document.deleted', $doc, ['title' => $doc->title], null, $actor);
            Storage::disk('local')->delete($doc->path);
            $doc->delete();
        });
    }
}
