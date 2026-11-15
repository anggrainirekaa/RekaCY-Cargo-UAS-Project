@php
    $dt = fn ($field) => old($field, optional($shipment->{$field})->format('Y-m-d\TH:i'));
    $items = old('items', $shipment->items->map(fn ($i) => ['description' => $i->description, 'quantity' => $i->quantity, 'weight_kg' => $i->weight_kg])->all() ?: [['description' => '', 'quantity' => 1, 'weight_kg' => '']]);
    $steps = ['Parties & route', 'Cargo', 'Schedule & review'];
@endphp
<div id="wizard" class="mx-auto max-w-4xl">
    <ol class="mb-4 grid grid-cols-3 gap-2" aria-label="Progress">
        @foreach ($steps as $i => $name)
            <li class="step-ind rounded-lg border border-line bg-deep px-3 py-2 text-xs" data-ind="{{ $i + 1 }}"><span class="mr-1.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-elev text-[10px] font-bold">{{ $i + 1 }}</span><span class="font-semibold">{{ $name }}</span></li>
        @endforeach
    </ol>

    {{-- Step 1 --}}
    <section class="card step space-y-4" data-step="1">
        <h2 class="card-title">1 · Parties &amp; route</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label" for="customer_id">Customer</label>
                <select id="customer_id" name="customer_id" required class="input"><option value="">Select…</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id', $shipment->customer_id) == $c->id)>{{ $c->name }}{{ $c->company ? ' — '.$c->company : '' }}</option>@endforeach</select></div>
            <div><label class="label" for="carrier_id">Carrier</label>
                <select id="carrier_id" name="carrier_id" required class="input"><option value="">Select…</option>@foreach ($carriers as $c)<option value="{{ $c->id }}" @selected(old('carrier_id', $shipment->carrier_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div><label class="label" for="origin_id">Origin</label>
                <select id="origin_id" name="origin_id" required class="input"><option value="">Select…</option>@foreach ($locations as $l)<option value="{{ $l->id }}" @selected(old('origin_id', $shipment->origin_id) == $l->id)>{{ $l->label }}</option>@endforeach</select></div>
            <div><label class="label" for="destination_id">Destination</label>
                <select id="destination_id" name="destination_id" required class="input"><option value="">Select…</option>@foreach ($locations as $l)<option value="{{ $l->id }}" @selected(old('destination_id', $shipment->destination_id) == $l->id)>{{ $l->label }}</option>@endforeach</select></div>
            <div><label class="label" for="priority">Priority</label>
                <select id="priority" name="priority" class="input">@foreach (\App\Enums\ShipmentPriority::cases() as $p)<option value="{{ $p->value }}" @selected(old('priority', $shipment->priority?->value ?? 'NORMAL') === $p->value)>{{ ucfirst(strtolower($p->value)) }}</option>@endforeach</select></div>
        </div>
    </section>

    {{-- Step 2 --}}
    <section class="card step hidden space-y-4" data-step="2">
        <h2 class="card-title">2 · Cargo</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div><label class="label" for="cargo_type">Cargo type</label><input id="cargo_type" name="cargo_type" list="cargoTypes" value="{{ old('cargo_type', $shipment->cargo_type) }}" required class="input"><datalist id="cargoTypes">@foreach ($cargoTypes as $t)<option value="{{ $t }}">@endforeach</datalist></div>
            <div><label class="label" for="weight_kg">Weight (kg)</label><input id="weight_kg" type="number" step="0.01" min="0.01" name="weight_kg" value="{{ old('weight_kg', $shipment->weight_kg) }}" required class="input"></div>
            <div><label class="label" for="volume_cbm">Volume (m³)</label><input id="volume_cbm" type="number" step="0.001" min="0" name="volume_cbm" value="{{ old('volume_cbm', $shipment->volume_cbm) }}" class="input"></div>
        </div>
        <div class="flex items-center justify-between"><h3 class="label !mb-0">Items</h3><button type="button" id="addItem" class="btn-ghost px-3 py-1 text-xs"><x-icon name="plus" class="h-3 w-3" /> Add item</button></div>
        <div id="items" class="space-y-2">
            @foreach ($items as $i => $item)
                <div class="item grid gap-2 rounded-lg bg-deep p-3 ring-1 ring-line sm:grid-cols-[1fr_6rem_7rem_auto]">
                    <input name="items[{{ $i }}][description]" value="{{ $item['description'] }}" placeholder="Description" required class="input" aria-label="Item description">
                    <input name="items[{{ $i }}][quantity]" type="number" min="1" value="{{ $item['quantity'] }}" required class="input" aria-label="Quantity" placeholder="Qty">
                    <input name="items[{{ $i }}][weight_kg]" type="number" step="0.01" min="0" value="{{ $item['weight_kg'] }}" class="input" aria-label="Item weight" placeholder="kg">
                    <button type="button" class="rm px-2 text-xs font-semibold text-red-300 hover:underline">Remove</button>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Step 3 --}}
    <section class="card step hidden space-y-4" data-step="3">
        <h2 class="card-title">3 · Schedule &amp; review</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div><label class="label" for="expected_departure_at">Expected departure</label><input id="expected_departure_at" type="datetime-local" name="expected_departure_at" value="{{ $dt('expected_departure_at') }}" required class="input"></div>
            <div><label class="label" for="expected_arrival_at">Expected arrival</label><input id="expected_arrival_at" type="datetime-local" name="expected_arrival_at" value="{{ $dt('expected_arrival_at') }}" required class="input"></div>
            <div><label class="label" for="estimated_arrival_at">Current ETA (optional)</label><input id="estimated_arrival_at" type="datetime-local" name="estimated_arrival_at" value="{{ $dt('estimated_arrival_at') }}" class="input"></div>
            <div class="sm:col-span-3"><label class="label" for="notes">Notes</label><textarea id="notes" name="notes" rows="2" class="input">{{ old('notes', $shipment->notes) }}</textarea></div>
        </div>
        <div class="rounded-lg bg-deep p-4 ring-1 ring-line"><h3 class="label">Review</h3><dl id="review" class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2"></dl></div>
    </section>

    <div class="mt-4 flex items-center justify-between">
        <button type="button" id="prevBtn" class="btn-ghost">Back</button>
        <div class="flex gap-2"><a href="{{ $shipment->exists ? route('shipments.show', $shipment) : route('shipments.index') }}" class="btn-ghost">Cancel</a>
            <button type="button" id="nextBtn" class="btn-primary">Next</button>
            <button type="submit" id="submitBtn" class="btn-primary hidden">{{ $shipment->exists ? 'Save changes' : 'Create shipment' }}</button></div>
    </div>
