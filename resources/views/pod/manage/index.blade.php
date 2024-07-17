@extends('layouts.admin')

@section('page-title')
    {{ __('POD') }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.index') }}">{{ __('Pod') }}</a></li>
    <li class="breadcrumb-item"> {{ __('Pod " '.$pod->title.' " Manage') }}</li>
@endsection


@section('content')
<div class="row">
    <div class="col-xl-2">
        @include('pod.manage.nav', ['podId' => $pod->id, 'workspace' => $pod->workspace])
    </div>
    <div class="col-xl-10">
        @yield('content.inside')
    </div>
</div>

@endsection

@push('scripts')
@section('scripts')
@endsection





@endpush