@php
    $profitAttachment = \App\Models\Utility::get_file('profit/');
    $taskName = \App\Models\Task::find($profitDetails->task_id);
@endphp
@if ($project && $currentWorkspace)
    {{-- <form class="">
        @csrf
        <div class="modal-body">
            <div class="row">
                <div class="form-group col-md-12">
                    <label class="col-form-label">{{ __('Project Name') }}</label>
                    <input type="text" class="form-control" id="project_name" placeholder="{{ __('Project Name') }}"
                        name="project_name" value="{{ $project->name }}" disabled>
                </div>

                <div class="form-group col-md-6">
                    <label class="col-form-label">{{ __('Profit Name') }}</label>
                    <input type="text" class="form-control" id="profit_name" placeholder="{{ __('Enter Title') }} "
                        value="{{ $profitDetails->title }}" name="profit_name" required>
                </div>

                <div class="form-group col-md-6">
                    <label for="assign_to" class="col-form-label">{{ __('Tasks') }}</label>
                    <select class="form-control select" id="tasks" name="tasks" required>
                        <option selected disabled>Select Tasks</option>
                        @foreach ($allTask as $task)
                            <option value="{{ $task->id }}"
                                {{ $profitDetails->task_id == $task->id ? 'selected' : '' }}>{{ $task->title }}
                            </option>
                        @endforeach

                    </select>
                </div>


                <div class="row d-flex align-items-center justify-content-center">
                    <div class="form-group col-md-6">
                        <label class="form-label">{{ __('Select Date') }}</label>
                        <div class="input-group date">
                            <input class="form-control datepicker2" type="text" id="date" name="date"
                                value="{{ $profitDetails->date }}" autocomplete="off" required>
                            <span class="input-group-text">
                                <i class="feather icon-calendar"></i>
                            </span>
                        </div>
                    </div>
    
                    <div class="form-group col-md-6">
                        <label class="col-form-label">{{ __('Amount') }}</label>
                        <input type="text" class="form-control" id="amount" placeholder="{{ __('Enter Amount') }}"
                            value="{{ $profitDetails->amount }}" name="amount" required>
                    </div>
                </div>
               




                <div class="form-group col-md-12 mb-0">
                    <label for="task-description" class="col-form-label">{{ __('Description') }}</label>
                    <textarea rows="3" class="form-control" id="description" name="description" required> {{ $profitDetails->description }}</textarea>
                </div>

                <div class="form-group col-md-12">
                    <label class="form-label" for="attachment">Attachment</label>
                    <input type="file" name="attachment" class="form-control">
                </div>
                <div class="mt-2">
                    <img class="img-fluid" src="{{ asset($profitAttachment . $profitDetails->image) }}"
                        alt="" height="100" width="100">
                </div>
            </div>
        </div>
    </form> --}}
    <div class="row p-3">
        <div class="col-12">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label" for="bank_details"
                        class="form-label">{{ __('Project Name : ') }}</label><br>
                </div>
                <div class="col-md-6">
                    {{ $project->name }}
                </div>
            </div>
            <hr class="my-3">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label" for="bank_details"
                        class="form-label">{{ __('Profit Name : ') }}</label><br>
                </div>
                <div class="col-md-6">
                   {{$profitDetails->title}}
                </div>
            </div>
            <hr class="my-3">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label" for="bank_details"
                        class="form-label">{{ __('Date : ') }}</label><br>
                </div>
                <div class="col-md-6">
                    {{$profitDetails->date}}
                </div>
            </div>
            <hr class="my-3">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label" for="bank_details"
                        class="form-label">{{ __('Amount : ') }}</label><br>
                </div>
                <div class="col-md-6">
                    {{$profitDetails->amount}}
                </div>
            </div>
            <hr class="my-3">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label" for="bank_details"
                        class="form-label">{{ __('Description : ') }}</label><br>
                </div>
                <div class="col-md-6">
                    {{$profitDetails->description}}
                </div>
            </div>
            <hr class="my-3">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label" for="bank_details"
                        class="form-label">{{ __('Attachment : ') }}</label><br>
                </div>
                @if ($profitDetails->image)
                    <div class="col-md-6">
                        <img class="img-fluid" src="{{ asset($profitAttachment . $profitDetails->image) }}"
                        alt="" height="100" width="100">
                    </div>
                @else 
                    <div class="col-md-6">
                        <span>Attachment is empty</span>
                    </div>
                @endif
            </div>
        </div>
        <hr class="my-3">
    </div>


    <script>
        (function() {
            const d_week = new Datepicker(document.querySelector('.datepicker2'), {
                buttonClass: 'btn',
                todayBtn: true,
                clearBtn: true,
                format: 'yyyy-mm-dd',
            });
        })();
    </script>

    <script>
        (function() {
            const d_week = new Datepicker(document.querySelector('.datepicker3'), {
                buttonClass: 'btn',
                todayBtn: true,
                clearBtn: true,
                format: 'yyyy-mm-dd',
            });
        })();
    </script>
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
                            <p class="text-muted mt-3">
                                {{ __("It's looking like you may have taken a wrong turn. Don't worry... it happens to the best of us. Here's a little tip that might help you get back on track.") }}
                            </p>
                            <div class="mt-3">
                                <a class="btn-return-home badge-blue" href="{{ route('home') }}"><i
                                        class="fas fa-reply"></i> {{ __('Return Home') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
