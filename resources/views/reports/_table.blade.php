<div class="card !p-0"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-line">
    <thead class="bg-slate-50"><tr><th class="th">Shipment</th><th class="th">Customer</th><th class="th">Carrier</th><th class="th">Route</th><th class="th">Status</th><th class="th">Expected</th><th class="th">Delay</th><th class="th">Exc.</th></tr></thead>
    <tbody class="divide-y divide-line">
    @forelse ($rows as $s)
        <tr><td class="td">{{ $s->shipment_number }}</td><td class="td">{{ $s->customer->name }}</td><td class="td">{{ $s->carrier->name }}</td><td class="td whitespace-nowrap">{{ $s->origin->city }} → {{ $s->destination->city }}</td><td class="td">{{ $s->status->label() }}</td><td class="td whitespace-nowrap">{{ $s->expected_arrival_at->format('d M Y') }}</td><td class="td">{{ $s->is_delayed ? $s->delay_hours.' h ('.$s->delay_severity?->value.')' : '—' }}</td><td class="td">{{ $s->exceptions_count }}</td></tr>
    @empty<tr><td colspan="8" class="py-12 text-center text-sm text-slate-500">No data for these filters.</td></tr>@endforelse
    </tbody></table></div></div>
