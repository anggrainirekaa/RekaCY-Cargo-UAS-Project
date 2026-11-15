<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Models\Shipment;
use App\Models\ShipmentDocument;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(private DocumentService $documents)
    {
    }

    public function index(Request $request): View
    {
        $docs = ShipmentDocument::query()->with(['shipment:id,shipment_number,customer_id', 'uploader:id,name'])
            ->whereHas('shipment', fn ($q) => $q->visibleTo($request->user()))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->latest()->paginate(15)->withQueryString();

        return view('documents.index', ['docs' => $docs]);
    }

    public function store(StoreDocumentRequest $request, Shipment $shipment): RedirectResponse
    {
        $data = $request->validated();
        $this->documents->store($shipment, $request->file('file'), $data, $request->user());

        return redirect()->route('shipments.show', $shipment)->with('status', 'Document uploaded.');
    }

    /** Authorised, private download/preview. Files are never reachable by a public URL. */
    public function download(Request $request, ShipmentDocument $document)
    {
        Gate::authorize('view', $document);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        $headers = ['X-Content-Type-Options' => 'nosniff'];

        if ($request->boolean('inline') && in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            return Storage::disk('local')->response($document->path, $document->original_name, $headers + ['Content-Type' => $document->mime_type]);
        }

        return Storage::disk('local')->download($document->path, $document->original_name, $headers);
    }

    public function destroy(Request $request, ShipmentDocument $document): RedirectResponse
    {
        Gate::authorize('delete', $document);
        $this->documents->delete($document, $request->user());

        return back()->with('status', 'Document deleted.');
    }
}
