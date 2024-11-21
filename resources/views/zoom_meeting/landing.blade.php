@extends('layouts.landing')

@section('body-class', 'tpl-meeting')
@section('title', 'Zoom Meetings')

@section('content')
<!-- Підключення стилів Choices.js -->
<link href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" rel="stylesheet">

<!-- Підключення JavaScript Choices.js -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>    
    <main>

        <section class="header-2">
            <div class="container-master">
                <nav class="nav__menu">
                <ul class="nav__list">
                    <li class="nav__item menu__has-children" data-dropdown="1">
                    <a href="/meeting.php" class="active">Meetings
                        <svg xmlns="http://www.w3.org/2000/svg" width="10.5" height="5.535" viewBox="0 0 10.5 5.535">
                        <path id="Path" d="M9.878.113a.441.441,0,0,0-.589,0L5,4.072.711.113a.441.441,0,0,0-.589,0,.364.364,0,0,0,0,.544L4.705,4.887a.441.441,0,0,0,.589,0L9.878.657A.364.364,0,0,0,9.878.113Z" transform="translate(0.25 0.285)" fill="#cfaa40" stroke="#cfaa40" stroke-miterlimit="10" stroke-width="0.5"></path>
                        </svg>
                    </a>

                    <ul class="nav-sub__menu">
                        <li><a href="#">Sub Menu 1</a></li>
                        <li><a href="#">Sub Menu 2</a></li>
                        <li><a href="#">Sub Menu 3</a></li>
                        <li><a href="#">Sub Menu 4</a></li>
                    </ul>
                    </li>

                    <li class="nav__item menu__has-children" data-dropdown="2">
                    <a href="/teams.php" class="">Teams
                        <svg xmlns="http://www.w3.org/2000/svg" width="10.5" height="5.535" viewBox="0 0 10.5 5.535">
                        <path id="Path" d="M9.878.113a.441.441,0,0,0-.589,0L5,4.072.711.113a.441.441,0,0,0-.589,0,.364.364,0,0,0,0,.544L4.705,4.887a.441.441,0,0,0,.589,0L9.878.657A.364.364,0,0,0,9.878.113Z" transform="translate(0.25 0.285)" fill="#cfaa40" stroke="#cfaa40" stroke-miterlimit="10" stroke-width="0.5"></path>
                        </svg>
                    </a>

                    <ul class="nav-sub__menu">
                        <li><a href="#">Sub Menu 1</a></li>
                        <li><a href="#">Sub Menu 2</a></li>
                        <li><a href="#">Sub Menu 3</a></li>
                        <li><a href="#">Sub Menu 4</a></li>
                    </ul>
                    </li>

                    <li class="nav__item">
                    <a href="/document.php" class="">Docs</a>
                    </li>
                    <li class="nav__item">
                    <a href="/task.php" class="">Tasks</a>
                    </li>
                    <li class="nav__item">
                    <a href="/document.php">Co. Notes</a>
                    </li>
                    <li class="nav__item">
                    <a href="/finance.php" class="">Finances</a>
                    </li>
                    <li class="nav__item">
                    <a href="/document.php">Resources</a>
                    </li>
                </ul>
                </nav>
            </div>
        </section>

        <!-- topAlert -->
        <section class="header__alert">
            <div class="container-master">
            <div class="header__alert__content">
                <img src="{{ asset('/assets/images/landing/meetings_ic_notif.png') }}" alt="iconAlert">
                <p>You’ve Been Pinged to the Office - New Meeting at 14:30</p>
            </div>
            </div>
        </section>
        <!-- end topAlert -->
        <!-- title Page -->
        <section class="section__titlepage">
            <div class="container-master">
                <h1 class="section__titlepage--headertitle"><span>Meetings</span></h1>
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
                            @if (!empty($projects) && $projects->count() > 1)
                            <option value="" disabled selected>Choose Project</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" {{ request('project') == $project->id ? 'selected' : '' }}>
                                        {{ $project->name }}
                                    </option>
                                @endforeach
                            @else
                                <option value="" disabled selected>No meetings in projects</option>
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
                        <button type="submit" class="filter-button">Search</button>
                    </div>
                </form>
            </div>
        </section>
        <!-- end title Page -->
        <!-- Meeting -->
        <section class="meetings ">
            <div class="container-master">
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
                                    <div class="meetings__item">
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
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const multipleChoices = new Choices('.choices-multiple', {
                removeItemButton: true,          // Кнопка для видалення тегів
                placeholder: true,
                placeholderValue: 'Choose Participants', // Місцева підказка
            });
        });
    </script>
@endsection

@section('scripts')

@endsection