@extends('layouts.landing')

@section('title')
    {{ __('Vacancy') }}
@endsection
@section('body-class', 'tpl-find-work')

@section('content')
    <style>
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination {
            display: inline-flex;
        }

        .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
        }

        .page-link {
            color: #007bff;
        }

        .page-link:hover {
            color: #0056b3;
        }
    </style>
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
            display: flex;
            height: 40px;
        }
    </style>

    <section class="header__search">
        <div class="container-master">
            <div class="search__wrap">
                <form id="searchForm" class="row col-lg-12 searchForm" method="GET" action="{{ route('job-vacancies.landing.list') }}">
                    <div class="col-md-4 form-group">
                        <div class="input-group">
                            <span class="input-group-text" id="basic-addon1"><i class="fa fa-search"></i></span>
                            <input type="text" class="form-control h-100" id="company-search" placeholder="Search Company..." aria-label="Username" aria-describedby="basic-addon1">
                        </div>
{{--                        <input type="text" id="company-search" class="form-control w-100 ps-5" placeholder="Search Company...">--}}
                    </div>
                    <div id="industry" class="col-md-4 form-group">
                        @php
                            $industries = \App\Models\Industry::all();
                        @endphp
                        <select id="industry" class="form-control h-100" name="industry">
                            <option value="">Select Industry </option>
                            @foreach($industries as $industry)
                                <option value="{{ $industry->id }}" {{ old('industry_id', request()->industry) == $industry->id ? 'selected' : '' }}>
                                        {{ $industry->name }}
                                </option>
                            @endforeach
                        </select>
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
            <h1 class="section__titlepage--headertitle"><span>Companies hiring</span></h1>
            <ul class="breadcrumb mt-1">
                <li class="breadcrumb-item"><a href="{{ route('landing.find-work') }}">Find Work</a></li>
                <li class="breadcrumb-item"> {{ __('Companies hiring') }}</li>
            </ul>
        </div>
    </section>
    <section class="section__layout section_company">
        <div class="container-master">
            <div class="row-master">
            @foreach($usersWithVacancies as $userWithVacancies)
                @include('company.landing.company_card', ['company' => $userWithVacancies->ownWorkspace()])
            @endforeach
            </div>
        </div>
    </section>

    <div class="pagination-container">
        {{ $vacancies->links('pagination::bootstrap-4') }}
    </div>
@endsection
@push('scripts')
@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('company-search');

            searchInput.addEventListener('input', function () {
                const query = this.value.trim();

                fetch(`{{ route('companies.search') }}?query=${query}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        const container = document.querySelector('.row-master');
                        container.innerHTML = '';

                        if (data.length === 0) {
                            container.innerHTML = '<p class="text-center">No companies found.</p>';
                            return;
                        }

                        data.forEach(company => {
                            const companyHtml = `
                                 <div class="col-lg-3 col-md-4 col-sm-6 company-box__wrap">
                                    <div class="company-box">
                                        <div class="title-box">
                                            <div class="title-box-wrap">
                                                <img alt="user-image" class="img-fluid img_users_fix_size"
                                                    src="${company.logo ? '/storage/logo/' + company.logo : '/storage/logo/logo-light.png'}">
                                            </div>
                                            <h4>${company.name}</h4>
                                        </div>
                                        <p>${company.description ? company.description.substring(0, 120) + '...' : 'about company'}</p>
                                        <a href="/company/${company.id}" class="learn-more">Learn more</a>
                                    </div>
                                </div>
                            `;
                            container.innerHTML += companyHtml;
                        });
                    })
                    .catch(error => console.error('Error:', error));
            });
        });
    </script>
@endsection
@endpush
