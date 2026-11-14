<!DOCTYPE html>
@php
    $logo = \App\Models\Utility::get_file('logo/');
@endphp
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ asset($logo . 'favicon.png') }}">
    <title>@yield('title')</title>

    <!-- CSS -->
<link rel="stylesheet" href="{{ asset('assets/css/plugins/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/landing/main.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/plugins/fontAwesome/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/landing/hire-page.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/landing/find-work.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/landing/inner-circle.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/landing/hub-shop.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/landing/commerce-shop.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/landing/meeting.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/landing/finance.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/material.css') }}">

    <style>
        #notification-container {
            position: fixed;
            top: 5%;
            right: 2%;
            z-index: 1050;
            max-width: 300px;
        }
        #notification-container a {
            color: blue !important;
        }
    </style>
</head>

<body class="@yield('body-class')">
@include('partials.landing.topnav')

<main>



@yield('content')
</main>
@php
    $settings = \Modules\LandingPage\Entities\LandingPageSetting::settings();
@endphp

<footer class="footer">
    <div class="container">
        <div class="footer__content mt-[100px]">
            <div>
                <img class="footer__contetnt__logo" src="{{ \App\Models\Utility::get_file('uploads/landing_page_image') . '/' . $settings['site_logo'] . '?v=' . time() }}" alt="logo">
                <div class="footer__socials">
                    <a target="_blank" href="https://www.facebook.com/virtualworkhub#"><i class="fab fa-facebook-f"></i></a>
                    <a target="_blank" href="https://www.youtube.com/channel/UCCls-q5CFpLOfRqyyU-KH3w "><i class="fab fa-youtube"></i></a>
                    <a target="_blank" href="https://x.com/unahub/">
                        <svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" viewBox="0 0 512 512">
                            <path fill="#022d4d" d="M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z"/>
                        </svg>
                    </a>
                </div>
            </div>
            <div class='footer__links'>
                <div>
                    <a href="/">Home</a>
                    <a href="{{ route('landing.find-work') }}" class="">Find Work</a>
                    <a href="{{ route('landing.hiring') }}">Hiring</a>
                    <a href="{{ route('pods.list') }}">PODs</a>
                    <a href="{{ route('login') }}">Manage</a>
                </div>
                <div>
                    <a href="https://app.unawork.net/pages/about_us">About us</a>
                    <a href="https://app.unawork.net/pages/contact_us" class="">Contact us</a>
                    <a href="https://app.unawork.net/pages/faq" class="">FAQ</a>
                </div>
                <div>
                    <a href="{{ route('login') }}">Login</a>
                    <a href="{{ route('register') }}" class="">Register</a>
                </div>
            </div>
        </div>
        <div class="footer__copyright">
            <p>Copyright © 2025 Unawork</p>
            <div class="footer__cookies">
                <a href="http://www.unawork.net/cookies-policy/">Cookies</a>
                <a href="http://www.unawork.net/privacy-policy-2/">Privacy Policy</a>
                <a href="http://www.unawork.net/terms-conditions/">Terms & Conditions</a>
            </div>
        </div>
    </div>
</footer>

<div class="container mt-3">
    <div id="notification-container" class="alert" style="display: none; color:#000;"></div>
