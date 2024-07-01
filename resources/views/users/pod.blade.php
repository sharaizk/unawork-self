@extends('layouts.admin')

@section('page-title')
    {{ __('POD') }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"> {{ __('POD') }}</li>
@endsection

@section('action-button')
    <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="lg"
        data-title="{{ __('Create new Pod') }}" data-url="{{ route('pods.create') }}"
        data-toggle="tooltip" title="{{ __('Create new Pod') }}">
        <i class="ti ti-plus"></i>
    </a>
@endsection

@section('content')
    <div class="row">
        @foreach ($pods as $pod)
            <div class="col-xl-3 col-lg-4 col-sm-6">
                <div class="card   text-center">
                    <div class="card-header border-0 pb-0">
                            @if (isset($pod->pivot->permission))
                                @if ($pod->pivot->permission == 'Owner')
                                    <span
                                        class="badge bg-primary">{{ __($pod->pivot->permission) }}</span>
                                @elseif ($pod->pivot->status == 'active')
                                    <span class="badge bg-dark">{{ __('Member') }}</span>
                                @elseif ($user->pivot->status == 'pod_pending')
                                    <span class="badge bg-dark">{{ __('pending pod') }}</span>
                                @elseif ($user->pivot->status == 'employee_pending')
                                    <span class="badge bg-dark">{{ __('pending employee') }}</span>
                                @else
                                    <span class="badge bg-dark">{{ __('declined') }}</span>
                                @endif
                            @endif
                        <div class="card-header-right">
                            <div class="btn-group card-option">
                                <button type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown"
                                    aria-haspopup="true" aria-expanded="false">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @if (isset($pod->pivot->permission) && $pod->pivot->permission == 'Owner')
                                        <a href="#" class="dropdown-item" data-ajax-popup="true" data-size="md" 
                                            data-title="{{ __('Edit') }}"
                                            data-url="{{ route('pods.edit', $podId=$pod->id) }}"><i 
                                                class="ti ti-pencil"></i> <span>{{ __('Edit') }}</span>
                                        </a>
                                    @endif
                                    <a href="{{ route('pods.show', $podId=$pod->id) }}" class="dropdown-item" data-size="md"
                                         data-title="{{ __('Reset Password') }}"><i 
                                            class="ti ti-pencil"></i> <span>{{ __('Go to Pod') }}</span></a> 
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <img alt="user-image" class="img-fluid rounded-circle img_users_fix_size"
                            @if ($pod->image) src="{{ asset ('storage/'. $pod->image) }}" @else avatar="{{ $pod->title }}" @endif>
                        <h4 class="mt-2">{{ $pod->title }}</h4>
                        <span>{{ $pod->description }}</span>
                        <br>
                        <small>
                            @if (count($pod->skills) >= 1) 
                                Skills: 
                            @endif
                            @foreach($pod->skills as $skill)
                               {{ $skill->name }} @if(!$loop->last), @endif
                            @endforeach
                        </small>
                    </div>
                </div>
            </div>
        @endforeach
        <div class="col-xl-3 col-lg-4 col-sm-6">
            <a href="#" class="btn-addnew-project" data-ajax-popup="true" data-size="lg"
                data-title="{{ __('Create new Pod') }}" data-url="{{ route('pods.create') }}"
                data-toggle="tooltip" title="{{ __('Create new Pod') }}">
                <div class="bg-primary proj-add-icon">
                    <i class="ti ti-plus"></i>
                </div>
                <h6 class="mt-4 mb-2">{{__('Create New Pod')}}</h6>
                <p class="text-muted text-center">{{__('Click here to create New Pod')}}</p>
            </a>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <!-- create pod JS -->
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            $(document).on('shown.bs.modal', function (e) {
                const modal = $(e.target);
                const form = modal.find('.create-pod-form');

                if (form.length) {
                form.off('submit').on('submit', async function(event) {
                    event.preventDefault(); 
            
                    const formData = new FormData(this); 
                    try {
                        const response = await axios.post('./api/pod', formData); 
                        console.log(response.data); 

                        this.reset();
                        document.getElementById('success-message').style.display = 'block';
                        setTimeout(function() {
                            // document.getElementById('success-message').style.display = 'none';
                            location.reload(); // Перезавантаження сторінки після 5 секунд
                        }, 3000);
                    } catch (error) {
                        console.error(error); 
                    }
                });
                }
            });
        });
    </script>
    <!-- Update pod JS -->
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            $(document).on('shown.bs.modal', function (e) {
                const modal = $(e.target);
                const form = modal.find('.update-pod-form');

                if (form.length) {
                form.off('submit').on('submit', async function(event) {
                    event.preventDefault(); 
            
                    const formData = new FormData(this); 
                    const url = this.getAttribute('data-url');
                    try {
                        const response = await axios.post(url, formData); 
                        console.log(response.data); 

                        this.reset();
                        document.getElementById('success-message').style.display = 'block';
                        setTimeout(function() {
                            // document.getElementById('success-message').style.display = 'none';
                            location.reload(); 
                        }, 3000);
                    } catch (error) {
                        console.error(error); 
                    }
                });
                }
            });
        });
    </script>
@endpush
