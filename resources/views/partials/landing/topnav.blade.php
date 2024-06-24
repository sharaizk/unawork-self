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
                                    <a href="/find-work.php">Find Work</a>
                                </li>
                            </ul>
                        </nav>
                        <nav class="col nav__right">
                            <ul class="nav__list">
                                <li class="">
                                    <a href="{{ route('landing.hiring') }}">Hire</a>
                                </li>
                                    <li class="">
                                        <a href="/manage-plate.php" title="Manage">Manage</a>
                                    </li>
                                    <li class="">
                                        <a href="/login.php" title="Manage">Login</a>
                                    </li>
                                    <li class="">
                                        <a href="/signup.php" title="Manage">Register</a>
                                    </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </header>