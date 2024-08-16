<form id="editProjectForm" class="" method="post" action="{{ route('projects.update',[$currentWorkspace->slug,$project->id]) }}">
    @csrf
    <div class="modal-body">
        <div class="row">
            @if ($currentWorkspace->ownerable_type != "App\Models\Pod" && $currentWorkspace->is_chagpt_enable())
            <div class="text-end col-12">
                <a href="#" data-size="lg" data-ajax-popup-over="true" class="btn btn-sm btn-primary" data-url="{{ route('generate',['project']) }}" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Generate with AI') }}" data-title="{{ __('Generate Project Name & Description') }}">
                    <i class="fas fa-robot px-1"></i>{{ __('Generate with AI') }}</a>
            </div>
            @endif

            <div class="form-group col-md-12">
                <label for="projectname" class="form-label">{{ __('Name') }}</label>
                <input class="form-control" type="text" id="projectname" name="name" required="" placeholder="{{ __('Project Name') }}" value="{{$project->name}}">
            </div>
            <div class="form-group col-md-12">
                <label for="description" class="form-label">{{ __('Description') }}</label>
                <textarea rows="3" class="form-control" id="description" name="description" required="" placeholder="{{ __('Add Description') }}">{{$project->description}}</textarea>
            </div>
            <div class="form-group col-md-12">
                <label class="form-label">{{ __('Visibility') }}</label>
                <div class="form-check">
                    <input type="hidden" name="private" value="0">
                    <input class="form-check-input" type="checkbox" id="private" name="private" value="1" {{ $project->private ? 'checked' : '' }}>
                    <label class="form-check-label" for="private">
                        {{ __('Private Project') }}
                    </label>
                </div>
            </div>
            <div class="form-group col-md-12">
                    <label for="project_category" class="form-label">{{ __('Project Category') }}</label>
                    <select id="project_category" name="project_category_id" class="form-control">
                        <option value="" {{ $project->project_category_id == null ? 'selected' : '' }}>{{ __('Select Category') }}</option>
                        @foreach(App\Models\ProjectCategory::all() as $category)
                            <option value="{{ $category->id }}" {{ $project->project_category_id == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    <button type="button" id="addCategoryButton" class="btn btn-link">{{ __('+ Add New Category') }}</button>
                    <div class="input-group mb-3 mt-2" id="newCategoryInput" style="display: none;">
                        <input type="text" id="newCategoryName" class="form-control" placeholder="{{ __('Category Name') }}" aria-label="{{ __('Category Name') }}">
                        <button type="button" id="createCategoryButton" class="btn btn-primary">{{ __('Create') }}</button>
                    </div>  
            </div>
            
            <div class="form-group col-md-6">
                <label for="status" class="form-label">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-control">
                    <option value="Ongoing">{{ __('Ongoing') }}</option>
                    <option value="Finished" @if($project->status == 'Finished') selected @endif>{{ __('Finished') }}</option>
                    <option value="OnHold" @if($project->status == 'OnHold') selected @endif>{{ __('OnHold') }}</option>
                </select>
            </div>

            <div class="form-group col-md-6">
                <label for="budget" class="form-label">{{ __('Budget') }}</label>
                <div class="form-icon-user ">
                    <span class="currency-icon bg-primary ">{{ (!empty($currentWorkspace->currency)) ? $currentWorkspace->currency : '$'}}</span>
                    <input class="form-control currency_input" type="number" min="0" id="budget" name="budget" value="{{$project->budget}}" placeholder="{{ __('Project Budget') }}">
                </div>
            </div>
            <div class="form-group col-md-6">
                <label class="form-label">{{ __('Start Date') }}</label>


                <div class="input-group date ">
                    <input class="form-control datepicker2" type="text" id="start_date" name="start_date" value="{{$project->start_date}}" autocomplete="off" required="required">
                    <span class="input-group-text btn btn-primary" id="start-calendar-icon">
                        <i class="feather icon-calendar"></i>
                    </span>
                </div>
            </div>
            <div class="form-group col-md-6">
                <label class="form-label">{{ __('End Date') }}</label>
                <div class="input-group date ">
                    <input class="form-control datepicker3" type="text" id="end_date" name="end_date" value="{{$project->end_date}}" autocomplete="off" required="required">
                    <span class="input-group-text btn btn-primary" id="end-calendar-icon">
                        <i class="feather icon-calendar"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn  btn-light" data-bs-dismiss="modal">{{ __('Close')}}</button>
        <input type="submit" value="{{ __('Save Changes')}}" class="btn  btn-primary">
    </div>

</form>

<script>
    document.getElementById('start-calendar-icon').addEventListener('click', function() {
        document.getElementById('start_date').focus(); 
    });

    document.getElementById('end-calendar-icon').addEventListener('click', function() {
        document.getElementById('end_date').focus(); 
    });
</script>
<script>
    (function() {
        const d_week = new Datepicker(document.querySelector('.datepicker2'), {
            buttonClass: 'btn'
            , todayBtn: true
            , clearBtn: true
            , format: 'yyyy-mm-dd'
        , });
    })();

</script>

<script>
    (function() {
        const d_week = new Datepicker(document.querySelector('.datepicker3'), {
            buttonClass: 'btn'
            , todayBtn: true
            , clearBtn: true
            , format: 'yyyy-mm-dd'
        , });
    })();

</script>

<script>
    $(document).on('shown.bs.modal', function () {
        // check form
        if ($('#createProjectForm, #editProjectForm').length) {
            $('#addCategoryButton').off('click').on('click', function() {
                $('#newCategoryInput').show();
                $('#addCategoryButton').hide();
            });

            $('#createCategoryButton').off('click').on('click', function() {
                const newCategoryName = $('#newCategoryName').val().trim();

                if (newCategoryName === '') {
                    alert("Please enter a category name.");
                    return;
                }

                fetch("{{ route('api.project-category.store') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({ name: newCategoryName })
                })
                .then(response => response.json())
                .then(data => {
                    const select = $("#project_category");
                    const option = $('<option></option>').val(data.id).text(data.name);
                    select.append(option).val(data.id);

                    $('#newCategoryInput').hide();
                    $('#newCategoryName').val('');
                    $('#addCategoryButton').show();
                })
                .catch(error => console.error('Error:', error));
            });
        }
    });
</script>