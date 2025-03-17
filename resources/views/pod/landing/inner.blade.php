@extends('layouts.landing')


@section('title')
    {{ __('Inner Circle') }}
@endsection

@section('body-class', 'tpl-innerCircle')


@section('content')

<style>
    .circle_content .text {
        min-width: 45vh; /* Set a minimum height for the description */
    }
</style>

<section class="header__search">
    <div class="container-master">
        <div class="search__wrap">
            <div class="search__box">
                <span class="search__icon">
                    <i class="fa fa-search"></i>
                </span>
                <input type="text" id="searchInput" placeholder="Search by name">
            </div>
            <ul class="search__list">
                <li class="search__item ">
                    <select id="categorySelect" class="form-control">
                        <option value="">All Categories</option>
                        @foreach(\App\Models\ProjectCategory::all() as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </li>
            </ul>
        </div>
    </div>
</section>

<section class="section__titlepage">
    <div class="container-master">
        <h1 class="section__titlepage--headertitle"><span>Inner Circle</span></h1>
    </div>
</section>

<section class="section__innerCircle">
        <div class="container-master">
            <div class="row-master" id="projectsContainer">
                @include('pod.landing.inner_projects_list', ['projects' => $projects])
            </div>
        </div>
    </section>
@endsection

@push('scripts')
@section('scripts')
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const searchInput = document.getElementById('searchInput');
            const categorySelect = document.getElementById('categorySelect');
            const projectsContainer = document.getElementById('projectsContainer');
            const podId = `{{ $pod->id }}`;

            function fetchProjects() {
                let query = searchInput.value.trim();
                let category = categorySelect.value;

                let url = `{{ route('pods.projects.search', ['pod' => ':id']) }}`
                    .replace(':id', podId) + `?query=${query}&category=${category}`;

                fetch(url, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(response => response.json())
                    .then(data => {
                        projectsContainer.innerHTML = data.projects;
                    })
                    .catch(error => console.error('Error:', error));
            }

            searchInput.addEventListener('input', fetchProjects);
            categorySelect.addEventListener('change', fetchProjects);
        });
    </script>

@endsection
@endpush
