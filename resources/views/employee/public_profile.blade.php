@extends('layouts.landing')
@section('title', 'Public Profile')
@section('body-class', 'tpl-hub-shop')
@section('content')
@php
    $logo = \App\Models\Utility::get_file('users-avatar/');
@endphp
<link rel="stylesheet" href="{{ asset('assets/css/landing/hub-shop.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/landing/employee-profile.css') }}">
<link rel="stylesheet" href="http://unawork.net/plugins/fancybox/fancybox.min.css">
<style>
    .header__search{
        background-color: #022d4d;
        height: 60px;
    }
</style>
<section class="header__search"></section>
<main>
    <div id="successMessage" class="alert alert-success" style="display: none;"></div>

    <div class="container main">
    <div class="row-master">
      <div class="col-12 col-md-12 col-xl-9">
        <section class="section__introduce">
          <div class="col-left">
            <div class="cm-shop__image">
              <img  alt="Thomas Johnson"
              @if ($employee->avatar) src="{{ asset($logo . $employee->avatar) }}" @else avatar="{{ $employee->name }}" @endif >
            </div>

          </div>
          <div class="col-right">
            <div class="item">
              <div class="item__title">
                <h1>{{ $employee->name }}</h1>
                @php
                    $p = $employee->profile;
                    $headline = array_filter([
                        optional($p->jobTitle)->title,
                        optional($p->industry)->name,
                        $p->years_experience !== null ? __(':years years of experience', ['years' => rtrim(rtrim(number_format((float) $p->years_experience, 1), '0'), '.')]) : null,
                    ]);
                @endphp
                @if ($headline)
                  <p class="emp-profile__headline">{{ implode(' · ', $headline) }}</p>
                @endif
              </div>
              <div class="item__content">
                <ul>
                  <li>
                    <i class="fa fa-map-marker"></i>
                    @php
                        $addressParts = array_filter([
                            $employee->profile->address,
                            $employee->profile->city,
                            $employee->profile->state,
                            $employee->profile->country,
                            $employee->profile->zip_code
                        ]);
                        $address = implode(', ', array_filter($addressParts));
                    @endphp
                    <span title="Address">
                      {{ $address ?: 'n/a' }}
                    </span>
                  </li>
                  @if ($p->timezone)
                    <li><i class="fa fa-clock-o"></i> <span>{{ str_replace('_', ' ', $p->timezone) }}</span></li>
                  @endif
                </ul>
              </div>
            </div>
            <section class="section__about section__layout">


              <div class="profile__skills">
                <div class="title">
                    <span>Skills:</span>
                </div>
                <ul class="p-0">
                  @forelse ($employee->skills as $skill)
                    @php $level = \App\Enums\Profile\SkillProficiency::tryFrom((string) $skill->pivot->proficiency); @endphp
                    <li class="skill__name">{{$skill->name}}@if ($level) <small class="emp-profile__level">· {{ $level->label() }}</small>@endif</li>
                  @empty
                    <li class="skill__name">n/a</li>
                  @endforelse
                </ul>
              </div>
              <div class="profile__bio">
                <div class="title">
                    <span>Bio:</span>
                </div>
                <p>
                  @if($employee->profile->about)
                    {{ $employee->profile->about }}
                  @else
                    Employee has`nt filled profile bio yet.
                  @endif
                </p>
                @if($employee->profile->cv_file)
                    <p class="mt-2 cv-title">{{ __('CV File:') }} <a href="{{ asset('storage/'.$employee->profile->cv_file) }}" target="_blank"> cv_file </a></p>
                @endif
              </div>
              @include('employee.public_profile_sections', ['profile' => $p])
           </section>
          </div>

        </section>

      </div>
      @if (Auth::id() != $employee->id)
      <div class="col-12 col-md-12 col-xl-3">
        <aside class="aside__container">

            @auth('web')
              <div class="aside__card">
              <div class="aside__card__title">
                <span>Invite user</span>
              </div>
              <div class="aside__card__body">
                <span>Chat with this user and invite them into your projects or workspace.</span>
              </div>
              <div class="aside__card__actions">
                <button class="hireButton" data-employee-email="{{ $employee->email }}">
                  <span>
                    <img src="{{ asset('assets/images/landing/icon-fly.png') }}" alt="">
                    Invite
                  </span>
                </button>
            <!-- <a href="#" class="section__btnMore " data-employee-email="{{ $employee->email }}"><span>Hire</span></a> -->

              </div>
            </div>
                <div class="aside__card">
                    <div class="aside__card__title">
                    <span>Send a message</span>
                    </div>
                    <div class="aside__card__body">
                    <span>Got questions? Send information and strike up a conversation with this user.</span>
                    </div>
                    <div class="aside__card__actions">
                        <button class="sendMessageButton" data-employee-id="{{ $employee->id }}">
                            <span>
                                <img src="{{ asset('assets/images/landing/icon-fly.png') }}" alt="">
                                Send a message
                            </span>
                        </button>
                    </div>
                </div>

            <div class="aside__card">
              <div class="aside__card__title">
                <span>Online Video</span>
              </div>
              <div class="aside__card__body">
                <span>Schedule a meeting or interview with this user for your next hire.</span>
              </div>
              <div class="aside__card__actions">
                <button id="startNowButton" data-member-id="{{ $employee->id }}" data-bs-toggle="modal" data-bs-target="#newMeetingPopup">
                  <span>
                    <img src="{{ asset('assets/images/landing/icon-video.png') }}" alt="">
                    Start Now
                  </span>
                </button>
              </div>
            </div>
          @endauth

        </aside>
      </div>
      @endif
    </div>
  </div>
</main>
  @auth('web')

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
              <div class="modal-footer justify-content-between">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                  <button type="button" class="btn btn-primary" id="sendMessage">Send</button>
              </div>
          </div>
      </div>
    </div>
    <div class="modal fade" tabindex="-1" role="dialog" id="inviteUserPopup">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header text-white">
                <h5 class="modal-title">Send Invite</h5>
                <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="selectItem" class="form-label">Select Workspace or Pod</label>
                    <select id="selectItem" class="form-select">
                        <option value="" selected disabled>Select Item</option>
                        @foreach (Auth::user()->invitableWorkspaces($employee->id) as $workspace)
                            <option value="workspace_{{ $workspace->slug }}">Company: {{ $workspace->name }}</option>
                        @endforeach
                        @foreach (Auth::user()->invitablePods($employee->id) as $pod)
                            <option value="pod_{{ $pod->id }}">POD: {{ $pod->title }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="inviteUser">Send</button>
            </div>
        </div>
    </div>
</div>
  @endauth
@endsection

@section('scripts')

    <!-- set member id in modal  -->
    <script type="text/javascript">
        document.getElementById('startNowButton').addEventListener('click', function() {
            var memberId = this.getAttribute('data-member-id');
            document.getElementById('memberIdInput').value = memberId;
        });
    </script>
    <!-- invite user modal + js -->
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function () {
          const inviteUserModal = new bootstrap.Modal(document.getElementById("inviteUserPopup"));

            document.querySelectorAll(".hireButton").forEach(button => {
                button.addEventListener("click", function() {
                    let employeeEmail = this.dataset.employeeEmail;
                    inviteUserModal.show();
                    document.getElementById("inviteUser").dataset.employeeEmail = employeeEmail;
                });
            });

            document.getElementById("inviteUser").addEventListener("click", function() {
                let employeeEmail = this.dataset.employeeEmail;
                let selectedItem = document.getElementById("selectItem").value;
                let type = '';
                let id = '';

                if (selectedItem) {
                    let splitItem = selectedItem.split('_');
                    type = splitItem[0];
                    id = splitItem[1];
                }

                let url = '';
                let method = 'GET';
                if (type === 'workspace') {
                    url = `{{ route('user.exists', ['slug' => 'SLUG_PLACEHOLDER']) }}`.replace('SLUG_PLACEHOLDER', id);
                    url += `?email=${encodeURIComponent(employeeEmail)}`;
                } else if (type === 'pod') {
                    url = `{{ route('pods.user.exists', ['podId' => 'POD_ID_PLACEHOLDER']) }}`.replace('POD_ID_PLACEHOLDER', id);
                    method = 'POST';
                }

                if (url) {
                    let data = {
                        email: employeeEmail,
                        _token: '{{ csrf_token() }}'
                    };

                    fetch(url, {
                        method: method,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: method === 'POST' ? JSON.stringify(data) : undefined
                    })
                    .then(response => {
                        if (response.status === 200) {
                            inviteUserModal.hide();
                            showNotification('User successfully invited', 'success');

                        } else {
                            return response.json().then(data => {
                                console.error('Error:', data.message);
                                showNotification(`Error: ${data.message}`, 'danger');
                            });
                        }
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                        document.getElementById("successMessage").innerText = `An error occurred: ${error.message}`;
                        document.getElementById("successMessage").style.display = "block";
                    });
                }
            });
        });
    </script>
    <!-- send message -->
    <script type="text/javascript">
        const sendMessageModal = new bootstrap.Modal(document.getElementById("sendMessagePopup"));
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".sendMessageButton").forEach(button => {
                button.addEventListener("click", function() {
                    let employeeId = this.dataset.employeeId;
                    sendMessageModal.show();
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
                    showNotification('Message successfully sent', 'success');
                    console.log('Success:', data);
                    sendMessageModal.hide();

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
