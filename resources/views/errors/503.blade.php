@extends('errors.layout')

@section('code', '503')
@section('title', 'Servicio no disponible')
@section('icon', '🛠️')
@section('icon-bg', '#fffbeb')
@section('code-color-from', '#d97706')
@section('code-color-to', '#f59e0b')

@section('message')
  El sistema está en mantenimiento o temporalmente fuera de servicio.
  Por favor, intenta de nuevo en unos minutos.
@endsection

@section('actions')
  <a href="javascript:location.reload()" class="btn btn-secondary">↺ Reintentar</a>
@endsection
