@extends('layouts.admin')

@section('page-title')
    {{ __('Job Vacancies') }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"> {{ __('Job Vacancies') }}</li>
@endsection

@section('action-button')
    <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="lg"
        data-title="{{ __('Create New Vacancy') }}" data-url="{{ route('job-vacancies.create') }}"
        data-toggle="tooltip" title="{{ __('Create New Vacancy') }}">
        <i class="ti ti-plus"></i>
    </a>
@endsection

@section('content')

    <style>
        th, td {
            text-align: center;
        }
        th {
            padding: 10px 0 !important;
            font-weight: bold;
        }
    </style>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>{{ __('ID') }}</th>
                    <th>{{ __('Title') }}</th>
                    <th>{{ __('Salary') }}</th>
                    <th>{{ __('Date of posting') }}</th>
                    <th>{{ __('Applicants Pending') }}</th>
                    <th>{{ __('Applicants Total') }}</th>
                    <th>{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vacancies as $vacancy)
                    <tr>
                        <td>{{ $vacancy->id }}</td>
                        <td>{{ $vacancy->title }}</td>
                        <td>{{ $vacancy->salary }}</td>
                        <td>{{ $vacancy->created_at->format('m/d/Y') }}</td>
                        <td>{{ $vacancy->getPendingApplicationsCount() }}</td>
                        <td>{{ $vacancy->getTotalApplicationsCount() }}</td>
                        <td>
                            <div class="btn-group">
                                <a href="{{ route('job-applications.index', ['vacancy' => $vacancy->id]) }}" class="btn btn-info btn-sm" data-toggle="tooltip" title="{{ __('View Applications') }}">
                                    <i class="ti ti-book"></i>
                                </a>
                                <a href="#" class="btn btn-primary btn-sm" data-ajax-popup="true" data-size="md"
                                    data-title="{{ __('Edit') }}" data-url="{{ route('job-vacancies.edit', $vacancy) }}">
                                    <i class="ti ti-pencil"></i>
                                </a>
                                <button class="btn btn-danger btn-sm delete-vacancy" data-vacancy-id="{{ $vacancy->id }}" data-toggle="tooltip" title="{{ __('Delete this vacancy') }}">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
    <!-- Job Vacancy creation JS -->
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            $(document).on('shown.bs.modal', function (e) {
                const modal = $(e.target);
                const form = modal.find('.create-vacancy-form');
                
                if (form.length) {
                form.off('submit').on('submit', async function(event) {
                    event.preventDefault(); 
                    
                    const formData = new FormData(this); 
                    let url = `{{ route('api.job_vacancies.store') }}`;
                    try {
                        const response = await axios.post(url, formData); 

                        $("#commonModal").modal('hide');
                        showNotification('Vacancy successfully created', 'success');
                        this.reset();
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } catch (error) {
                        $("#commonModal").modal('hide');
                        showNotification('Vacancy creation failed.', 'danger');
                        console.error(error); 
                    }
                });
                }
            });
        });
    </script>

    <!-- Update Job Vacancy JS -->
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            $(document).on('shown.bs.modal', function (e) {
                const modal = $(e.target);
                const form = modal.find('.update-vacancy-form');

                if (form.length) {
                form.off('submit').on('submit', async function(event) {
                    event.preventDefault(); 
            
                    const formData = new FormData(this); 
                    const url = this.getAttribute('data-url');

                    try {
                        const response = await axios.post(url, formData); 
                        $("#commonModal").modal('hide');
                        showNotification('Vacancy successfully updated', 'success');
                        this.reset();
                        setTimeout(function() {
                            location.reload(); 
                        }, 1000);
                    } catch (error) {
                        $("#commonModal").modal('hide');
                        showNotification('Vacancy update failed.', 'danger');
                        console.error(error); 
                    }
                });
                }
            });
        });
    </script>

    <!-- Delete Job Vacancy JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.delete-vacancy');

            deleteButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    const vacancyId = button.getAttribute('data-vacancy-id');
                    const url = `{{ route('api.job_vacancies.destroy', ['job_vacancy' => ':vacancyId']) }}`.replace(':vacancyId', vacancyId);

                    if (confirm('Are you sure you want to delete this vacancy?')) {
                        fetch(url, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json'
                            },
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Failed to delete vacancy');
                            }
                            location.reload(); 
                        })
                        .catch(error => {
                            console.error(error);
                            alert('Failed to delete vacancy');
                        });
                    }
                });
            });
        });
    </script>
@endpush