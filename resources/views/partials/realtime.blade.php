{{-- Connection state pill + toast notifications. Pages react to the 'rk:state' event and fall back to polling. --}}
<div id="toasts" class="pointer-events-none fixed bottom-4 right-4 z-[60] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2"></div>
<script>
(() => {
    const pill = document.getElementById('rtPill');
    const LABEL = { live: 'LIVE', connecting: 'CONNECTING…', offline: 'OFFLINE · POLLING', polling: 'POLLING' };
    const CLS = { live: 'bg-emerald-400/10 text-emerald-300 ring-emerald-400/30', connecting: 'bg-gold/10 text-gold ring-gold/30', offline: 'bg-red-500/10 text-red-300 ring-red-400/30', polling: 'bg-slate-100 text-slate-600 ring-slate-200' };
    const set = (s) => {
        window.rkState = s;
        if (pill) { pill.className = 'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[11px] font-bold ring-1 ' + CLS[s]; pill.textContent = '● ' + LABEL[s]; pill.title = s === 'live' ? 'WebSocket connected: updates arrive instantly' : 'No WebSocket: data refreshes by polling'; }
        window.dispatchEvent(new CustomEvent('rk:state', { detail: s }));
    };

    const toast = (e) => {
        const box = document.getElementById('toasts'); if (!box) return;
        const colors = { info: '#22d3ee', success: '#22c55e', warning: '#f5be42', danger: '#ef4444' };
        const d = document.createElement('div');
        d.className = 'pointer-events-auto rounded-lg border border-line bg-panel p-3 text-sm shadow-xl';
        d.style.borderLeft = '3px solid ' + (colors[e.level] || colors.info);
        const a = document.createElement('p'); a.className = 'font-semibold text-slate-900'; a.textContent = e.title;
        const b = document.createElement('p'); b.className = 'text-xs text-slate-500'; b.textContent = e.message;
        d.append(a, b); box.appendChild(d); setTimeout(() => d.remove(), 7000);
        const badge = document.getElementById('bellBadge');
        if (badge) { badge.classList.remove('hidden'); badge.textContent = String((parseInt(badge.textContent) || 0) + 1); }
    };

    const cfg = window.RK_REALTIME;
    if (!cfg || !cfg.enabled) { set('polling'); return; }
    window.addEventListener('load', () => {
        if (!window.Echo) { set('offline'); return; }
        const c = window.Echo.connector.pusher.connection;
        set(c.state === 'connected' ? 'live' : 'connecting');
        c.bind('connected', () => set('live'));
        c.bind('connecting', () => set('connecting'));
        ['unavailable', 'failed', 'disconnected'].forEach(ev => c.bind(ev, () => set('offline')));
        window.Echo.private('App.Models.User.' + window.RK.userId).listen('.user.notified', toast);
    });
})();
</script>
