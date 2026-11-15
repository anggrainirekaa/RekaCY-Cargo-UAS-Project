@extends('layouts.app')
@section('title', 'New shipment')
@section('subtitle', 'Registers the shipment in CREATED status with its first tracking event')
@section('content')
<form method="POST" action="{{ route('shipments.store') }}">
    @csrf
    @include('shipments._form')
</form>
@endsection
