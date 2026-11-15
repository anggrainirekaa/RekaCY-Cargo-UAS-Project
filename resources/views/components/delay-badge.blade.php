@props(['shipment'])
@if ($shipment->is_delayed)
    <span {{ $attributes->merge(['class' => 'badge '.$shipment->delay_severity?->badgeClasses()]) }} title="Delay rule result">
        <x-icon name="clock" class="h-3 w-3" /> +{{ rtrim(rtrim(number_format($shipment->delay_hours, 1), '0'), '.') }}h · {{ $shipment->delay_severity?->value }}
    </span>
@endif
