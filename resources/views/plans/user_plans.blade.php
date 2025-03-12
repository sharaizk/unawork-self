@extends('layouts.admin')

@php
    use App\Models\Utility;
    $setting = Utility::getAdminPaymentSetting();
@endphp

@section('page-title')
    {{ __('User - Plans') }}
@endsection


@section('links')
    @if (\Auth::guard('client')->check())
        <li class="breadcrumb-item"><a href="{{ route('client.home') }}">{{ __('Home') }}</a></li>
    @else
        <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    @endif
    <li class="breadcrumb-item"> {{ __('User - plans') }}</li>
@endsection

@section('content')

    <div class="card-body table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Plan</th>
                    <th>Plan Price</th>
                    <th>Plan purchase. date</th>
                    <th>Plan Exp. date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr>
                        <td> {{ $user->id }} </td>
                        <td> {{ $user->name }} </td>
                        <td> {{ $user->email }} </td>
                        <td> {{ $user->getPlan?->name }} </td>
                        <td> ${{ $user->getPlan?->monthly_price ?? '' }} </td>
                        <td> {{ $user->is_plan_purchased ? ($user->lastPurchasedPlan()?->created_at ?? '-') : '-' }} </td>
                        <td> {{ $user->plan_expire_date ?? '-' }} </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="d-flex justify-content-center mt-3">
            {{ $users->links('vendor.pagination.custom') }}
        </div>
    </div>
@endsection
@push('scripts')
@endpush
