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
    <section class="section__titlepage">
        <div class="container-master">
            <h1 class="section__titlepage--headertitle"><span>Companies hiring</span></h1>
            <ul class="breadcrumb mt-1">
                <li class="breadcrumb-item"><a href="{{ route('landing.find-work') }}">Find Work</a></li>
                <li class="breadcrumb-item"> {{ __('Vacancies') }}</li>
            </ul>
        </div>
    </section>
    <section class="section__layout section_company">
        <div class="container-master">
            <div class="row-master">
            @foreach($vacancies as $vacancy)
                @include('job_vacancies.landing.card', ['vacancy' => $vacancy])
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