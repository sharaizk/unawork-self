@extends('layouts.landing')

@section('body-class', 'tpl-pods-list')

@section('content')
    <style>
        .dropdown-menu-xl {
            min-width: 40vh;
            width: 40vh;
            max-height: 400px;
            overflow-y: auto;
        }

        .dropdown-menu-xl li {
            padding: 10px 20px;
        }

        .dropdown-menu-xl .form-check-input {
            width: 15px;
            height: 15px;
            margin-right: 6px;
        }

        .dropdown-menu-xl .form-check-label {
            font-size: 12px;
        }

        .btn-xl {
            font-size: 1.5rem;
            height: 40px;
        }
        .searchForm {
            display: flex;
        }

        .form-group {
            height: 40px;
        }
        .header__search{
            display: flex;
            justify-items: center;
        }

        .skills-button {
            background-color: #fff;
            color: #022d4d;
            height: 40px;
            font-size: 1.4rem;
        }
    </style>
    <main>
    <section class="header__search">
        <div class="container-master d-flex align-items-center">
            <div class="search__wrap d-flex align-items-center">
                <form id="searchForm" class="row g-3 searchForm" method="GET" action="{{ route('pods.list') }}">
                    <div id="skillsField" class="col-md-8 form-group d-flex align-items-center">
                        <label>Select Skills</label>
                        <div class="dropdown">
                            <button class="btn btn-xl btn-secondary dropdown-toggle w-100 skills-button" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="true">
                                {{ count(request('skill_ids', [])) > 0 ? 'Selected Skills' : 'Select Skills' }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-xl w-100 skills-dropdown-menu">
                                @foreach($skills as $skill)
                                    <li class="px-3">
                                        <input class="form-check-input" type="checkbox" name="skill_ids[]" id="skill_{{ $skill->id }}" value="{{ $skill->id }}" {{ in_array($skill->id, request('skill_ids', [])) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="skill_{{ $skill->id }}">
                                            {{ $skill->name }}
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <div class="col-md-4 form-group d-flex align-items-center">
                        <button type="submit" class="btn btn-xl btn-primary w-100 filter-btn header-search-btn">Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

        <section class="section__titlepage">
            <div class="container-master">
                <h1 class="section__titlepage--headertitle"><span>Pods</span></h1>
                <ul class="breadcrumb mt-1">
                    <li class="breadcrumb-item"><a href="{{ route('landing.find-work') }}">Find Work</a></li>
                    <li class="breadcrumb-item"> {{ __('Pods') }}</li>
                </ul>
            </div>
        </section>
        <div class="container-master">

        <section class="section__layout section_pod">
                <div class="container-master pods__title">
                    <h2>Pods of Interest </h2>
                </div>
                <div class="container-master">
                    <div class="row-master">
                        @foreach($pods as $pod)
                            @php
                                $joinable = ($user && !in_array($pod->id, $joinedPods)) ? 1 : 0;
                            @endphp
                            @include('pod.landing.pod_card', ['pod' => $pod, 'joinable' => $joinable])
                        @endforeach
                    </div>
                    <div class="pagination-container d-flex justify-content-center mt-4">
                        {{ $pods->appends(request()->query())->links() }}
                    </div>
                </div>
            </section>
        </div>
    </main>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('.join-form');

        forms.forEach(form => {
            form.addEventListener('submit', function(event) {
                event.preventDefault();

                const podId = this.getAttribute('data-pod-id');

                fetch(`/api/pods/${podId}/requests`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showNotification('Request sended successfully:', 'success');
                        form.style.display = 'none';
                    } else {
                        showNotification('Request sending error:', 'danger');
                    }
                })
                .catch(error => {
                        showNotification('Request sending error:', 'danger');
                });
            });
        });
    });
</script>
@endsection
