@extends('errors.layout')

@section('code', '404')
@section('title', 'Página no encontrada')
@section('icon', '🔍')
@section('icon-bg', '#eff6ff')
@section('code-color-from', '#2563eb')
@section('code-color-to', '#7c3aed')

@section('message')
  La página que buscas no existe o fue movida a otra dirección.
  Verifica la URL o regresa al inicio.
@endsection

@section('actions')
  <a href="javascript:history.back()" class="btn btn-secondary">← Volver atrás</a>
  <a href="/" class="btn btn-primary">Ir al inicio</a>
@endsection
