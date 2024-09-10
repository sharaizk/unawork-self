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
            display: inline-flex;
        }
    </style>

    <section class="header__search">
        <div class="container-master">
            <div class="search__wrap">
                <form id="searchForm" class="row col-lg-6 searchForm" method="GET" action="{{ route('job-vacancies.landing.list') }}">
                    <div id="industry" class="col-lg-8 form-group">
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
@endsection
@endpush