<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>RekaCy shipment report</title>
<style>body{font-family:Inter,Arial,sans-serif;color:#0f172a;margin:24px}h1{margin:0 0 4px}table{border-collapse:collapse;width:100%;font-size:12px;margin-top:12px}th,td{border:1px solid #cbd5e1;padding:6px 8px;text-align:left}th{background:#e2e8f0}.k{display:inline-block;margin:4px 16px 4px 0}@media print{button{display:none}}</style></head>
<body><button onclick="window.print()">Print / Save as PDF</button>
<h1>RekaCy — Shipment report</h1><p>Generated {{ now()->format('d M Y H:i') }} · Filters: {{ collect($filters)->map(fn ($v, $k) => "$k=$v")->implode(', ') ?: 'none' }}</p>
<p>@foreach (['total' => 'Shipments', 'delivered' => 'Delivered', 'delayed' => 'Delayed', 'exceptions' => 'Exceptions'] as $k => $l)<span class="k"><b>{{ $summary[$k] }}</b> {{ $l }}</span>@endforeach<span class="k"><b>{{ $summary['on_time_rate'] ?? '—' }}{{ $summary['on_time_rate'] !== null ? '%' : '' }}</b> on-time</span></p>
<table><thead><tr><th>Shipment</th><th>Customer</th><th>Carrier</th><th>Route</th><th>Status</th><th>Expected</th><th>Delay</th><th>Exc.</th></tr></thead><tbody>
@foreach ($rows as $s)<tr><td>{{ $s->shipment_number }}</td><td>{{ $s->customer->name }}</td><td>{{ $s->carrier->name }}</td><td>{{ $s->origin->city }} → {{ $s->destination->city }}</td><td>{{ $s->status->label() }}</td><td>{{ $s->expected_arrival_at->format('d M Y') }}</td><td>{{ $s->is_delayed ? $s->delay_hours.' h' : '—' }}</td><td>{{ $s->exceptions_count }}</td></tr>@endforeach
</tbody></table></body></html>
