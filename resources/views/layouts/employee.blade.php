<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Laravel') }} - Employee Dashboard</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="base-url" content="{{ URL::to('/') }}">
    
    <link rel="stylesheet" href="{{ asset('assets/custom/css/custom.css') }}">

    <link rel="stylesheet" href="{{ asset('assets/css/plugins/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/flatpickr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/main.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/datepicker-bs5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/bootstrap-switch-button.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/customizer.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/dragula.min.css') }}">
    {{-- <link rel="stylesheet" href="{{asset('assets/css/landing.css')}}" /> --}}
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/animate.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/custom/libs/summernote/summernote-bs4.css') }}">


    <link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/material.css') }}">
    
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" id="main-style-link">

    <script src='https://unpkg.com/nprogress@0.2.0/nprogress.js'></script>


    <link rel="stylesheet" href="{{ asset('css/custom-color.css') }}">

</head>
    <style type="text/css">
        [dir="rtl"] .dash-sidebar {
            left: auto !important;
        }

        @media (min-width: 1024px) {
            [dir="rtl"] .dash-header {
                left: 0;
                right: 280px;
            }
        }

        [dir="rtl"] .dash-header:not(.transprent-bg) .header-wrapper {
            padding: 0 0 0 30px;
        }

        [dir="rtl"] .dash-header:not(.transprent-bg):not(.dash-mob-header)~.dash-container {
            margin-left: 0px;
        }

        [dir="rtl"] .me-auto.dash-mob-drp {
            margin-right: 10px !important;
        }

        [dir="rtl"] .me-auto {
            margin-left: 10px !important;
        }

        [dir="rtl"] .header-wrapper .ms-auto {
            margin-left: 0 !important;
        }

        @media (min-width: 1024px) {
            [dir="rtl"] .dash-header {
                left: 0 !important;
                right: 280px !important;
            }
        }

        [dir="rtl"] #calender_type {
            float: left !important;
        }
    </style>
    <style type="text/css">
        .list-group-item.active {
            border-color: #ffff !important;
        }
    </style>
<body>
    <div id="app">

        <main class="py-4">
            @yield('content')
        </main>
    </div>
    
    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
