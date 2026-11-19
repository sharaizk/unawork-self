@extends('layouts.admin')

@section('page-title')
    {{ __('Outer Circle Learning') }} - {{ $pod->title }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.index') }}">{{ __('Pod') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.show', $pod->id) }}">{{ $pod->title }}</a></li>
    <li class="breadcrumb-item">{{ __('Outer Circle Learning') }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-2 col-lg-3">
        @include('pod.manage.nav', ['pod' => $pod, 'podId' => $pod->id, 'workspace' => $pod->workspace])
    </div>
    <div class="col-xl-10 col-lg-9">
        {{-- Progress Overview Card --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <div>
                        <span class="badge bg-primary-light text-primary mb-2 px-3 py-1 fs-6">
                            <i class="ti ti-school me-1"></i> {{ __('Outer Circle Onboarding & Learning') }}
                        </span>
                        <h4 class="mb-1 fw-bold">{{ __('Required POD Learning Materials') }}</h4>
                        <p class="text-muted small mb-0">
                            {{ __('Review and complete the designated mandatory materials to achieve full Outer Circle completion and progress toward the Inner Circle.') }}
                        </p>
                    </div>
                    <div class="text-end">
                        <div class="display-6 fw-bold text-primary">{{ $progress['percentage'] }}%</div>
                        <small class="text-muted fw-bold">{{ __('Overall Completion') }}</small>
                    </div>
                </div>

                {{-- Progress Bar --}}
                <div class="progress mb-3" style="height: 12px; border-radius: 6px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated @if($progress['complete']) bg-success @else bg-primary @endif"
                         role="progressbar"
                         style="width: {{ $progress['percentage'] }}%;"
                         aria-valuenow="{{ $progress['percentage'] }}"
                         aria-valuemin="0"
                         aria-valuemax="100">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
                    <div class="text-muted small">
                        <span><i class="ti ti-check text-success me-1"></i> <strong>{{ $progress['completed_mandatory'] }}</strong> {{ __('of') }} <strong>{{ $progress['total_mandatory'] }}</strong> {{ __('Mandatory Materials Completed') }}</span>
                        @if ($progress['total_optional'] > 0)
                            <span class="ms-3"><i class="ti ti-info-circle text-info me-1"></i> <strong>{{ $progress['completed_optional'] }}</strong> {{ __('of') }} <strong>{{ $progress['total_optional'] }}</strong> {{ __('Optional Materials Reviewed') }}</span>
                        @endif
                    </div>
                    <div>
                        @if ($progress['next_mandatory'])
                            <a href="{{ route('pods.learning.show', [$pod, $progress['next_mandatory']]) }}" class="btn btn-primary btn-sm px-3 shadow-sm">
                                <i class="ti ti-player-play me-1"></i> {{ __('Resume Learning (:title)', ['title' => \Illuminate\Support\Str::limit($progress['next_mandatory']->title, 25)]) }}
                            </a>
                        @elseif ($progress['complete'])
                            <span class="badge bg-success px-3 py-2 fs-6">
                                <i class="ti ti-circle-check me-1"></i> {{ __('All Mandatory Materials Completed!') }}
                            </span>
                        @else
                            <a href="{{ route('pods.learning.resume', $pod) }}" class="btn btn-primary btn-sm px-3 shadow-sm">
                                <i class="ti ti-player-play me-1"></i> {{ __('Start Learning') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Mandatory Learning Materials Section --}}
        <div class="card mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="ti ti-star text-danger me-1"></i> {{ __('Mandatory Learning Materials') }}
                        <span class="badge bg-danger ms-2">{{ $progress['total_mandatory'] }}</span>
                    </h5>
                    <small class="text-muted">{{ __('Required for your Outer Circle completion percentage. Presented in business display order.') }}</small>
                </div>
            </div>
            <div class="card-body p-0">
                @php
                    $mandatoryItems = $progress['items']->filter(fn ($d) => $d->isMandatory());
                @endphp
                @if ($mandatoryItems->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>{{ __('Material') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Format') }}</th>
                                    <th>{{ __('Completion Status') }}</th>
                                    <th class="text-end pe-3">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($mandatoryItems as $index => $item)
                                    @php
                                        $pubVer = $item->publishedVersion();
                                    @endphp
                                    <tr @if($item->is_completed) class="table-light" @endif>
                                        <td class="text-muted fw-bold">{{ $loop->iteration }}</td>
                                        <td>
                                            <a href="{{ route('pods.learning.show', [$pod, $item]) }}" class="fw-bold text-dark text-decoration-none">
                                                {{ $item->title }}
                                            </a>
                                            @if ($item->description)
                                                <small class="text-muted d-block text-truncate" style="max-width: 380px;">
                                                    {{ $item->description }}
                                                </small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">{{ $item->type?->label() }}</span>
                                        </td>
                                        <td>
                                            @if ($pubVer && $pubVer->isFile())
                                                <span class="badge bg-info text-white"><i class="ti ti-file-text me-1"></i>{{ $pubVer->fileExtension() ?? 'FILE' }}</span>
                                            @elseif ($pubVer && $pubVer->isLink())
                                                <span class="badge bg-secondary"><i class="ti ti-link me-1"></i>{{ __('Link') }}</span>
                                            @else
                                                <span class="badge bg-light text-dark border">{{ __('Text') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($item->is_completed)
                                                <span class="badge bg-success-light text-success">
                                                    <i class="ti ti-check me-1"></i> {{ __('Completed') }}
                                                </span>
                                                @if ($item->completed_at)
                                                    <small class="text-muted d-block">{{ $item->completed_at->format('M d, Y') }}</small>
                                                @endif
                                            @else
                                                <span class="badge bg-warning-light text-warning">
                                                    <i class="ti ti-clock me-1"></i> {{ __('Required') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            @if ($item->is_completed)
                                                <a href="{{ route('pods.learning.show', [$pod, $item]) }}" class="btn btn-xs btn-outline-secondary">
                                                    {{ __('Review') }}
                                                </a>
                                            @else
                                                <a href="{{ route('pods.learning.show', [$pod, $item]) }}" class="btn btn-xs btn-primary">
                                                    <i class="ti ti-arrow-right me-1"></i> {{ __('Start') }}
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-4 text-center text-muted">
                        <i class="ti ti-checklist fs-2 mb-2"></i>
                        <p class="mb-0">{{ __('No mandatory materials have been assigned to this POD yet.') }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Optional Learning Materials Section --}}
        @php
            $optionalItems = $progress['items']->filter(fn ($d) => !$d->isMandatory());
        @endphp
        @if ($optionalItems->isNotEmpty())
            <div class="card">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="ti ti-book text-secondary me-1"></i> {{ __('Optional & Recommended Resources') }}
                            <span class="badge bg-secondary ms-2">{{ $progress['total_optional'] }}</span>
                        </h5>
                        <small class="text-muted">{{ __('Recommended reading to expand your knowledge. These materials do not block your Outer Circle progress.') }}</small>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>{{ __('Material') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Format') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="text-end pe-3">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($optionalItems as $item)
                                    @php
                                        $pubVer = $item->publishedVersion();
                                    @endphp
                                    <tr>
                                        <td class="text-muted fw-bold">{{ $loop->iteration }}</td>
                                        <td>
                                            <a href="{{ route('pods.learning.show', [$pod, $item]) }}" class="fw-bold text-dark text-decoration-none">
                                                {{ $item->title }}
                                            </a>
                                            @if ($item->description)
                                                <small class="text-muted d-block text-truncate" style="max-width: 380px;">
                                                    {{ $item->description }}
                                                </small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">{{ $item->type?->label() }}</span>
                                        </td>
                                        <td>
                                            @if ($pubVer && $pubVer->isFile())
                                                <span class="badge bg-info text-white">{{ $pubVer->fileExtension() ?? 'FILE' }}</span>
                                            @elseif ($pubVer && $pubVer->isLink())
                                                <span class="badge bg-secondary">{{ __('Link') }}</span>
                                            @else
                                                <span class="badge bg-light text-dark border">{{ __('Text') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($item->is_completed)
                                                <span class="badge bg-success-light text-success">
                                                    <i class="ti ti-check me-1"></i> {{ __('Reviewed') }}
                                                </span>
                                            @else
                                                <span class="badge bg-light text-secondary">{{ __('Optional') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('pods.learning.show', [$pod, $item]) }}" class="btn btn-xs btn-outline-secondary">
                                                {{ __('View') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
