@extends('layouts.landing')

@section('body-class', 'tpl-meeting')
@section('title', 'Zoom Meetings')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<style>
    .header__search{
        background-color: #022d4d;
        height: 60px;
    }
    .meetings {
        margin-bottom: 50px;
    }
    .section__titlepage--subtitle {
        margin-top: 30px;
    }
    .header__search .container {
        height: 100%;
    }
    .header__search .filters {
        display: flex;
        align-items: center;
        gap: 20px;
        height: 100%;
    }
    .header__search .choices,
    .header__search .choices__inner {
        height: 40px;
        margin-bottom: 0!important;
        padding: 0 4px;
    }
    .header__search #searchForm {
        height: 100%;
    }
    @media (max-width: 996px) {
        .header__search #searchForm {
            display: none;
        }
    }
    .header__search .filter-select {
        padding: 0 6px;
        border-radius: 4px;
    }
    .header-search-btn {
        background: #cfaa40!important;
        width : 200px;
    }
    .header-search-btn:hover {
        color: #022d4d!important;
        opacity: 0.8;
    }
    .clear__filters {
        display: flex;
        align-items: center;
        color: #FFF;
        font-size: 15px;
        font-weight: 700;
        margin-left: 10px;
    }

    .clear__filters:hover {
        text-decoration: underline!important;
    }

    @media (max-width: 996px) {
        .header__search .container {
            display: flex;
            align-items: center;
            padding-right: 10px;
        }
        .filters {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        .filter-mobile {
            display: block;
        }

        .filter-modal__header {
            margin-bottom: 30px;
        }

        .filter-modal .filter-select,
        .filter-modal .filter-date
          {
            height: 40px;
            width: 90%;
            margin: 0 auto;
            border-radius: 4px;
            padding: 0 10px;
        }
        .filter-modal .filter-btn {
            height: 40px!important;
            width: 60%;
            margin: 0 auto;
        }
    }
</style>
<section class="header__search">
    <div class="container">
    <form id="searchForm" method="GET" action="{{ route('zoom-meeting.landing', $currentWorkspace->slug) }}">
        <div class="filters">
        <select class="filter-select" name="workspace">
            <option value="" selected>Choose Workspace</option>
            @foreach ($workspaces as $workspace)
                <option value="{{ $workspace->id }}" {{ request('workspace') == $workspace->id ? 'selected' : '' }}>
                    {{$workspace->name}}
                </option>
            @endforeach
        </select>

        <select class="filter-select" name="project">
            @if (!empty($projects) && $projects->count() >= 1)
            <option value="" disabled selected>Choose Project</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" {{ request('project') == $project->id ? 'selected' : '' }}>
                        {{ $project->name }}
                    </option>
                @endforeach
            @else
                <option value="" disabled selected>No projects in meetings</option>
            @endif
        </select>

        <select class="filter-select choices-multiple" name="participants[]" multiple>
            @foreach ($participants as $participant)
                <option value="{{ $participant->id }}"
                    {{ is_array(request('participants')) && in_array($participant->id, request('participants')) ? 'selected' : '' }}>
                    {{ $participant->name }}
                </option>
            @endforeach
        </select>
        <input type="date" class="filter-date" name="date" value="{{ request('date') }}" placeholder="Choose Date">
        <button type="submit" class="btn btn-xl btn-primary filter-btn header-search-btn">Search</button>
        <a href="{{ route('zoom-meeting.landing', $currentWorkspace->slug) }}" class="clear__filters">Clear Filters</a>
    </div>
</form>
<button type="button" class="filter-mobile">Filters</button>
    </div>
</section>
    <main>
        <!-- header search  -->
        <section class="section__titlepage">
            <div class="container">
                <h1 class="section__titlepage--headertitle"><span>Meetings</span></h1>
                <h2 class="section__titlepage--subtitle">Planned Meetings</h2>
                <p>After Zoom is integrated, you can set up any meetings with other team members or guests. Stay connected with your team and network with other guests through our open meeting system. </p>
            </div>
        </section>
        <!-- end header search -->
        <!-- Meeting -->
        <section class="meetings ">
            <div class="container">
            <div class="row-master">
                <div class="col-12 col-lg-12">
                    <div class="meetings--content">
                        <h3 class="title_box">
                        My Meetings
                        </h3>
                        <div class="meetings__list">
                            @if ($meetings->isEmpty())
                                <p class="no-meetings">No Zoom meetings available.</p>
                            @else
                                @foreach ($meetings as $meeting)
                                    <div class="meetings__item" data-meeting="{{ $meeting->toJson() }}">
                                        <div class="timeMeeting">
                                            <p class="hour">{{ \Carbon\Carbon::parse($meeting->start_date)->format('h:i A') }}</p>
                                            <p class="date">{{ \Carbon\Carbon::parse($meeting->start_date)->format('m/d/Y') }}</p>
                                        </div>
                                        <div class="meetings__item--content">
                                        <div class="meetings__item--left">
                                            <h3>{{ $meeting->title }}</h3>
                                            @php
                                                $project  = $meeting->project;
                                                $workspace = $meeting->workspace;
                                                $participants = $meeting->getMembers();
                                            @endphp
                                            <p>Project:
                                                @if (!empty($project))
                                                    <a href=" {{ route('projects.show', [$currentWorkspace->slug, $project->id]) }} ">{{ $project->name }}</a>
                                                @else ——
                                                @endif
                                            </p>
                                            <p>
                                                Workspace: {{ $workspace->name }}
                                            </p>
                                            <p>
                                                Participants: {{ $meeting->countMembers() }}
                                            </p>
                                            <div class="memberMeeting">
                                                @foreach ($participants as $participant)
                                                    <a href="" class="project-person__link" data-toggle="tooltip" data-placement="top" title="{{$participant->name}}">
                                                        <img alt="{{$participant->name}}" @if ($participant->avatar) src="{{ App\Helpers\FileHelper::getUrl('users-avatar/' . $participant->avatar, 'small') }}" @else avatar="{{ $participant->name }}" @endif>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="meetings__item--right text-left">
                                            <h3 class="text-white"><i class="fa fa-info-circle"></i></h3>
                                            <p class="date"> <b>Date:</b>  {{ \Carbon\Carbon::parse($meeting->start_date)->format('Y-m-d') }}</p>
                                            <p class="hour"> <b>Time:</b>  {{ \Carbon\Carbon::parse($meeting->start_date)->format('H:i:s') }}</p>
                                            <p class="duration"><b>Duration:</b>  {{ $meeting->duration }} min.</p>
                                            <!-- @if ($meeting->created_by == \Auth::user()->id && $meeting->checkDateTime())
                                            <a href="{{ $meeting->join_url }}" target="_blank"></a><p class="duration"><b>Go to meeting:</b>  </p></a>
                                            @elseif($meeting->checkDateTime())
                                                <a href="{{ $meeting->join_url }}" target="_blank"> {{ __('Join meeting') }}
                                                    <i class="fas fa-external-link-square-alt "></i></a>
                                            @else
                                                -
                                            @endif -->

                                        </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </section>
        <!--end Meeting -->
    </main>

    <!-- Modal -->
    <div class="modal fade" id="meeting-modal" tabindex="-1" aria-labelledby="meetingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="meetingModalLabel">Meeting Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h2 id="meeting-title" class="text-center"></h2>
                <br>
                <label for="meeting-note"><strong>Your Notes:</strong></label>
                <textarea id="meeting-note" class="form-control" rows="8"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button id="save-note" type="button" class="btn btn-primary">Save</button>
            </div>
            </div>
        </div>
    </div>

    <div class="filter-modal">
        <div class="filter-modal__header">
            <p>Filters</p>
            <button type="button" class="close"></button>
        </div>
        <form method="GET" action="{{ route('zoom-meeting.landing', $currentWorkspace->slug) }}">
            <div class="filters">
            <select class="filter-select" name="workspace">
                <option value="" selected>Choose Workspace</option>
                @foreach ($workspaces as $workspace)
                    <option value="{{ $workspace->id }}" {{ request('workspace') == $workspace->id ? 'selected' : '' }}>
                        {{$workspace->name}}
                    </option>
                @endforeach
            </select>

            <select class="filter-select" name="project">
                @if (!empty($projects) && $projects->count() >= 1)
                <option value="" disabled selected>Choose Project</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" {{ request('project') == $project->id ? 'selected' : '' }}>
                            {{ $project->name }}
                        </option>
                    @endforeach
                @else
                    <option value="" disabled selected>No projects in meetings</option>
                @endif
            </select>

            <!-- <select class="filter-select choices-multiple" name="participants[]" multiple>
                @foreach ($participants as $participant)
                    <option value="{{ $participant->id }}"
                        {{ is_array(request('participants')) && in_array($participant->id, request('participants')) ? 'selected' : '' }}>
                        {{ $participant->name }}
                    </option>
                @endforeach
            </select> -->
            <input type="date" class="filter-date" name="date" value="{{ request('date') }}" placeholder="Choose Date">
            <button type="submit" class="btn btn-xl btn-primary filter-btn header-search-btn">Search</button>
            <a href="{{ route('zoom-meeting.landing', $currentWorkspace->slug) }}" class="clear__filters">Clear Filters</a>
        </div>
    </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterBtn = document.querySelector('.filter-mobile');
            const filterModal = document.querySelector('.filter-modal');
            const closeBtn = document.querySelector('.filter-modal .close');

            if (filterBtn && filterModal && closeBtn) {
                filterBtn.addEventListener('click', function () {
                    filterModal.classList.add('active');
                });

                closeBtn.addEventListener('click', function () {
                    filterModal.classList.remove('active');
                });

                filterModal.addEventListener('click', function (event) {
                    if (event.target === filterModal) {
                        filterModal.classList.remove('active');
                    }
                });
            }
        });

        document.addEventListener("DOMContentLoaded", function () {
            const multipleChoices = new Choices('.choices-multiple', {
                removeItemButton: true,
                placeholder: true,
                placeholderValue: 'Choose Participants',
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            const modal = new bootstrap.Modal(document.getElementById('meeting-modal'));
            const modalTitle = document.getElementById('meeting-title');
            const modalNote = document.getElementById('meeting-note');
            const saveNoteButton = document.getElementById('save-note');

            let currentMeetingId = null;

            // Open modal and populate data
            document.querySelectorAll('.meetings__item').forEach(button => {
                button.addEventListener('click', function () {
                    const meeting = JSON.parse(this.getAttribute('data-meeting'));
                    modalTitle.textContent = 'Title: ' + meeting.title;
                    modalNote.value = meeting.note || '';
                    currentMeetingId = meeting.id;
                    modal.show();
                });
            });

            // Save note
            saveNoteButton.addEventListener('click', function () {
                // Формування URL для запиту
                var urlTemplate = "{{ route('zoom_meeting.update-note', ['meetingId' => ':meetingId']) }}";
                var url = urlTemplate.replace(':meetingId', currentMeetingId);

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({ note: modalNote.value }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showNotification('Note updated successfully');
                        modal.hide();
                    } else {
                        showNotification('Failed to update note');
                    }
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred. Please try again.');
                });
            });
        });
    </script>
@endsection

@section('scripts')

@endsection
