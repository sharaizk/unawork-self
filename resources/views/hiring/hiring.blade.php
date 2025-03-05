@extends('layouts.landing')
@section('title', 'Hire')

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
        gap: 5px;
        font-size: 1.8rem;
        height: 60px;
    }

    @media (max-width: 991px) {
        .search__wrap {
            padding-top: 10px;
            padding-bottom:  10px;
            height: max-content;
        }
    }

    .search__box {
        flex: 1;
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
        align-items: center;
        margin-right: 15px;
        font-size: 1.8rem;
    }

    .search__field {
        height: 40px;
        background-color: #fff;
        border: 1px solid #88a6b5;
        border-radius: 3px;
        overflow: hidden;
        justify-content: center;
        align-items: center;
        padding: 0 15px;
        margin-right: 10px;
        display: flex;
        color: #022d4d;
    }

    .skills-button {
        background-color: #fff;
        color: #022d4d;
        height: 40px;
        font-size: 1.4rem;
    }

    .filter-btn {
        height: 40px;
        font-size: 1.4rem;
    }

    .skills-dropdown-menu {
        min-width: 130px;
        width: 150px;
        max-height: 400px;
        overflow-y: auto;
    }


    .skills-dropdown-menu li {
        padding: 10px 20px;
    }

    .skills-dropdown-menu .form-check-input {
        width: 15px;
        height: 15px;
        margin-right: 6px;
    }

    .skills-dropdown-menu .form-check-label {
        font-size: 12px;
    }

    .search__icon {
        padding-right: 10px;
        color: #022d4d;
        font-size: 1rem;
    }

    .search__list {
        margin-right: 15px;
        font-size: 1.8rem;
    }

    .form-control {
        flex: 1;
        margin-right: 5px;
        font-size: 1.5rem;
        border: none;
    }

    .dropdown-menu-xl {
        min-width: 40vh;
        width: 40vh;
        max-height: 380px;
        overflow-y: auto;
        border: 2px solid #022D4D;
    }

    .dropdown-menu-xl .form-check-input:checked {
        background-color: #E8BE61;
    }

    .dropdown-menu-xl li {
        padding: 10px 20px;
    }

    .dropdown-menu-xl .form-check-input {
        width: 15px;
        height: 15px;
        margin-right: 6px;
    }

    .dropdown-menu-xl .form-check-label {
        font-size: 14px;
        font-weight: 600;
        color: #022D4D;
    }

    .btn-xl {
        font-size: 1.5rem;
        padding: 10px 20px;
    }

    .dropdown {
        display: flex;
    }
</style>
<!-- filter form -->
<section class="header__search">
    <div class="container">
        <div class="search__wrap">
            <form id="searchForm" class="search__box">
                <div class="search__field">
                    <span class="search__icon">
                        <i class="fa fa-search"></i>
                    </span>
                    <input type="text" name="name" id="name" class="form-control" placeholder="Enter name" value="{{ request('name') ? request('name') : '' }}">
                </div>
                <div class="search__field">
                    <select name="country" id="country" class="form-control">
                        <option value="">Select Country</option>
                        @foreach($countries as $country)
                            <option value="{{ $country }}" {{ request('country') == $country ? 'selected' : '' }}>{{ $country }}</option>
                        @endforeach
                    </select>
                </div>
                @php
                    $jobTitles = \App\Models\JobTitle::all();
                @endphp
                <div class="search__field">
                    <select name="job_title_id" id="job_title_id" class="form-control">
                        <option value="">Select Job Title</option>
                        @foreach($jobTitles as $jobTitle)
                            <option value="{{ $jobTitle->id }}" {{ request('job_title_id') == $jobTitle->id ? 'selected' : '' }}>{{ $jobTitle->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="search__list">
                    <div class="dropdown">
                        <button class="btn btn-secondary dropdown-toggle skills-button" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                            Select Skills
                        </button>
                        <ul class="dropdown-menu dropdown-menu-xl skills-dropdown-menu">
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
{{--                <button type="submit" class="btn btn-primary filter-btn header-search-btn">Search</button>--}}
            </form>
        </div>
    </div>
</section>
<!-- <section class="hire__banner">
    <img src="{{ asset('assets/images/landing/bannerHire.jpg') }}" alt="">
</section> -->
<section class="hire__wrapper">
    <div class="container">
        <h1 class="hire__title">
            <span>Employees</span>
        </h1>
        <h4 class="hire__subtitle">
            <span>Find Empoyees</span>
        </h4>
        <p class="hire__descr">Looking for skilled support? Browse through our available profiles and speed up your time to hire. Click on any profile to view their skillset and CV. Open them to your network to find how they can support your growth. </p>
        <div class="row-master" id="employeeContainer">
            <p class="text-center">Enter filters to find employees...</p>
        </div>
        <div class="pagination-container d-flex justify-content-center mt-4" id="paginationContainer"></div>
    </div>
</section>


<div class="modal fade" tabindex="-1" role="dialog" id="sendMessagePopup">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header text-white">
        <h5 class="modal-title">Send Message</h5>
        <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label for="messageInput" class="form-label">Your Message:</label>
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
@endsection

@section('scripts')
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('searchForm');
            const employeeContainer = document.getElementById('employeeContainer');
            const paginationContainer = document.getElementById('paginationContainer');

            function fetchEmployees(url) {
                fetch(url, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(response => response.json())
                    .then(data => {
                        employeeContainer.innerHTML = data.employees;
                        paginationContainer.innerHTML = data.pagination;
                        attachPaginationListeners();

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
                    })
                    .catch(error => console.error('Error:', error));
            }

            function debounce(func, delay) {
                let timer;
                return function () {
                    clearTimeout(timer);
                    timer = setTimeout(() => func.apply(this, arguments), delay);
                };
            }

            function triggerSearch() {
                let formData = new FormData(form);
                let params = new URLSearchParams(formData).toString();
                fetchEmployees(`{{ route('hiring.search') }}?${params}`);
            }

            const debouncedSearch = debounce(triggerSearch, 300);

            form.addEventListener('input', debouncedSearch);
            form.addEventListener('change', debouncedSearch);
            document.querySelectorAll('.form-check-input').forEach(checkbox => {
                checkbox.addEventListener('click', debouncedSearch);
            });

            function attachPaginationListeners() {
                document.querySelectorAll('.pagination-container a').forEach(link => {
                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        fetchEmployees(this.href);
                    });
                });
            }

            fetchEmployees(`{{ route('hiring.search') }}`);
        });
    </script>




@endsection
