@extends('layouts.landing')

@section('body-class', 'tpl-pods-list')

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
            <form id="searchForm" class="row g-3 searchForm" method="GET" action="{{ route('pods.list') }}">
                <div id="skillsField" class="col-md-8 form-group">
                    <label>Select Skills</label>
                    <div class="dropdown">
                        <button class="btn btn-xl btn-secondary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="true">
                            {{ count(request('skill_ids', [])) > 0 ? 'Selected Skills' : 'Select Skills' }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-xl w-100">
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
                
                <div class="col-md-4 form-group d-flex align-items-end">
                    <button type="submit" class="btn btn-xl btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>
</section>

    <section class="section__titlepage">
        <div class="container-master">
            <h1 class="section__titlepage--headertitle"><span>Pods</span></h1>
            <ul class="breadcrumb mt-1">
                <li class="breadcrumb-item"><a href="{{ route('landing.find-work') }}">Find Work</a></li>
                <li class="breadcrumb-item"> {{ __('Pods') }}</li>
            </ul>
        </div>
    </section>
    <div class="container-master">

    <section class="section__layout section_pod">
            <div class="container-master pods__title">
                <h2>Pods of Interest </h2>
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

@endsection