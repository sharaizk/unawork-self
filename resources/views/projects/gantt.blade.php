@extends('layouts.admin')
@php
    // $permissions = Auth::user()->getPermission($project->id);
    $client_keyword = Auth::user()->getGuard() == 'client' ? 'client.' : '';

    $logo = \App\Models\Utility::get_file('avatars/');
    $logo_tasks = \App\Models\Utility::get_file('tasks/');
@endphp

@section('page-title') {{__('Gantt Chart')}} @endsection
@section('links')
@if(\Auth::guard('client')->check())   
<li class="breadcrumb-item"><a href="{{route('client.home')}}">{{__('Home')}}</a></li>
 @else
 <li class="breadcrumb-item"><a href="{{route('home')}}">{{__('Home')}}</a></li>
 @endif
 @if(\Auth::guard('client')->check())  
<li class="breadcrumb-item"><a href="{{ route('client.projects.index',$currentWorkspace->slug) }}">{{__('Project')}}</a></li>
 @else  
<li class="breadcrumb-item"><a href="{{ route('projects.index',$currentWorkspace->slug)}}">{{__('Project')}}</a></li>
@endif
<li class="breadcrumb-item"><a href="{{route($client_keyword.'projects.show',[$currentWorkspace->slug,$project->id])}}">{{__('Project Details')}}</a></li>
<li class="breadcrumb-item">{{__('Gantt Chart')}}</li>
 @endsection

@section('action-button')
    <div class="btn-group mx-1" id="change_view" role="group">
        <a href="{{route($is_client.'projects.gantt',[$currentWorkspace->slug,$project->id,'Quarter Day'])}}" class="btn btn-sm px-3 btn-info @if($duration == 'Quarter Day')active @endif" data-value="Quarter Day">{{__('Quarter Day')}}</a>
        <a href="{{route($is_client.'projects.gantt',[$currentWorkspace->slug,$project->id,'Half Day'])}}" class="btn btn-sm  px-3 btn-info @if($duration == 'Half Day')active @endif" data-value="Half Day">{{__('Half Day')}}</a>
        <a href="{{route($is_client.'projects.gantt',[$currentWorkspace->slug,$project->id,'Day'])}}" class="btn btn-sm px-3 btn-info @if($duration == 'Day')active @endif" data-value="Day">{{__('Day')}}</a>
        <a href="{{route($is_client.'projects.gantt',[$currentWorkspace->slug,$project->id,'Week'])}}" class="btn btn-sm px-3 btn-info @if($duration == 'Week')active @endif" data-value="Week">{{__('Week')}}</a>
        <a href="{{route($is_client.'projects.gantt',[$currentWorkspace->slug,$project->id,'Month'])}}" class="btn btn-sm px-3 btn-info @if($duration == 'Month')active @endif" data-value="Month">{{__('Month')}}</a>
    </div>
    @auth('client')
        <a href="{{route('client.projects.show',[$currentWorkspace->slug,$project->id])}}" class="btn-submit btn btn-md btn-primary btn-icon px-2 py-2" data-toggle="tooltip" title="{{__('Back')}}">
            <i class=" ti ti-arrow-back-up"></i> 
        </a>
    @elseauth
       <div class="col-auto">
        <a href="{{route('projects.show',[$currentWorkspace->slug,$project->id])}}" class="btn btn-sm btn-primary" data-toggle="tooltip" title="{{__('Back')}}">
            <i class=" ti ti-arrow-back-up"></i>
        </a>
    </div>


    @endauth
@endsection
 
@section('content')
    <?php $permissions = Auth::user()->getPermission($project->id); ?>

    <section class="section">

        @if($project && $currentWorkspace)
            <div class="row">
                <div class="col-12">
                    <div class="gantt-target"></div>
                </div>
            </div>
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
    </section>

