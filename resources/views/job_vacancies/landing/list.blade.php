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
            height: 20px!important;
            margin-right: 10px;
        }

        .dropdown-menu-xl .form-check-label {
            font-size: 14px;
            font-weight: 600;
            color: #022D4D;
        }

        .btn-xl {
            font-size: 1.5rem;
            padding: 10px 20px;
        }
        .searchForm {
            display: flex;
            height: 40px;
        }
        .filter-modal .input-group,
        .filter-modal .form-control {
            height: 40px!important;
        }
        .filter-modal .clear__filters {
            margin-top: 50px;
        }
        @media (max-width: 768px) {
            #searchForm {
                display: none!important;
            }
        }
    </style>

    <section class="header__search">
        <div class="container">
            <div class="search__wrap">
                <form id="searchForm" class="d-flex searchForm" method="GET" action="{{ route('job-vacancies.landing.list') }}">
                    <div class=" form-group">
                        <div class="input-group">
                            <span class="input-group-text" id="basic-addon1"><i class="fa fa-search"></i></span>
                            <input type="text" class="form-control h-100" id="company-search" placeholder="Search Company..." aria-label="Username" aria-describedby="basic-addon1">
                        </div>
{{--                        <input type="text" id="company-search" class="form-control w-100 ps-5" placeholder="Search Company...">--}}
                    </div>
                    <div id="industry" class=" form-group">
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

                    <div class=" form-group d-flex align-items-end search-btn__wrap">
                        <button type="submit" class="btn btn-xl btn-primary w-100 header-search-btn">Search</button>
                    </div>
                    <a href="{{ route('job-vacancies.landing.list') }}" class="clear__filters">Clear Filters</a>
                </form>
                <button type="button" class="filter-mobile">Filters</button>
            </div>
        </div>
    </section>

    <section class="section__titlepage">
        <div class="container">
            <h1 class="section__titlepage--headertitle"><span>Companies</span></h1>
            <!-- <ul class="breadcrumb mt-1">
                <li class="breadcrumb-item"><a href="{{ route('landing.find-work') }}">Find Work</a></li>
                <li class="breadcrumb-item"> {{ __('Companies hiring') }}</li>
            </ul> -->
            <h4 class="section__titlepage--headersubtitle">Company’s Hiring</h4>
            <p>Find the most relevant companies hiring based on your qualifications and desired position, including full-time, part time, and contractual positions.</p>
        </div>
    </section>
    <section class="section__layout section_company">
        <div class="container">
            <div class="row-master">
            @foreach($usersWithVacancies as $userWithVacancies)
                @include('company.landing.company_card', ['company' => $userWithVacancies->ownWorkspace()])
            @endforeach
            </div>
        </div>
    </section>

    <div class="pagination-container">
        {{ $vacancies->links('vendor.pagination.custom') }}
    </div>
    <div class="filter-modal">
            <form  class="g-3 searchForm" method="GET" action="{{ route('job-vacancies.landing.list') }}">
                <div class="filter-modal__header">
                    <p>Filters</p>
                    <button type="button" class="close"></button>
                </div>
                <div class=" form-group">
                        <div class="input-group">
                            <span class="input-group-text" id="basic-addon1"><i class="fa fa-search"></i></span>
                            <input type="text" class="form-control h-100" id="company-search-mobile" placeholder="Search Company..." aria-label="Username" aria-describedby="basic-addon1">
                        </div>
                    </div>
                    <div id="industry" class=" form-group">
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
                <!-- <div class="search-btn__wrap form-group d-flex align-items-center">
                    <button type="submit" class="btn btn-xl btn-primary w-100 filter-btn header-search-btn">Search</button>
                </div> -->
                <a href="{{ route('job-vacancies.landing.list') }}" class="clear__filters">Clear Filters</a>
            </form>
        </div>
@endsection
@push('scripts')
@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
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

            const searchInput = document.getElementById('company-search');
            const searchInputMobile = document.getElementById('company-search-mobile');

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

            searchInputMobile.addEventListener('input', function () {
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
