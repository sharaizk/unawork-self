@extends('layouts.landing')


@section('title')
    {{ __('Vacancy') }}
@endsection
@section('body-class', 'tpl-commerce-shop')

@section('content')

    @php
        $company = $vacancy->company;
    @endphp
    <main>
        <div class="container-master">
            <div class="row-master">
                <div class="col-12 col-xl-9">
                    <div class="cm-shop__left">
                        <div class="row-master">
                            <section class="col-12 cm-shop__information">
                                <div class="cm-shop__image">
                                    <img @if ($company->avatar) 
                                            src="{{ App\Helpers\FileHelper::getUrl('users-avatar/' . $company->avatar) }}" 
                                        @else 
                                            src="{{ App\Helpers\FileHelper::getUrl('logo/' . 'logo-light.png') }}" 
                                        @endif
                                        alt="{{ $company->name }}">

                                </div>
                                <div class="cm-shop__profile">
                                    <div class="profile__name">
                                        <h1>{{ $vacancy->title }}</h1>
                                        <div>For: {{ $company->name }}</div>
                                    </div>
                                    <!-- <div class="profile__skills">
                                        <div class="title">
                                            <span>Skills</span>
                                        </div>
                                        <ul>
                                            <li class="skill__name">Skill</li>
                                            <li class="skill__name">Skill</li>
                                            <li class="skill__name">Skill</li>
                                            <li class="skill__name">Skill</li>
                                            <li class="skill__name">Skill</li>
                                        </ul>
                                    </div> -->

                                    <div class="profile__bio">
                                        <div class="title">
                                            <span>About this vacancy</span>
                                        </div>
                                        <p> {{ $vacancy->description ?? '' }}</p>
                                    </div>
                                    <!-- <div class="profile__interview">
                                        <div class="title">
                                            <span>Interview</span>
                                        </div>
                                        <p>Lorem ipsum dolor sit amet, consectet</p>
                                    </div>

                                    <div class="profile__actions">
                                        <button class="actions__btn">
                                            <i class="fa fa-video-camera" aria-hidden="true"></i>
                                            <a href="/manage-plate.php"><span>Video call</span></a>
                                        </button>
                                        <button class="actions__btn">
                                            <i class="fa fa-phone" aria-hidden="true"></i>
                                            <a href="/manage-plate.php"><span>Voice call</span></a>
                                        </button>
                                    </div> -->
                                </div>
                            </section>     
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-3">
                    <aside class="aside__container">
                        <div class="aside__card">
                            <div class="aside__card__title">
                                <span>Send request</span>
                            </div>
                            <div class="aside__card__body">
                                <span>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</span>
                            </div>
                            <div class="aside__card__actions">
                                <a href="/hub-shop.php"><span>Send request</span></a>
                            </div>
                        </div>
                        <!-- <div class="aside__card">
                            <div class="aside__card__title">
                                <span>Contact</span>
                            </div>
                            <div class="aside__card__body">
                                <span>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</span>
                            </div>
                            <div class="aside__card__actions">
                                <a href="#">
                                    <span>
                                        <img src="./images/icons/icon-fly.png" alt="">
                                        Send a message
                                    </span>
                                </a>
                            </div>
                        </div>
                        <div class="aside__card">
                            <div class="aside__card__title">
                                <span>Interlink</span>
                            </div>
                            <div class="aside__card__body">
                                <span>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</span>
                            </div>
                            <div class="aside__card__actions">
                                <a href="#"><span>Manage Process</span></a>
                            </div>
                        </div> -->
                    </aside>
                </div>
            </div>
        </div>
    </main>

@endsection

@push('scripts')
@section('scripts')
@endsection
@endpush