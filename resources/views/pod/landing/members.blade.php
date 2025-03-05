<style>
    .hidden {
        display: none;
    }
</style>
<div class="section__general_info">
    <div class="d-flex align-items-center justify-content-between section__general_info-head">
        <h3 class="title_box">Members</h3>
        <a href="#" class="buttonViewAll">View All <i class="fa fa-chevron-right"></i></a>
    </div>

    <div class="members__info_list">

        @foreach($users as $index => $user)

        <div class="members__info_list--item" {!! $index > 2 ? 'style="display: none;"' : '' !!}>
            <img alt="user-image" class="img-fluid rounded-circle img_users_fix_size"
                @if ($user->avatar) src="{{ App\Helpers\FileHelper::getUrl('users-avatar/' . $user->avatar, 'small') }}" @else avatar="{{ $user->name }}" @endif>

            <div class="right">
                <div>
                    <h3 class="member_name"><a href="#">{{ $user->name }}</a></h3>

                    @if ($user->isEmployee())
                    <div class="content">
                        <span>Skills: </span>
                        <!-- <strong>Country:</strong> {{$user->profile->country ?? 'n/a'}}
                        <strong>State:</strong> {{$user->profile->state ?? 'n/a'}}
                        <strong>City:</strong> {{$user->profile->city ?? 'n/a'}}
                        <strong>Skills:</strong> -->
                        @forelse ($user->skills as $skill)
                            <div class="content__skill">{{$skill->name}}</div>
                        @empty
                            n/a
                        @endforelse
                    </div>
                    @elseif ($user->isCompany())
                        @php
                            $company = $user->ownWorkspace();
                            $addressParts = array_filter([
                                $company->address,
                                $company->city,
                                $company->state,
                                $company->country,
                                $company->zip_code
                            ]);
                            $address = implode(', ', array_filter($addressParts));
                        @endphp
                        <p class="content">

                            <strong>Address: </strong>{{ $address ?: 'n/a' }}
                            @if($company->telephone)
                                    <br/>
                                    <i class="fa fa-phone"></i>
                                    <a href="tel:{{$company->telephone}}" title="Phone">
                                        {{$company->telephone}}
                                    </a>
                            @endif
                            @if($company->website)
                                <br/>
                                    <i class="fa fa-globe"></i>
                                    @php
                                        $website = $company->website;
                                        if (!preg_match('#^http(s)?://#', $website)) {
                                            $website = 'http://' . $website;
                                        }
                                    @endphp
                                    <a href="{{ $website }}" title="Website">Visit Website</a>
                            @endif
                        </p>
                    @endif
                </div>
                <div class="buttons">
                    @if (Auth::user())
                        @if (Auth::user()->id != $user->id)
                            <button class="btn btn-primary sendMessageButton" data-employee-id="{{ $user->id }}">
                                <span> Message </span>
                            </button>
                        @endif
                    @else
                        <a href="{{ route('login') }}"><span>Message</span></a>
                    @endif
                </div>
                <!-- <a href="" class="learn-more">Learn more</a> -->
            </div>
        </div>

        @endforeach

        <a href="#" class="buttonViewAll-mobile">
            View All <i class="fa fa-chevron-right"></i>
        </a>

    </div>

</div>

<div class="modal" tabindex="-1" role="dialog" id="sendMessagePopup">
  <div class="modal-dialog modal-dialog-centered" role="document">
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

<script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function () {
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
            .then(response => {
                if (!response.ok) {
                    return response.json().then(error => {
                        throw new Error(error.message || 'Something went wrong');
                        showNotification('Message sending failed:'.error.message, 'danger');
                    });
                }
                return response.json();
            })
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
