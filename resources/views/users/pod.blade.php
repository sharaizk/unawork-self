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
                                @elseif ($pod->pivot->status == 'pod_pending')
                                    <span class="badge bg-dark">{{ __('pending pod') }}</span>
                                @elseif ($pod->pivot->status == 'employee_pending')
                                    <span class="badge bg-dark">{{ __('pending employee') }}</span>
                                @else
                                    <span class="badge bg-dark">{{ __('declined') }}</span>
                                @endif
                            @endif
                        <div class="card-header-right">
                            <div class="btn-group card-option">
                                @if ($pod->pivot->status != App\Enums\PodUser\Status::DECLINED->value)
                                <button type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown"
                                    aria-haspopup="true" aria-expanded="false">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                @endif
                                <div class="dropdown-menu dropdown-menu-end">
                                    @if (isset($pod->pivot->permission) && $pod->pivot->permission == 'Owner')
                                        @if (!$pod->inner_enabled)
                                            <a href="{{ route('pods.enableInnerCircle', $pod->id) }}" class="dropdown-item">
                                                <i class="ti ti-toggle-on"></i> <span>{{ __('Enable Inner Circle') }}</span>
                                            </a>
                                        @elseif ($pod->inner_enabled)
                                            <a href="{{ route('pods.disableInnerCircle', $pod->id) }}" class="dropdown-item">
                                                <i class="ti ti-toggle-off"></i> <span>{{ __('Disable Inner Circle') }}</span>
                                            </a>
                                        @endif
                                        <a href="#" class="dropdown-item" data-ajax-popup="true" data-size="md"
                                            data-title="{{ __('Edit') }}"
                                            data-url="{{ route('pods.edit', $podId=$pod->id) }}"><i
                                                class="ti ti-pencil"></i> <span>{{ __('Edit') }}</span>
                                        </a>
                                        <a href="#" class="dropdown-item bs-pass-para "
                                            data-confirm="{{ __('Are You Sure?') }}"
                                            data-text="{{ __('This action can not be undone. Do you want to continue?') }}"
                                            data-confirm-yes="delete_pod_{{ $pod->id }}"><i
                                                class="ti ti-trash"></i><span>{{ __('Delete') }}</span>
                                        </a>
                                        <form action="{{ route('pods.delete', $pod->id) }}" method="post"
                                            id="delete_pod_{{ $pod->id }}" style="display: none;">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    @endif
                                    @if ($pod->pivot->status != App\Enums\PodUser\Status::DECLINED->value)
                                    <a href="{{ route('change-workspace', ($pod->workspace) ? $pod->workspace->id : '#') }}" class="dropdown-item" data-size="md"
                                         data-title="{{ __('Go to Pod') }}">
                                         <i class="ti ti-align-right"></i> <span>{{ __('Go to Pod') }}</span></a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <img alt="user-image" class="img-fluid rounded-circle img_users_fix_size"
                            @if ($pod->image) src="{{ asset ('storage/'. $pod->image) }}" @else avatar="{{ $pod->title }}" @endif>

                        <h4 class="mt-2">
                            @if ($pod->pivot->status != App\Enums\PodUser\Status::DECLINED->value)

                            <a href="{{ route('change-workspace', ($pod->workspace) ? $pod->workspace->id : '#') }}">
                                @endif
                                {{ $pod->title }}
                                <!-- <i class="ti ti-eye"></i> -->
                            </a>
                        </h4>
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
                        <!-- Toggle Switch for Inner Circle -->
                        @if (isset($pod->pivot->permission) && $pod->pivot->permission == 'Owner')
                            <a href="{{ $pod->inner_enabled ? route('pods.disableInnerCircle', $pod->id) : route('pods.enableInnerCircle', $pod->id) }}"
                               class="form-check form-switch d-flex align-items-center mt-2 text-decoration-none">
                                <input class="form-check-input" type="checkbox" id="toggle-inner-{{ $pod->id }}"
                                       {{ $pod->inner_enabled ? 'checked' : '' }} style="pointer-events: none;">
                                <label class="form-check-label ms-2" for="toggle-inner-{{ $pod->id }}">
                                    {{ __('Inner Circle') }}
                                </label>
                            </a>
                        @endif
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.jquery.min.js"></script>
    <!-- create pod JS -->
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            $(document).on('shown.bs.modal', function (e) {
                const modal = $(e.target);
                const form = modal.find('.create-pod-form');

                $(".chosen-select").chosen({
                    width: "100%",
                    placeholder_text_multiple: "Select Skills"
                });

                if (form.length) {
                form.off('submit').on('submit', async function(event) {
                    event.preventDefault();

                    const formData = new FormData(this);
                    try {
                        const response = await axios.post('./api/pod', formData);

                        $("#commonModal").modal('hide');
                        showNotification('Pod successfully created', 'success');
                        this.reset();
                        document.getElementById('success-message').style.display = 'block';
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } catch (error) {
                        $("#commonModal").modal('hide');
                        showNotification('Pod creation failed.', 'danger');
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

                $(".chosen-select").chosen({
                    width: "100%",
                    placeholder_text_multiple: "Select Skills"
                });

                if (form.length) {
                form.off('submit').on('submit', async function(event) {
                    event.preventDefault();

                    const formData = new FormData(this);
                    const url = this.getAttribute('data-url');
                    try {
                        const response = await axios.post(url, formData);
                        $("#commonModal").modal('hide');
                        showNotification('Pod successfully updated', 'success');
                        this.reset();
                        setTimeout(function() {
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
