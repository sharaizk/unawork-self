<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="./images/header/favicon.png">
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


<div class="container mt-3">
    <div id="notification-container" class="alert" style="display: none; color:#000;"></div>
</div>
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
    @auth('web')
    <!-- create meeting popup -->
    <div class="modal" tabindex="-1" role="dialog" id="newMeetingPopup">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Meeting</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createMeetingForm" action="{{ route('zoom-meeting.store', \Auth::user()->currentWorkspace->slug) }}" method="POST">
                @csrf
                <div class="modal-body">
                <input type="hidden" name="members[0]" id="memberIdInput" value="">
                <input type="hidden" name="members[1]" value="{{ \Auth::id() }}">
                <div class="mb-3">
                    <label for="title" class="form-label">Title</label>
                    <input type="text" name="title" id="title" class="form-control" placeholder="Title" required>
                </div>
                <div class="mb-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    {{ Form::text('start_date', null, ['class' => 'form-control date', 'placeholder' => __('Select Date/Time'), 'required' => 'required']) }}
                </div>
                <div class="mb-3">
                    <label for="duration" class="form-label">Duration (minutes)</label>
                    <input type="number" name="duration" id="duration" class="form-control" required>
                </div>
                </div>
                <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Create Meeting</button>
                </div>
            </form>
            </div>
        </div>
    </div>
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