@extends('layouts.admin')

@section('page-title')
    {{ __('POD') }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"> {{ __('POD') }}</li>
@endsection

@php
    // UNA-010: a company publishes PODs only with a complete company profile (null = not a company).
    $canPublishPods = !$businessProfile || $businessProfile['complete'];
@endphp

@section('action-button')
    @if ($canPublishPods)
        <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="lg"
            data-title="{{ __('Create new Pod') }}" data-url="{{ route('pods.create') }}"
            data-bs-toggle="tooltip" title="{{ __('Create new Pod') }}" aria-label="{{ __('Create new Pod') }}">
            <i class="ti ti-plus"></i>
        </a>
    @else
        <a href="{{ route('company.onboarding.edit') }}" class="btn btn-sm btn-primary">
            <i class="ti ti-building"></i> {{ __('Complete Company Profile') }}
        </a>
    @endif
@endsection

@section('content')
    @if ($businessProfile)
        <div class="card mb-4" id="pod-business-profile">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <div class="flex-grow-1">
                    <p class="fw-semibold mb-1">{{ __('Company profile: :percentage% complete', ['percentage' => $businessProfile['percentage']]) }}</p>
                    <div class="progress mb-2" style="height: 8px;" role="progressbar" aria-label="{{ __('Company profile completion') }}"
                        aria-valuenow="{{ $businessProfile['percentage'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: {{ (int) $businessProfile['percentage'] }}%"></div>
                    </div>
                    @if ($businessProfile['complete'])
                        <p class="small text-success mb-0">{{ __('Your company profile is complete. You can publish PODs.') }}</p>
                    @else
                        <p class="small text-muted mb-1">{{ __(':required% is required to publish a POD. Still missing:', ['required' => $businessProfile['required']]) }}</p>
                        <ul class="small text-muted mb-0">
                            @foreach ($businessProfile['missing'] as $missing)
                                <li>{{ $missing }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @unless ($businessProfile['complete'])
                    <a href="{{ route('company.onboarding.edit') }}" class="btn btn-primary"><i class="ti ti-building"></i> {{ __('Complete Company Profile') }}</a>
                @endunless
            </div>
        </div>
    @endif
    <div class="row">
        @foreach ($pods as $pod)
            <div class="col-xl-3 col-lg-4 col-sm-6">
                <div class="card   text-center">
                    <div class="card-header border-0 pb-0">
                            @php
                                $podState = \App\Enums\PodUser\State::from($pod->pivot->state);
                                // Rejected/suspended memberships get no actions (no workspace access).
                                $podActionable = !in_array($podState, [\App\Enums\PodUser\State::REJECTED, \App\Enums\PodUser\State::SUSPENDED], true);
                            @endphp
                            @if (isset($pod->pivot->permission))
                                @if ($pod->pivot->permission == 'Owner')
                                    <span
                                        class="badge bg-primary">{{ __($pod->pivot->permission) }}</span>
                                @else
                                    <span class="badge bg-dark">{{ $podState->label() }}</span>
                                @endif
                            @endif
                        <div class="card-header-right">
                            <div class="btn-group card-option">
                                @if ($podActionable)
                                <button type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown"
                                    aria-haspopup="true" aria-expanded="false">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                @endif
                                <div class="dropdown-menu dropdown-menu-end">
                                    @if (isset($pod->pivot->permission) && $pod->pivot->permission == 'Owner')
                                        @can('toggleInnerCircle', $pod)
                                        @if (!$pod->inner_enabled)
                                            <a href="{{ route('pods.enableInnerCircle', $pod->id) }}" class="dropdown-item">
                                                <i class="ti ti-toggle-on"></i> <span>{{ __('Enable Inner Circle') }}</span>
                                            </a>
                                        @elseif ($pod->inner_enabled)
                                            <a href="{{ route('pods.disableInnerCircle', $pod->id) }}" class="dropdown-item">
                                                <i class="ti ti-toggle-off"></i> <span>{{ __('Disable Inner Circle') }}</span>
                                            </a>
                                        @endif
                                        @endcan
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
                                    @if ($podActionable)
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
                            @if ($podActionable)

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
                        @can('toggleInnerCircle', $pod)
                            <a href="{{ $pod->inner_enabled ? route('pods.disableInnerCircle', $pod->id) : route('pods.enableInnerCircle', $pod->id) }}"
                               class="form-check form-switch d-flex align-items-center mt-2 text-decoration-none">
                                <input class="form-check-input" type="checkbox" id="toggle-inner-{{ $pod->id }}"
                                       {{ $pod->inner_enabled ? 'checked' : '' }} style="pointer-events: none;">
                                <label class="form-check-label ms-2" for="toggle-inner-{{ $pod->id }}">
                                    {{ __('Inner Circle') }}
                                </label>
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
        @endforeach
        <div class="col-xl-3 col-lg-4 col-sm-6">
            @if ($canPublishPods)
                <a href="#" class="btn-addnew-project" data-ajax-popup="true" data-size="lg"
                    data-title="{{ __('Create new Pod') }}" data-url="{{ route('pods.create') }}"
                    data-bs-toggle="tooltip" title="{{ __('Create new Pod') }}">
                    <div class="bg-primary proj-add-icon">
                        <i class="ti ti-plus"></i>
                    </div>
                    <h6 class="mt-4 mb-2">{{__('Create New Pod')}}</h6>
                    <p class="text-muted text-center">{{__('Click here to create New Pod')}}</p>
                </a>
            @else
                <a href="{{ route('company.onboarding.edit') }}" class="btn-addnew-project">
                    <div class="bg-primary proj-add-icon">
                        <i class="ti ti-lock"></i>
                    </div>
                    <h6 class="mt-4 mb-2">{{ __('Complete Company Profile') }}</h6>
                    <p class="text-muted text-center">{{ __('A complete company profile is required to publish a POD.') }}</p>
                </a>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    {{-- axios, jQuery and Chosen come from layouts.admin; no duplicate CDN copies here. --}}
    <div class="d-none" id="pods-page-config"
        data-store-url="{{ url('api/pod') }}"
        data-i18n="{{ json_encode([
            'created' => __('Pod successfully created'),
            'updated' => __('Pod successfully updated'),
            'failed' => __('The POD could not be saved. Please try again.'),
            'fixErrors' => __('Please correct the highlighted fields.'),
            'completeProfile' => __('Complete Company Profile'),
            'selectSkills' => __('Select Skills'),
        ]) }}"></div>
    <script src="{{ asset('assets/custom/js/pods.js') }}"></script>
@endpush
