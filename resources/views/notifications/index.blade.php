@extends(auth()->user()->isCourier() ? 'layouts.courier' : 'layouts.app')
@section('title', 'Notifications')
@section('subtitle', 'In-app alerts about your shipments')
@section('content')
<div class="mb-3 flex justify-end">
    <form method="POST" action="{{ route('notifications.readAll') }}">@csrf<button class="btn-ghost">Mark all as read</button></form>
</div>
<div class="card divide-y divide-slate-100 p-0">
    @forelse ($items as $n)
        @php $lvl = ['info' => 'bg-sky-500', 'success' => 'bg-emerald-500', 'warning' => 'bg-amber-500', 'danger' => 'bg-rose-500'][$n->data['level'] ?? 'info'] ?? 'bg-sky-500'; @endphp
        <a href="{{ route('notifications.read', $n->id) }}" class="flex items-start gap-3 px-5 py-4 hover:bg-slate-50 {{ $n->read_at ? 'opacity-60' : '' }}">
            <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $lvl }}"></span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-slate-900">{{ $n->data['title'] }} <span class="font-normal text-slate-400">· {{ $n->data['shipment_number'] }}</span></p>
                <p class="text-sm text-slate-600">{{ $n->data['message'] }}</p>
            </div>
            <span class="shrink-0 text-xs text-slate-400">{{ $n->created_at->diffForHumans() }}</span>
        </a>
    @empty
        <p class="py-16 text-center text-sm text-slate-500">You are all caught up.</p>
    @endforelse
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
