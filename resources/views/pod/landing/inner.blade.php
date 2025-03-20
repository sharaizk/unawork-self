@extends('layouts.landing')


@section('title')
    {{ __('Inner Circle') }}
@endsection

@section('body-class', 'tpl-innerCircle')


@section('content')

<style>
    .header__search .container {
        height: 100%;
    }
    .section__titlepage--subtitle {
        font-size: 20px;
        font-weight: 600;
        color: #022D4D;
        margin-top: 20px;
    }
    .header__search input,
    .header__search select {
        font-weight: 600;
        font-size: 15px;
    }
    .header__search select {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 229px;
    }
    .search__item {
        display: flex;
        justify-content: center;
        position: relative;
    }
    .search__item i {
        position: absolute;
        right: 9px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 16px;
        color: #022D4D;
        pointer-events: none;
    }
    .filter-modal .search__list,
    .filter-modal .search__box {
        padding-left: 0;
        width: 90%;
        margin: 0 auto;
        font-size: 15px;
    }
    .filter-modal .search__wrap {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }
    .filter-modal .search__box input {
        width: 100%;
        height: 40px;
        border-radius: 4px;
        padding: 0 10px;
        font-size: 15px;
    }
    .filter-modal select {
        font-size: 15px;
    }
    .filter-modal .header-search-btn {
        height: 40px!important;
        max-width: 200px;
        margin: 0 auto;
        font-size: 15px;
        font-weight: 600;
        background-color: #cfaa40!important;
        margin-top: 50px;
    }
    @media (max-width: 996px) {
        #searchWrap {
            display: none!important;
        }
        .filter-mobile {
            display: block;
        }
    }
</style>

<section class="header__search">
    <div class="container">
        <div id="searchWrap" class="search__wrap">
            <div class="search__box">
                <span class="search__icon">
                    <i class="fa fa-search"></i>
                </span>
                <input type="text" id="searchInput" placeholder="Search by name">
            </div>
            <ul class="search__list">
                <li class="search__item ">
                    <select id="categorySelect" class="form-control">
                        <option value="">Select Category</option>
                        @foreach(\App\Models\ProjectCategory::all() as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <i class="ti ti-chevron-down"></i>
                </li>
            </ul>
            <a href="{{ route('landing.pods.show.inner', $pod->id) }}" class="clear__filters">Clear Filters</a>
        </div>
        <button type="button" class="filter-mobile">Filters</button>
    </div>
</section>
<div class="filter-modal">
    <div class="filter-modal__header">
        <p>Filters</p>
        <button type="button" class="close"></button>
    </div>
    <div class="search__wrap">
        <div class="search__box">
            <span class="search__icon">
                <i class="fa fa-search"></i>
            </span>
            <input type="text" id="searchInputMob" placeholder="Search by name">
        </div>
        <ul class="search__list">
            <li class="search__item ">
                <select id="categorySelectMob" class="form-control">
                    <option value="">Select Category</option>
                    @foreach(\App\Models\ProjectCategory::all() as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <i class="ti ti-chevron-down"></i>
            </li>
        </ul>
        <button type="submit" class="btn btn-xl btn-primary w-100 filter-btn header-search-btn">Search</button>
        <a href="{{ route('landing.pods.show.inner', $pod->id) }}" class="clear__filters">Clear Filters</a>
    </div>
</div>

<section class="section__titlepage">
    <div class="container">
        <h1 class="section__titlepage--headertitle"><span>Inner Circle</span></h1>
        <h2 class="section__titlepage--subtitle">Inner POD Circle</h2>
        <p class="pods__subtitle">Ready for a new project? View available tasks and projects and take what you want to work on. If you see a project available, be the first to accept the project. This lets you own the project and no longer displays it to other available members. If you can’t take it, simply reject the project so it doesn’t show up on your board.</p>
    </div>
</section>

<section class="section__innerCircle">
        <div class="container">
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
            const filterBtn = document.querySelector('.filter-mobile');
            const filterModal = document.querySelector('.filter-modal');
            const closeBtn = document.querySelector('.filter-modal .close');
            const headerSearchBtn = document.querySelector('.header-search-btn');

            if (filterBtn && filterModal && closeBtn) {
                filterBtn.addEventListener('click', function () {
                    filterModal.classList.add('active');
                });

                closeBtn.addEventListener('click', function () {
                    filterModal.classList.remove('active');
                });

                headerSearchBtn.addEventListener('click', function () {
                    filterModal.classList.remove('active');
                });

                filterModal.addEventListener('click', function (event) {
                    if (event.target === filterModal) {
                        filterModal.classList.remove('active');
                    }
                });
            }

            const searchInput = document.getElementById('searchInput');
            const categorySelect = document.getElementById('categorySelect');
            const searchInputMob = document.getElementById('searchInputMob');
            const categorySelectMob = document.getElementById('categorySelectMob')
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

            function fetchProjectsMob() {
                let query = searchInputMob.value.trim();
                let category = categorySelectMob.value;

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
            searchInputMob.addEventListener('input', fetchProjectsMob);
            categorySelectMob.addEventListener('change', fetchProjectsMob);
        });
    </script>

@endsection
@endpush
