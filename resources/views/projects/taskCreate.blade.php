@if ($project && $currentWorkspace)
    <form class="" method="post"
        action="@auth('web'){{ route('tasks.store', [$currentWorkspace->slug, $project->id]) }}@elseauth{{ route('client.tasks.store', [$currentWorkspace->slug, $project->id]) }}@endauth">
        @csrf
        <div class="modal-body">
            <div class="row">
                @if ($currentWorkspace->is_chagpt_enable())
                    <div class="text-end col-12">
                        <a href="#" data-size="lg" data-ajax-popup-over="true" class="btn btn-sm btn-primary"
                            data-url="{{ route('generate', ['task']) }}" data-bs-toggle="tooltip" data-bs-placement="top"
                            title="{{ __('Generate with AI') }}"
                            data-title="{{ __('Generate Task Title & Description') }}">
                            <i class="fas fa-robot px-1"></i>{{ __('Generate with AI') }}</a>
                    </div>
                @endif

                <div class="form-group col-md-8">
                    <label class="col-form-label">{{ __('Project') }}</label>
                    <select class="form-control form-control-light select" name="project_id" required>

                        @if ($projectID != null) 
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @else
                            @foreach($projects as $proj)
                                <option value="{{ $proj->id }}" {{ $project && $project->id == $proj->id ? 'selected' : '' }}>{{ $proj->name }}</option>
                            @endforeach
                        @endif

                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label class="col-form-label">{{ __('Milestone') }}</label>
                    <div class="input-group">
                        <select class="form-control form-control-light select" name="milestone_id" id="task-milestone" required>
                            <option value="">{{ __('Select Milestone') }}</option>
                            @foreach ($project->milestones as $milestone)
                                <option value="{{ $milestone->id }}">{{ $milestone->title }}</option>
                            @endforeach
                        </select>
                        <div class="input-group-append">
                            
                            <a href="#" id="createMilestoneButton" class="btn btn-sm h-100 btn-primary d-flex align-items-center" " 
                                        data-url="{{ route('projects.milestone', [$currentWorkspace->slug, $project->id]) }}">
                                <i class="ti ti-plus"></i>
                                
                            </a>
                        </div>
                    </div>
                </div>
                <div class="form-group col-md-8">
                    <label class="col-form-label">{{ __('Title') }}</label>
                    <input type="text" class="form-control form-control-light" id="task-title"
                        placeholder="{{ __('Enter Title') }}" name="title" required>
                </div>
                <div class="form-group col-md-4">
                    <label class="col-form-label">{{ __('Priority') }}</label>
                    <select class="form-control form-control-light select" name="priority" id="task-priority" required>
                        <option value="Low">{{ __('Low') }}</option>
                        <option value="Medium">{{ __('Medium') }}</option>
                        <option value="High">{{ __('High') }}</option>
                    </select>
                </div>
                <div class="form-group col-md-12">
                    <label class="col-form-label">{{ __('Assign To') }}</label>

                    <select class="assign_to multi-select" id="assign_to" name="assign_to[]" data-toggle="select2"
                         data-placeholder="{{ __('Select User ...') }}">
                        @foreach ($project->users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} - {{ $u->email }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="col-form-label">{{ __('Duration') }}</label>
                    <div class='input-group form-group'>
                        <input type='text' class=" form-control form-control-light" id="duration" name="duration"
                            required autocomplete="off" placeholder="Select date range" />
                        <input type="hidden" name="start_date">
                        <input type="hidden" name="due_date">
                        <span class="input-group-text"><i class="feather icon-calendar"></i></span>
                    </div>
                </div>
                <div class="form-group col-md-12">
                    <label class="col-form-label">{{ __('Description') }}</label>
                    <textarea class="form-control form-control-light" id="task-description" rows="3" name="description" required></textarea>
                </div>
                @if ($currentWorkspace->is_googlecalendar_enabled == 'on')
                    <div class="form-group col-md-6">
                        {{ Form::label('synchronize_type', __('Synchroniz in Google Calendar ?'), ['class' => 'col-form-label']) }}
                        <div class=" form-switch">
                            <input type="checkbox" class="form-check-input mt-2" name="synchronize_type"
                                id="switch-shadow" value="google_calender">
                            <label class="form-check-label" for="switch-shadow"></label>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn  btn-light" data-bs-dismiss="modal">{{ __('Close') }}</button>
            <input type="submit" value="{{ __('Save Changes') }}" class="btn  btn-primary">
        </div>

    </form>
    <div id="overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5); z-index:1040;"></div>
    <script>
        $(document).ready(function(){
            $('#createMilestoneButton').click(function(e){
                e.preventDefault();
                var url = $('#createMilestoneButton').attr('data-url');
                var title = "Create New Milestone";
                $("#commonModalOver .modal-title").html(title);
                
                $('#commonModalOver .modal-body').load(url, function() {
                    $('#overlay').show();
                    $('#commonModalOver').modal('show');
                    
                    $('#commonModalOver').find('form').off('submit').on('submit', function(e) {
                        e.preventDefault(); 
                        
                        var form = $(this);
                        var formData = form.serialize(); 
                        
                        $.ajax({
                            type: 'POST',
                            url: form.attr('action'), 
                            data: formData,
                            success: function(response) {
                                $('#commonModalOver').modal('hide');
                                $('#overlay').hide(); 
                                updateProjectMilestones();
                            },
                            error: function(response) {
                                console.log('Error creating milestone.', response);
                            }
                        });
                    });
                });
            });
            $('#commonModalOver').on('hidden.bs.modal', function () {
                $('#overlay').hide();
            });

            function updateProjectMilestones() {
                let projectId = $("select[name=project_id]").val();
                let url = '{{ route('tasks.create', [$currentWorkspace->slug, 'projectIdPlace']) }}';
                url = url.replace('projectIdPlace', projectId); 

                // Update milestone creation URL and title
                let milestoneButton = $('#createMilestoneButton');
                let milestoneUrl = '{{ route('projects.milestone', [$currentWorkspace->slug, 'projectIdPlace']) }}';
                milestoneUrl = milestoneUrl.replace('projectIdPlace', projectId);
                milestoneButton.attr('data-url', milestoneUrl);

                $.ajax({
                    url: url + '?json=true',
                    type: 'GET',
                    success: function(response) {
                        let assignToContainer = $('#assign_to').closest('.form-group');
                        assignToContainer.html(''); 

                        let label = $('<label>', {
                            class: 'col-form-label',
                            text: '{{ __('Assign To') }}'
                        });
                        assignToContainer.append(label);

                        let newSelect = $('<select>', {
                            id: 'assign_to',
                            name: 'assign_to[]',
                            class: 'multi-select form-control',
                            multiple: 'multiple',
                            'data-toggle': 'select2',
                            'data-placeholder': '{{ __("Select User ...") }}'
                        });

                        if (response.users && response.users.length > 0) {
                            response.users.forEach(function(user) {
                                newSelect.append('<option value="' + user.id + '">' + user.name + ' - ' + user.email + '</option>');
                            });
                        } 

                        assignToContainer.append(newSelect); 
                        new Choices('#assign_to', { removeItemButton: true });

                        $('#task-milestone').html('<option value="">{{ __('Select Milestone') }}</option>');
                        if (response.milestones.length > 0) {
                            response.milestones.forEach(function(milestone) {
                                $('#task-milestone').append('<option value="' + milestone.id + '">' + milestone.title + '</option>');
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                    }
                });
            }

            $(document).on('change', "select[name=project_id]", function() {
                updateProjectMilestones();
            });

            // Optional: Call updateProjectMilestones() on page load if needed
            // updateProjectMilestones();
        });
    </script>
    <link rel="stylesheet" href="{{ asset('assets/custom/libs/bootstrap-daterangepicker/daterangepicker.css') }}">
    <script src="{{ asset('assets/custom/libs/bootstrap-daterangepicker/daterangepicker.js') }}"></script>
    
    <script>
        if ($(".multi-select").length > 0) {
            $($(".multi-select")).each(function(index, element) {
                var id = $(element).attr('id');
                var multipleCancelButton = new Choices(
                    '#' + id, {
                        removeItemButton: true,
                    }
                );
            });
        }

        $(function() {
            var start = moment('{{ date('Y-m-d') }}', 'YYYY-MM-DD HH:mm:ss');
            var end = moment('{{ date('Y-m-d') }}', 'YYYY-MM-DD HH:mm:ss');

            function cb(start, end) {
                $("form #duration").val(start.format('MMM D, YY hh:mm A') + ' - ' + end.format(
                'MMM D, YY hh:mm A'));
                $('form input[name="start_date"]').val(start.format('YYYY-MM-DD HH:mm:ss'));
                $('form input[name="due_date"]').val(end.format('YYYY-MM-DD HH:mm:ss'));
            }

            $('form #duration').daterangepicker({
                /*autoApply: true,
                autoclose: true,*/
                autoApply: true,
                timePicker: true,
                autoUpdateInput: false,
                startDate: start,
                endDate: end,
                /*startDate: start,
                endDate: end,
                autoApply: true,
                autoclose: true,
                autoUpdateInput: false,*/
                drops: 'up',
                locale: {
                    format: 'MMMM D, YYYY hh:mm A',
                    applyLabel: "{{ __('Apply') }}",
                    cancelLabel: "{{ __('Cancel') }}",
                    fromLabel: "{{ __('From') }}",
                    toLabel: "{{ __('To') }}",
                    daysOfWeek: [
                        "{{ __('Sun') }}",
                        "{{ __('Mon') }}",
                        "{{ __('Tue') }}",
                        "{{ __('Wed') }}",
                        "{{ __('Thu') }}",
                        "{{ __('Fri') }}",
                        "{{ __('Sat') }}"
                    ],
                    monthNames: [
                        "{{ __('January') }}",
                        "{{ __('February') }}",
                        "{{ __('March') }}",
                        "{{ __('April') }}",
                        "{{ __('May') }}",
                        "{{ __('June') }}",
                        "{{ __('July') }}",
                        "{{ __('August') }}",
                        "{{ __('September') }}",
                        "{{ __('October') }}",
                        "{{ __('November') }}",
                        "{{ __('December') }}"
                    ],
                }
            }, cb);

            cb(start, end);
        });
        // $(document).on('change', "select[name=project_id]", function() {
        //     $.get('@auth('web'){{ route('home') }}@elseauth{{ route('client.home') }}@endauth' +
        //         '/userProjectJson/' + $(this).val(),
        //         function(data) {
        //             $('select[name=assign_to]').html('');
        //             data = JSON.parse(data);
        //             $(data).each(function(i, d) {
        //                 $('select[name=assign_to]').append('<option value="' + d.id + '">' + d.name +
        //                     ' - ' + d.email + '</option>');
        //             });
        //         });
        //     $.get('@auth('web'){{ route('home') }}@elseauth{{ route('client.home') }}@endauth' +
        //         '/projectMilestoneJson/' + $(this).val(),
        //         function(data) {
        //             $('select[name=milestone_id]').html(
        //                 '<option value="">{{ __('Select Milestone') }}</option>');
        //             data = JSON.parse(data);
        //             $(data).each(function(i, d) {
        //                 $('select[name=milestone_id]').append('<option value="' + d.id + '">' + d
        //                     .title + '</option>');
        //             });
        //         })
        // })
    </script>
@else
    <div class="container mt-5">
        <div class="card">
            <div class="card-body p-4">
                <div class="page-error">
                    <div class="page-inner">
                        <h1>404</h1>
                        <div class="page-description">
                            {{ __('Page Not Found. No Projects For this user!') }}
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
