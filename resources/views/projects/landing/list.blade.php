@extends('layouts.landing')

@section('body-class', 'tpl-find-work')

@section('content')
<main>
    <section class="header__search">
        <div class="container-master">
            <div class="search__wrap">
                <form id="searchForm" class="row g-3 searchForm w-100" method="GET" action="{{ route('project.list') }}">
                    <div id="budgetField" class="col-md-8 form-group">
                        <label for="budget">Budget</label>
                        <select id="budget" class="form-control w-100" name="budget">
                            <option value="" selected>Select Budget</option>
                            <option value="[0,500]" {{ request('budget') == json_encode([0, 500]) ? 'selected' : '' }}>$0 - $500</option>
                            <option value="[500,1000]" {{ request('budget') == json_encode([500, 1000]) ? 'selected' : '' }}>$500 - $1000</option>
                            <option value="[1000,5000]" {{ request('budget') == json_encode([1000, 5000]) ? 'selected' : '' }}>$1000 - $5000</option>
                            <option value="[5000,999999999]" {{ request('budget') == json_encode([5000, 999999999]) ? 'selected' : '' }}>$5000+</option>
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
            <h1 class="section__titlepage--headertitle"><span>Projects Availables</span></h1>
            <ul class="breadcrumb mt-1">
                <li class="breadcrumb-item"><a href="{{ route('landing.find-work') }}">Find Work</a></li>
                <li class="breadcrumb-item"> {{ __('Projects') }}</li>
            </ul>
        </div>
    </section>
    <div class="container-master">

        <section class="section__layout section__project">
            <div class="container-master teams__title">
                <h2>Projects Availables</h2>
            </div>
            <div class="container-master">
                <div class="row-master">
                    @foreach($projects as $project)
                        @include('find_work.project.project_card', ['project' => $project])
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</main>

@endsection

@section('scripts')

@endsection