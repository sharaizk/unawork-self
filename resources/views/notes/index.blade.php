@extends('layouts.admin')

@section('page-title') {{__(' Notes')}} @endsection
@section('links')
@if(\Auth::guard('client')->check())
<li class="breadcrumb-item"><a href="{{route('client.home')}}">{{__('Home')}}</a></li>
 @else
 <li class="breadcrumb-item"><a href="{{route('home')}}">{{__('Home')}}</a></li>
 @endif
<li class="breadcrumb-item"> {{ __('Notes') }}</li>
@endsection
@section('action-button')
    @auth('web')

      <a href="#" class="btn btn-sm btn-primary" data-toggle="tooltip" title="{{__('Create')}}" data-ajax-popup="true" data-size="lg" data-title="{{ __('Create New Note') }}" data-url="{{route('note.create',$currentWorkspace->slug)}}">
            <i class="ti ti-plus"></i>
        </a>


    @endauth
@endsection

@section('content')

    @if($currentWorkspace)
            <div class="row justify-content-between align-items-center mb-3">
                <div class="col-xl-12 col-lg-12 col-md-12 ps-0 pe-4">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="ti ti-search"></i>
                        </span>
                        <input type="text" id="notes-search" class="form-control" placeholder="{{ __('Search Notes...') }}">
                    </div>
                </div>
            </div>
            <div class="row justify-content-center mb-3">
                <div class="col-auto">
                    <button class="btn btn-primary" id="show-personal">{{ __('Personal Notes') }}</button>
                    <button class="btn btn-outline-primary" id="show-shared">{{ __('Shared Notes') }}</button>
                </div>
            </div>
        <section class="section d-none" id="personal-notes-section">
            @if(count($personal_notes) > 0)
                <div class="row">
                    <div class="col-lg-12">
                        <div class="row notes-list" id="personal-notes-container">
                            @foreach($personal_notes as $note)
                                <div class="col-md-4">
                                    <div class="card ">
                                         <div class="{{$note->color}} note_color_dot px-1"></div>
                                        <div class="card-header">
                                            <h5  class="">{{$note->title}}</h5>
                                        <div class="card-header-right">
                                            <div class="btn-group card-option">
                                                <button type="button" class="btn dropdown-toggle"
                                                    data-bs-toggle="dropdown" aria-haspopup="true"
                                                    aria-expanded="false">
                                                    <i class="feather icon-more-vertical"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a href="#" class="dropdown-item" data-ajax-popup="true" data-size="lg" data-title="{{ __('Edit Note') }}" data-url="{{route('notes.edit',[$currentWorkspace->slug,$note->id])}}">
                                                    <i class="ti ti-edit"></i> <span>{{ __('Edit') }}</span>
                                                </a>
                                                <a href="#" class="dropdown-item bs-pass-para" data-confirm="{{__('Are You Sure?')}}" data-text="{{__('This action can not be undone. Do you want to continue?')}}"  data-confirm-yes="delete-form-{{$note->id}}">
                                                   <i class="ti ti-trash"></i>  <span>{{ __('Delete')}}</span>
                                                </a>
                                                <form id="delete-form-{{$note->id}}" action="{{ route('notes.destroy',[$currentWorkspace->slug,$note->id]) }}" method="POST" style="display: none;">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                                </div>
                                            </div>
                                        </div>
                                        </div>
                                        <div class="card-body" style=" height: 240px !important;" >
                                             <div class="scrollText note-text ">
                                                {{$note->text}}

                                            </div>
                                            @if($note->files && $note->files->count() > 0)
                                                <div class="scrollFiles note-text mt-2">
                                                    <div class="mt-2 d-flex flex-wrap align-items-center">
                                                        @foreach($note->files->take(2) as $file)
                                                            <div class="badge bg-light text-dark me-2 mb-2 d-flex align-items-center">
                                                                <a href="{{ asset('storage/notes_files/' . $file->file_path) }}" target="_blank" class="text-decoration-none text-dark">
                                                                    <i class="ti ti-file"></i> {{ $file->file_name }}
                                                                </a>
                                                                <a href="{{ route('notes.file.delete', [$currentWorkspace->slug, $file->id]) }}" class="text-danger ms-2">
                                                                    <i class="ti ti-x"></i>
                                                                </a>
                                                            </div>
                                                        @endforeach
                                                        @if($note->files->count() > 2)
                                                            <div class="badge bg-light text-dark me-2 mb-2 d-flex align-items-center">
                                                                <a href="#" class="text-decoration-none text-dark" data-ajax-popup="true"
                                                                   data-size="lg" data-title="{{ __('Edit Note') }}"
                                                                   data-url="{{route('notes.edit',[$currentWorkspace->slug,$note->id])}}">
                                                                    <i class="ti ti-eye"></i>{{ __(' All files') }}
                                                                </a>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div id="personal-notes-pagination" class="d-flex justify-content-center mt-4"></div>
            @else
                <div class="card">
                    <div class="card-body p-4">
                        <div class="page-error">
                            <div class="page-inner">
                                <div class="page-description">
                                    {{ __('No Personal Notes available') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </section>

        <section class="section" id="shared-notes-section">
            @if(count($shared_notes) > 0)
                <div class="row">
                    <div class="col-lg-12">
                        <div class="row notes-list" id="shared-notes-container">
                               @foreach($shared_notes as $note)
                                <div class="col-md-4">
                                    <div class="card  ">
                                          <div class="{{$note->color}} note_color_dot px-1"></div>
                                        <div class="card-header">
                                            <h5  class="">{{$note->title}}</h5>
                                        <div class="card-header-right">
                                            <div class="btn-group card-option">
                                                <button type="button" class="btn dropdown-toggle"
                                                    data-bs-toggle="dropdown" aria-haspopup="true"
                                                    aria-expanded="false">
                                                    <i class="feather icon-more-vertical"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a href="#" class="dropdown-item" data-ajax-popup="true" data-size="lg" data-title="{{ __('Edit Note') }}" data-url="{{route('notes.edit',[$currentWorkspace->slug,$note->id])}}">
                                                    <i class="ti ti-edit"></i> <span>{{ __('Edit') }}</span>
                                                </a>
                                                <a href="#" class="dropdown-item bs-pass-para" data-confirm="{{__('Are You Sure?')}}" data-text="{{__('This action can not be undone. Do you want to continue?')}}" data-confirm-yes="delete-form-{{$note->id}}">
                                                   <i class="ti ti-trash"></i>  <span>{{ __('Delete')}}</span>
                                                </a>
                                                <form id="delete-form-{{$note->id}}" action="{{ route('notes.destroy',[$currentWorkspace->slug,$note->id]) }}" method="POST" style="display: none;">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                                </div>
                                            </div>
                                        </div>
                                        </div>
                                        <div class="card-body" style=" height: 240px !important;" >
                                            <div class="scrollText note-text ">
                                                {{$note->text}}

                                            </div>
                                            @if($note->files && $note->files->count() > 0)
                                                <div class="scrollFiles note-text mt-2">
                                                    <div class="mt-2 d-flex flex-wrap align-items-center">
                                                        @foreach($note->files->take(2) as $file)
                                                            <div class="badge bg-light text-dark me-2 mb-2 d-flex align-items-center">
                                                                <a href="{{ asset('storage/notes_files/' . $file->file_path) }}" target="_blank" class="text-decoration-none text-dark">
                                                                    <i class="ti ti-file"></i> {{ $file->file_name }}
                                                                </a>
                                                                <a href="{{ route('notes.file.delete', [$currentWorkspace->slug, $file->id]) }}" class="text-danger ms-2">
                                                                    <i class="ti ti-x"></i>
                                                                </a>
                                                            </div>
                                                        @endforeach
                                                        @if($note->files->count() > 2)
                                                            <div class="badge bg-light text-dark me-2 mb-2 d-flex align-items-center">
                                                                <a href="#" class="text-decoration-none text-dark" data-ajax-popup="true"
                                                                   data-size="lg" data-title="{{ __('Edit Note') }}"
                                                                   data-url="{{route('notes.edit',[$currentWorkspace->slug,$note->id])}}">
                                                                    <i class="ti ti-eye"></i>{{ __(' All files') }}
                                                                </a>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div id="shared-notes-pagination" class="d-flex justify-content-center mt-4"></div>
            @else
                <div class="card">
                    <div class="card-body p-4">
                        <div class="page-error">
                            <div class="page-inner">
                                <div class="page-description">
                                    {{ __('No Shared Notes available') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </section>
    @else
        <div class="container mt-5">
            <div class="card">
                <div class="card-body p-4">
                    <div class="page-error">
                        <div class="page-inner">
                            <h1>404</h1>
                            <div class="page-description">
                                {{ __('Page Not Found') }}
                            </div>
                            <div class="page-search">
                                <p class="text-muted mt-3">{{ __("It's looking like you may have taken a wrong turn. Don't worry... it happens to the best of us. Here's a little tip that might help you get back on track.")}}</p>
                                <div class="mt-3">
                                    <a class="btn-return-home badge-blue" href="{{route('home')}}"><i class="fas fa-reply"></i> {{ __('Return Home')}}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const itemsPerPage = 3;
            const searchInput = document.getElementById('notes-search');

            const personalNotesSection = document.getElementById('personal-notes-section');
            const sharedNotesSection = document.getElementById('shared-notes-section');
            const showPersonalBtn = document.getElementById('show-personal');
            const showSharedBtn = document.getElementById('show-shared');

            function paginateNotes(notesContainerId, paginationContainerId) {
                const notesContainer = document.getElementById(notesContainerId);
                const notes = Array.from(notesContainer.querySelectorAll('.card'));
                const paginationContainer = document.getElementById(paginationContainerId);
                let filteredNotes = notes;
                let currentPage = 1;

                function displayNotes(notesToDisplay, page) {
                    notesContainer.innerHTML = '';
                    const start = (page - 1) * itemsPerPage;
                    const end = start + itemsPerPage;

                    const notesToShow = notesToDisplay.slice(start, end);

                    if (notesToShow.length === 0) {
                        notesContainer.innerHTML = `<p class="text-center mt-4">{{ __('No notes found') }}</p>`;
                        paginationContainer.innerHTML = '';
                        return;
                    }

                    const rowDiv = document.createElement('div');
                    rowDiv.className = 'row';

                    notesToDisplay.slice(start, end).forEach(note => {
                        note.classList.add('col-md-4');
                        rowDiv.appendChild(note);
                    });

                    notesContainer.appendChild(rowDiv);
                }

                function createPagination(notesToPaginate) {
                    paginationContainer.innerHTML = '';

                    const totalPages = Math.ceil(notesToPaginate.length / itemsPerPage);

                    if (totalPages === 0) return;

                    for (let i = 1; i <= totalPages; i++) {
                        const button = document.createElement('button');
                        button.textContent = i;
                        button.className = `btn mx-1 ${i === currentPage ? 'btn-primary' : 'btn-outline-primary'}`;
                        button.addEventListener('click', function () {
                            currentPage = i;
                            displayNotes(filteredNotes, currentPage);
                            createPagination(filteredNotes);
                        });
                        paginationContainer.appendChild(button);
                    }
                }

                function filterNotes() {
                    const searchTerm = searchInput.value.toLowerCase();

                    filteredNotes = notes.filter(note => {
                        const noteTitle = note.querySelector('.card-header h5').textContent.toLowerCase();
                        return noteTitle.includes(searchTerm);
                    });

                    currentPage = 1;
                    displayNotes(filteredNotes, currentPage);
                    createPagination(filteredNotes);
                }

                searchInput.addEventListener('input', filterNotes);

                displayNotes(filteredNotes, currentPage);
                createPagination(filteredNotes);
            }

            function toggleNotesView(showPersonal) {
                if (showPersonal) {
                    personalNotesSection.classList.remove('d-none');
                    sharedNotesSection.classList.add('d-none');
                    showPersonalBtn.classList.add('btn-primary');
                    showPersonalBtn.classList.add('active');
                    showPersonalBtn.classList.remove('btn-outline-primary');
                    showSharedBtn.classList.add('btn-outline-primary');
                    showSharedBtn.classList.remove('btn-primary');
                    showSharedBtn.classList.remove('active');
                } else {
                    personalNotesSection.classList.add('d-none');
                    sharedNotesSection.classList.remove('d-none');
                    showSharedBtn.classList.add('btn-primary');
                    showSharedBtn.classList.add('active');
                    showSharedBtn.classList.remove('btn-outline-primary');
                    showPersonalBtn.classList.add('btn-outline-primary');
                    showPersonalBtn.classList.remove('btn-primary');
                    showPersonalBtn.classList.remove('active');
                }
            }

            showPersonalBtn.addEventListener('click', function () {
                toggleNotesView(true);
            });

            showSharedBtn.addEventListener('click', function () {
                toggleNotesView(false);
            });

            paginateNotes('personal-notes-container', 'personal-notes-pagination');
            paginateNotes('shared-notes-container', 'shared-notes-pagination');

            toggleNotesView(true);
        });
    </script>
@endpush
