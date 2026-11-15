@extends('layouts.app')
@section('title', 'Edit '.$shipment->shipment_number)
@section('subtitle', 'Status changes are made through tracking events, not here')
@section('content')
<form method="POST" action="{{ route('shipments.update', $shipment) }}">
    @csrf @method('PUT')
    @include('shipments._form')
</form>
@endsection
