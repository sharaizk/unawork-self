{{--
    Administrator catalog listing (Industries, Skills, Professional Roles).
    Params: $catalog (labels + field + tagged), $entries (paginator with *_count usage columns and, for
    tagged catalogs, eager-loaded industries), $search, $industries, $industryFilter.
--}}
@extends('layouts.admin')

@section('page-title')
    {{ $catalog['title'] }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item">{{ __('Catalog Management') }}</li>
    <li class="breadcrumb-item">{{ $catalog['title'] }}</li>
@endsection

@section('action-button')
    <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="md"
        data-url="{{ route($catalog['route'] . '.create') }}" data-title="{{ $catalog['add'] }}"
        title="{{ $catalog['add'] }}" aria-label="{{ $catalog['add'] }}">
        <i class="ti ti-plus"></i> <span class="d-none d-sm-inline">{{ $catalog['add'] }}</span>
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header catalog-header">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-3">
                                <span class="catalog-icon" aria-hidden="true"><i class="ti ti-{{ $catalog['icon'] }}"></i></span>
                                <div>
                                    <h5 class="mb-1">{{ $catalog['title'] }}</h5>
                                    <p class="text-muted small mb-0">{{ $catalog['description'] }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" action="{{ route($catalog['route'] . '.index') }}" role="search" class="d-flex gap-2 justify-content-md-end">
                                <label for="catalog-search" class="visually-hidden">{{ $catalog['search'] }}</label>
                                <input type="search" id="catalog-search" name="search" value="{{ $search }}" maxlength="255"
                                    class="form-control form-control-sm catalog-search" placeholder="{{ $catalog['search'] }}">
                                @if ($catalog['tagged'])
                                    <label for="catalog-industry-filter" class="visually-hidden">{{ __('Filter by industry') }}</label>
                                    <select id="catalog-industry-filter" name="industry" class="form-select form-select-sm catalog-search" data-catalog-autosubmit>
                                        <option value="">{{ __('All industries') }}</option>
                                        @foreach ($industries as $industry)
                                            <option value="{{ $industry->id }}" @selected($industryFilter === $industry->id)>{{ $industry->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <button type="submit" class="btn btn-sm btn-primary" title="{{ __('Search') }}" aria-label="{{ __('Search') }}">
                                    <i class="ti ti-search"></i>
                                </button>
                                @if ($search !== '' || $industryFilter)
                                    <a href="{{ route($catalog['route'] . '.index') }}" class="btn btn-sm btn-light" title="{{ __('Clear search') }}" aria-label="{{ __('Clear search') }}">
                                        <i class="ti ti-x"></i>
                                    </a>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body table-border-style">
                    @if ($entries->isEmpty())
                        <div class="text-center py-5">
                            <i class="ti ti-{{ $catalog['icon'] }} catalog-empty-icon" aria-hidden="true"></i>
                            <p class="mb-1 fw-semibold">{{ $catalog['empty'] }}</p>
                            @if ($search !== '' || $industryFilter)
                                <p class="text-muted small mb-0">{{ __('Try a different search term.') }}</p>
                            @else
                                <p class="text-muted small mb-0">{{ __('Use the add button to create the first entry.') }}</p>
                            @endif
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Name') }}</th>
                                        @if ($catalog['tagged'])
                                            <th scope="col">{{ __('Industries') }}</th>
                                        @endif
                                        @if ($catalog['links'])
                                            <th scope="col">{{ __('Linked to') }}</th>
                                        @endif
                                        <th scope="col" class="text-center">
                                            <span title="{{ $catalog['usage_help'] }}">{{ __('Used by') }} <i class="ti ti-info-circle" aria-hidden="true"></i></span>
                                            <span class="visually-hidden">{{ $catalog['usage_help'] }}</span>
                                        </th>
                                        <th scope="col" class="text-end">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($entries as $entry)
                                        @php
                                            $label = $entry->getAttribute($catalog['field']);
                                            $usage = $entry->usageCount();
                                        @endphp
                                        <tr>
                                            <td class="text-break">{{ $label }}</td>
                                            @if ($catalog['tagged'])
                                                <td>
                                                    @forelse ($entry->industries->take(3) as $industry)
                                                        <span class="badge bg-light-secondary text-body fw-normal">{{ $industry->name }}</span>
                                                    @empty
                                                        <span class="small text-muted">{{ __('All industries') }}</span>
                                                    @endforelse
                                                    @if ($entry->industries->count() > 3)
                                                        <span class="small text-muted" title="{{ $entry->industries->pluck('name')->implode(', ') }}">{{ __('+:count more', ['count' => $entry->industries->count() - 3]) }}</span>
                                                    @endif
                                                </td>
                                            @endif
                                            @if ($catalog['links'])
                                                <td class="small text-muted text-nowrap">
                                                    @php
                                                        $linked = collect($catalog['links'])->map(fn ($noun, $relation) => [
                                                            'count' => (int) $entry->getAttribute(\Illuminate\Support\Str::snake($relation) . '_count'),
                                                            'noun' => $noun,
                                                            'route' => $relation === 'skills' ? 'skills.index' : 'job-titles.index',
                                                        ]);
                                                    @endphp
                                                    @foreach ($linked as $link)
                                                        @if ($link['count'])
                                                            <a href="{{ route($link['route'], ['industry' => $entry->getKey()]) }}" class="text-muted">{{ $link['count'] }} {{ $link['noun'] }}</a>
                                                        @else
                                                            0 {{ $link['noun'] }}
                                                        @endif
                                                        @if (!$loop->last) · @endif
                                                    @endforeach
                                                </td>
                                            @endif
                                            <td class="text-center">
                                                <span class="badge {{ $usage ? 'bg-light-primary text-primary' : 'bg-light-secondary text-muted' }}"
                                                    title="{{ __(':count records', ['count' => $usage]) }}">{{ $usage }}</span>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <a href="#" class="action-btn btn btn-sm btn-info d-inline-flex align-items-center" data-ajax-popup="true" data-size="md"
                                                    data-url="{{ route($catalog['route'] . '.edit', [$catalog['param'] => $entry]) }}" data-title="{{ $catalog['edit'] }}"
                                                    title="{{ __('Edit :name', ['name' => $label]) }}" aria-label="{{ __('Edit :name', ['name' => $label]) }}">
                                                    <i class="ti ti-pencil"></i>
                                                </a>
                                                @if ($usage)
                                                    <span class="d-inline-block" tabindex="0" title="{{ __('In use: reassign or remove its :count records before deleting.', ['count' => $usage]) }}">
                                                        <button type="button" class="action-btn btn btn-sm btn-danger d-inline-flex align-items-center" disabled
                                                            aria-label="{{ __('Delete :name (in use)', ['name' => $label]) }}">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
                                                    </span>
                                                @else
                                                    <a href="#" class="action-btn btn btn-sm btn-danger d-inline-flex align-items-center bs-pass-para"
                                                        data-confirm="{{ __('Are You Sure?') }}"
                                                        data-text="{{ $catalog['links'] && collect($catalog['links'])->keys()->sum(fn ($r) => (int) $entry->getAttribute(\Illuminate\Support\Str::snake($r) . '_count'))
                                                            ? __('Its links to skills and roles are removed too; the skills and roles themselves are kept. This action can not be undone.')
                                                            : __('This action can not be undone. Do you want to continue?') }}"
                                                        data-confirm-yes="delete-form-{{ $catalog['route'] }}-{{ $entry->getKey() }}"
                                                        title="{{ __('Delete :name', ['name' => $label]) }}" aria-label="{{ __('Delete :name', ['name' => $label]) }}">
                                                        <i class="ti ti-trash"></i>
                                                    </a>
                                                @endif
                                                <form id="delete-form-{{ $catalog['route'] }}-{{ $entry->getKey() }}" method="POST" class="d-none"
                                                    action="{{ route($catalog['route'] . '.destroy', [$catalog['param'] => $entry]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                            <span class="small text-muted">
                                {{ __('Showing :from to :to of :total entries', ['from' => $entries->firstItem(), 'to' => $entries->lastItem(), 'total' => $entries->total()]) }}
                            </span>
                            {{ $entries->links('pagination::bootstrap-5') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('change', function (event) {
            if (event.target.matches('[data-catalog-autosubmit]')) {
                event.target.form.submit();
            }
        });
    </script>
@endpush

@push('css-page')
    <style>
        .catalog-header h5::after { display: none; } /* theme accent bar would overlap the icon */
        .catalog-icon { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: 1.35rem;
            color: var(--bs-primary); background-color: rgba(var(--bs-primary-rgb, 81, 69, 157), 0.12); flex: 0 0 auto; }
        .catalog-search { max-width: 280px; }
        .catalog-empty-icon { font-size: 2.5rem; opacity: 0.35; }
    </style>
@endpush
