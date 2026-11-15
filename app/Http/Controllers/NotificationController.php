<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', ['items' => $request->user()->notifications()->paginate(15)]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $n = $request->user()->notifications()->findOrFail($id); // scoped to the owner
        $n->markAsRead();

        if ($request->user()->isCourier()) {
            return redirect()->route('courier.index');
        }

        return redirect()->route('shipments.show', $n->data['shipment_id']);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }
}
