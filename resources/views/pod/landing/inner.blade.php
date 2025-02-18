@extends('layouts.landing')


@section('title')
    {{ __('Inner Circle') }}
@endsection

@section('body-class', 'tpl-innerCircle')


@section('content')

<style>
    .circle_content .text {
        min-width: 45vh; /* Set a minimum height for the description */
    }
</style>

<section class="header__search">
    <div class="container-master">
        <div class="search__wrap">
            <form action="" class="search__box">
                <span class="search__icon">
                    <i class="fa fa-search"></i>
                </span>
                <input type="text" placeholder="Search">
            </form>
            <ul class="search__list">
                <li class="search__item">
                    <a href="#" class="search__link">Location</a>
                </li>
                <li class="search__item">
                    <a href="#" class="search__link">Remote</a>
                </li>
                <li class="search__item ">
                    <a href="#" class="search__link ">Search types by catagory
                        <svg xmlns="http://www.w3.org/2000/svg" width="10.5" height="5.535" viewBox="0 0 10.5 5.535">
                            <path id="Path" d="M9.878.113a.441.441,0,0,0-.589,0L5,4.072.711.113a.441.441,0,0,0-.589,0,.364.364,0,0,0,0,.544L4.705,4.887a.441.441,0,0,0,.589,0L9.878.657A.364.364,0,0,0,9.878.113Z" transform="translate(0.25 0.285)" fill="#022d4d" stroke="#022d4d" stroke-miterlimit="10" stroke-width="0.5"></path>
                        </svg>
                    </a>

                    <ul class="search__submenu">
                        <li>
                            <a class="submenu__link" href="#">Inner Pod</a>
                        </li>
                        <li>
                            <a class="submenu__link" href="#">Inner Pod</a>
                        </li>
                        <li>
                            <a class="submenu__link" href="#">Inner Pod</a>
                        </li>
                        <li>
                            <a class="submenu__link" href="#">Inner Pod</a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</section>

<section class="section__titlepage">
    <div class="container-master">
        <h1 class="section__titlepage--headertitle"><span>Inner Circle</span></h1>
    </div>
</section>

<section class="section__innerCircle">
        <div class="container-master">
            <div class="row-master">

            @foreach ($projects as $project)
                <div class="circle col-12 col-xl-6">
                    <div class="section__list_inner_circle">

                        <div class="circle__info_list--item">
                            <div class="circle_img">
                                <img src="{{ asset('/assets/images/gallery.png') }}" alt="">
                            </div>
                            <div class="circle_content">
                                <div class="title">{{ $project->name }}</div>
                                <div class="price">$ {{ $project->budget }}</div>
                                <div class="text">
                                    {{ \Illuminate\Support\Str::limit($project->description, 120, '...') }}
                                </div>
                            </div>
                            <div class="cicrle_btn">
{{--                                <a href="#" class="btn-accept">Accept</a>--}}
{{--                                <button class="btn-reject">Reject</button>--}}
                                @if (in_array($project->id, \Auth::user()->projects->where('pivot.status', 'active')->pluck('id')->toArray()))
                                    <button class="btn-reject">Joined</button>
                                @elseif (in_array($project->id, \Auth::user()->projects->where('pivot.status', 'pending')->pluck('id')->toArray()))
                                    <button class="btn-reject">Request Sent</button>
                                @elseif (in_array($project->id, \Auth::user()->projects->where('pivot.status', 'declined')->pluck('id')->toArray()))
                                    <button class="btn-reject">Request Declined</button>
                                @elseif (!in_array($project->id, \Auth::user()->projects->pluck('id')->toArray()))
                                        <a href="{{ route('pods.projects.join', ['project' => $project->id]) }}" class="btn-accept">Join</a>
                                @endif
                            </div>
                        </div>

                    </div>
                </div>
            @endforeach
            </div>
        </div>
    </section>
@endsection

@push('scripts')
@section('scripts')


@endsection
@endpush
