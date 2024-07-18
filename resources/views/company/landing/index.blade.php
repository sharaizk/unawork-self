@extends('layouts.landing')


@section('page-title')
    {{ __('POD') }}
@endsection
@section('body-class', 'tpl-hub-shop')


@section('content')
<div class="container-master">
    <div class="row-master">
        <div class="col-12 col-md-12 col-xl-9">
            <section class="section__titlepage">
            </section>

            <section class="section__introduce">
            <div class="col-left">
                <img @if ($company->logo) src="{{ App\Helpers\FileHelper::getUrl('logo/' . $company->logo) }}" @else src="{{ App\Helpers\FileHelper::getUrl('logo/' . 'logo-light.png') }}" @endif
                     alt="Logo Shop">
            </div>
            <div class="col-right">
                <div class="item">
                <div class="item__title">
                    <h1>{{ $company->name }}</h1>
                </div>
                <div class="item__content">
                    <ul>
                        @if($company->address || $company->city || $company->state)
                            <li>
                                <i class="fa fa-map-marker"></i>

                                    <address title="Address">
                                        {{ $company->address ? $company->address : '' }}
                                        {{ $company->city ? ', ' . $company->city : '' }}
                                        {{ $company->state ? ', ' . $company->state : '' }}
                                    </address>
                            </li>
                        @endif

                        @if($company->telephone)
                            <li>
                                <i class="fa fa-phone"></i> 
                                <a href="tel:{{$company->telephone}}" title="Phone">
                                    {{$company->telephone}}
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
                </div>
            </div>

            </section>
            <section class="section__about section__layout">
            <div class="section__title">
                <h2>About</h2>
            </div>
            <div class="section__content">
                Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed consectetur quam nisl, et faucibus
                tortor viverra ac. In lobortis tortor massa, a ultrices lectus elementum eu. Proin bibendum
                interdum ex sed congue. Curabitur dapibus tellus eu orci placerat euismod. Suspendisse vitae
                orci porta justo hendrerit imperdiet.
                <!-- <a href="#" class="section__btnMore"><span>Visit Site</span></a> -->
            </div>
            </section>

        </div>
    </div>
</div>
    

@endsection

@push('scripts')
@section('scripts')

@endsection



@endpush