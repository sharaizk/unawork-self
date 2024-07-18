<style>
    .hidden {
        display: none;
    }
</style>
<div class="section__general_info">
    <h3 class="title_box">Members</h3>
    <a href="#" class="buttonViewAll">ViewAll <i class="fa fa-chevron-right"></i></a>

    <div class="members__info_list">

        @foreach($users as $index => $user)
                    
        <div class="members__info_list--item" {!! $index > 2 ? 'style="display: none;"' : '' !!}>
            <img alt="user-image" class="img-fluid rounded-circle img_users_fix_size"
                            @if ($user->image) src="{{ asset ('storage/'. $user->avatar) }}" @else avatar="{{ $user->name }}" @endif>
                        
            <div class="right">
                <h3 class="member_name"><a href="#">{{ $user->name }}</a></h3>
                @if (Auth::user())
                    @if (Auth::user()->id != $user->id)
                        <button class="btn btn-secondary sendMessageButton" data-employee-id="{{ $user->id }}">
                            <span> Message </span> 
                        </button>
                    @endif
                @else
                    <a href="{{ route('login') }}"><span>Message</span></a>
                @endif
                
                <p class="content">
                    <strong>Country:</strong> {{$user->profile->country ?? 'n/a'}}
                    <strong>State:</strong> {{$user->profile->state ?? 'n/a'}}
                    <strong>City:</strong> {{$user->profile->city ?? 'n/a'}}
                    <strong>Skills:</strong>
                    @forelse ($user->skills as $skill)
                        {{$skill->name}},
                    @empty
                        n/a
                    @endforelse
                </p>
                <!-- <a href="" class="learn-more">Learn more</a> -->
            </div>
        </div>

        @endforeach
        
    </div>

</div>

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
            let message = document.getElementById("messageInput").value;
            let employeeId = this.dataset.employeeId;
            let data = {
                id: employeeId,
                type: 'user',
                message: message.replace(/\s/g, ''),
                temporaryMsgId: 'temp_1',
                _token: '{{ csrf_token() }}'
            };

            fetch('{{ ENV("APP_URL") }}chatify/api/sendMessage', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            })
            .then(response => response.json())
            .then(data => {
                console.log('Success:', data);
                    $("#sendMessagePopup").modal('hide');
            })
            .catch((error) => {
                console.error('Error:', error);
            });
        });
    });
</script>