</div>
<div class="modal fade" id="commonModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="body">
                </div>

            </div>
        </div>
    </div>
    <div class="modal fade" id="commonModalOver" tabindex="1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                </div>

            </div>
        </div>
    </div>
    @auth('web')
    @if (!\Auth::user()->isAdministrator())
    <!-- create meeting popup -->
    <div class="modal fade" tabindex="-1" role="dialog" id="newMeetingPopup">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header text-white">
                    <h5 class="modal-title">Create Meeting</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form id="createMeetingForm" action="{{ route('zoom-meeting.store', \Auth::user()->ownWorkspace()->slug) }}" method="POST" @if (!Auth::user()->isAbleToCreateZoomMeetings()) class="d-none" @endif >
                        @csrf
                        <div class="modal-body">
                            <input type="hidden" name="members[0]" id="memberIdInput" value="">
                            <input type="hidden" name="members[1]" value="{{ \Auth::id() }}">

                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" name="title" id="title" class="form-control" placeholder="Enter meeting title" required>
                            </div>

                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                {{ Form::text('start_date', null, ['class' => 'form-control date', 'placeholder' => __('Select Date/Time'), 'required' => 'required']) }}
                            </div>

                            <div class="mb-3">
                                <label for="duration" class="form-label">Duration (minutes)</label>
                                <input type="number" name="duration" id="duration" class="form-control" placeholder="Enter meeting duration in minutes" required>
                            </div>
                        </div>

                        <div class="modal-footer justify-content-center">
                            <button type="submit" class="btn btn-primary">Create Meeting</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </form>
                    @if (!Auth::user()->isAbleToCreateZoomMeetings()) <h1> {{ __('Your plan does not allow creating Zoom meetings.') }}</h1> @endif
            </div>
        </div>
    </div>
    @endif
    @endauth

    <script src="{{ asset('assets/custom/js/site.core.js') }}"></script>
    <script src="{{ asset('assets/custom/libs/moment/min/moment.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/popper.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/custom/js/main.min.js') }}"></script>
    <script src="{{ asset('assets/custom/libs/bootstrap-notify/bootstrap-notify.min.js') }}"></script>
    <script src="{{ asset('assets/custom/js/custom.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/bootstrap-switch-button.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/choices.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/simplebar.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/datepicker-full.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/feather.min.js') }}"></script>
    <script src="{{ asset('assets/js/dash.js') }}"></script>
    <script src="{{ asset('assets/custom/libs/summernote/summernote-bs4.js') }}"></script>

    <script src="{{ asset('assets/js/plugins/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('assets/custom/js/ac-alert.js') }}"></script>
    <script src="{{ asset('assets/custom/js/letter.avatar.js') }}"></script>
    <script src="{{ asset('assets/custom/js/fire.modal.js') }}"></script>

    <script src="{{ asset('assets/js/plugins/simple-datatables.js') }}"></script>
    <script>
        var site_url = $('meta[name="base-url"]').attr("content");
        const dataTable = new simpleDatatables.DataTable("#selection-datatable", {
            language: {
                url: site_url + '/resources/lang/datatables/' + window.currentLocale + '.json'
            }
        });
    </script>

    <!-- loaded to body -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('loaded');
        });
    </script>
    <script>
        function showNotification(message, type = 'info') {
            const notificationContainer = document.getElementById('notification-container');
            notificationContainer.className = `alert alert-${type} alert-dismissible fade show`;
            notificationContainer.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            notificationContainer.style.display = 'block';

            setTimeout(() => {
                notificationContainer.style.display = 'none';
            }, 5000);
        }
    </script>

    <!-- datepicker -->
    <link rel="stylesheet" href="{{ asset('assets/custom/libs/bootstrap-daterangepicker/daterangepicker.css') }}">
    <script src="{{ asset('assets/custom/libs/bootstrap-daterangepicker/daterangepicker.js') }}"></script>

    <!-- make datepicker for create meeting popup  -->
    <script>
            $('#newMeetingPopup').on('shown.bs.modal', function () {
                var dateInput = $(this).find('.date');
                dateInput.daterangepicker({
                    "singleDatePicker": true,
                    "timePicker": true,
                    "locale": {
                        "format": 'MM/DD/YYYY H:mm'
                    },
                    "timePicker24Hour": true,
                }, function(start, end, label) {
                    // console.log('New date range selected: ' + start.format('YYYY-MM-DD') + ' to ' + end.format(
                    //     'YYYY-MM-DD') + ' (predefined range: ' + label + ')');
                });
            });
    </script>

    @if (Session::has('success'))
        <script>
            showNotification('{!! session('success') !!}', 'success');
        </script>
    @endif
    @if (Session::has('error'))
        <script>
            showNotification('{!! session('error') !!}', 'danger');
        </script>
    @endif

@yield('scripts')

</body>
</html>
