@extends('layouts.app')

@section('title', 'Editor de Declaración Jurada PEP')

@section('content')
    @livewire('pep-editor', ['cliente' => $cliente])
@endsection
