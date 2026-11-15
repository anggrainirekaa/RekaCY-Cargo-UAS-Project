
@php
    $rtEnabled = config('broadcasting.default') === 'reverb'
        && filled(config('broadcasting.connections.reverb.key'));

    $rtConfig = [
        'enabled' => $rtEnabled,
        'key' => config('broadcasting.connections.reverb.key'),
        'host' => config('broadcasting.connections.reverb.options.host'),
        'port' => (int) config('broadcasting.connections.reverb.options.port'),
        'scheme' => config('broadcasting.connections.reverb.options.scheme'),
    ];

    $rkUser = [
        'userId' => auth()->id(),
        'role' => auth()->user()?->role?->value,
    ];
@endphp

<script>
    window.RK = {{ \Illuminate\Support\Js::from($rkUser) }};
    window.RK_REALTIME = {{ \Illuminate\Support\Js::from($rtConfig) }};
</script>