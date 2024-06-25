<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="./images/header/favicon.png">
    <title>Hire</title>

    <!-- CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/landing/main.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/landing/hire-page.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/landing/find-work.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/material.css') }}">

</head>

<body class="@yield('body-class')">

<main>

@yield('title')

@include('partials.landing.topnav')

@yield('content')
</main>


<div class="modal fade" id="commonModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
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
        <div class="modal-dialog" role="document">
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



@yield('scripts')

</body>
</html>