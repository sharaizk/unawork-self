@extends('layouts.admin')
@section('page-title')
    {{ __('Zoom Meeting') }}
@endsection
@section('links')
    @if (\Auth::guard('client')->check())
        <li class="breadcrumb-item"><a href="{{ route('client.home') }}">{{ __('Home') }}</a></li>
    @else
        <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    @endif
    <li class="breadcrumb-item"> {{ __('Zoom Meeting') }}</li>
@endsection
@php
    $logo = \App\Models\Utility::get_file('users-avatar/');
    $userWorkspace = \App\Models\UserWorkspace::where('user_id', Auth::id())->where('workspace_id', $currentWorkspace->id)->first();
    $settings = \App\Models\Utility::getPaymentSetting($currentWorkspace->id);
@endphp
@push('css-page')
    <style>
        .avatar-group .avatar {
            width: 2rem !important;
            height: 2rem !important;
        }

        .user-group img:hover {
            z-index: 5;
            cursor: default;
        }

        table .user-group img {
            position: relative;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            z-index: 2;
            transition: all 0.1s ease-in-out;
            border: 2px solid #ffffff;
        }
        @media (max-width: 340px) {
        .header_breadcrumb {
            width: 100% !important;
        }
        }
    </style>
@endpush


@section('action-button')
    @auth('web')
    <a href="{{ route('zoommeeting.Calender', $currentWorkspace->slug) }}" class="btn btn-sm btn-primary mx-1"
                id="" data-toggle="tooltip" title="{{ __('calendar') }}"> 
                <!-- <i class="ti ti-calendar"></i> -->
                {{ __('Calendar') }}
    </a>

    <a href="{{ route('zoom-meeting.landing', $currentWorkspace->slug) }}" class="btn btn-sm btn-primary mx-1"
                id="" data-toggle="tooltip" title="{{ __('Landing View') }}"> 
                    <!-- <i class="ti ti-eye"></i> -->
                    {{ __('Landing View') }}
    </a>
        @if (isset($currentWorkspace) && $currentWorkspace->creater->id == Auth::id() || $userWorkspace->hasPermissionTo('zoom_meeting_edit'))
            

            @if (
                !isset($settings['zoom_account_id']) || empty($settings['zoom_account_id']) ||
                !isset($settings['zoom_client_id']) || empty($settings['zoom_client_id']) ||
                !isset($settings['zoom_client_secret']) || empty($settings['zoom_client_secret'])
            ) 
                <button class="btn btn-sm btn-gray"
                        data-bs-toggle="tooltip" data-bs-placement="top" 
                        title="Zoom meeting settings are not set. 
                            @if ($currentWorkspace->creater->id == Auth::id()) 
                                Please fill in all the information in settings." onclick="window.location.href='{{ route('workspace.settings', $currentWorkspace->slug) }}#zoom-meeting-settings';" 
                            @else 
                                Contact workspace owner."
                            @endif>
                    <i class="ti ti-plus "></i>
                </button>
            @elseif (Auth::user()->isAbleToCreateZoomMeetings())
                <a href="#" 
                    class="btn btn-sm btn-primary" 
                    data-ajax-popup="true" 
                    data-size="lg"
                    data-title="{{ __('Create New Meeting') }}" 
                    data-toggle="tooltip" 
                    title="{{ __('Add Meeting') }}"
                    data-url="{{ route('zoom-meeting.create', $currentWorkspace->slug) }}">
                    <i class="ti ti-plus"></i>
                </a>
            @else
                <button class="btn btn-sm btn-gray"
                        data-bs-toggle="tooltip" data-bs-placement="top" 
                        title="Your plan does not allow creating Zoom meetings.">
                    <i class="ti ti-plus "></i>
                </button>
            @endif
            
        @endif
    @endauth

    @auth('client')
        <a href="{{ route('zoommeetings.Calender', $currentWorkspace->slug) }}" data-toggle="tooltip"
            title="{{ __('calendar') }}" class="btn btn-sm btn-primary mx-1" id=""> <i class="ti ti-calendar"></i>
        </a>
    @endauth
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table  dataTable" id="selection-datatable">
                            <thead>
                                <tr>
                                    <th> {{ __('Title') }} </th>
                                    <th> {{ __('Workspace') }} </th>
                                    <th> {{ __('Project') }} </th>
                                    <th> {{ __('Members') }} </th>
                                    @if (Auth::user()->type == 'user' && $currentWorkspace->creater->id == Auth::user()->id)
                                        <th> {{ __('Client') }} </th>
                                    @endif
                                    <th> {{ __('Meeting Time') }} </th>
                                    <th> {{ __('Duration') }} </th>
                                    <th> {{ __('Notes') }} </th>
                                    <th> {{ __('Join URL') }} </th>
                                    <th> {{ __('Status') }} </th>
                                    @if (Auth::user()->type == 'user' && $currentWorkspace->creater->id == Auth::user()->id || $userWorkspace->hasPermissionTo('zoom_meeting_edit'))
                                        <th class="text-right"> {{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($meetings as $item)
                                    <tr>
                                        <td>{{ $item->title }}</td>
                                        <td>{{ $item->workspace  ? $item->workspace->name : '---' }}</td>
                                        <td>{{ $item->project_name  ? $item->project_name : '---' }}</td>
                                        <td>
                                            <div class="user-group">
                                                @foreach ($item->getMembers() as $user)
                                                    <a href="#" class="img_group" data-toggle="tooltip"
                                                        data-placement="top" title="{{ $user->name }}"
                                                        data-original-title="{{ $user->name }}">
                                                        <img alt="{{ $user->name }}"
                                                            @if ($user->avatar) src="{{ asset($logo . $user->avatar) }}" @else avatar="{{ $user->name }}" @endif>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </td>
                                        @if (Auth::user()->type == 'user' && $currentWorkspace->creater->id == Auth::user()->id)
                                            <td>
                                                <div class="avatar-group hover-avatar-ungroup mb-3">
                                                    @foreach ($item->getClients() as $user)
                                                        <a href="#" class="avatar rounded-circle avatar-sm img_group"
                                                            data-toggle="tooltip" data-placement="top" title=""
                                                            data-original-title="{{ $user->name }}">
                                                            <img alt="{{ $user->name }}"
                                                                @if ($user->avatar) src="{{ asset($logo . $user->avatar) }}" @else avatar="{{ $user->name }}" @endif
                                                                style="border-radius: 50%; max-hight: 40px; max-width: 30px;">
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </td>
                                        @endif
                                        <td>{{ $item->start_date }}</td>
                                        <td>{{ $item->duration }} {{ __('Minutes') }}</td>
                                        <td class="meetings__item" data-meeting="{{ $item->toJson() }}">
                                            <button class="btn btn-secondary btn-sm">
                                                <i class="ti ti-list"></i>
                                            </button>
                                        </td>

                                        <td>
                                            @if ($item->created_by == \Auth::user()->id && $item->checkDateTime())
                                                <a href="{{ $item->join_url }}" target="_blank"> {{ __('Start meeting') }}
                                                    <i class="fas fa-external-link-square-alt "></i></a>
                                            @elseif($item->checkDateTime())
                                                <a href="{{ $item->join_url }}" target="_blank"> {{ __('Join meeting') }}
                                                    <i class="fas fa-external-link-square-alt "></i></a>
                                            @else
                                                -
                                            @endif

                                        </td>
                                        <td>
                                            @if ($item->checkDateTime())
                                                @if ($item->status == 'waiting')
                                                <span
                                                        class="badge badge-info p-2 px-3 bg-success rounded">{{ $item->status }}</span>
                                                @else
                                                    <span
                                                        class="badge badge-success p-2 px-3 bg-success rounded">{{ $item->status }}</span>
                                                @endif
                                            @else
                                                <span
                                                    class="badge badge-danger p-2 px-3 rounded">{{ __('End') }}</span>
                                            @endif
                                        </td>
                                        @if (Auth::user()->type == 'user' && $currentWorkspace->creater->id == Auth::user()->id || $userWorkspace->hasPermissionTo('zoom_meeting_edit'))
                                            <td class="text-right">

                                                <a href="#"
                                                    class="action-btn btn-danger  btn btn-sm d-inline-flex align-items-center bs-pass-para"
                                                    data-confirm="{{ __('Are You Sure?') }}"
                                                    data-text="{{ __('This action can not be undone. Do you want to continue?') }}"
                                                    data-confirm-yes="delete-form-{{ $item->id }}"
                                                    data-toggle="tooltip" title="{{ __('Delete') }}">
                                                    <i class="ti ti-trash"></i>
                                                </a>
                                                <form id="delete-form-{{ $item->id }}"
                                                    action="{{ route('zoom-meeting.destroy', [$currentWorkspace->slug, $item->id]) }}"
                                                    method="POST" style="display: none;">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal -->
    <div class="modal fade" id="meeting-modal" tabindex="-1" aria-labelledby="meetingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="meetingModalLabel">Meeting Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h4 id="meeting-title" class="text-center"></h5>
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

    <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg ss_modale" role="document">
            <div class="modal-content image_sider_div">

            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // filtration
        const filterInput = document.getElementById('projectFilter');

        // filter func
        function filterTable() {
            const filterValue = filterInput.value.toLowerCase();
            const rows = document.querySelectorAll('#meetingsTableBody tr');

            rows.forEach(row => {
                const projectName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                if (projectName.includes(filterValue)) {
                    row.style.display = ''; // show row
                } else {
                    row.style.display = 'none'; // hide row
                }
            });
        }

        // filter
        filterInput.addEventListener('input', filterTable);
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

@endpush
