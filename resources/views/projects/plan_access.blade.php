@extends('layouts.admin')

@section('content')
    <h1>You have no access to this page</h1>
    <a href="{{ route('plans.index') }}">UPGRADE YOUR PLAN</a>
    <br>
    In order to create Inner Circle, please activate it  <a href="{{ route('pods.index') }}" class="btn btn-secondary">here</a>
@endsection
