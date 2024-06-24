@extends('layouts.landing')

@section('content')
<section class="hire__banner">
    <img src="{{ asset('assets/images/landing/bannerHire.jpg') }}" alt="">
  </section>
    <section class="hire__wrapper">
        <div class="container-master">
            <h1 class="hire__title">
                <span>Employees</span>
            </h1>
            <div class="row-master">
                @foreach($employees as $employee)
                    <div class="col-12 col-md-6">
                        @include('hiring.employeecard', ['employee' => $employee])
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    
    <div class="modal" tabindex="-1" role="dialog" id="sendMessagePopup">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Send Message</h5>
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
@endsection


@section('scripts')
<script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".sendMessageButton").forEach(button => {
                button.addEventListener("click", function() {
                    let employeeId = this.dataset.employeeId;
                    document.getElementById("sendMessagePopup").style.display = "block";
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
                    document.getElementById("sendMessagePopup").style.display = "none";
                })
                .catch((error) => {
                    console.error('Error:', error);
                });
            });
        });
    </script>

@endsection
