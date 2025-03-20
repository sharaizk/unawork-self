@extends('layouts.landing')


@section('title', 'Public Profile '. $company->company)

@section('body-class', 'tpl-hub-shop')


@section('content')

<!-- <section class="section__titlepage">
        <div class="container-master">
            <h1 class="section__titlepage--headertitle"><span>Job Vacancies</span></h1>
        </div>
    </section> -->
<style>
    .header__search{
        background-color: #022d4d;
        height: 60px;
    }
</style>
<section class="header__search"></section>
<div class="container">
    <div class="row-master">
        <div class="col-12 col-md-12 col-xl-12">
            <section class="section__titlepage">
            </section>
            <section class="section__introduce">
                <div class="col-left">
                    <div class="cm-shop__image">
                        <img @if ($company->creater->avatar) src="{{ App\Helpers\FileHelper::getUrl('users-avatar/' . $company->creater->avatar) }}" @else src="{{ App\Helpers\FileHelper::getUrl('logo/' . 'logo-light.png') }}" @endif
                            alt="Logo Shop">
                    </div>
                </div>
                <div class="col-right">
                    <div class="item">
                        <div class="item__title">
                            <h1>{{ $company->company }}</h1>
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
                                        <a href="{{ $website }}" title="Website">Visit</a>
                                    </li>
                                @endif
                                @if($company->linkedin)
                                    <li>
                                        <!-- <i class="fa fa-globe"></i>  -->
                                        @php
                                            $linkedin = $company->linkedin;
                                            if (!preg_match('#^http(s)?://#', $linkedin)) {
                                                $linkedin = 'http://' . $linkedin;
                                            }
                                        @endphp
                                        <a href="{{ $linkedin }}" title="Linkedin">
                                            <svg xmlns="http://www.w3.org/2000/svg" height="16" width="14" style="margin-right:5px;" viewBox="0 0 448 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path fill="#cfaa40" d="M416 32H31.9C14.3 32 0 46.5 0 64.3v383.4C0 465.5 14.3 480 31.9 480H416c17.6 0 32-14.5 32-32.3V64.3c0-17.8-14.4-32.3-32-32.3zM135.4 416H69V202.2h66.5V416zm-33.2-243c-21.3 0-38.5-17.3-38.5-38.5S80.9 96 102.2 96c21.2 0 38.5 17.3 38.5 38.5 0 21.3-17.2 38.5-38.5 38.5zm282.1 243h-66.4V312c0-24.8-.5-56.7-34.5-56.7-34.6 0-39.9 27-39.9 54.9V416h-66.4V202.2h63.7v29.2h.9c8.9-16.8 30.6-34.5 62.9-34.5 67.2 0 79.7 44.3 79.7 101.9V416z"/></svg>
                                            LinkedIn
                                        </a>
                                    </li>
                                @endif
                                @if($company->facebook)
                                    <li>
                                        <!-- <i class="fa fa-globe"></i>  -->

                                        @php
                                            $facebook = $company->facebook;
                                            if (!preg_match('#^http(s)?://#', $facebook)) {
                                                $facebook = 'http://' . $facebook;
                                            }
                                        @endphp
                                        <a href="{{ $facebook }}" title="facebook">
                                            <svg xmlns="http://www.w3.org/2000/svg" height="16" width="16" style="margin-right:5px;" viewBox="0 0 512 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path fill="#cfaa40" d="M512 256C512 114.6 397.4 0 256 0S0 114.6 0 256C0 376 82.7 476.8 194.2 504.5V334.2H141.4V256h52.8V222.3c0-87.1 39.4-127.5 125-127.5c16.2 0 44.2 3.2 55.7 6.4V172c-6-.6-16.5-1-29.6-1c-42 0-58.2 15.9-58.2 57.2V256h83.6l-14.4 78.2H287V510.1C413.8 494.8 512 386.9 512 256h0z"/></svg>
                                            Facebook
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <section class="section__about section__layout col-12">
            <div class="section__title">
                <h2>About</h2>
            </div>
            <div class="section__content">
                {{ $company->description }}
            </div>
            <div class="section__content">
                @if (Auth::user())
                    <button class="btn btn-primary sendMessageButton section__btnMore" data-employee-id="{{ $company->creater->id }}">
                        <span> Send message </span>
                    </button>
                @else
                    <a class="section__btnMore" href="{{ route('login') }}"><span>Send message</span></a>
                @endif
                @if($company->website)
                    <a href="{{$website}}" class="section__btnMore"><span>Visit Site</span></a>
                @endif
            </div>
        </section>
        <section class="section__courses section__layout w-100">
            <div class="section__title col-12">
                <h2>Vacancies</h2>
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
                            <div class="courses__card p-2">
                            <div class="courses__header">
                                <img @if ($company->creater->avatar) src="{{ App\Helpers\FileHelper::getUrl('users-avatar/' . $company->creater->avatar) }}" @else src="{{ App\Helpers\FileHelper::getUrl('logo/' . 'logo-light.png') }}" @endif
                                    alt="Logo Shop">
                            </div>
                            <div class="courses__body">
                                <div class="courses__title">
                                    <a href="{{$vacancy_page}}">{{ $vacancy->title }}</a>
                                    <!-- <div class="courses__dot">
                                        <a href="{{$vacancy_page}}">
                                            <i class="fa fa-circle" aria-hidden="true"></i>
                                            <i class="fa fa-circle" aria-hidden="true"></i>
                                            <i class="fa fa-circle" aria-hidden="true"></i>
                                        </a>
                                    </div> -->
                                </div>
                                <div class="courses__subtitle">
                                    <p>
                                        <b>Salary:</b> {{ $vacancy->salary ?? '' }}
                                    </p>
                                    <!-- <p class="small">
                                        {{ \Illuminate\Support\Str::limit($skills, 38, '...') }}
                                    </p> -->
                                    <div class="title">
                                        <b>Skills:</b>
                                        <ul class="p-0 skills__list">
                                            @forelse ($vacancy->skills->take(2) as $skill)
                                                <li class="skill__name">{{$skill->name}}</li>
                                            @empty
                                                <li class="skill__name">n/a</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                    {{ \Illuminate\Support\Str::limit($vacancy->description, 120, '...') }}
                                </div>

                            </div>
                            <a class="section__btnMore" href="{{$vacancy_page}}"><span>Learn more</span></a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="section__title col-12">
                <h2>Pods</h2>
                <div>
                <!-- <a href="#">View All</a>
                <i class="fa fa-chevron-right"></i> -->
                </div>
            </div>
            <div class="section__content section_pod">
                <div class="row-master">
                    @foreach($pods as $pod)
                        @php
                            $user = Auth::user();
                            $joinedPods = [];
                            if ($user) {
                                $joinedPods = $user->pods()->pluck('pod_id')->toArray();
                            }
                            $joinable = ($user && !in_array($pod->id, $joinedPods)) ? 1 : 0;
                        @endphp
                        @include('pod.landing.pod_card', ['pod' => $pod, 'joinable' => $joinable])
                    @endforeach
                </div>
            </div>
        </section>


    </div>
