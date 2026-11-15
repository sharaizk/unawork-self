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
    .company-profile__industry { margin: 0.25rem 0 0; opacity: 0.75; font-weight: 500; }
    .company-profile__description { white-space: pre-line; }
</style>
<section class="header__search"></section>
@php
    // UNA-010: the public page reads the structured company profile on the workspace. Links are shown
    // only when they are valid http(s) URLs; every value is escaped by {{ }}.
    $safeUrl = function ($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    };
    $website = $safeUrl($company->website);
    $linkedin = $safeUrl($company->linkedin);
    $facebook = $safeUrl($company->facebook);
    $contactEmail = filter_var((string) $company->contact_email, FILTER_VALIDATE_EMAIL) ? $company->contact_email : null;
    $owner = $company->creater;
    $logoSrc = $company->logo
        ? \App\Models\Utility::get_file('logo/') . $company->logo
        : ($owner && $owner->avatar
            ? App\Helpers\FileHelper::getUrl('users-avatar/' . $owner->avatar)
            : App\Helpers\FileHelper::getUrl('logo/' . 'logo-light.png'));
    $address = implode(', ', array_filter([$company->address, $company->city, $company->state, $company->country, $company->zipcode]));
@endphp
<div class="container">
    <div class="row-master">
        <div class="col-12 col-md-12 col-xl-12">
            <section class="section__titlepage">
            </section>
            <section class="section__introduce">
                <div class="col-left">
                    <div class="cm-shop__image">
                        <img src="{{ $logoSrc }}" alt="{{ __(':company logo', ['company' => $company->company ?: $company->name]) }}" data-company-logo>
                    </div>
                </div>
                <div class="col-right">
                    <div class="item">
                        <div class="item__title">
                            <h1>{{ $company->company ?: $company->name }}</h1>
                            @if ($company->industry)
                                <p class="company-profile__industry" data-company-industry>{{ $company->industry->name }}</p>
                            @endif
                        </div>
                        <div class="item__content">
                            <ul>
                                @if ($address !== '')
                                    <li>
                                        <i class="fa fa-map-marker"></i>
                                        <span title="{{ __('Address') }}">{{ $address }}</span>
                                    </li>
                                @endif
                                @if ($company->contact_name)
                                    <li>
                                        <i class="fa fa-user"></i>
                                        <span>{{ $company->contact_name }}@if ($company->contact_position) · {{ $company->contact_position }}@endif</span>
                                    </li>
                                @endif
                                @if ($contactEmail)
                                    <li>
                                        <i class="fa fa-envelope"></i>
                                        <a href="mailto:{{ $contactEmail }}" title="{{ __('Email') }}">{{ $contactEmail }}</a>
                                    </li>
                                @endif
                                @if ($company->telephone)
                                    <li>
                                        <i class="fa fa-phone"></i>
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $company->telephone) }}" title="{{ __('Phone') }}">{{ $company->telephone }}</a>
                                    </li>
                                @endif
                                @if ($website)
                                    <li>
                                        <i class="fa fa-globe"></i>
                                        <a href="{{ $website }}" title="{{ __('Website') }}" target="_blank" rel="noopener nofollow">{{ __('Visit') }}</a>
                                    </li>
                                @endif
                                @if ($linkedin)
                                    <li>
                                        <i class="fa fa-linkedin"></i>
                                        <a href="{{ $linkedin }}" title="{{ __('LinkedIn') }}" target="_blank" rel="noopener nofollow">{{ __('LinkedIn') }}</a>
                                    </li>
                                @endif
                                @if ($facebook)
                                    <li>
                                        <i class="fa fa-facebook"></i>
                                        <a href="{{ $facebook }}" title="{{ __('Facebook') }}" target="_blank" rel="noopener nofollow">{{ __('Facebook') }}</a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <section class="section__about section__layout col-12">
            @if (trim((string) $company->description) !== '')
                <div class="section__title">
                    <h2>{{ __('About') }}</h2>
                </div>
                <div class="section__content company-profile__description">{{ $company->description }}</div>
            @endif
            <div class="section__content">
                @if (Auth::user())
                    <button class="btn btn-primary sendMessageButton section__btnMore" data-employee-id="{{ $owner->id }}">
                        <span>{{ __('Send message') }}</span>
                    </button>
                @else
                    <a class="section__btnMore" href="{{ route('login') }}"><span>{{ __('Send message') }}</span></a>
                @endif
                @if ($website)
                    <a href="{{ $website }}" class="section__btnMore" target="_blank" rel="noopener nofollow"><span>{{ __('Visit Site') }}</span></a>
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
