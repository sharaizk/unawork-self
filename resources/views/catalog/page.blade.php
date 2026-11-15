{{-- Full-page create/edit for a catalog entry (non-AJAX requests and redisplay after validation errors). --}}
@extends('layouts.admin')

@section('page-title')
    {{ $entry ? $catalog['edit'] : $catalog['add'] }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route($catalog['route'] . '.index') }}">{{ $catalog['title'] }}</a></li>
    <li class="breadcrumb-item">{{ $entry ? $catalog['edit'] : $catalog['add'] }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-6 col-md-8">
            <div class="card">
                @include('catalog.form')
            </div>
        </div>
    </div>
@endsection