@endsection
@if($project && $currentWorkspace)
    @push('css-page')
        <link rel="stylesheet" href="{{asset('assets/custom/css/frappe-gantt.css')}}" />
    @endpush
    @push('scripts')
        @php
            $currantLang = basename(App::getLocale());
        @endphp
        <script>
            const month_names = {
                "{{$currantLang}}": [
                    '{{__('January')}}',
                    '{{__('February')}}',
                    '{{__('March')}}',
                    '{{__('April')}}',
                    '{{__('May')}}',
                    '{{__('June')}}',
                    '{{__('July')}}',
                    '{{__('August')}}',
                    '{{__('September')}}',
                    '{{__('October')}}',
                    '{{__('November')}}',
                    '{{__('December')}}'
                ],
                "en": [
                    'January',
                    'February',
                    'March',
                    'April',
                    'May',
                    'June',
                    'July',
                    'August',
                    'September',
                    'October',
                    'November',
                    'December'
                ],
            };
        </script>
        <script src="{{asset('assets/custom/js/frappe-gantt.js')}}"></script>
        <script>
            var tasks = JSON.parse('{!! addslashes(json_encode($tasks)) !!}');
            var gantt_chart = new Gantt(".gantt-target", tasks, {
                custom_popup_html: function(task) {
                    var status_class = 'success';
                    if(task.custom_class == 'medium'){
                        status_class = 'info'
                    }else if(task.custom_class == 'high'){
                        status_class = 'danger'
                    }
                    return `<div class="details-container">
                                <div class="title">${task.name} <span class="badge badge-${status_class} float-right">${task.extra.priority}</span></div>
                                <div class="subtitle">
                                    <b>${task.progress}%</b> {{ __('Progress')}} <br>
                                    <b>${task.extra.comments}</b> {{ __('Comments')}} <br>
                                    <b>{{ __('Duration')}}</b> ${task.extra.duration}
                                </div>
                            </div>
                          `;
                },
                on_click: function (task) {
                    //console.log(task);
                    var urlTemplate = "{{ route('tasks.show', [$currentWorkspace->slug, $project->id, '__task_id__']) }}";
                    var taskId = task.id.split('_') [1];
                    var taskUrl = urlTemplate.replace('__task_id__', taskId);
                    console.log(taskUrl);
                    console.log(task);

                    $.ajax({
                        url: taskUrl,
                        type: 'GET',
                        success: function(response) {
                            $('#commonModal .modal-content .body').html(response);

                            $('#commonModal .modal-title').text('Task Detail');

                            $('#commonModal').modal('show');
                        },
                        error: function() {
                            alert('Unable to load data');
                        }
                    });
                },
                on_date_change: function(task, start, end) {
                    task_id = task.id;
                    start = moment(start);
                    end = moment(end);
                    $.ajax({
                        url:"@auth('client'){{route('client.projects.gantt.post',[$currentWorkspace->slug,$project->id])}}@else{{route('projects.gantt.post',[$currentWorkspace->slug,$project->id])}}@endif",
                        data:{
                            start:start.format('YYYY-MM-DD HH:mm:ss'),
                            end:end.format('YYYY-MM-DD HH:mm:ss'),
                            task_id:task_id,
                        },
                        type:'POST',
                        success:function (data) {

                        },
                        error:function (data) {
                            show_toastr('Error', '{{ __("Some Thing Is Wrong!")}}', 'error');
                        }
                    });
                },
                view_mode: '{{$duration}}',
                language: '{{$currantLang}}'
            });


            $(document).on('click', '#form-comment button', function(e) {
                var comment = $("#form-comment textarea[name='comment']");
                if ($.trim(comment.val()) != '') {
                    $.ajax({
                        url: $("#form-comment").data('action'),
                        data: {
                            comment: comment.val()
                        },
                        type: 'POST',
                        success: function(data) {
                            data = JSON.parse(data);

                            if (data.user_type == 'Client') {
                                var avatar = "avatar='" + data.client.name + "'";
                                var html = "<li class='media border-bottom mb-3'>" +
                                    "                    <img class='mr-3 avatar-sm rounded-circle img-thumbnail' width='60' " +
                                    avatar + " alt='" + data.client.name + "'>" +
                                    "                    <div class='media-body mb-2'>" +
                                    "                        <h5 class='mt-0 mb-1 form-control-label'>" +
                                    data.client.name + "</h5>" +
                                    "                        " + data.comment +
                                    "                    </div>" +
                                    "                </li>";
                            } else {
                                var avatar = (data.user.avatar) ? "src='{{ $logo }}/" + data.user
                                    .avatar + "'" : "avatar='" + data.user.name + "'";
                                var html = "<li class='media border-bottom mb-3'>" +
                                    "                    <img class='mr-3 avatar-sm rounded-circle img-thumbnail' width='60' " +
                                    avatar + " alt='" + data.user.name + "'>" +
                                    "                    <div class='media-body mb-2'>" +
                                    "                           <div class='float-left'>" +
                                    "                               <h5 class='mt-0 mb-1 form-control-label'>" +
                                    data.user.name + "</h5>" +
                                    "                               " + data.comment +
                                    "                           </div>" +
                                    "                           <div class='float-right'>" +
                                    "                               <a href='#' class='delete-icon' data-url='" +
                                    data.deleteUrl + "'>" +
                                    "                                   <i class='fas fa-trash'></i>" +
                                    "                               </a>" +
                                    "                           </div>" +
                                    "                    </div>" +
                                    "                </li>";
                            }

                            $("#comments").prepend(html);
                            $("#form-comment textarea[name='comment']").val('');
                            $('#commonModal').modal('hide');
                            show_toastr('{{ __('Success') }}', '{{ __('Comment Added Successfully!') }}',
                                'success');
                        },
                        error: function(data) {
                            show_toastr('{{ __('Error') }}', '{{ __('Some Thing Is Wrong!') }}',
                                'error');
                        }
                    });
                } else {
                    comment.focus();
                    show_toastr('{{ __('Error') }}', '{{ __('Please write comment!') }}', 'error');
                }
            });

            $(document).on("click", ".delete-comment", function() {
                if (confirm('{{ __('Are you sure ?') }}')) {
                    var btn = $(this);
                    $.ajax({
                        url: $(this).attr('data-url'),
                        type: 'DELETE',
                        dataType: 'JSON',
                        success: function(data) {
                            show_toastr('{{ __('Success') }}',
                                '{{ __('Comment Deleted Successfully!') }}', 'success');
                            btn.closest('.media').remove();
                        },
                        error: function(data) {
                            data = data.responseJSON;
                            if (data.message) {
                                show_toastr('{{ __('Error') }}', data.message, 'error');
                            } else {
                                show_toastr('{{ __('Error') }}', '{{ __('Some Thing Is Wrong!') }}',
                                    'error');
                            }
                        }
                    });
                }
            });

            $(document).on('click', '#form-subtask .create-subtask', function(e) {
                e.preventDefault();

                var name = $('#form-subtask input[name="name"]');
                var due_date = $('#form-subtask input[name="due_date"]');

                if ($.trim(name.val()) != '' && due_date.val() != '') {
                    $.ajax({
                        url: $("#form-subtask").data('action'),
                        type: 'POST',
                        data: {
                            name: name.val(),
                            due_date: due_date.val()
                        },
                        dataType: 'json',
                        success: function(data) {

                            show_toastr('{{ __('Success') }}', '{{ __('Sub Task Added Successfully!') }}',
                                'success');

                            var html = '<li class="list-group-item py-3">' +
                                '    <div class="form-check form-switch d-inline-block">' +
                                '        <input type="checkbox" class="form-check-input" name="option" id="option' +
                                data.id + '" value="' + data.id + '" data-url="' + data.updateUrl + '">' +
                                '        <label class="custom-control-label form-control-label" for="option' +
                                data.id + '">' + data.name + '</label>' +
                                '    </div>' +
                                '    <div class="text-end">' +
                                '        <a href="#" class=" action-btn btn-danger  btn btn-sm d-inline-flex align-items-center delete-comment delete-icon delete-subtask" data-url="' +
                                data.deleteUrl + '">' +
                                '            <i class="ti ti-trash"></i>' +
                                '        </a>' +
                                '    </div>' +
                                '</li>';
                            $("#subtasks").prepend(html);
                            name.val('');
                            due_date.val('');
                            $("#form-subtask").collapse('toggle');
                        },
                        error: function(data) {
                            data = data.responseJSON;
                            if (data.message) {
                                show_toastr('{{ __('Error') }}', data.message, 'error');
                                $('#file-error').text(data.errors.file[0]).show();
                            } else {
                                show_toastr('{{ __('Error') }}', '{{ __('Some Thing Is Wrong!') }}',
                                    'error');
                            }
                        }
                    });
                } else {
                    name.focus();
                    show_toastr('{{ __('Error') }}', '{{ __('Some Thing Is Wrong!') }}', 'error');
                }
            });

            $(document).on("change", "#subtasks input[type=checkbox]", function() {
                $.ajax({
                    url: $(this).attr('data-url'),
                    type: 'POST',
                    dataType: 'JSON',
                    success: function(data) {
                        show_toastr('{{ __('Success') }}', '{{ __('Subtask Updated Successfully!') }}',
                            'success');
                    },
                    error: function(data) {
                        data = data.responseJSON;
                        if (data.message) {
                            show_toastr('{{ __('Error') }}', data.message, 'error');
                        } else {
                            show_toastr('{{ __('Error') }}', '{{ __('Some Thing Is Wrong!') }}',
                                'error');
                        }
                    }
                });
            });

            $(document).on("click", ".delete-subtask", function() {
                if (confirm('{{ __('Are you sure ?') }}')) {
                    var btn = $(this);
                    $.ajax({
                        url: $(this).attr('data-url'),
                        type: 'DELETE',
                        dataType: 'JSON',
                        success: function(data) {
                            show_toastr('{{ __('Success') }}',
                                '{{ __('Subtask Deleted Successfully!') }}', 'success');
                            btn.closest('.list-group-item').remove();
                        },
                        error: function(data) {
                            data = data.responseJSON;
                            if (data.message) {
                                show_toastr('{{ __('Error') }}', data.message, 'error');
                            } else {
                                show_toastr('{{ __('Error') }}', '{{ __('Some Thing Is Wrong!') }}',
                                    'error');
                            }
                        }
                    });
                }
            });

            $(document).on('submit', '#form-file', function(e) {

                e.preventDefault();

                $.ajax({
                    url: $("#form-file").data('url'),
                    type: 'POST',
                    data: new FormData(this),
                    dataType: 'JSON',
                    contentType: false,
                    cache: false,
                    processData: false,
                    success: function(data) {
                        show_toastr('{{ __('Success') }}', '{{ __('File Upload Successfully!') }}',
                            'success');
                        // console.log(data);
                        var delLink = '';

                        if (data.deleteUrl.length > 0) {
                            delLink =
                                "<a href='#' class=' action-btn btn-danger  btn btn-sm d-inline-flex align-items-center delete-comment delete-icon delete-comment-file'  data-url='" +
                                data.deleteUrl + "'>" +
                                "                                        <i class='ti ti-trash'></i>" +
                                "                                    </a>";
                        }

                        var html = "<div class='card mb-1 shadow-none border'>" +

                            "                        <div class='card-body p-3'>" +

                            "                            <div class='row align-items-center'>" +

                            "                                <div class='col-auto'>" +

                            "                                    <div class='avatar-sm'>" +

                            "                                        <span class='avatar-title rounded text-uppercase'>" +
                            "<img class='preview_img_size' " + "src='{{ $logo_tasks }}/" + data.file +
                            "'>" +
                            "                                        </span>" +
                            "                                    </div>" +
                            "                                </div>" +
                            "                                <div class='col pl-0'>" +
                            "                                    <a href='#' class='text-muted form-control-label'>" +
                            data.name + "</a>" +
                            "                                    <p class='mb-0'>" + data.file_size +
                            "</p>" +
                            "                                </div>" +
                            "                                <div class='col-auto'>" +
                            "                                    <a download href='{{ $logo_tasks }}/" +
                            data.file +
                            "' class='edit-icon action-btn btn-primary  btn btn-sm d-inline-flex align-items-center'>" +
                            "                                        <i class='ti ti-download'></i>" +
                            "                                    </a>" +


                            "                                   <a  href='{{ $logo_tasks }}/" + data
                            .file +
                            "' class='edit-icon action-btn btn-secondary  btn btn-sm d-inline-flex align-items-center mx-1'>" +
                            "                                        <i class='ti ti-crosshair text-white'></i>" +
                            "                                    </a>" +
                            delLink +
                            "                                </div>" +
                            "                            </div>" +
                            "                        </div>" +
                            "                    </div>";
                        $("#comments-file").prepend(html);
                    },
                    error: function(data) {
                        data = data.responseJSON;
                        if (data.message) {
                            show_toastr('{{ __('Error') }}', data.message, 'error');
                            $('#file-error').text(data.errors.file[0]).show();
                        } else {
                            show_toastr('{{ __('Error') }}', '{{ __('Some Thing Is Wrong!') }}',
                                'error');
                        }
                    }
                });
            });
            
            $(document).on("click", ".delete-comment-file", function() {
                if (confirm('{{ __('Are you sure ?') }}')) {
                    var btn = $(this);
                    $.ajax({
                        url: $(this).attr('data-url'),
                        type: 'DELETE',
                        dataType: 'JSON',
                        success: function(data) {
                            show_toastr('{{ __('Success') }}', '{{ __('File Deleted Successfully!') }}',
                                'success');
                            btn.closest('.border').remove();
                        },
                        error: function(data) {
                            data = data.responseJSON;
                            if (data.message) {
                                show_toastr('{{ __('Error') }}', data.message, 'error');
                            } else {
                                show_toastr('{{ __('Error') }}', '{{ __('Some Thing Is Wrong!') }}',
                                    'error');
                            }
                        }
                    });
                }
            });
        </script>
    @endpush
@endif
