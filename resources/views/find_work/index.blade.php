@extends('layouts.landing')

@section('body-class', 'tpl-find-work')
@section('title', 'Find Work')

@section('content')
<style>
    .dropdown-menu-xl {
        min-width: 130px; 
        width: 150px; 
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
        height: 40px;
        padding: 10px 5px; 
        font-size: 1.4rem;
    }
    .searchForm {
        display: inline-flex;
    }
</style>
<main>
    <section class="header__search">
        <div class="container-master">
            <div class="search__wrap">
                <form id="searchForm" class=" g-3 searchForm" method="GET">
                <div class="col-md-4 form-group">
                    <label for="type">Type</label>
                    <select id="type" class="form-control" name="type">
                        <option value="" selected>Select Type</option>
                        <option value="pod">Pod</option>
                        <option value="company">Company</option>
                        <!-- <option value="vacancy">Vacancy</option> -->
                        <!-- <option value="project">Project</option> -->
                    </select>
                </div>

                <div id="budgetField" class="col-md-4 form-group" style="display: none;">
                    <label for="budget">Budget</label>
                    <select id="budget" class="form-control" name="budget">
                        <option value="" selected>Select Budget</option>
                            <option value="[0,500]">$0 - $500</option>
                            <option value="[500,1000]">$500 - $1000</option>
                            <option value="[1000,5000]">$1000 - $5000</option>
                            <option value="[5000,999999999]">$5000+</option>
                    </select>
                </div>

                @php
                    $industries = \App\Models\Industry::all();
                    $currentIndustryId = $currentWorkspace->industry_id ?? null;
                @endphp
                <div id="industryField" class="col-md-4 form-group" style="display: none;">
                    <label for="industry">Industry</label>
                    <select id="industry" class="form-control" name="industry">
                        <option value="">Select Industry </option>
                        @foreach($industries as $industry)
                            <option value="{{ $industry->id }}">
                                    {{ $industry->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div id="skillsField" class="col-md-2 form-group" style="display: none;">
                    <div class="dropdown">
                        <button class="btn btn-xl btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="true">
                            Select Skills
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

                <div class="col-md-4 form-group d-flex align-items-end">
                    <button type="submit" class="btn btn-xl btn-primary w-100">Filter</button>
                </div>
                </form>
            </div>
        </div>
    </section>


    <section class="section__titlepage">
        <div class="container-master">
            <h1 class="section__titlepage--headertitle"><span>Find Work</span></h1>
        </div>
    </section>

    <div class="container-master">
        <section class="section__layout section_company">
            <div class="container-master pods__title">
                <h2>Companies Hiring</h2>
                <a href="{{ route('job-vacancies.landing.list') }}" class="buttonViewAll">View All <i class="fa fa-chevron-right"></i></a>
            </div>
            <div class="container-master">
                <div class="row-master d-flex align-items-stretch">
                    @foreach($usersWithVacancies as $userWithVacancies)
                        @include('company.landing.company_card', ['company' => $userWithVacancies->ownWorkspace()])
                    @endforeach
                </div>
            </div>
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
            <div class="container-master pods__title">
                <h2>Pods of Interest </h2>
                <a href="{{ route('pods.list') }}" class="buttonViewAll">View All <i class="fa fa-chevron-right"></i></a>
            </div>
            <div class="container-master">
                <div class="row-master  d-flex align-items-stretch">
                    @foreach($pods as $pod)
                            @php
                                $joinable = ($user && !in_array($pod->id, $joinedPods)) ? 1 : 0;
                            @endphp
                        @include('pod.landing.pod_card', ['pod' => $pod, 'joinable' => $joinable])
                    @endforeach
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
    });
</script>
<script>
    document.getElementById('type').addEventListener('change', function () {
        var type = this.value;
        var countryField = document.getElementById('countryField');
        var skillsField = document.getElementById('skillsField');

        if (type === 'company') {
            industryField.style.display = 'flex';
            skillsField.style.display = 'none';
            budgetField.style.display = 'none';
        } else if (type === 'pod') {
            skillsField.style.display = 'flex';
            industryField.style.display = 'none';
            budgetField.style.display = 'none';
        } else if (type === 'project') {
            skillsField.style.display = 'none';
            industryField.style.display = 'none';
            budgetField.style.display = 'flex';
        } else {
            skillsField.style.display = 'none';
            industryField.style.display = 'none';
            budgetField.style.display = 'none';
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
</script>

@endsection