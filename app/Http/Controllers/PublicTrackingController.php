<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public lookup by shipment number. Exposes status + timeline only — no customer data, no coordinates. */
class PublicTrackingController extends Controller
{
    public function show(Request $request): View
    {
        $number = trim((string) $request->query('number', ''));
        $shipment = null;

        if ($number !== '') {
            $shipment = Shipment::query()->where('shipment_number', $number)
                ->with(['origin:id,city,country', 'destination:id,city,country', 'carrier:id,name', 'trackingEvents'])->first();
        }

        return view('track.public', ['number' => $number, 'shipment' => $shipment]);
    }
}
