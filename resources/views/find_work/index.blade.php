@extends('layouts.landing')

@section('body-class', 'tpl-find-work')

@section('content')
<style>
    .dropdown-menu-xl {
        min-width: 200px; 
        width: 300px; 
        max-height: 400px; 
        overflow-y: auto; 
    }

    .dropdown-menu-xl li {
        padding: 10px 20px; 
    }

    .dropdown-menu-xl .form-check-input {
        width: 20px; 
        height: 20px; 
        margin-right: 10px; 
    }

    .dropdown-menu-xl .form-check-label {
        font-size: 16px; 
    }

    .btn-xl {
        font-size: 1.5rem;
        padding: 10px 20px; 
    }
    .searchForm {
        display: inline-flex;
    }
</style>
<main>
    <section class="header__search">
        <div class="container-master">
            <div class="search__wrap">
                <form id="searchForm" class="row g-3 searchForm" method="GET">
                <div class="col-md-4 form-group">
                    <label for="type">Type</label>
                    <select id="type" class="form-control" name="type">
                        <option value="" selected>Select Type</option>
                        <option value="pod">Pod</option>
                        <option value="company">Company</option>
                        <option value="project">Project</option>
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

                <div id="countryField" class="col-md-4 form-group" style="display: none;">
                    <label for="country">Country</label>
                    <select id="country" class="form-control" name="country">
                        <option value="" selected>Select Country</option>
                        @foreach($countries as $country)
                            <option value="{{ $country }}">{{ $country }}</option>
                        @endforeach
                    </select>
                </div>

                <div id="skillsField" class="col-md-4 form-group" style="display: none;">
                    <label>Select Skills</label>
                    <div class="dropdown">
                        <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="true">
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
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                </form>
            </div>
        </div>
    </section>
    <div id="message-container"></div>


    <section class="section__titlepage">
        <div class="container-master">
            <h1 class="section__titlepage--headertitle"><span>Find Work</span></h1>
        </div>
    </section>

    <div class="container-master">
        <section class="section__layout section_company">
            <div class="container-master pods__title">
                <h2>Companies Hiring</h2>
                <a href="{{ route('companies.list') }}" class="buttonViewAll">ViewAll <i class="fa fa-chevron-right"></i></a>
            </div>
            <div class="container-master">
                <div class="row-master">
                    @foreach($companies as $company)
                        @include('company.landing.company_card', ['company' => $company])
                    @endforeach
                </div>
            </div>
        </section>

        <section class="section__layout section__project">
            <div class="container-master teams__title">
                <h2>Projects Availables</h2>
                <a href="{{ route('project.list') }}" class="buttonViewAll">ViewAll <i class="fa fa-chevron-right"></i></a>
            </div>
            <div class="container-master">
                <div class="row-master">
                        @foreach($projects as $project)
                            @include('find_work.project.project_card', ['project' => $project])
                        @endforeach
                </div>
            </div>
        </section>

        <section class="section__layout section_pod">
            <div class="container-master pods__title">
                <h2>Pods of Interest </h2>
                <a href="{{ route('pods.list') }}" class="buttonViewAll">ViewAll <i class="fa fa-chevron-right"></i></a>
            </div>
            <div class="container-master">
                <div class="row-master">
                    @foreach($pods as $pod)
                        @include('pod.landing.pod_card', ['pod' => $pod])
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

                fetch(`{{ ENV('APP_URL') }}api/pods/${podId}/requests`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    const messageContainer = document.getElementById('message-container');
                    if (data.status === 'success') {
                        messageContainer.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                        form.style.display = 'none';
                    } else {
                        messageContainer.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    const messageContainer = document.getElementById('message-container');
                    messageContainer.innerHTML = `<div class="alert alert-danger">An error occurred: ${error.message}</div>`;
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
            countryField.style.display = 'block';
            skillsField.style.display = 'none';
            budgetField.style.display = 'none';
        } else if (type === 'pod') {
            skillsField.style.display = 'block';
            countryField.style.display = 'none';
            budgetField.style.display = 'none';
        } else if (type === 'project') {
            skillsField.style.display = 'none';
            countryField.style.display = 'none';
            budgetField.style.display = 'block';
        } else {
            countryField.style.display = 'none';
            skillsField.style.display = 'none';
            budgetField.style.display = 'none';
        }
    });

    document.getElementById('searchForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var type = document.getElementById('type').value;
        var formAction = '';

        if (type === 'company') {
            var country = document.getElementById('country').value;
            formAction = '{{ route("companies.list") }}';
            this.action = formAction + '?country=' + country;
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
        }

        this.submit();
    });
</script>

@endsection