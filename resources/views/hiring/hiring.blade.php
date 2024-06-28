@extends('layouts.landing')
@section('body-class', 'tpl-outerPods')
@section('content')
<style>
    .header__search{
        background-color: #022d4d;
        font-size: 1.8rem;
    }

    .search__wrap {
        display: flex;
        align-items: center;
        font-size: 1.8rem;
    }

    .search__box {
        flex: 1;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        margin-right: 15px;
        font-size: 1.8rem;
    }

    .search__field {
        flex: 1;
        display: flex;
        align-items: center;
        margin-right: 15px;
        font-size: 1.8rem;
    }

    .search__icon {
        margin-right: 5px;
    }

    .search__list {
        flex: 1;
        margin-right: 15px;
        font-size: 1.8rem;
    }

    .form-control {
        flex: 1;
        margin-right: 5px;
        font-size: 1.8rem;
    }

    .btn-primary {
        padding: 10px 20px;
        font-size: 1.8rem;
    }

    .dropdown-menu-xl {
        min-width: 200px; 
        width: 300px; 
        max-height: 400px; 
        overflow-y: auto; 
    }

    .dropdown-menu-xl li {
        padding: 10px 20px; 
    }

    .dropdown-menu-xl .form-check-input {
        width: 20px; 
        height: 20px; 
        margin-right: 10px; 
    }

    .dropdown-menu-xl .form-check-label {
        font-size: 16px; 
    }

    .btn-xl {
        font-size: 1.5rem;
        padding: 10px 20px; 
    }
</style>
<!-- filter form -->
<section class="header__search">
    <div class="container-master">
        <div class="search__wrap">
            <form method="GET" action="{{ route('landing.hiring') }}" class="search__box">
                <div class="search__field">
                    <span class="search__icon">
                        <i class="fa fa-search"></i>
                    </span>
                    <input type="text" name="name" id="name" class="form-control" placeholder="Enter name" value="{{ request('name') ? request('name') : '' }}"">
                </div>
                <div class="search__field">
                    <select name="country" id="country" class="form-control">
                        <option value="">Select Country</option>
                        @foreach($countries as $country)
                            <option value="{{ $country }}" {{ request('country') == $country ? 'selected' : '' }}>{{ $country }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="search__list">
                    <div class="dropdown">
                        <button class="btn btn-xl btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                            Select Skills
                        </button>
                        <ul class="dropdown-menu dropdown-menu-xl">
                            @foreach($skills as $skill)
                            <li>
                                <input class="form-check-input" type="checkbox" name="skill_ids[]" id="skill_{{ $skill->id }}" value="{{ $skill->id }}" {{ in_array($skill->id, request('skill_ids', [])) ? 'checked' : '' }}>
                                <label class="form-check-label" for="skill_{{ $skill->id }}">
                                    {{ $skill->name }}
                                </label>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>
    </div>
</section>
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
                <div class="col-12 col-md-6 col-xl-3">
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
    document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.search__box');
    form.addEventListener('submit', function(event) {
        const name = document.getElementById('name');
        const country = document.getElementById('country');
        if (name.value.trim() === '') {
            name.remove();
        }
        if (country.value.trim() === '') {
            country.remove();
        }
    });
});
</script>
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