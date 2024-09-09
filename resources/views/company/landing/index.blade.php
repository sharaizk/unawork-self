@extends('layouts.landing')


@section('title', 'Public Profile '. $company->name)

@section('body-class', 'tpl-hub-shop')


@section('content')

<!-- <section class="section__titlepage">
        <div class="container-master">
            <h1 class="section__titlepage--headertitle"><span>Job Vacancies</span></h1>
        </div>
    </section> -->
<div class="container-master">
    <div class="row-master">
        <div class="col-12 col-md-12 col-xl-9">
            <section class="section__titlepage">
            </section>
            <section class="section__introduce">
                <div class="col-left">
                    <div class="cm-shop__image">
                        <img @if ($company->logo) src="{{ App\Helpers\FileHelper::getUrl('logo/' . $company->logo) }}" @else src="{{ App\Helpers\FileHelper::getUrl('logo/' . 'logo-light.png') }}" @endif
                            alt="Logo Shop">
                    </div>
                </div>
                <div class="col-right">
                    <div class="item">
                        <div class="item__title">
                            <h1>{{ $company->name }}</h1>
                        </div>
                        <div class="item__content">
                            <ul>
                                    <li>
                                        <i class="fa fa-map-marker"></i>
                                        @php
                                            $addressParts = array_filter([
                                                $company->address,
                                                $company->city,
                                                $company->state,
                                                $company->country,
                                                $company->zip_code
                                            ]);
                                            $address = implode(', ', array_filter($addressParts));
                                        @endphp
                                        <span title="Address">
                                            {{ $address ?: 'n/a' }}
                                        </span>
                                    </li>

                                @if($company->telephone)
                                    <li>
                                        <i class="fa fa-phone"></i> 
                                        <a href="tel:{{$company->telephone}}" title="Phone">
                                            {{$company->telephone}}
                                        </a>
                                    </li>
                                @endif
                                @if($company->website)
                                    <li>
                                        <i class="fa fa-globe"></i> 
                                        @php
                                            $website = $company->website;
                                            if (!preg_match('#^http(s)?://#', $website)) {
                                                $website = 'http://' . $website;
                                            }
                                        @endphp
                                        <a href="{{ $website }}" title="Website">Visit Website</a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                    <section class="section__about section__layout">
                        <div class="section__title">
                            <h2>About</h2>
                        </div>
                        <div class="section__content">
                            {{ $company->description }}
                            @if($company->website)
                                <a href="{{$website}}" class="section__btnMore"><span>Visit Site</span></a>
                            @endif
                        </div>
                    </section>
                </div>
            </section>
        </div>
        <section class="section__courses section__layout w-100">
            <div class="section__title">
                <h2>Job Vacancies</h2>
                <div>
                <!-- <a href="#">View All</a>
                <i class="fa fa-chevron-right"></i> -->
                </div>
            </div>
            <div class="section__content">
                <div class="row-master">
                    @foreach($vacancies as $vacancy)
                        @php
                            if ($vacancy->skills->isNotEmpty()) {
                                $skills = 'Skills: '. $vacancy->skills->pluck('name')->implode(', ');
                            } else {
                                $skills = 'Skills not selected for this vacancion';
                            }
                            $vacancy_page = route('job-vacancies.landing.show', ['vacancy' => $vacancy->id]);
                        @endphp
                        <div class="col-12 col-md-4 col-xl-3">
                            <div class="courses__card">
                            <div class="courses__header">
                                <img @if ($company->logo) src="{{ App\Helpers\FileHelper::getUrl('logo/' . $company->logo) }}" @else src="{{ App\Helpers\FileHelper::getUrl('logo/' . 'logo-light.png') }}" @endif
                                    alt="Logo Shop">
                            </div>
                            <div class="courses__body">
                                <div class="courses__title">
                                    <a href="{{$vacancy_page}}">{{ $vacancy->title }}</a>
                                    <div class="courses__dot">
                                        <a href="{{$vacancy_page}}">
                                            <i class="fa fa-circle" aria-hidden="true"></i>
                                            <i class="fa fa-circle" aria-hidden="true"></i>
                                            <i class="fa fa-circle" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="courses__subtitle">
                                    <p>
                                        <b>Salary</b>: {{ $vacancy->salary ?? '' }}
                                    </p>
                                    <p class="small">
                                        {{ \Illuminate\Support\Str::limit($skills, 38, '...') }}
                                    </p>
                                    {{ \Illuminate\Support\Str::limit($vacancy->description, 30, '...') }}
                                </div>
                                
                            </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</div>
    

@endsection

@push('scripts')
@section('scripts')

@endsection



@endpush