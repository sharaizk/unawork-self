@extends('layouts.landing')
@section('body-class', 'tpl-hub-shop')
@section('content')
@php
    $logo = \App\Models\Utility::get_file('users-avatar/');
@endphp
<link rel="stylesheet" href="{{ asset('assets/css/landing/hub-shop.css') }}">
<link rel="stylesheet" href="http://unawork.net/plugins/fancybox/fancybox.min.css">
<main>
    <div id="successMessage" class="alert alert-success" style="display: none;"></div>

    <div class="container-master main">
    <div class="row-master">
      <div class="col-12 col-md-12 col-xl-9">
        <section class="section__introduce">
          <div class="col-left">
            <img alt="user-image" class="img-fluid rounded-circle img_users_fix_size"
                                @if ($employee->avatar) src="{{ asset($logo . $employee->avatar) }}" @else avatar="{{ $employee->name }}" @endif>
                            
          </div>
          <div class="col-right">
            <div class="item">
              <div class="item__title">
                <h1>{{ $employee->name }}</h1>
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
                </ul>
              </div>
            </div>
          </div>

        </section>
        <section class="section__about section__layout">
          <div class="section__title">
            <h2>About</h2>
          </div>
          <div class="section__content">
            <p>
                <strong>Skills:</strong> 
                    @forelse ($employee->skills as $skill) 
                        {{$skill->name}},
                    @empty
                        n/a
                    @endforelse
                </p>
          </div>
        </section>
      </div>
      <div class="col-12 col-md-12 col-xl-3">
        <aside class="aside__container">
        
            @auth('web')
              <div class="aside__card">
              <div class="aside__card__title">
                <span>Invite user</span>
              </div>
              <div class="aside__card__body">
                <span>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</span>
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
                    <span>Integer scelerisque leo a maximus volutpat. Cras commodo est sed lectus elementum
                        pulvinar.</span>
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
            @endauth

          <div class="aside__card">
            <div class="aside__card__title">
              <span>Online Video</span>
            </div>
            <div class="aside__card__body">
              <span>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</span>
            </div>
            <div class="aside__card__actions">
              <button>
                <span>
                  <img src="{{ asset('assets/images/landing/icon-video.png') }}" alt="">
                  Start Now
                </span>
              </button>
            </div>
          </div>
          
        </aside>
      </div>
    </div>
  </div>
</main>
<div class="modal" tabindex="-1" role="dialog" id="sendMessagePopup">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Send Message</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="text" id="messageInput" class="form-control" placeholder="Type your message...">
      </div>
      <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="sendMessage">Send</button>
      </div>
    </div>
  </div>
</div>
<div class="modal" tabindex="-1" role="dialog" id="inviteUserPopup">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Send Invite</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="selectItem">Select Workspace or Pod</label>
        <select id="selectItem" class="form-control">
            <option value="" selected disabled>Select Item</option>
            @foreach (Auth::user()->invitableWorkspaces($employee->id) as $workspace)
                <option value="workspace_{{ $workspace->slug }}">Company: {{ $workspace->name }}</option>
            @endforeach
            @foreach (Auth::user()->invitablePods($employee->id) as $pod)
                <option value="pod_{{ $pod->id }}">POD: {{ $pod->title }}</option>
            @endforeach
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="inviteUser">Send</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
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
                            document.getElementById("inviteUserPopup").style.display = "none";
                            document.getElementById("successMessage").innerText = 'User invited successfully!';
                            document.getElementById("successMessage").style.display = "block";
                        } else {
                            return response.json().then(data => {
                                console.error('Error:', data.message);
                                document.getElementById("successMessage").innerText = `Error: ${data.message}`;
                                document.getElementById("successMessage").style.display = "block";
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
                let message = document.getElementById("messageInput").value;
                let employeeId = this.dataset.employeeId;
                let data = {
                    id: employeeId,
                    type: 'user',
                    message: message.replace(/\s/g, ''),
                    temporaryMsgId: 'temp_1',
                    _token: '{{ csrf_token() }}'
                };

                fetch('/chatify/api/sendMessage', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data),
                })
                .then(response => response.json())
                .then(data => {
                    showNotification('Message successfully sended', 'success');
                    console.log('Success:', data);
                    sendMessageModal.hide();

                })
                .catch((error) => {
                    showNotification('Message sending failed:'.error, 'danger');
                    console.error('Error:', error);
                });
            });
        });
    </script>
@endsection