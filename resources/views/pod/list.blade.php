@extends('layouts.landing')

@section('body-class', 'tpl-pods-list')

@section('content')
    <style>
        .dropdown-menu-xl {
            min-width: 40vh;
            width: 40vh;
            max-height: 380px;
            overflow-y: auto;
            border: 2px solid #022D4D;
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
            font-size: 14px;
            font-weight: 600;
            color: #022D4D;
        }

        .btn-xl {
            font-size: 1.5rem;
            height: 40px;
        }

        .form-group {
            height: 40px;
        }
        .header__search{
            display: flex;
            justify-items: center;
        }

        .dropdown {
            display: flex;
        }

        .dropdown-menu-xl .form-check-input {
            border-color: #022D4D;
            accent-color: #022D4D;
        }

        .dropdown-menu-xl .form-check-input:checked {
            background-color: #E8BE61;
        }

        .skills-button {
            background-color: #fff;
            color: #022d4d;
            height: 40px;
            font-size: 1.4rem;
        }

        .see-more-btn {
            display: none;
            background: none;
            border: none;
            color: #022D4D;
            font-weight: bold;
            cursor: pointer;
            padding: 5px 0;
        }

        @media (max-width: 768px) {
            .see-more-btn {
                display: inline;
            }

            .pods__subtitle {
                position: relative;
                overflow: hidden;
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                max-height: 4.5em; 
                transition: max-height 0.3s ease-in-out;
            }

            .pods__subtitle.expanded {
                -webkit-line-clamp: unset;
                max-height: none;
            }
        }
    </style>
    <main>
    <section class="header__search">
        <div class="container d-flex align-items-center">
            <div class="search__wrap d-flex align-items-center">
                <form id="searchForm" class="g-3 searchForm" method="GET" action="{{ route('pods.list') }}">
                    <div class="d-flex align-items-center">
                        <div class="form-group search-input-wrap">
                            <input type="text" name="name" id="pod-search" placeholder="Search by name" class="form-control search-input">
                        </div>
                        <div id="skillsField" class="form-group d-flex align-items-center me-4 ms-4">
                            <!-- <label>Select Skills</label> -->
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

                        <div class="search-btn__wrap form-group d-flex align-items-center">
{{--                            <button type="submit" class="btn btn-xl btn-primary w-100 filter-btn header-search-btn">Search</button>--}}
                        </div>
{{--                        <a id="clearFilters" href="{{ route('pods.list') }}" class="clear__filters">Clear Filters</a>--}}
                    </div>
{{--                    <button type="button" class="filter-mobile">Filter</button>--}}
                </form>
            </div>
        </div>
        <div class="filter-modal">
            <form id="searchForm" class="g-3 searchForm" method="GET" action="{{ route('pods.list') }}">
                <div class="filter-modal__header">
                    <p>Filters</p>
                    <button type="button" class="close"></button>
                </div>
                <div class="form-group">
                    <label class="title-label">Skills</label>
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
                <div class="search-btn__wrap form-group d-flex align-items-center">
                    <button type="submit" class="btn btn-xl btn-primary w-100 filter-btn header-search-btn">Search</button>
                </div>
                <a href="{{ route('pods.list') }}" class="clear__filters">Clear Filters</a>
            </form>
        </div>
    </section>

        <section class="section__titlepage">
            <div class="container">
                <h1 class="section__titlepage--headertitle"><span>Pods</span></h1>
                <!-- <ul class="breadcrumb mt-1">
                    <li class="breadcrumb-item"><a href="{{ route('landing.find-work') }}">Find Work</a></li>
                    <li class="breadcrumb-item"> {{ __('Pods') }}</li>
                </ul> -->
            </div>
        </section>
        <div class="container">

        <section class="section__layout section_pod">
                <div class=" pods__title">
                    <h2>Pods of Interest </h2>
                </div>
                <div class="pods__subtitle-wrap">
                    <p class="pods__subtitle">
                        Join the open network for skills and monetary opportunities. Pods allow you to join a group of like-minded people with the same skillset. When you join, on-demand opportunities are sent, allowing you to work on projects as companies and businesses need them.
                        </br></br>
                        Request to join as many pods as you like to receive updates. If you are invited into an inner circle, you will have access to relevant projects so you can build your wealth.
                    </p>
                    <button class="see-more-btn">See More</button>
                </div>
                <div class="">
                    <div class="row-master">

                    </div>
                    <div class="pagination-container d-flex justify-content-md-end justify-content-center mt-4">
                        <!-- {{ $pods->appends(request()->query())->links('vendor.pagination.custom') }} -->
                    </div>
                </div>
            </section>
        </div>
    </main>

@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const subtitle = document.querySelector(".pods__subtitle");
            const btn = document.querySelector(".see-more-btn");
    
            btn.addEventListener("click", function () {
                subtitle.classList.toggle("expanded");
                btn.innerText = subtitle.classList.contains("expanded") ? "See Less" : "See More";
            });

            const searchInput = document.getElementById('pod-search');
            const skillsCheckboxes = document.querySelectorAll('input[name="skill_ids[]"]');
            const container = document.querySelector('.row-master');
            const paginationContainer = document.querySelector('.pagination-container');

            function fetchPods(url) {
                const selectedSkills = Array.from(document.querySelectorAll('input[name="skill_ids[]"]:checked'))
                    .map(checkbox => `skill_ids[]=${checkbox.value}`)
                    .join('&');
                const query = searchInput.value.trim();
                const fullUrl = `${url}${query ? '&name=' + query : ''}${selectedSkills ? '&' + selectedSkills : ''}`;

                fetch(fullUrl, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        container.innerHTML = data.pods;
                        paginationContainer.innerHTML = data.pagination;
                        attachPaginationListeners();
                    })
                    .catch(error => console.error('Error:', error));
            }

            searchInput.addEventListener('input', function () {
                fetchPods(`{{ route('pods.search') }}?`);
            });

            skillsCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    fetchPods(`{{ route('pods.search') }}?`);
                });
            });

            function attachPaginationListeners() {
                document.querySelectorAll('.pagination-container a').forEach(link => {
                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        fetchPods(this.href);
                    });
                });
            }

            fetchPods(`{{ route('pods.search') }}?`);
        });
    </script>
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

        const filterBtn = document.querySelector('.filter-mobile');
        const filterModal = document.querySelector('.filter-modal');
        const closeBtn = document.querySelector('.filter-modal .close');

        if (filterBtn && filterModal && closeBtn) {
            filterBtn.addEventListener('click', function () {
                filterModal.classList.add('active');
            });

            closeBtn.addEventListener('click', function () {
                filterModal.classList.remove('active');
            });

            filterModal.addEventListener('click', function (event) {
                if (event.target === filterModal) {
                    filterModal.classList.remove('active');
                }
            });
        }
    });
</script>
@endsection
