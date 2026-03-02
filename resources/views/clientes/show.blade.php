{{-- resources/views/clientes/show.blade.php --}}
@extends('layouts.app')
@section('title','Cliente '.$dni)
@section('crumb','Cliente')

@push('head')
  @vite(['resources/css/clientes/show.css','resources/js/clientes/index.js'])
@endpush
@section('content')

  <x-alerts />

  <x-cliente-head
    :dni="$dni"
    :titular="$titular"
    :cuentas="$cuentas"
    :pagos="$pagos"
    :promesas="$promesas"
    :ccdByDni="$ccdByDni"
  />

  <x-cliente-cuentas
    :dni="$dni"
    :cuentas="$cuentas"
    :cnasByCuenta="$cnasByCuenta"
    :ccdByDni="$ccdByDni"
  />

  <x-cliente-pagos
    :dni="$dni"
    :pagos="$pagos"
  />

  <x-cliente-promesas
    :promesas="$promesas"
  />

  <x-modal-cliente-cronograma />
  <x-modal-cliente-propuesta :dni="$dni" />
  <x-modal-cliente-cna :dni="$dni" />

@endsection