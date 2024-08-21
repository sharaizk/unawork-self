@extends('layouts.admin')

@section('page-title')
    {{ __('Job Applications') }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"> <a href="{{ route('job-vacancies.index') }}">{{ __('Job Vacancies') }}</a></li>
    <li class="breadcrumb-item"> {{ $vacancy->title }}</li>
    <li class="breadcrumb-item"> {{ __('Job Applications') }}</li>
@endsection

@section('action-button')
@endsection

@section('content')
    <style>
        .text-truncate {
            max-width: 8rem; /* Визначте бажану ширину */
        }
        .in-text-button {
            padding: 0.1rem 0.3rem;
            font-size: 0.8rem;
            line-height: 1.2;
        }
    </style>
    @php
        $adminSetting = App\Models\Utility::getAdminPaymentSetting();
    @endphp
    <div class="container">
            @if($applications->isEmpty())
                <h3 class="text-center m-5">{{ __('For this vacancy, you have not applications yet') }}</h3>
            @else
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 10%;">{{ __('Created') }}</th>
                            <th style="width: 15%;">{{ __('Applicant Name') }}</th>
                            <th style="width: 5%;">{{ __('Status') }}</th>
                            <th style="width: 60%;">{{ __('Description') }}</th>
                            <th style="width: 10%;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($applications as $application)
                            <tr>
                                <td>{{ $application->created_at }}</td>
                                <td>
                                    <a href="{{ route('employee.show', ['employee' => $application->user_id]) }}">
                                        {{ $application->user->name }}
                                    </a>
                                </td>
                                <td>
                                    @if ($application->status == 'pending')
                                        <span class="badge bg-primary">{{ __('Pending') }}</span>
                                    @elseif ($application->status == 'accepted')
                                        <span class="badge bg-success">{{ __('Accepted') }}</span>
                                    @elseif ($application->status == 'rejected')
                                        <span class="badge bg-danger">{{ __('Rejected') }}</span>
                                    @endif
                                </td>
                                <td class="text-truncate">
                                    <div class="d-flex align-items-center">
                                        <p class="description-preview m-0">
                                            @if(strlen($application->description) > 70)
                                                <a href="#" 
                                                class="btn btn-primary btn-sm in-text-button" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#descriptionModal" 
                                                data-description="{{ $application->description }}"
                                                data-name="{{ $application->user->name }}"
                                                data-profile-url="{{ route('employee.show', ['employee' => $application->user_id]) }}">
                                                    <i class="ti ti-eye"></i>{{ __('') }}
                                                </a>
                                            @endif
                                            {{ \Illuminate\Support\Str::limit($application->description, 70, '...') }}
                                        </p>
                                    </div>
                                </td>
                                <td>
                                    @if ($application->status == 'pending')
                                        <a href="#" class="btn btn-success btn-sm accept-application" data-url="{{ route('api.job-applications.accept', ['jobApplication' => $application->id]) }}"><i class="ti ti-check"></i></a>
                                        <a href="#" class="btn btn-danger btn-sm reject-application" data-url="{{ route('api.job-applications.reject', ['jobApplication' => $application->id]) }}"><i class="ti ti-x"></i></a>
                                    @endif

                                    @if ($adminSetting['enable_chat'] == 'on')
                                        <a href="#" class="btn btn-primary btn-sm sendMessageUser" data-employee-id="{{ $application->user_id }}">
                                            <i class="ti ti-mail"></i>{{ __('') }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
    </div>

    <!-- Modal for full description -->
    <div class="modal fade" id="descriptionModal" tabindex="-1" aria-labelledby="descriptionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="descriptionModalLabel">{{ __('Full Description') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>{{ __('Name:') }}</strong> <span id="applicantName"></span></p>
                    <strong>{{ __('Description:') }}</strong><p id="fullDescriptionText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    <a href="#" class="btn btn-primary" id="viewProfileButton">{{ __('View Profile') }}</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Message popUp -->
    <div class="modal" tabindex="-1" role="dialog" id="sendMessagePopup">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="messageInput" class="form-control" placeholder="Type your message...">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="sendMessage" data-employee-id="">Send</button>
            </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <!-- show all description JS -->
    <script>
        $(document).ready(function() {
            $('#descriptionModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget); // Button that triggered the modal
                var description = button.data('description'); // Extract info from data-* attributes
                var name = button.data('name'); // user name
                var profileUrl = button.data('profile-url'); // profile limk

                var modal = $(this);
                modal.find('.modal-body #fullDescriptionText').text(description);
                modal.find('.modal-body #applicantName').text(name); // set user name
                modal.find('.modal-footer #viewProfileButton').attr('href', profileUrl); // set user link
            });
        });
    </script>

    <!-- accept/reject application JS -->
    <script>
        $(document).ready(function() {
            $('.accept-application').on('click', function(e) {
                e.preventDefault();
                var url = $(this).data('url');
                $.ajax({
                    url: url,
                    type: 'PATCH',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        location.reload(); // Reload the page to show the updated status
                    },
                    error: function(xhr) {
                        alert('An error occurred while processing your request.');
                    }
                });
            });

            $('.reject-application').on('click', function(e) {
                e.preventDefault();
                var url = $(this).data('url');
                $.ajax({
                    url: url,
                    type: 'PATCH',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        location.reload(); // Reload the page to show the updated status
                    },
                    error: function(xhr) {
                        alert('An error occurred while processing your request.');
                    }
                });
            });
        });
    </script>

    <!-- send message js -->
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".sendMessageUser").forEach(a => {
                a.addEventListener("click", function() {
                    let employeeId = this.dataset.employeeId;
                    console.log(employeeId);

                    $("#sendMessagePopup").modal('show');
                    document.getElementById("sendMessage").setAttribute('data-employee-id', employeeId);
                });
            });

            document.getElementById("sendMessage").addEventListener("click", function() {
                let message = document.getElementById("messageInput").value;
                let employeeId = this.getAttribute('data-employee-id');
                console.log(employeeId);
                let data = {
                    recipient_id: employeeId,
                    type: 'user',
                    message: message,
                    temporaryMsgId: 'temp_1',
                    _token: '{{ csrf_token() }}'
                };
                let url = `{{ route('api.send-message') }}`;

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data),
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(error => {
                            throw new Error(error.message || 'Something went wrong');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Success:', data);
                    showNotification('Message successfully sent', 'success');
                    $("#sendMessagePopup").modal('hide');
                })
                .catch(error => {
                    showNotification('Message sending failed: ' + error.message, 'danger');
                    console.error('Error:', error);
                });
            });
        });
    </script>
@endpush