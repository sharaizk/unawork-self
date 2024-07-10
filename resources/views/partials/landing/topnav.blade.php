@php
    $logo = \App\Models\Utility::get_file('users-avatar/');
@endphp
<header id="header">
        <div class="header__top">
            <div class="container-master">
                <div class="header__container">
                    <div class="logoHeader">
                        <a href="/" title="UnaWork">
                            <img src="{{ asset('assets/images/landing/logo.png') }}" alt="Logo UnaWork">
                        </a>
                    </div>
                    <div class="header__main">
                        <nav class="col nav__left">
                            <ul class="nav__list">
                                <li class="logoHeaderMenu">
                                    <a href="/">
                                        <img src="{{ asset('assets/images/landing/logo.png') }}" alt="Logo UnaWork">
                                    </a>
                                </li>
                                <li class="">
                                    <!-- is going to turn into the manage plate after they sign in -->
                                    <a href="#">Home</a>
                                </li>
                                <li class="">
                                    <a href="{{ route('landing.find-work') }}">Find Work</a>
                                </li>
                                <li class="">
                                    <a href="{{ route('landing.hiring') }}">Hire</a>
                                </li>
                            </ul>
                        </nav>
                        <nav class="col nav__right">
                            <ul class="nav__list">
                                
                                @guest
                                    <li class="">
                                        <a href="{{ route('login') }}" title="Login">Login</a>
                                    </li>
                                    <li class="">
                                        <a href="{{ route('register') }}" title="Register">Register</a>
                                    </li>
                                @endguest
                                @auth 
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
            </div>
        </div>
    </header>