</div>

<script>
(() => {
    const steps = [...document.querySelectorAll('.step')], inds = [...document.querySelectorAll('.step-ind')];
    const prev = document.getElementById('prevBtn'), next = document.getElementById('nextBtn'), submit = document.getElementById('submitBtn');
    let cur = 1;
    const errs = @json(array_keys($errors->messages()));
    const stepOf = (k) => /^(customer_id|carrier_id|origin_id|destination_id|priority)/.test(k) ? 1 : (/^(cargo_type|weight_kg|volume_cbm|items)/.test(k) ? 2 : 3);
    const text = (id) => { const e = document.getElementById(id); return e.tagName === 'SELECT' ? (e.selectedOptions[0]?.text || '—') : (e.value || '—'); };
    const review = () => {
        const rows = [['Customer', 'customer_id'], ['Carrier', 'carrier_id'], ['Origin', 'origin_id'], ['Destination', 'destination_id'], ['Priority', 'priority'], ['Cargo', 'cargo_type'], ['Weight (kg)', 'weight_kg'], ['Departure', 'expected_departure_at'], ['Expected arrival', 'expected_arrival_at']];
        const dl = document.getElementById('review'); dl.replaceChildren();
        rows.forEach(([l, id]) => { const d = document.createElement('div'); d.className = 'flex justify-between gap-3'; const a = document.createElement('dt'); a.className = 'text-slate-500'; a.textContent = l; const b = document.createElement('dd'); b.className = 'text-right font-medium text-slate-900'; b.textContent = text(id); d.append(a, b); dl.appendChild(d); });
    };
    const show = (n) => {
        cur = n; steps.forEach(s => s.classList.toggle('hidden', +s.dataset.step !== n));
        inds.forEach(i => { const on = +i.dataset.ind === n, done = +i.dataset.ind < n; i.className = 'step-ind rounded-lg border px-3 py-2 text-xs ' + (on ? 'border-cyan-400/60 bg-cyan-400/10 text-cyan-300' : done ? 'border-emerald-400/30 bg-emerald-400/5 text-emerald-300' : 'border-line bg-deep text-slate-500'); });
        prev.classList.toggle('invisible', n === 1); next.classList.toggle('hidden', n === 3); submit.classList.toggle('hidden', n !== 3);
        if (n === 3) review();
    };
    const valid = () => [...steps[cur - 1].querySelectorAll('input,select,textarea')].every(e => e.reportValidity());
    next.addEventListener('click', () => { if (valid()) show(cur + 1); });
    prev.addEventListener('click', () => show(cur - 1));
    show(errs.length ? Math.min(...errs.map(stepOf)) : 1);

    const wrap = document.getElementById('items'); let n = wrap.querySelectorAll('.item').length;
    const bind = (el) => el.querySelector('.rm').addEventListener('click', () => { if (wrap.querySelectorAll('.item').length > 1) el.remove(); });
    wrap.querySelectorAll('.item').forEach(bind);
    document.getElementById('addItem').addEventListener('click', () => {
        const el = wrap.querySelector('.item').cloneNode(true);
        el.querySelectorAll('input').forEach(i => { i.name = i.name.replace(/items\[\d+\]/, `items[${n}]`); i.value = i.name.endsWith('[quantity]') ? 1 : ''; });
        n++; wrap.appendChild(el); bind(el);
    });
})();
</script>
