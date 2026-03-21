@extends('errors.layout')

@section('code', '500')
@section('title', 'Error interno del servidor')
@section('icon', '⚙️')
@section('icon-bg', '#fef2f2')
@section('code-color-from', '#dc2626')
@section('code-color-to', '#f97316')

@section('message')
  Ocurrió un error inesperado en el servidor. Nuestro equipo ya fue notificado.
  Intenta recargar la página o vuelve más tarde.
@endsection

@section('actions')
  <a href="javascript:location.reload()" class="btn btn-secondary">↺ Recargar</a>
  <a href="/" class="btn btn-primary">Ir al inicio</a>
@endsection
