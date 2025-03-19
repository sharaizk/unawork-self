@extends('layouts.landing')


@section('title')
    {{ __('Vacancy') }}
@endsection
@section('body-class', 'tpl-commerce-shop')

@section('content')

    @php
        $company = $vacancy->company;
        $company_workspace = $company->ownWorkspace();
        if (Auth::user()) {
            if (Auth::user()->hasRole('employee')) {
                $sendRequestUrl = route('login');
            } else {
                $sendRequestUrl = "#";
            }
        } else {
            $sendRequestUrl = route('login');
        }
    @endphp
    <style>
        .header__search{
            background-color: #022d4d;
            height: 60px;
        }
    </style>
    <section class="header__search">
        <div class="container"></div>
    </section>
    <main>
    <section class="section__titlepage">
        <div class="container">
            <h1 class="section__titlepage--headertitle"><span>Job Vacancies</span></h1>
            <!-- <ul class="breadcrumb mt-1">
                <li class="breadcrumb-item"><a href="{{ route('landing.company.show', $company_workspace) }}">{{ $company_workspace->name }}</a></li>
                <li class="breadcrumb-item"> {{$vacancy->title}} </li>
            </ul> -->
        </div>
    </section>
        <div class="container">
            <div class="row-master">
                <div class="col-12 col-xl-9">
                    <div class="cm-shop__left">
                        <div class="row-master">
                            <section class="col-12 cm-shop__information">
                                <div class="cm-shop__image">
                                <img @if ($company_workspace->logo) src="{{ App\Helpers\FileHelper::getUrl('logo/' . $company_workspace->logo) }}" @else src="{{ App\Helpers\FileHelper::getUrl('logo/' . 'logo-light.png') }}" @endif
                                    alt="Logo Shop">

                                </div>
                                <div class="cm-shop__profile">
                                    <div class="profile__name">
                                        <h1>{{ $vacancy->title }}</h1>
                                        <div>For: {{ $company_workspace->company }}</div>
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
                                <span>Got questions? Send information and strike up a conversation with this user.</span>
                            </div>
                            <div class="aside__card__actions">
                                @guest
                                    <a href="{{ route('login') }}"><span>Send Application</span></a>
                                @endguest
                                @if (Auth::check() && Auth::user()->hasRole('employee'))
                                <a href="#" id="sendApplButton"><span>Send Application</span></a>
                                @endif
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
    <!-- Application Modal -->
    <div class="modal fade" id="applicationModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Send Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="applicationForm" method="POST" action="{{ route('api.job-applications.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                        </div>
                        <input type="hidden" name="job_vacancy_id" value="{{ $vacancy->id }}">
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">Submit Application</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
@section('scripts')
<script>
    $(document).ready(function() {
        $('#sendApplButton').on('click', function(event) {
            $('#applicationModal').modal('show')
        });

        $('#applicationForm').on('submit', function(event) {
            event.preventDefault();
            var form = $(this);
            $.ajax({
                type: 'POST',
                url: form.attr('action'),
                data: form.serialize(),
                success: function(response) {
                    $('#applicationModal').modal('hide');
                    showNotification('Application submitted successfully!', 'success');
                },
                error: function(xhr) {
                    $('#applicationModal').modal('hide');

                    var errorMessage = 'An error occurred while submitting your application.';
                    if(xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    showNotification(errorMessage, 'danger');
                }
            });
        });
    });
</script>
@endsection
@endpush
