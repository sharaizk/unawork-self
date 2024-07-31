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
    <div class="row">
        @foreach ($vacancies as $vacancy)
            <div class="col-xl-3 col-lg-4 col-sm-6">
                    <div class="card   text-center">
                        <div class="card-header border-0 pb-0">
                            <span class="badge bg-primary"></span>
                            <h4 class="mt-2">   
                                <a href="#">
                                    {{$vacancy->title}}
                                    <!-- <i class="ti ti-eye"></i> -->
                                </a>    
                            </h4>
                            <div class="card-header-right">
                                <div class="btn-group card-option">
                                    <button type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        <i class="feather icon-more-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a href="{{ route('job-applications.index', ['vacancy' => $vacancy->id]) }}" class="dropdown-item">
                                            <i class="ti ti-book"></i> <span>{{ __('View Applications') }}</span>
                                        </a>
                                        <a href="#" class="dropdown-item" data-ajax-popup="true" data-size="md" 
                                            data-title="{{ __('Edit') }}"
                                            data-url="{{ route('job-vacancies.edit', $vacancy=$vacancy) }}"><i 
                                                class="ti ti-pencil"></i> <span>{{ __('Edit') }}</span>
                                        </a>
                                        <a href="#" class="dropdown-item delete-vacancy"
                                            data-vacancy-id="{{ $vacancy->id }}">
                                            <i class="ti ti-trash"></i> 
                                            <span>{{ __('Delete this vacancy') }}</span>
                                        </a>
                                        <!-- <a href="#" class="dropdown-item" data-size="md"
                                            data-title="{{ __('Go to Pod') }}">
                                            <i class="ti ti-align-right"></i> <span>{{ __('Item 1') }}</span></a>  -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            
                            <span>Salary : {{ $vacancy->salary }}</span>
                            <br>
                            <small>
                                    {{ $vacancy->description }}
                            </small>
                        </div>
                    </div>
                </div>
        @endforeach
    </div>
@endsection

@push('scripts')
    <!-- Job Vacancy creation JS (send request to api) -->
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