</div>

<div class="modal fade" tabindex="-1" role="dialog" id="sendMessagePopup">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header text-white">
        <h5 class="modal-title">Send Message</h5>
        <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <textarea id="messageInput" class="form-control" rows="3" placeholder="Type your message..."></textarea>
        </div>
      </div>
      <div class="modal-footer justify-content-center">
            <button type="button" class="btn btn-primary" id="sendMessage">Send Message</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
    </div>
  </div>
</div>


@endsection

@push('scripts')
@section('scripts')
<script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll(".sendMessageButton").forEach(button => {
                    button.addEventListener("click", function() {
                        let employeeId = this.dataset.employeeId;
                        $("#sendMessagePopup").modal('show');
                        document.getElementById("sendMessage").dataset.employeeId = employeeId;
                    });
                });

                document.getElementById("sendMessage").addEventListener("click", function() {
                    if (this.dataset.sending === "true") return;
                    this.dataset.sending = "true";

                    let message = document.getElementById("messageInput").value;
                    let employeeId = this.dataset.employeeId;
                    let data = {
                        recipient_id: employeeId,
                        type: 'user',
                        message: message,
                        temporaryMsgId: 'temp_1',
                        _token: '{{ csrf_token() }}'
                    };
                    let url = `{{ route('api.send-message') }}`;

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(data),
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Success:', data);
                        showNotification('Message successfully sended', 'success');
                        $("#sendMessagePopup").modal('hide');
                    })
                    .catch((error) => {
                        showNotification('Message sending failed:'.error, 'danger');
                        console.error('Error:', error);
                    })
                    .finally(() => {
                        this.dataset.sending = "false";
                    });
                });
            });
</script>

@endsection



@endpush
