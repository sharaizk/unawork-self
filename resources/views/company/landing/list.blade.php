@extends('layouts.landing')

@section('body-class', 'tpl-company-list')

@section('content')
<main>
    <section class="header__search">
        <div class="container-master">
            <div class="search__wrap">
                <form id="searchForm" class="row g-3 searchForm" method="GET" action="{{ route('companies.list') }}">
                    <div id="countryField" class="col-md-8 form-group">
                        <label for="country">Country</label>
                        <select id="country" class="form-control" name="country">
                            <option value="" selected>Select Country</option>
                            @foreach($countries as $country)
                                <option value="{{ $country }}" {{ request('country') == $country ? 'selected' : '' }}>
                                    {{ $country }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 form-group d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
    <section class="section__titlepage">
        <div class="container-master">
            <h1 class="section__titlepage--headertitle"><span>Сompanies</span></h1>
            <ul class="breadcrumb mt-1">
                <li class="breadcrumb-item"><a href="{{ route('landing.find-work') }}">Find Work</a></li>
                <li class="breadcrumb-item"> {{ __('Сompanies') }}</li>
            </ul>
        </div>
    </section>
    <div class="container-master">

        <section class="section__layout section_company">
            <div class="container-master pods__title">
                <h2>Сompanies Hiring</h2>
            </div>
            <div class="container-master">
                <div class="row-master">
                    @foreach($companies as $company)
                        @include('company.landing.company_card', ['company' => $company->ownWorkspace()])
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</main>

@endsection

@section('scripts')

@endsection
