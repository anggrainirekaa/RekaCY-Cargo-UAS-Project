<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(Request $request): View
    {
        $f = $request->validate(['status' => ['nullable', Rule::enum(TicketStatus::class)]]);

        return view('support.index', [
            'tickets' => SupportTicket::query()->visibleTo($request->user())->with(['requester:id,name', 'assignee:id,name', 'shipment:id,shipment_number'])
                ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->latest()->paginate(12)->withQueryString(),
            'filters' => $f,
        ]);
    }

    public function create(Request $request): View
    {
        return view('support.create', ['shipments' => Shipment::query()->visibleTo($request->user())->latest('id')->limit(50)->get(['id', 'shipment_number'])]);
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $ticket = DB::transaction(function () use ($data, $request) {
            $t = new SupportTicket(collect($data)->only(['shipment_id', 'subject', 'category', 'priority'])->all());
            $t->user_id = $request->user()->id;
            $t->status = TicketStatus::Open;
            $t->reference = 'TKT-'.now()->format('ymd').'-'.str_pad((string) ((int) SupportTicket::max('id') + 1), 4, '0', STR_PAD_LEFT);
            $t->save();
            $t->replies()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
            $this->audit->log('ticket.created', $t, null, ['reference' => $t->reference], $request->user());

            return $t;
        });

        return redirect()->route('support.show', $ticket)->with('status', 'Ticket submitted.');
    }

    public function show(SupportTicket $ticket, Request $request): View
    {
        Gate::authorize('view', $ticket);
        $ticket->load(['requester:id,name,company', 'assignee:id,name', 'shipment:id,shipment_number', 'replies.author:id,name,role']);

        $replies = $request->user()->isStaff() ? $ticket->replies : $ticket->replies->where('is_internal', false);

        return view('support.show', [
            'ticket' => $ticket,
            'replies' => $replies,
            'operators' => User::whereIn('role', [Role::Admin->value, Role::Operator->value])->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        Gate::authorize('reply', $ticket);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000'], 'is_internal' => ['nullable', 'boolean']]);

        if (in_array($ticket->status, [TicketStatus::Closed], true)) {
            return back()->withErrors(['business_rule' => 'This ticket is closed.']);
        }

        $internal = $request->user()->isStaff() && $request->boolean('is_internal');
        $ticket->replies()->create(['user_id' => $request->user()->id, 'body' => $data['body'], 'is_internal' => $internal]);

        // A customer reply re-opens a resolved ticket; a first staff reply moves it to IN_PROGRESS.
        if ($request->user()->isCustomer() && $ticket->status === TicketStatus::Resolved) {
            $ticket->status = TicketStatus::Open;
            $ticket->save();
        } elseif ($request->user()->isStaff() && $ticket->status === TicketStatus::Open && ! $internal) {
            $ticket->status = TicketStatus::InProgress;
            $ticket->save();
        }

        return back()->with('status', 'Reply posted.');
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        Gate::authorize('manage', $ticket);
        $data = $request->validate([
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'priority' => ['required', 'in:LOW,MEDIUM,HIGH'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->whereIn('role', ['admin', 'operator'])],
        ]);

        $old = ['status' => $ticket->status->value, 'priority' => $ticket->priority, 'assigned_to' => $ticket->assigned_to];
        $ticket->priority = $data['priority'];
        $ticket->assigned_to = $data['assigned_to'] ?? null;
        $ticket->status = TicketStatus::from($data['status']);
        $ticket->resolved_at = in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true) ? ($ticket->resolved_at ?? now()) : null;
        $ticket->save();
        $this->audit->log('ticket.updated', $ticket, $old, $data, $request->user());

        return back()->with('status', 'Ticket updated.');
    }
}
