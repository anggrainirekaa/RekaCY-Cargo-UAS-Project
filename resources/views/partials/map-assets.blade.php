{{-- Shared Leaflet + Chart.js assets and the RekaCy map renderer. Include once per page. --}}
@once
@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
/**
 * rkMap(elementId, data, opts)
 *   data = { routes[], exceptions[], ports[] }  — built server-side from tracking events (never invented)
 *   opts = { mode: 'full' | 'preview' | 'exceptions', onFilter?: fn }
 * Returns a controller: { setFilter(state|'all'), setCarrier(id|''), focus(shipmentId), map }
 */
window.rkMap = function (id, data, opts = {}) {
    const el = document.getElementById(id);
    const COLORS = { normal: '#22d3ee', attention: '#f5be42', critical: '#ef4444', completed: '#22c55e' };
    const CLS = { normal: 'route-cyan', attention: 'route-gold', critical: 'route-red', completed: 'route-green' };
    const preview = opts.mode === 'preview';

    const map = L.map(el, { zoomControl: true, worldCopyJump: true, scrollWheelZoom: !preview, minZoom: 2, zoomSnap: .5 }).setView([20, 40], 2);
    el.classList.add('rk-map');
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    const layers = { normal: L.layerGroup(), attention: L.layerGroup(), critical: L.layerGroup(), completed: L.layerGroup() };
    const excLayer = L.layerGroup(), portLayer = L.layerGroup();
    const byShipment = {};
    const meta = [];

    const pin = (color, size = 22) => L.divIcon({ className: '', iconSize: [size, size], iconAnchor: [size / 2, size / 2],
        html: `<div class="rk-pin" style="--c:${color}"><span class="ring"></span><span class="core"></span></div>` });

    const popup = (r) => `<div style="min-width:190px"><a href="${esc(r.url)}">${esc(r.number)}</a>
        <div style="color:#94a3b8">${esc(r.route)} · ${esc(r.carrier)}</div>
        <div style="margin-top:4px">Status: <b>${esc(r.status)}</b>${r.delay ? ` · <span style="color:#f5be42">+${esc(r.delay)}h delay</span>` : ''}</div>
        <div>ETA: ${esc(r.eta)}</div>
        ${r.current ? `<div style="color:#64748b;margin-top:4px">Last position: ${esc(r.current.place || 'coordinates')} · ${esc(r.current.at)}</div>` : '<div style="color:#64748b;margin-top:4px">No tracked position yet</div>'}</div>`;

    (data.routes || []).forEach(r => {
        const color = COLORS[r.state], g = layers[r.state];
        const group = L.layerGroup();
        if (r.path.length > 1) {
            L.polyline(r.path, { color, weight: 5, opacity: .14, interactive: false }).addTo(group);
            L.polyline(r.path, { color, weight: 2, opacity: .95, className: CLS[r.state] }).addTo(group).bindPopup(popup(r));
        }
        if (r.remaining) {
            L.polyline(r.remaining, { color, weight: 1.6, opacity: .7, dashArray: '2 8', className: 'route-flow ' + CLS[r.state] }).addTo(group);
        }
        if (r.current) {
            const m = L.marker([r.current.lat, r.current.lng], { icon: pin(color), title: r.number }).bindPopup(popup(r));
            m.addTo(group); byShipment[r.id] = m;
        } else if (r.path.length) {
            byShipment[r.id] = L.circleMarker(r.path[0], { radius: 4, color, fillColor: color, fillOpacity: .8, weight: 1 }).bindPopup(popup(r)).addTo(group);
        }
        group.addTo(g); meta.push({ r, group, g });
    });

    (data.ports || []).forEach(p => {
        L.marker([p.lat, p.lng], { icon: L.divIcon({ className: '', iconSize: [14, 14], iconAnchor: [7, 7], html: '<div class="rk-port"></div>' }), title: p.name })
            .bindPopup(`<b>${esc(p.name)}</b><br>${esc(p.city)} · ${esc(p.count)} active shipment(s)`).addTo(portLayer);
    });

    (data.exceptions || []).forEach(x => {
        const color = x.severity === 'HIGH' ? '#ef4444' : '#f5be42';
        L.marker([x.lat, x.lng], { icon: L.divIcon({ className: '', iconSize: [20, 20], iconAnchor: [10, 10], html: `<div class="rk-exc" style="--c:${color}">!</div>` }), zIndexOffset: 800 })
            .bindPopup(`<a href="${esc(x.url)}">${esc(x.type)}</a><div style="color:#94a3b8">${esc(x.shipment)}</div>
                <div>${esc(x.severity)} · ${esc(x.status)}</div><div style="color:#64748b">Near ${esc(x.place)} (position at detection) · ${esc(x.at)}</div>`).addTo(excLayer);
    });

    const show = () => {
        Object.values(layers).forEach(l => l.addTo(map)); portLayer.addTo(map); excLayer.addTo(map);
    };
    show();

    const fit = () => {
        const pts = [];
        meta.forEach(m => { if (map.hasLayer(m.group)) m.r.path.forEach(p => pts.push(p)); });
        (data.exceptions || []).forEach(x => pts.push([x.lat, x.lng]));
        if (pts.length) map.fitBounds(pts, { padding: [24, 24], maxZoom: preview ? 4 : 5 });
    };
    fit();

    let state = 'all', carrier = '';
    const apply = () => {
        meta.forEach(m => {
            const ok = (state === 'all' || m.r.state === state) && (!carrier || String(m.r.carrier_id) === String(carrier));
            ok ? m.group.addTo(m.g) : m.g.removeLayer(m.group);
        });
    };

    // Legend
    if (opts.mode !== 'preview') {
        const lg = L.control({ position: 'bottomleft' });
        lg.onAdd = () => {
            const d = L.DomUtil.create('div'); d.className = 'rounded-lg border border-line bg-panel/90 px-3 py-2 text-[11px] text-slate-700 backdrop-blur';
            d.innerHTML = `<p class="mb-1 font-semibold uppercase tracking-wider text-slate-500" style="font-size:10px">Legend</p>
                <p><span style="color:#22d3ee">━</span> Normal &nbsp; <span style="color:#f5be42">━</span> Attention &nbsp; <span style="color:#ef4444">━</span> Critical &nbsp; <span style="color:#22c55e">━</span> Delivered</p>
                <p style="margin-top:2px"><span style="color:#168bff">◆</span> Port &nbsp; <span style="color:#f5be42">▣</span> Exception &nbsp; <span style="color:#64748b">┄</span> Remaining leg</p>`;
            L.DomEvent.disableClickPropagation(d); return d;
        };
        lg.addTo(map);
    }

    // Live courier markers (moved in real time; coordinates come only from courier devices or labelled demo points)
    const liveMarkers = {};
    const livePin = L.divIcon({ className: '', iconSize: [28, 28], iconAnchor: [14, 14], html: '<div class="rk-pin" style="--c:#ffffff"><span class="ring"></span><span class="core"></span></div>' });
    const setLive = (id, lat, lng, info = {}) => {
        if (lat == null || lng == null) return;
        let m = liveMarkers[id];
        if (!m) { m = L.marker([lat, lng], { icon: livePin, zIndexOffset: 1500 }).addTo(map); liveMarkers[id] = m; } else { m.setLatLng([lat, lng]); }
        m.bindPopup(`<b>LIVE · ${esc(info.number || '')}</b><br>Courier: ${esc(info.courier || '—')}${info.source === 'demo' ? '<br><span style="color:#f5be42">DEMO point (not device GPS)</span>' : ''}<br><span style="color:#64748b">${esc(info.at ? new Date(info.at).toLocaleTimeString('en-GB') : '')}</span>`);
    };

    const ctl = {
        map,
        setLive,
        clearLive(id) { if (liveMarkers[id]) { map.removeLayer(liveMarkers[id]); delete liveMarkers[id]; } },
        setFilter(s) { state = s; apply(); opts.onFilter && opts.onFilter(s); },
        setCarrier(c) { carrier = c; apply(); },
        focus(id) { const m = byShipment[id]; if (m) { map.setView(m.getLatLng ? m.getLatLng() : map.getCenter(), 5, { animate: true }); m.openPopup(); } },
        fit,
    };
    setTimeout(() => map.invalidateSize(), 200);
    window.addEventListener('resize', () => map.invalidateSize());
    return ctl;
};
</script>
@endpush
@endonce
