{{-- resources/views/autorizacion/index.blade.php --}}
@extends('layouts.app')
@section('title','Autorización')
@section('crumb','Autorización')

@push('head')
    @vite(['resources/css/auth/autorizacion.css'])
    <meta name="autz-pagos-url" content="{{ route('autorizacion.pagos', '__DNI__') }}">
@endpush

@section('content')
@php
    $countProm = method_exists($rows,'total') ? $rows->total() : (is_countable($rows) ? count($rows) : 0);
    $countCna  = method_exists($cnaRows,'total') ? $cnaRows->total() : (is_countable($cnaRows) ? count($cnaRows) : 0);
@endphp

<div class="autz-page space-y-4">

    {{-- 1. Buscador y Filtros --}}
    @include('autorizacion.partials._header', [
        'isSupervisor' => $isSupervisor,
        'q' => $q,
        'countProm' => $countProm,
        'countCna' => $countCna
    ])

    {{-- 2. Tab de Promesas --}}
    @include('autorizacion.partials._tab-promesas', [
        'rows' => $rows,
        'isSupervisor' => $isSupervisor
    ])

    {{-- 3. Tab de CNA --}}
    @include('autorizacion.partials._tab-cna', [
        'cnaRows' => $cnaRows,
        'prodByOp' => $prodByOp,
        'isSupervisor' => $isSupervisor
    ])

    {{-- 4. Modales (Se cargan al final) --}}
    @include('autorizacion.partials._modals')

</div>
@endsection

@push('scripts')
    @vite(['resources/js/auth/autorizacion.js'])
@endpush