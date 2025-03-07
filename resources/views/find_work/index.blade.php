@extends('layouts.landing')

@section('body-class', 'tpl-find-work')
@section('title', 'Find Work')

@section('content')
<style>
    .dropdown-menu-xl {
        min-width: 29vh;
        width: 29vh;
        max-height: 380px;
        overflow-y: auto;
        border: 2px solid #022D4D;
    }

    .dropdown-menu-xl li {
        padding: 10px 20px;
    }

    .dropdown-menu-xl .form-check-input {
        width: 15px;
        height: 15px!important;
        margin-right: 6px;
    }

    .dropdown-menu-xl .form-check-label {
        font-size: 14px;
        font-weight: 600;
        color: #022D4D;
    }

    .dropdown-menu-xl .form-check-input {
        border-color: #022D4D;
        accent-color: #022D4D;
    }

    .dropdown-menu-xl .form-check-input:checked {
        background-color: #E8BE61; 
    }

    .dropdown {
        display: flex;
    }

    .btn-xl {
        height: 40px;
        padding: 10px 5px;
        font-size: 1.4rem;
    }
    .filter-modal .dropdown {
        margin: 0;
    }
</style>
<main>
    <section class="header__search">
        <div class="container">
            <div class="search__wrap">
                <form id="searchForm" class=" g-3 searchForm" method="GET">
                <div class="hidden-mobile left-wrapper">
                    <div class="form-group select-wrapper">
                        <!-- <label for="type">Type</label> -->
                        <select id="type" class="form-control" name="type">
                            <option value="" selected>Type</option>
                            <option value="pod">Pod</option>
                            <option value="company">Company</option>
                            <!-- <option value="vacancy">Vacancy</option> -->
                            <!-- <option value="project">Project</option> -->
                        </select>
                        <i class="ti ti-chevron-down"></i>
                    </div>

                    <div id="budgetField" class="col-md-4 form-group select-wrapper" style="display: none;">
                        <!-- <label for="budget">Budget</label> -->
                        <select id="budget" class="form-control" name="budget">
                            <option value="" selected>Select Budget</option>
                                <option value="[0,500]">$0 - $500</option>
                                <option value="[500,1000]">$500 - $1000</option>
                                <option value="[1000,5000]">$1000 - $5000</option>
                                <option value="[5000,999999999]">$5000+</option>
                        </select>
                        <i class="ti ti-chevron-down"></i>
                    </div>

                    @php
                        $industries = \App\Models\Industry::all();
                        $currentIndustryId = $currentWorkspace->industry_id ?? null;
                    @endphp
                    <div id="industryField" class="form-group select-wrapper" style="display: none;">
                        <!-- <label for="industry">Industry</label> -->
                        <select id="industry" class="form-control" name="industry">
                            <option value="">Select Industry </option>
                            @foreach($industries as $industry)
                                <option value="{{ $industry->id }}">
                                        {{ $industry->name }}
                                </option>
                            @endforeach
                        </select>
                        <i class="ti ti-chevron-down"></i>
                    </div>

                    <div id="skillsField" class="form-group form-skill">
                        <div class="dropdown">
                            <button class="form-control" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="true">
                                Skills
                                <i class="ti ti-chevron-down"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-xl">
                                @foreach($skills as $skill)
                                    <li>
                                        <input class="form-check-input" type="checkbox" name="skill_ids[]" id="skill_{{ $skill->id }}" value="{{ $skill->id }}">
                                        <label class="form-check-label" for="skill_{{ $skill->id }}">
                                            {{ $skill->name }}
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <div class="form-group search-btn__wrap d-flex align-items-end">
                        <button type="submit" class="btn btn-xl btn-primary w-100 header-search-btn">Search</button>
                    </div>
                </div>
                <button type="button" class="filter-mobile">Filters</button>
                </form>
            </div>
        </div>
        <div class="filter-modal">
            <form id="searchFormMob" class="g-3 searchForm" method="GET">
                <div class="filter-modal__header">
                    <p>Filters</p>
                    <button type="button" class="close"></button>
                </div>
                <div class="form-group">
                    <label class="title-label" for="type">Type</label>
                    <select id="typeMobile" class="form-control" name="type">
                        <option value="" selected>Type</option>
                        <option value="pod">Pod</option>
                        <option value="company">Company</option>
                        <!-- <option value="vacancy">Vacancy</option> -->
                        <!-- <option value="project">Project</option> -->
                    </select>
                </div>
                @php
                    $industries = \App\Models\Industry::all();
                    $currentIndustryId = $currentWorkspace->industry_id ?? null;
                @endphp
                <div id="industryFieldMobile" class="form-group" style="display: none;">
                    <label for="industry" class="title-label">Industry</label>
                    <select id="industry" class="form-control" name="industry">
                        <option value="">Select Industry </option>
                        @foreach($industries as $industry)
                            <option value="{{ $industry->id }}">
                                    {{ $industry->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div id="skillsFieldMobile" class="form-group">
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
                <a href="{{ route('landing.find-work') }}" class="clear__filters">Clear Filters</a>
            </form>
        </div>
    </section>


    <section class="section__titlepage">
        <div class="container">
            <h1 class="section__titlepage--headertitle"><span>Find Work</span></h1>
            <p class="pods__subtitle">The best opportunities for work at your fingertips. Whether you are looking for a side hustle, second income, or new career growth, UnaWork offers layers of opportunities to hire and get hired.</p>
        </div>
    </section>

    <div class="container">
        <section class="section__layout section_company">
            <div class="container pods__title">
                <h2>Company’s Hiring</h2>
                <a href="{{ route('job-vacancies.landing.list') }}" class="buttonViewAll">View All <i class="fa fa-chevron-right"></i></a>
            </div>
            <div class="container">
                <div class="row-master d-flex align-items-stretch">
                    @foreach($usersWithVacancies as $userWithVacancies)
                        @include('company.landing.company_card', ['company' => $userWithVacancies->ownWorkspace()])
                    @endforeach
                </div>
            </div>
            <a href="{{ route('job-vacancies.landing.list') }}" class="buttonViewAll-mobile">
                View All <i class="fa fa-chevron-right"></i>
            </a>
        </section>

        <!-- <section class="section__layout section__project">
            <div class="container-master teams__title">
                <h2>Projects Availables</h2>
                <a href="{{ route('project.list') }}" class="buttonViewAll">View All <i class="fa fa-chevron-right"></i></a>
            </div>
            <div class="container-master">
                <div class="row-master">
                        @foreach($projects as $project)
                            @include('find_work.project.project_card', ['project' => $project])
                        @endforeach
                </div>
            </div>
        </section> -->

        <section class="section__layout section_pod">
            <div class="container pods__title">
                <h2>PODs of Interest </h2>
                <a href="{{ route('pods.list') }}" class="buttonViewAll">View All <i class="fa fa-chevron-right"></i></a>
            </div>
            <div class="container">
                <div class="row-master  d-flex align-items-stretch">
                    @foreach($pods as $pod)
                            @php
                                $joinable = ($user && !in_array($pod->id, $joinedPods)) ? 1 : 0;
                            @endphp
                        @include('pod.landing.pod_card', ['pod' => $pod, 'joinable' => $joinable])
                    @endforeach
                </div>
            </div>
            <a href="{{ route('pods.list') }}" class="buttonViewAll-mobile">
                View All <i class="fa fa-chevron-right"></i>
            </a>
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
                        showNotification('Request sended successfully', 'success');
                        form.style.display = 'none';
                    } else {
                        showNotification('Request sending error:', 'danger');
                    }
                })
                .catch(error => {
                        showNotification('Request sending error', 'danger');
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
<script>
    document.getElementById('typeMobile').addEventListener('change', function () {
        var type = this.value;
        var skillsFieldMobile = document.getElementById('skillsFieldMobile');
        var industryFieldMobile = document.getElementById('industryFieldMobile');

        if (type === 'company') {
            industryFieldMobile.style.display = 'flex';
            skillsFieldMobile.style.display = 'none';
        } else if (type === 'pod') {
            skillsFieldMobile.style.display = 'flex';
            industryFieldMobile.style.display = 'none';
         } else {
            skillsFieldMobile.style.display = 'none';
            industryFieldMobile.style.display = 'none';
        }
    });
    document.getElementById('type').addEventListener('change', function () {
        var type = this.value;
        var countryField = document.getElementById('countryField');
        var skillsField = document.getElementById('skillsField');
        var skillsFieldMobile = document.getElementById('skillsFieldMobile');
        var industryFieldMobile = document.getElementById('industryFieldMobile');

        if (type === 'company') {
            industryField.style.display = 'flex';
            industryFieldMobile.style.display = 'flex';
            skillsField.style.display = 'none';
            skillsFieldMobile.style.display = 'none';
            budgetField.style.display = 'none';
        } else if (type === 'pod') {
            skillsField.style.display = 'flex';
            skillsFieldMobile.style.display = 'flex';
            industryField.style.display = 'none';
            industryFieldMobile.style.display = 'none';
            budgetField.style.display = 'none';
        } else if (type === 'project') {
            skillsField.style.display = 'none';
            industryField.style.display = 'none';
            budgetField.style.display = 'flex';
        } else {
            skillsField.style.display = 'none';
            industryField.style.display = 'none';
            budgetField.style.display = 'none';
            skillsFieldMobile.style.display = 'none';
            industryFieldMobile.style.display = 'none';
        }
    });

    document.getElementById('searchForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var type = document.getElementById('type').value;
        var formAction = '';

        if (type === 'vacancy') {
            var skillIds = [];
            document.querySelectorAll('input[name="skill_ids[]"]:checked').forEach(function (checkbox) {
                skillIds.push(checkbox.value);
            });
            formAction = '{{ route("job-vacancies.landing.list") }}';
            this.action = formAction + '?skill_ids=' + JSON.stringify(skillIds);
        } else if (type === 'pod') {
            var skillIds = [];
            document.querySelectorAll('input[name="skill_ids[]"]:checked').forEach(function (checkbox) {
                skillIds.push(checkbox.value);
            });
            formAction = '{{ route("pods.list") }}';
            this.action = formAction + '?skill_ids=' + JSON.stringify(skillIds);
        } else if (type === 'project') {
            var budget = document.getElementById('budget').value;
            formAction = '{{ route("project.list") }}';
            this.action = formAction + '?budget=' + encodeURIComponent(budget);
        } else if (type === 'company') {
            var industry = document.getElementById('industry').value;
            formAction = '{{ route("job-vacancies.landing.list") }}';
            this.action = formAction + '?industry=' + budget;
        }

        this.submit();
    });

    document.getElementById('searchFormMob').addEventListener('submit', function (e) {
        e.preventDefault();
        var type = document.getElementById('typeMobile').value;
        var formAction = '';

        if (type === 'vacancy') {
            var skillIds = [];
            document.querySelectorAll('input[name="skill_ids[]"]:checked').forEach(function (checkbox) {
                skillIds.push(checkbox.value);
            });
            formAction = '{{ route("job-vacancies.landing.list") }}';
            this.action = formAction + '?skill_ids=' + JSON.stringify(skillIds);
        } else if (type === 'pod') {
            var skillIds = [];
            document.querySelectorAll('input[name="skill_ids[]"]:checked').forEach(function (checkbox) {
                skillIds.push(checkbox.value);
            });
            formAction = '{{ route("pods.list") }}';
            this.action = formAction + '?skill_ids=' + JSON.stringify(skillIds);
        } else if (type === 'project') {
            var budget = document.getElementById('budget').value;
            formAction = '{{ route("project.list") }}';
            this.action = formAction + '?budget=' + encodeURIComponent(budget);
        } else if (type === 'company') {
            var industry = document.getElementById('industry').value;
            formAction = '{{ route("job-vacancies.landing.list") }}';
            this.action = formAction + '?industry=' + budget;
        }

        this.submit();
    });
</script>

@endsection
