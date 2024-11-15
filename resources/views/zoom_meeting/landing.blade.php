@extends('layouts.landing')

@section('body-class', 'tpl-meeting')
@section('title', 'Find Work')

@section('content')
    <main>

        <section class="header-2">
            <div class="container-master">
                <nav class="nav__menu">
                <ul class="nav__list">
                    <li class="nav__item menu__has-children" data-dropdown="1">
                    <a href="/meeting.php" class="active">Meetings
                        <svg xmlns="http://www.w3.org/2000/svg" width="10.5" height="5.535" viewBox="0 0 10.5 5.535">
                        <path id="Path" d="M9.878.113a.441.441,0,0,0-.589,0L5,4.072.711.113a.441.441,0,0,0-.589,0,.364.364,0,0,0,0,.544L4.705,4.887a.441.441,0,0,0,.589,0L9.878.657A.364.364,0,0,0,9.878.113Z" transform="translate(0.25 0.285)" fill="#cfaa40" stroke="#cfaa40" stroke-miterlimit="10" stroke-width="0.5"></path>
                        </svg>
                    </a>

                    <ul class="nav-sub__menu">
                        <li><a href="#">Sub Menu 1</a></li>
                        <li><a href="#">Sub Menu 2</a></li>
                        <li><a href="#">Sub Menu 3</a></li>
                        <li><a href="#">Sub Menu 4</a></li>
                    </ul>
                    </li>

                    <li class="nav__item menu__has-children" data-dropdown="2">
                    <a href="/teams.php" class="">Teams
                        <svg xmlns="http://www.w3.org/2000/svg" width="10.5" height="5.535" viewBox="0 0 10.5 5.535">
                        <path id="Path" d="M9.878.113a.441.441,0,0,0-.589,0L5,4.072.711.113a.441.441,0,0,0-.589,0,.364.364,0,0,0,0,.544L4.705,4.887a.441.441,0,0,0,.589,0L9.878.657A.364.364,0,0,0,9.878.113Z" transform="translate(0.25 0.285)" fill="#cfaa40" stroke="#cfaa40" stroke-miterlimit="10" stroke-width="0.5"></path>
                        </svg>
                    </a>

                    <ul class="nav-sub__menu">
                        <li><a href="#">Sub Menu 1</a></li>
                        <li><a href="#">Sub Menu 2</a></li>
                        <li><a href="#">Sub Menu 3</a></li>
                        <li><a href="#">Sub Menu 4</a></li>
                    </ul>
                    </li>

                    <li class="nav__item">
                    <a href="/document.php" class="">Docs</a>
                    </li>
                    <li class="nav__item">
                    <a href="/task.php" class="">Tasks</a>
                    </li>
                    <li class="nav__item">
                    <a href="/document.php">Co. Notes</a>
                    </li>
                    <li class="nav__item">
                    <a href="/finance.php" class="">Finances</a>
                    </li>
                    <li class="nav__item">
                    <a href="/document.php">Resources</a>
                    </li>
                </ul>
                </nav>
            </div>
        </section>

        <!-- topAlert -->
        <section class="header__alert">
            <div class="container-master">
            <div class="header__alert__content">
                <img src="./images/meeting/ic_notif.png" alt="iconAlert">
                <p>You’ve Been Pinged to the Office - New Meeting at 14:30</p>
            </div>
            </div>
        </section>
        <!-- end topAlert -->
        <!-- title Page -->
        <section class="section__titlepage">
            <div class="container-master">
            <h1 class="section__titlepage--headertitle"><span>Meetings</span></h1>
            </div>
        </section>
        <!-- end title Page -->
        <!-- Meeting -->
        <section class="meetings ">
            <div class="container-master">
            <div class="row-master">
                <div class="col-12 col-lg-6">
                <div class="meetings--content">
                    <h3 class="title_box">
                    Team Schedule of Meetings
                    </h3>
                    <div class="meetings__list">
                    <div class="meetings__item">
                        <div class="timeMeeting">
                        18:13 PM </div>
                        <div class="meetings__item--content">
                        <div class="meetings__item--left">
                            <h3>Jack Mato</h3>
                            <p><a href="#">Hong Kong</a></p>
                            <div class="memberMeeting">
                            <img src="./images/meeting/img.png" alt="membermeeting">
                            <img src="./images/meeting/img copy.png" alt="membermeeting">
                            <img src="./images/meeting/img copy2.png" alt="membermeeting">
                            <img src="./images/meeting/img copy3.png" alt="membermeeting">
                            <img src="./images/meeting/img copy4.png" alt="membermeeting">
                            </div>
                        </div>
                        <div class="meetings__item--right">
                            <p class="date">Oct 10</p>
                            <p class="hour">19:36 PM-12:21 PM</p>
                        </div>
                        </div>
                    </div>
                    <div class="meetings__item">
                        <div class="timeMeeting">
                        21:45 PM </div>
                        <div class="meetings__item--content">
                        <div class="meetings__item--left">
                            <h3>Alpha A.I</h3>
                            <p><a href="#">San Francisco</a></p>
                            <div class="memberMeeting">
                            <img src="./images/meeting/img.png" alt="membermeeting">
                            <img src="./images/meeting/img copy.png" alt="membermeeting">
                            <img src="./images/meeting/img copy2.png" alt="membermeeting">
                            <img src="./images/meeting/img copy3.png" alt="membermeeting">
                            <img src="./images/meeting/img copy4.png" alt="membermeeting">
                            </div>
                        </div>
                        <div class="meetings__item--right">
                            <p class="date">Jul 15</p>
                            <p class="hour">23:43 PM-18:40 PM</p>
                        </div>
                        </div>
                    </div>
                    <div class="meetings__item">
                        <div class="timeMeeting">
                        15:19 PM </div>
                        <div class="meetings__item--content">
                        <div class="meetings__item--left">
                            <h3>Rockefeld</h3>
                            <p><a href="#">New York</a></p>
                            <div class="memberMeeting">
                            <img src="./images/meeting/img.png" alt="membermeeting">
                            <img src="./images/meeting/img copy.png" alt="membermeeting">
                            <img src="./images/meeting/img copy2.png" alt="membermeeting">
                            <img src="./images/meeting/img copy3.png" alt="membermeeting">
                            <img src="./images/meeting/img copy4.png" alt="membermeeting">
                            </div>
                        </div>
                        <div class="meetings__item--right">
                            <p class="date">Jun 10</p>
                            <p class="hour">20:11 PM-09:53 AM</p>
                        </div>
                        </div>
                    </div>
                    </div>
                </div>
                </div>
                <div class="col-12 col-lg-6">
                <div class="meetings--content">
                    <h3 class="title_box">
                    Team Schedule of Meetings
                    </h3>
                    <div class="meetings__list">
                    <div class="meetings__item">
                        <div class="timeMeeting">
                        17:57 PM </div>
                        <div class="meetings__item--content">
                        <div class="meetings__item--left">
                            <h3>Newman Gregory</h3>
                            <p><a href="#">Stonybrook City</a></p>
                            <div class="memberMeeting">
                            <img src="./images/meeting/img.png" alt="membermeeting">
                            <img src="./images/meeting/img copy.png" alt="membermeeting">
                            <img src="./images/meeting/img copy2.png" alt="membermeeting">
                            <img src="./images/meeting/img copy3.png" alt="membermeeting">
                            <img src="./images/meeting/img copy4.png" alt="membermeeting">
                            </div>
                        </div>
                        <div class="meetings__item--right">
                            <p class="date">Jun 17</p>
                            <p class="hour">23:29 PM-11:07 AM</p>
                        </div>
                        </div>
                    </div>
                    <div class="meetings__item">
                        <div class="timeMeeting">
                        12:34 PM </div>
                        <div class="meetings__item--content">
                        <div class="meetings__item--left">
                            <h3>Regina Stevenson</h3>
                            <p><a href="#">Colton City</a></p>
                            <div class="memberMeeting">
                            <img src="./images/meeting/img.png" alt="membermeeting">
                            <img src="./images/meeting/img copy.png" alt="membermeeting">
                            <img src="./images/meeting/img copy2.png" alt="membermeeting">
                            <img src="./images/meeting/img copy3.png" alt="membermeeting">
                            <img src="./images/meeting/img copy4.png" alt="membermeeting">
                            </div>
                        </div>
                        <div class="meetings__item--right">
                            <p class="date">Jun 16</p>
                            <p class="hour">21:13 PM-05:28 AM</p>
                        </div>
                        </div>
                    </div>
                    <div class="meetings__item">
                        <div class="timeMeeting">
                        06:58 AM </div>
                        <div class="meetings__item--content">
                        <div class="meetings__item--left">
                            <h3>Alston Oneil</h3>
                            <p><a href="#">Elliott City</a></p>
                            <div class="memberMeeting">
                            <img src="./images/meeting/img.png" alt="membermeeting">
                            <img src="./images/meeting/img copy.png" alt="membermeeting">
                            <img src="./images/meeting/img copy2.png" alt="membermeeting">
                            <img src="./images/meeting/img copy3.png" alt="membermeeting">
                            <img src="./images/meeting/img copy4.png" alt="membermeeting">
                            </div>
                        </div>
                        <div class="meetings__item--right">
                            <p class="date">Nov 17</p>
                            <p class="hour">12:33 PM-17:51 PM</p>
                        </div>
                        </div>
                    </div>
                    </div>
                </div>
                </div>
            </div>
            </div>
        </section>
        <!--end Meeting -->
        <!-- Video Bar -->
        <section class="videobars">
            <div class="container-master">
            <div class="videobars__content">
                <h3 class="title_box">Video Bar</h3>
                <div class="videobars__list">
                <div class="videobars__video">
                    <a class="content_meets-video" data-fancybox="" href="https://www.youtube.com/watch?v=cnXapYkboRQ">
                    <img src="./images/thumbnails/video-thumb1.png" class="Image thumb">
                    <span class="btn__play">
                        <i class="fa fa-play"></i>
                    </span>
                    </a>
                    <a href="#" class="started">
                    <span>GET STARTED</span>
                    </a>
                </div>
                <div class="videobars__video">
                    <a class="content_meets-video" data-fancybox="" href="https://www.youtube.com/watch?v=cnXapYkboRQ">
                    <img src="./images/thumbnails/video-thumb2.png" class="Image thumb">
                    <span class="btn__play">
                        <i class="fa fa-play"></i>
                    </span>
                    </a>
                    <!-- <a href="#" class="started">GET STARTED</a> -->
                </div>
                <div class="videobars__video">
                    <a class="content_meets-video" data-fancybox="" href="https://www.youtube.com/watch?v=cnXapYkboRQ">
                    <img src="./images/thumbnails/video-thumb6.png" class="Image thumb">
                    <span class="btn__play">
                        <i class="fa fa-play"></i>
                    </span>
                    </a>
                    <!-- <a href="#" class="started">GET STARTED</a> -->
                </div>
                <div class="videobars__video">
                    <a class="content_meets-video" data-fancybox="" href="https://www.youtube.com/watch?v=cnXapYkboRQ">
                    <img src="./images/thumbnails/video-thumb3.png" class="Image thumb">
                    <span class="btn__play">
                        <i class="fa fa-play"></i>
                    </span>
                    </a>
                    <!-- <a href="#" class="started">GET STARTED</a> -->
                </div>
                <div class="videobars__video">
                    <a class="content_meets-video" data-fancybox="" href="https://www.youtube.com/watch?v=cnXapYkboRQ">
                    <img src="./images/thumbnails/video-thumb5.png" class="Image thumb">
                    <span class="btn__play">
                        <i class="fa fa-play"></i>
                    </span>
                    </a>
                    <!-- <a href="#" class="started">GET STARTED</a> -->
                </div>
                </div>
            </div>
            </div>
        </section>
        <!--end Video Bar -->
        <div class="pastmeetingnotes">
            <div class="container-master">
            <div class="row-master">
                <div class="col-12 col-lg-6">
                <div class="pastmeetingnotes__content">
                    <h3 class="title_box">Past Meeting Agendas </h3>
                    <a href="" class="buttonViewAll">View All<svg xmlns="http://www.w3.org/2000/svg" width="8.055" height="15" viewBox="0 0 8.055 15">
                        <path id="Path" d="M6.852,6.616.857.158a.477.477,0,0,0-.711,0,.574.574,0,0,0,0,.767L5.786,7,.148,13.073a.574.574,0,0,0,0,.767A.483.483,0,0,0,.5,14a.483.483,0,0,0,.355-.158l6-6.457a.574.574,0,0,0,0-.768Z" transform="translate(0.555 0.5)" fill="#cfaa40" stroke="#cfaa40" stroke-miterlimit="10" stroke-width="1"></path>
                    </svg>
                    </a>
                    <div class="pastmeetingnotes__list--meeting">
                    <div class="pastmeetingnotes__list--meeting--item">
                        <div class="contentItem">
                        <p class="date">Yesterday</p>
                        <p class="time">22 May, 2020</p>
                        </div>
                    </div>
                    <div class="pastmeetingnotes__list--meeting--item">
                        <div class="contentItem">
                        <p class="date">Previously this Week</p>
                        <p class="time">18 - 21 May, 2020</p>
                        </div>
                    </div>
                    <div class="pastmeetingnotes__list--meeting--item">
                        <div class="contentItem">
                        <p class="date">3rd Week of May</p>
                        <p class="time">11- 15 May, 2020</p>
                        </div>
                    </div>
                    <div class="pastmeetingnotes__list--meeting--item">
                        <div class="contentItem">
                        <p class="date">Last Month</p>
                        <p class="time">April, 2020</p>
                        </div>
                    </div>
                    <div class="pastmeetingnotes__list--meeting--item">
                        <div class="contentItem">
                        <p class="date">March</p>
                        <p class="time">2020</p>
                        </div>
                    </div>
                    <div class="pastmeetingnotes__list--meeting--item">
                        <div class="contentItem">
                        <p class="date">February</p>
                        <p class="time">2020</p>
                        </div>
                    </div>
                    </div>
                </div>
                </div>
                <div class="col-12 col-lg-6">
                <div class="pastmeetingnotes__content">
                    <h3 class="title_box">Notes</h3>
                    <a href="" class="buttonViewAll">View All<svg xmlns="http://www.w3.org/2000/svg" width="8.055" height="15" viewBox="0 0 8.055 15">
                        <path id="Path" d="M6.852,6.616.857.158a.477.477,0,0,0-.711,0,.574.574,0,0,0,0,.767L5.786,7,.148,13.073a.574.574,0,0,0,0,.767A.483.483,0,0,0,.5,14a.483.483,0,0,0,.355-.158l6-6.457a.574.574,0,0,0,0-.768Z" transform="translate(0.555 0.5)" fill="#cfaa40" stroke="#cfaa40" stroke-miterlimit="10" stroke-width="1"></path>
                    </svg>
                    </a>
                    <div class="pastmeetingnotes__list--notes">
                    <div class="pastmeetingnotes__list--notes--item">
                        <h3 class="titleNotes"><a href="/document.php">Title of Note</a></h3>
                        <div class="date">MAY 16</div>
                        <p class="content">
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit.
                        Nam volutpat convallis ipsum, sed pellentesque orci gravida sit amet.
                        Aliquam at odio lorem.
                        </p>
                    </div>
                    <div class="pastmeetingnotes__list--notes--item">
                        <h3 class="titleNotes"><a href="/document.php">Title of Note</a></h3>
                        <div class="date">MAY 16</div>
                        <p class="content">
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit.
                        Nam volutpat convallis ipsum, sed pellentesque orci gravida sit amet.
                        Aliquam at odio lorem.
                        </p>
                    </div>
                    <div class="pastmeetingnotes__list--notes--item">
                        <h3 class="titleNotes"><a href="/document.php">Title of Note</a></h3>
                        <div class="date">MAY 16</div>
                        <p class="content">
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit.
                        Nam volutpat convallis ipsum, sed pellentesque orci gravida sit amet.
                        Aliquam at odio lorem.
                        </p>
                    </div>
                    </div>
                </div>
                </div>
            </div>
            </div>
        </div>
    </main>
@endsection

@section('scripts')

@endsection