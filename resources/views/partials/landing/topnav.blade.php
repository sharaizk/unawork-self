@php
    $settings = \Modules\LandingPage\Entities\LandingPageSetting::settings();
    $logo = \App\Models\Utility::get_file('users-avatar/');
@endphp
<header id="header">
        <div class="header__top">
{{--            <div class="container-master">--}}
                <div class="header__container">
                    <div class="header__main">

                        <div class="logoHeader ms-5 me-5">
                            <a href="/" title="UnaWork">
                                <img src="{{ \App\Models\Utility::get_file('uploads/landing_page_image') . '/' . $settings['site_logo'] . '?v=' . time() }}" alt="logo">
                                <!-- <img src="{{ asset('assets/images/landing/logo.png') }}" alt="Logo UnaWork"> -->
                            </a>
                        </div>
                        <nav class="col nav__left">
                            <ul class="nav__list m-0">
                                <li class="">
                                    <!-- is going to turn into the manage plate after they sign in -->
                                    <a href="{{ route('login') }}">Home</a>
                                </li>
                                <li class="{{ Request::route()->getName() == 'landing.find-work' ? 'active' : '' }}">
                                    <a href="{{ route('landing.find-work') }}" class="">Find Work</a>
                                </li>
                                <li class="{{ Request::route()->getName() == 'landing.hiring' ? 'active' : '' }}">
                                    <a href="{{ route('landing.hiring') }}">Hiring</a>
                                </li>
                                <li class="{{ Request::route()->getName() == 'pods.list' ? 'active' : '' }}">
                                    <a href="{{ route('pods.list') }}">PODs</a>
                                </li>
                            </ul>
                        </nav>
                        <nav class="col nav__right">
                            <ul class="nav__list m-0">

                                @guest
                                    <li class="">
                                        <a href="{{ route('login') }}" title="Login">Login</a>
                                    </li>
                                    <li class="">
                                        <a href="{{ route('register') }}" title="Register">Register</a>
                                    </li>
                                @endguest
                                @auth
                                        <li class="">
                                            <a href="{{ route('login') }}">Manage</a>
                                        </li>
                                            <div class="dash-header">

                                                    <div class="header-wrapper">
                                                        <div class="dash-mob-drp">
                                                            <li class="dropdown dash-h-item drp-company">
                                                                <a class="dash-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#"
                                                                    role="button" aria-haspopup="false" aria-expanded="false">
                                                                    <img class="theme-avtar"@if (Auth::user()->avatar) src="{{ asset($logo . Auth::user()->avatar) }}" @else avatar="{{ Auth::user()->name }}" @endif
                                                                        alt="{{ Auth::user()->name }}">
                                                                    <span class="hide-mob ms-2">{{ __('Hi') }},{{ Auth::user()->name }} !</span>
                                                                    <i class="ti ti-chevron-down drp-arrow nocolor hide-mob"></i>
                                                                </a>
                                                                <div class="dropdown-menu dash-h-dropdown">
                                                                    <a href="@auth('web'){{ route('users.my.account') }}@elseauth{{ route('client.users.my.account') }}@endauth"
                                                                        class="dropdown-item p-3">
                                                                        <i class="ti ti-user"></i>
                                                                        <span>{{ __('My Profile') }}</span>
                                                                    </a>
                                                                    <a href="{{ route('dashboard') }}"
                                                                        class="dropdown-item p-3">
                                                                        <i class="ti ti-user"></i>
                                                                        <span>{{ __('Dashboard') }}</span>
                                                                    </a>
                                                                    <a href="#"
                                                                        class="dropdown-item p-3 "onclick="event.preventDefault();document.getElementById('logout-form1').submit();">
                                                                        <i class="ti ti-power"></i>
                                                                        <span>{{ __('Logout') }}</span>
                                                                    </a>
                                                                    <form id="logout-form1"
                                                                        action="@auth('web'){{ route('logout') }}@elseauth{{ route('client.logout') }}@endauth"
                                                                        method="POST" style="display: none;">
                                                                        @csrf
                                                                    </form>
                                                                </div>
                                                            </li>
                                                        </div>
                                                    </div>
                                            </div>
                                @endauth
                            </ul>
                        </nav>
                    </div>
                </div>
{{--            </div>--}}
        </div>
    </header>
